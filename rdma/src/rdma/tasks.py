"""
RDMA Task Management System

Manages task execution, scheduling, and resource management.
"""

import asyncio
import time
import traceback
from abc import ABC, abstractmethod
from typing import Dict, Any, Optional, List, Callable, Union
from dataclasses import dataclass, field
from datetime import datetime, timedelta
from enum import Enum
import uuid
import json

from .config import TaskConfig
from .logging import RDMALogger
from .exceptions import RDMAException, TimeoutError


class TaskStatus(Enum):
    """Task execution status."""
    PENDING = "pending"
    RUNNING = "running"
    COMPLETED = "completed"
    FAILED = "failed"
    CANCELLED = "cancelled"
    TIMEOUT = "timeout"


@dataclass
class TaskResult:
    """Task execution result."""
    task_id: str
    status: TaskStatus
    result: Any = None
    error: Optional[str] = None
    start_time: Optional[datetime] = None
    end_time: Optional[datetime] = None
    duration: Optional[float] = None
    attempts: int = 0


@dataclass
class Task:
    """Task definition and metadata."""
    task_id: str
    name: str
    params: Dict[str, Any]
    priority: int = 0
    max_retries: int = 0
    timeout: Optional[int] = None
    created_at: datetime = field(default_factory=datetime.now)
    scheduled_for: Optional[datetime] = None
    user_id: Optional[str] = None
    metadata: Dict[str, Any] = field(default_factory=dict)


class TaskExecutor(ABC):
    """Abstract base class for task executors."""
    
    def __init__(self, name: str, logger: RDMALogger):
        self.name = name
        self.logger = logger
    
    @abstractmethod
    async def execute(self, task: Task) -> TaskResult:
        """Execute a task."""
        pass
    
    def validate_params(self, params: Dict[str, Any]) -> bool:
        """Validate task parameters."""
        return True


class CommandExecutor(TaskExecutor):
    """Executor for system commands."""
    
    async def execute(self, task: Task) -> TaskResult:
        """Execute a system command."""
        import subprocess
        
        command = task.params.get("command")
        if not command:
            return TaskResult(
                task_id=task.task_id,
                status=TaskStatus.FAILED,
                error="No command specified"
            )
        
        try:
            self.logger.info(f"Executing command: {command}")
            
            # Execute command
            result = subprocess.run(
                command,
                shell=True,
                capture_output=True,
                text=True,
                timeout=task.timeout
            )
            
            # Prepare result
            output = result.stdout
            if result.stderr:
                output += f"\nSTDERR: {result.stderr}"
            
            status = TaskStatus.COMPLETED if result.returncode == 0 else TaskStatus.FAILED
            
            return TaskResult(
                task_id=task.task_id,
                status=status,
                result={
                    "return_code": result.returncode,
                    "output": output,
                    "stdout": result.stdout,
                    "stderr": result.stderr
                }
            )
            
        except subprocess.TimeoutExpired:
            return TaskResult(
                task_id=task.task_id,
                status=TaskStatus.TIMEOUT,
                error=f"Command timed out after {task.timeout} seconds"
            )
        except Exception as e:
            return TaskResult(
                task_id=task.task_id,
                status=TaskStatus.FAILED,
                error=f"Command execution failed: {str(e)}"
            )


