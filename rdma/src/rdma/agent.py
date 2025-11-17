"""
RDMA Core Agent Module

The main agent class that orchestrates remote management operations.
"""

import asyncio
import signal
import sys
from typing import Dict, Any, Optional, List
from pathlib import Path
import json
import time
from datetime import datetime

from .config import ConfigManager
from .protocols import ProtocolManager
from .monitoring import Monitor, MetricsCollector
from .logging import RDMALogger
from .exceptions import RDMAException, ConfigurationError
from .tasks import TaskManager
from .security import SecurityManager


class RDMAgent:
    """
    Remote Digital Management Agent - Main orchestrator class.
    
    This class manages the overall operation of the RDMA system including:
    - Configuration management
    - Protocol handling
    - Monitoring and metrics
    - Task execution
    - Security management
    """
    
    def __init__(self, config_path: Optional[str] = None):
        """
        Initialize the RDMAgent.
        
        Args:
            config_path: Path to configuration file
        """
        self.config_manager = ConfigManager(config_path)
        self.config = self.config_manager.get_config()
        
        # Initialize core components
        self.logger = RDMALogger(self.config.logging)
        self.protocol_manager = ProtocolManager(self.config.protocols, self.logger)
        self.monitor = Monitor(self.config.monitoring, self.logger)
        self.metrics_collector = MetricsCollector(self.config.metrics, self.logger)
        self.task_manager = TaskManager(self.config.tasks, self.logger)
        self.security_manager = SecurityManager(self.config.security, self.logger)
        
        # Runtime state
        self.is_running = False
        self.start_time = None
        self._shutdown_event = asyncio.Event()
        
        self.logger.info("RDMAgent initialized successfully")
    
    async def start(self) -> None:
        """
        Start the RDMAgent and all its components.
        """
        if self.is_running:
            self.logger.warning("RDMAgent is already running")
            return
        
        self.logger.info("Starting RDMAgent...")
        self.start_time = datetime.now()
        self.is_running = True
        
        try:
            # Start core components
            await self._start_components()
            
            # Set up signal handlers
            self._setup_signal_handlers()
            
            self.logger.info(f"RDMAgent started successfully at {self.start_time}")
            
            # Main event loop
            await self._main_loop()
            
        except Exception as e:
            self.logger.error(f"Error starting RDMAgent: {e}")
            raise RDMAException(f"Failed to start RDMAgent: {e}")
    
    async def stop(self) -> None:
        """
        Stop the RDMAgent and all its components gracefully.
        """
        if not self.is_running:
            self.logger.warning("RDMAgent is not running")
            return
        
        self.logger.info("Stopping RDMAgent...")
        self.is_running = False
        
        try:
            # Stop components in reverse order
            await self._stop_components()
            
            # Signal main loop to exit
            self._shutdown_event.set()
            
            self.logger.info("RDMAgent stopped successfully")
            
        except Exception as e:
            self.logger.error(f"Error stopping RDMAgent: {e}")
            raise RDMAException(f"Failed to stop RDMAgent: {e}")
    
    async def _start_components(self) -> None:
        """Start all RDMA components."""
        self.logger.info("Starting RDMA components...")
        
        # Start security manager first
        await self.security_manager.start()
        
        # Start protocol manager
        await self.protocol_manager.start()
        
        # Start monitoring
        await self.monitor.start()
        
        # Start metrics collection
        await self.metrics_collector.start()
        
        # Start task manager
        await self.task_manager.start()
        
        self.logger.info("All RDMA components started successfully")
    
    async def _stop_components(self) -> None:
        """Stop all RDMA components."""
        self.logger.info("Stopping RDMA components...")
        
        # Stop components in reverse order
        await self.task_manager.stop()
        await self.metrics_collector.stop()
        await self.monitor.stop()
        await self.protocol_manager.stop()
        await self.security_manager.stop()
        
        self.logger.info("All RDMA components stopped successfully")
    
    def _setup_signal_handlers(self) -> None:
        """Set up signal handlers for graceful shutdown."""
        def signal_handler(signum, frame):
            self.logger.info(f"Received signal {signum}, initiating shutdown...")
            asyncio.create_task(self.stop())
        
        signal.signal(signal.SIGINT, signal_handler)
        signal.signal(signal.SIGTERM, signal_handler)
    
    async def _main_loop(self) -> None:
        """
        Main event loop for the RDMAgent.
        """
        self.logger.info("Entering main event loop...")
        
        try:
            while self.is_running:
                # Perform periodic maintenance tasks
                await self._maintenance_cycle()
                
                # Wait for shutdown signal or timeout
                try:
                    await asyncio.wait_for(self._shutdown_event.wait(), timeout=1.0)
                    break
                except asyncio.TimeoutError:
                    continue
                    
        except Exception as e:
            self.logger.error(f"Error in main loop: {e}")
            raise
    
    async def _maintenance_cycle(self) -> None:
        """
        Perform periodic maintenance tasks.
        """
        try:
            # Collect metrics
            metrics = await self.metrics_collector.collect()
            
            # Check system health
            health_status = await self.monitor.check_health()
            
            # Process any pending tasks
            await self.task_manager.process_pending_tasks()
            
            # Log periodic status
            if int(time.time()) % 60 == 0:  # Every minute
                uptime = datetime.now() - self.start_time if self.start_time else "N/A"
                self.logger.info(f"Status - Uptime: {uptime}, Health: {health_status}")
            
        except Exception as e:
            self.logger.error(f"Error during maintenance cycle: {e}")
    
    async def execute_command(self, command: str, params: Dict[str, Any]) -> Dict[str, Any]:
        """
        Execute a remote management command.
        
        Args:
            command: Command name
            params: Command parameters
            
        Returns:
            Command execution result
        """
        self.logger.info(f"Executing command: {command} with params: {params}")
        
        try:
            # Validate command
            if not self.security_manager.validate_command(command, params):
                raise RDMAException(f"Invalid or unauthorized command: {command}")
            
            # Execute command through task manager
            result = await self.task_manager.execute_command(command, params)
            
            self.logger.info(f"Command executed successfully: {command}")
            return result
            
        except Exception as e:
            self.logger.error(f"Error executing command {command}: {e}")
            raise RDMAException(f"Failed to execute command {command}: {e}")
    
    async def get_status(self) -> Dict[str, Any]:
        """
        Get current RDMAgent status.
        
        Returns:
            Status information dictionary
        """
        uptime = datetime.now() - self.start_time if self.start_time else None
        
        return {
            "running": self.is_running,
            "uptime": str(uptime) if uptime else "Not started",
            "start_time": self.start_time.isoformat() if self.start_time else None,
            "config": self.config.dict() if hasattr(self.config, 'dict') else str(self.config),
            "components": {
                "protocols": self.protocol_manager.get_status(),
                "monitor": self.monitor.get_status(),
                "metrics": self.metrics_collector.get_status(),
                "tasks": self.task_manager.get_status(),
                "security": self.security_manager.get_status()
            }
        }
    
    async def get_metrics(self) -> Dict[str, Any]:
        """
        Get current system metrics.
        
        Returns:
            Metrics dictionary
        """
        return await self.metrics_collector.get_current_metrics()
    
    def get_logger(self) -> RDMALogger:
        """Get the RDMA logger instance."""
        return self.logger
    
    def get_config(self) -> Any:
        """Get the current configuration."""
        return self.config