class HTTPRequestExecutor(TaskExecutor):
    """Executor for HTTP requests."""
    
    async def execute(self, task: Task) -> TaskResult:
        """Execute an HTTP request."""
        import aiohttp
        
        url = task.params.get("url")
        method = task.params.get("method", "GET").upper()
        headers = task.params.get("headers", {})
        data = task.params.get("data")
        json_data = task.params.get("json")
        
        if not url:
            return TaskResult(
                task_id=task.task_id,
                status=TaskStatus.FAILED,
                error="No URL specified"
            )
        
        try:
            self.logger.info(f"Executing HTTP {method} request: {url}")
            
            timeout = aiohttp.ClientTimeout(total=task.timeout or 30)
            
            async with aiohttp.ClientSession(timeout=timeout) as session:
                async with session.request(
                    method=method,
                    url=url,
                    headers=headers,
                    data=data,
                    json=json_data
                ) as response:
                    
                    response_text = await response.text()
                    
                    # Prepare result
                    result = {
                        "status_code": response.status,
                        "headers": dict(response.headers),
                        "text": response_text,
                        "url": str(response.url)
                    }
                    
                    # Try to parse JSON response
                    try:
                        result["json"] = await response.json()
                    except:
                        pass
                    
                    status = TaskStatus.COMPLETED if response.status < 400 else TaskStatus.FAILED
                    
                    return TaskResult(
                        task_id=task.task_id,
                        status=status,
                        result=result
                    )
                    
        except asyncio.TimeoutError:
            return TaskResult(
                task_id=task.task_id,
                status=TaskStatus.TIMEOUT,
                error=f"HTTP request timed out after {task.timeout} seconds"
            )
        except Exception as e:
            return TaskResult(
                task_id=task.task_id,
                status=TaskStatus.FAILED,
                error=f"HTTP request failed: {str(e)}"
            )


class HamRadioExecutor(TaskExecutor):
    """Executor for amateur radio operations."""
    
    def __init__(self, name: str, logger: RDMALogger, ham_manager):
        super().__init__(name, logger)
        self.ham_manager = ham_manager
    
    async def execute(self, task: Task) -> TaskResult:
        """Execute amateur radio operation."""
        operation = task.params.get("operation")
        
        if not operation:
            return TaskResult(
                task_id=task.task_id,
                status=TaskStatus.FAILED,
                error="No operation specified"
            )
        
        try:
            self.logger.info(f"Executing ham radio operation: {operation}")
            
            if operation == "send_cq":
                # Send CQ call
                mode = task.params.get("mode", "FT8")
                message = task.params.get("message", "CQ")
                
                # This would interface with the ham radio manager
                result = {"operation": "cq_sent", "mode": mode, "message": message}
                
                return TaskResult(
                    task_id=task.task_id,
                    status=TaskStatus.COMPLETED,
                    result=result
                )
            
            elif operation == "get_status":
                # Get ham radio status
                status = self.ham_manager.get_status()
                
                return TaskResult(
                    task_id=task.task_id,
                    status=TaskStatus.COMPLETED,
                    result=status
                )
            
            elif operation == "log_qso":
                # Log QSO
                callsign = task.params.get("callsign")
                if not callsign:
                    return TaskResult(
                        task_id=task.task_id,
                        status=TaskStatus.FAILED,
                        error="No callsign specified"
                    )
                
                self.ham_manager.add_worked_call(callsign)
                
                return TaskResult(
                    task_id=task.task_id,
                    status=TaskStatus.COMPLETED,
                    result={"callsign": callsign, "logged": True}
                )
            
            else:
                return TaskResult(
                    task_id=task.task_id,
                    status=TaskStatus.FAILED,
                    error=f"Unknown ham radio operation: {operation}"
                )
                
        except Exception as e:
            return TaskResult(
                task_id=task.task_id,
                status=TaskStatus.FAILED,
                error=f"Ham radio operation failed: {str(e)}"
            )


class TaskManager:
    """Manages task execution and scheduling."""
    
    def __init__(self, config: TaskConfig, logger: RDMALogger):
        self.config = config
        self.logger = logger
        self.executors: Dict[str, TaskExecutor] = {}
        self.pending_tasks: List[Task] = []
        self.running_tasks: Dict[str, asyncio.Task] = {}
        self.completed_tasks: List[TaskResult] = []
        self.is_running = False
        
        # Register default executors
        self._register_default_executors()
    
    def _register_default_executors(self) -> None:
        """Register default task executors."""
        self.register_executor("command", CommandExecutor("command", self.logger))
        self.register_executor("http", HTTPRequestExecutor("http", self.logger))
    
    def register_executor(self, executor_type: str, executor: TaskExecutor) -> None:
        """Register a task executor."""
        self.executors[executor_type] = executor
        self.logger.info(f"Registered task executor: {executor_type}")
    
    def register_ham_radio_executor(self, ham_manager) -> None:
        """Register ham radio task executor."""
        self.register_executor(
            "ham_radio",
            HamRadioExecutor("ham_radio", self.logger, ham_manager)
        )
    
    async def start(self) -> None:
        """Start the task manager."""
        if self.is_running:
            self.logger.warning("Task manager is already running")
            return
        
        self.is_running = True
        self.logger.info("Task manager started")
    
    async def stop(self) -> None:
        """Stop the task manager."""
        if not self.is_running:
            return
        
        self.logger.info("Stopping task manager...")
        self.is_running = False
        
        # Cancel all running tasks
        for task in self.running_tasks.values():
            task.cancel()
        
        # Wait for tasks to complete
        if self.running_tasks:
            await asyncio.gather(*self.running_tasks.values(), return_exceptions=True)
        
        self.logger.info("Task manager stopped")
    
    async def submit_task(self, task: Task) -> str:
        """Submit a task for execution."""
        if not self.is_running:
            raise RDMAException("Task manager is not running")
        
        if not task.task_id:
            task.task_id = str(uuid.uuid4())
        
        self.logger.info(f"Task submitted: {task.name} (ID: {task.task_id})")
        
        # Validate task
        if not self._validate_task(task):
            return await self._fail_task(task, "Task validation failed")
        
        # Check if task is allowed
        if self.config.allowed_tasks and task.name not in self.config.allowed_tasks:
            return await self._fail_task(task, f"Task '{task.name}' is not allowed")
        
        # Add to pending tasks
        self.pending_tasks.append(task)
        
        # Start processing if not already running
        if not self.running_tasks:
            asyncio.create_task(self._process_tasks())
        
        return task.task_id
    
    def _validate_task(self, task: Task) -> bool:
        """Validate task parameters and configuration."""
        # Check if executor exists
        if task.name not in self.executors:
            self.logger.error(f"No executor found for task: {task.name}")
            return False
        
        # Validate parameters with executor
        executor = self.executors[task.name]
        if not executor.validate_params(task.params):
            self.logger.error(f"Task parameter validation failed: {task.task_id}")
            return False
        
        return True
    
    async def _process_tasks(self) -> None:
        """Process pending tasks."""
        while self.pending_tasks and len(self.running_tasks) < self.config.max_concurrent:
            try:
                task = self.pending_tasks.pop(0)
                asyncio.create_task(self._execute_task(task))
            except Exception as e:
                self.logger.error(f"Error processing task: {e}")
    
    async def _execute_task(self, task: Task) -> None:
        """Execute a single task."""
        self.logger.info(f"Executing task: {task.name} (ID: {task.task_id})")
        
        start_time = datetime.now()
        result = None
        
        try:
            # Get executor
            executor = self.executors.get(task.name)
            if not executor:
                result = TaskResult(
                    task_id=task.task_id,
                    status=TaskStatus.FAILED,
                    error=f"No executor found for task: {task.name}"
                )
                return
            
            # Execute with retry logic
            for attempt in range(task.max_retries + 1):
                try:
                    task_result = await executor.execute(task)
                    task_result.attempts = attempt + 1
                    task_result.start_time = start_time
                    task_result.end_time = datetime.now()
                    
                    if task_result.duration is None:
                        task_result.duration = (task_result.end_time - start_time).total_seconds()
                    
                    # Handle different statuses
                    if task_result.status == TaskStatus.FAILED and attempt < task.max_retries:
                        self.logger.warning(f"Task {task.task_id} failed (attempt {attempt + 1}), retrying...")
                        await asyncio.sleep(self.config.retry_delay)
                        continue
                    
                    result = task_result
                    break
                    
                except asyncio.TimeoutError:
                    result = TaskResult(
                        task_id=task.task_id,
                        status=TaskStatus.TIMEOUT,
                        error=f"Task timed out after {task.timeout} seconds"
                    )
                    break
                except Exception as e:
                    if attempt < task.max_retries:
                        self.logger.warning(f"Task {task.task_id} error (attempt {attempt + 1}): {e}")
                        await asyncio.sleep(self.config.retry_delay)
                        continue
                    else:
                        result = TaskResult(
                            task_id=task.task_id,
                            status=TaskStatus.FAILED,
                            error=f"Task failed after {task.max_retries + 1} attempts: {str(e)}"
                        )
                        break
            
            # Store result
            self.completed_tasks.append(result)
            
            # Log result
            if result.status == TaskStatus.COMPLETED:
                self.logger.info(f"Task completed: {task.name} (ID: {task.task_id})")
            else:
                self.logger.error(f"Task failed: {task.name} (ID: {task.task_id}) - {result.error}")
            
        except Exception as e:
            self.logger.error(f"Unexpected error executing task {task.task_id}: {e}")
            result = TaskResult(
                task_id=task.task_id,
                status=TaskStatus.FAILED,
                error=f"Unexpected error: {str(e)}"
            )
            self.completed_tasks.append(result)
        
        finally:
            # Remove from running tasks
            if task.task_id in self.running_tasks:
                del self.running_tasks[task.task_id]
            
            # Process more tasks if available
            if self.pending_tasks and len(self.running_tasks) < self.config.max_concurrent:
                await self._process_tasks()
    
    async def _fail_task(self, task: Task, error: str) -> str:
        """Fail a task immediately."""
        result = TaskResult(
            task_id=task.task_id,
            status=TaskStatus.FAILED,
            error=error
        )
        self.completed_tasks.append(result)
        return task.task_id
    
    def execute_command(self, command: str, params: Dict[str, Any]) -> asyncio.Task:
        """Execute a command as a task."""
        task = Task(
            task_id=str(uuid.uuid4()),
            name="command",
            params={"command": command, **params},
            timeout=self.config.timeout
        )
        
        return asyncio.create_task(self._execute_task(task))
    
    def get_task_status(self, task_id: str) -> Optional[TaskResult]:
        """Get task status by ID."""
        for result in self.completed_tasks:
            if result.task_id == task_id:
                return result
        return None
    
    def get_pending_tasks(self) -> List[Task]:
        """Get list of pending tasks."""
        return self.pending_tasks.copy()
    
    def get_running_tasks(self) -> List[str]:
        """Get list of running task IDs."""
        return list(self.running_tasks.keys())
    
    def get_completed_tasks(self, limit: int = 100) -> List[TaskResult]:
        """Get recent completed tasks."""
        return self.completed_tasks[-limit:]
    
    def get_status(self) -> Dict[str, Any]:
        """Get task manager status."""
        return {
            "running": self.is_running,
            "pending_tasks": len(self.pending_tasks),
            "running_tasks": len(self.running_tasks),
            "completed_tasks": len(self.completed_tasks),
            "max_concurrent": self.config.max_concurrent,
            "timeout": self.config.timeout
        }