async def main():
    """
    Main entry point for the RDMAgent.
    """
    import argparse
    
    parser = argparse.ArgumentParser(description="RDMA - Remote Digital Management Agent")
    parser.add_argument(
        "--config", "-c",
        type=str,
        help="Path to configuration file",
        default=None
    )
    parser.add_argument(
        "--daemon", "-d",
        action="store_true",
        help="Run as daemon"
    )
    parser.add_argument(
        "--verbose", "-v",
        action="store_true",
        help="Verbose logging"
    )
    
    args = parser.parse_args()
    
    # Create and start agent
    agent = RDMAgent(args.config)
    
    try:
        await agent.start()
    except KeyboardInterrupt:
        print("\nShutting down RDMAgent...")
        await agent.stop()
    except Exception as e:
        print(f"Fatal error: {e}")
        sys.exit(1)


if __name__ == "__main__":
    asyncio.run(main())


class RDMAgentService:
    """
    Service wrapper for RDMAgent to integrate with system service managers.
    """
    
    def __init__(self, config_path: Optional[str] = None):
        self.agent = RDMAgent(config_path)
        self._loop = None
    
    def start(self) -> None:
        """Start the service."""
        self._loop = asyncio.new_event_loop()
        asyncio.set_event_loop(self._loop)
        
        try:
            self._loop.run_until_complete(self.agent.start())
        except Exception as e:
            print(f"Service error: {e}")
            sys.exit(1)
    
    def stop(self) -> None:
        """Stop the service."""
        if self._loop and self.agent.is_running:
            self._loop.run_until_complete(self.agent.stop())
            self._loop.close()
    
    def reload(self) -> None:
        """Reload configuration."""
        if self.agent.is_running:
            asyncio.create_task(self.agent.config_manager.reload_config())


# Service integration for systemd, etc.
service_instance = None

def start_service(config_path: Optional[str] = None) -> None:
    """Start RDMAgent as a service."""
    global service_instance
    service_instance = RDMAgentService(config_path)
    service_instance.start()

def stop_service() -> None:
    """Stop RDMAgent service."""
    global service_instance
    if service_instance:
        service_instance.stop()

def reload_service() -> None:
    """Reload RDMAgent service configuration."""
    global service_instance
    if service_instance:
        service_instance.reload()