class TaskScheduler:
    """Advanced task scheduling with cron-like functionality."""
    
    def __init__(self, logger: RDMALogger):
        self.logger = logger
        self.scheduled_tasks: List[Dict[str, Any]] = []
        self.is_running = False
        self._scheduler_task: Optional[asyncio.Task] = None
    
    async def start(self) -> None:
        """Start the task scheduler."""
        if self.is_running:
            return
        
        self.is_running = True
        self._scheduler_task = asyncio.create_task(self._scheduler_loop())
        self.logger.info("Task scheduler started")
    
    async def stop(self) -> None:
        """Stop the task scheduler."""
        if not self.is_running:
            return
        
        self.is_running = False
        
        if self._scheduler_task:
            self._scheduler_task.cancel()
            try:
                await self._scheduler_task
            except asyncio.CancelledError:
                pass
        
        self.logger.info("Task scheduler stopped")
    
    async def _scheduler_loop(self) -> None:
        """Main scheduler loop."""
        try:
            while self.is_running:
                try:
                    current_time = datetime.now()
                    
                    # Check scheduled tasks
                    for scheduled_task in self.scheduled_tasks.copy():
                        if self._should_run_task(scheduled_task, current_time):
                            await self._execute_scheduled_task(scheduled_task)
                    
                    # Wait before next check
                    await asyncio.sleep(60)  # Check every minute
                    
                except Exception as e:
                    self.logger.error(f"Error in scheduler loop: {e}")
                    await asyncio.sleep(60)
                    
        except asyncio.CancelledError:
            self.logger.debug("Scheduler loop cancelled")
        except Exception as e:
            self.logger.error(f"Fatal error in scheduler: {e}")
    
    def _should_run_task(self, scheduled_task: Dict[str, Any], current_time: datetime) -> bool:
        """Check if a scheduled task should run."""
        # Simple implementation - check minute, hour, day, month, weekday
        cron_spec = scheduled_task.get("cron", "* * * * *")
        
        # Parse cron specification
        try:
            minute, hour, day, month, weekday = cron_spec.split()
            
            # Check each field
            if not self._check_cron_field(minute, current_time.minute):
                return False
            if not self._check_cron_field(hour, current_time.hour):
                return False
            if not self._check_cron_field(day, current_time.day):
                return False
            if not self._check_cron_field(month, current_time.month):
                return False
            if not self._check_cron_field(weekday, current_time.weekday()):
                return False
            
            return True
            
        except ValueError:
            self.logger.error(f"Invalid cron specification: {cron_spec}")
            return False
    
    def _check_cron_field(self, field: str, value: int) -> bool:
        """Check if a cron field matches the current value."""
        if field == "*":
            return True
        
        try:
            # Handle ranges (e.g., "1-5")
            if "-" in field:
                start, end = map(int, field.split("-"))
                return start <= value <= end
            
            # Handle lists (e.g., "1,3,5")
            if "," in field:
                values = map(int, field.split(","))
                return value in values
            
            # Handle single value
            return int(field) == value
            
        except ValueError:
            return False
    
    async def _execute_scheduled_task(self, scheduled_task: Dict[str, Any]) -> None:
        """Execute a scheduled task."""
        try:
            self.logger.info(f"Executing scheduled task: {scheduled_task['name']}")
            
            # Create task from scheduled task
            task = Task(
                task_id=str(uuid.uuid4()),
                name=scheduled_task["task_type"],
                params=scheduled_task.get("params", {}),
                metadata={"scheduled": True, "schedule_name": scheduled_task["name"]}
            )
            
            # Submit to task manager
            # Note: This would need access to the task manager instance
            self.logger.info(f"Scheduled task submitted: {task.task_id}")
            
        except Exception as e:
            self.logger.error(f"Error executing scheduled task: {e}")
    
    def schedule_task(self, name: str, task_type: str, cron: str, params: Dict[str, Any]) -> None:
        """Schedule a task with cron specification."""
        scheduled_task = {
            "name": name,
            "task_type": task_type,
            "cron": cron,
            "params": params,
            "created_at": datetime.now().isoformat()
        }
        
        self.scheduled_tasks.append(scheduled_task)
        self.logger.info(f"Task scheduled: {name} with cron: {cron}")
    
    def remove_scheduled_task(self, name: str) -> bool:
        """Remove a scheduled task."""
        for i, task in enumerate(self.scheduled_tasks):
            if task["name"] == name:
                del self.scheduled_tasks[i]
                self.logger.info(f"Scheduled task removed: {name}")
                return True
        return False
    
    def get_scheduled_tasks(self) -> List[Dict[str, Any]]:
        """Get all scheduled tasks."""
        return self.scheduled_tasks.copy()