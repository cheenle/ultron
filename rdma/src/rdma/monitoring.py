"""
RDMA Monitoring and Metrics Collection

Provides system monitoring, health checks, and metrics collection capabilities.
"""

import asyncio
import time
import psutil
from typing import Dict, Any, Optional, List
from dataclasses import dataclass
from datetime import datetime
import json

from .config import MonitoringConfig
from .logging import RDMALogger, MetricsLogger


@dataclass
class SystemMetrics:
    """System performance metrics."""
    timestamp: float
    cpu_percent: float
    memory_percent: float
    memory_available: int
    disk_usage: Dict[str, float]
    network_io: Dict[str, int]
    load_average: Optional[List[float]] = None


@dataclass
class ProcessMetrics:
    """Process-specific metrics."""
    timestamp: float
    pid: int
    name: str
    cpu_percent: float
    memory_percent: float
    memory_rss: int
    memory_vms: int
    num_threads: int
    status: str


class MetricsCollector:
    """Collects and manages system and application metrics."""
    
    def __init__(self, config: MonitoringConfig, logger: RDMALogger):
        self.config = config
        self.logger = logger
        self.metrics_logger = MetricsLogger(config)
        self.metrics_history: List[Dict[str, Any]] = []
        self.max_history_size = 1000
        self.is_collecting = False
        self._collection_task: Optional[asyncio.Task] = None
    
    async def start(self) -> None:
        """Start metrics collection."""
        if not self.config.metrics_enabled:
            self.logger.info("Metrics collection is disabled")
            return
        
        if self.is_collecting:
            self.logger.warning("Metrics collector is already running")
            return
        
        self.is_collecting = True
        self._collection_task = asyncio.create_task(self._collection_loop())
        self.logger.info("Metrics collector started")
    
    async def stop(self) -> None:
        """Stop metrics collection."""
        if not self.is_collecting:
            return
        
        self.is_collecting = False
        
        if self._collection_task:
            self._collection_task.cancel()
            try:
                await self._collection_task
            except asyncio.CancelledError:
                pass
        
        self.logger.info("Metrics collector stopped")
    
    async def _collection_loop(self) -> None:
        """Main metrics collection loop."""
        try:
            while self.is_collecting:
                try:
                    # Collect system metrics
                    system_metrics = self._collect_system_metrics()
                    
                    # Log metrics
                    self.metrics_logger.log_system_metrics(
                        system_metrics.cpu_percent,
                        system_metrics.memory_percent,
                        system_metrics.disk_usage.get("/", 0.0)
                    )
                    
                    # Store in history
                    self._store_metrics(system_metrics)
                    
                    # Wait for next collection cycle
                    await asyncio.sleep(self.config.interval)
                    
                except Exception as e:
                    self.logger.error(f"Error collecting metrics: {e}")
                    await asyncio.sleep(self.config.interval)
                    
        except asyncio.CancelledError:
            self.logger.debug("Metrics collection loop cancelled")
        except Exception as e:
            self.logger.error(f"Fatal error in metrics collection: {e}")
    
    def _collect_system_metrics(self) -> SystemMetrics:
        """Collect current system metrics."""
        timestamp = time.time()
        
        # CPU usage
        cpu_percent = psutil.cpu_percent(interval=1)
        
        # Memory usage
        memory = psutil.virtual_memory()
        memory_percent = memory.percent
        memory_available = memory.available
        
        # Disk usage
        disk_usage = {}
        for partition in psutil.disk_partitions():
            try:
                usage = psutil.disk_usage(partition.mountpoint)
                disk_usage[partition.mountpoint] = usage.percent
            except PermissionError:
                # Skip partitions we can't access
                continue
        
        # Network I/O
        network_io = {}
        try:
            net_io = psutil.net_io_counters()
            network_io["bytes_sent"] = net_io.bytes_sent
            network_io["bytes_recv"] = net_io.bytes_recv
            network_io["packets_sent"] = net_io.packets_sent
            network_io["packets_recv"] = net_io.packets_recv
        except AttributeError:
            # Network stats not available
            pass
        
        # Load average (Unix systems only)
        load_average = None
        try:
            load_avg = psutil.getloadavg()
            load_average = list(load_avg)
        except AttributeError:
            # Load average not available on Windows
            pass
        
        return SystemMetrics(
            timestamp=timestamp,
            cpu_percent=cpu_percent,
            memory_percent=memory_percent,
            memory_available=memory_available,
            disk_usage=disk_usage,
            network_io=network_io,
            load_average=load_average
        )
    
    def _store_metrics(self, metrics: SystemMetrics) -> None:
        """Store metrics in history."""
        metrics_dict = {
            "timestamp": metrics.timestamp,
            "cpu_percent": metrics.cpu_percent,
            "memory_percent": metrics.memory_percent,
            "memory_available": metrics.memory_available,
            "disk_usage": metrics.disk_usage,
            "network_io": metrics.network_io,
            "load_average": metrics.load_average
        }
        
        self.metrics_history.append(metrics_dict)
        
        # Keep only recent metrics
        if len(self.metrics_history) > self.max_history_size:
            self.metrics_history = self.metrics_history[-self.max_history_size:]
    
    async def get_current_metrics(self) -> Dict[str, Any]:
        """Get current system metrics."""
        if not self.is_collecting:
            # Collect metrics on demand
            system_metrics = self._collect_system_metrics()
        else:
            # Use latest from history
            if self.metrics_history:
                latest = self.metrics_history[-1]
                system_metrics = SystemMetrics(**latest)
            else:
                system_metrics = self._collect_system_metrics()
        
        return {
            "timestamp": system_metrics.timestamp,
            "system": {
                "cpu_percent": system_metrics.cpu_percent,
                "memory_percent": system_metrics.memory_percent,
                "memory_available": system_metrics.memory_available,
                "disk_usage": system_metrics.disk_usage,
                "network_io": system_metrics.network_io,
                "load_average": system_metrics.load_average
            }
        }
    
    def get_metrics_history(self, limit: int = 100) -> List[Dict[str, Any]]:
        """Get metrics history."""
        return self.metrics_history[-limit:]
    
    def get_status(self) -> Dict[str, Any]:
        """Get metrics collector status."""
        return {
            "enabled": self.config.metrics_enabled,
            "collecting": self.is_collecting,
            "history_size": len(self.metrics_history),
            "interval": self.config.interval
        }


class Monitor:
    """System monitor for health checks and alerting."""
    
    def __init__(self, config: MonitoringConfig, logger: RDMALogger):
        self.config = config
        self.logger = logger
        self.is_monitoring = False
        self._monitor_task: Optional[asyncio.Task] = None
        self.health_status: Dict[str, Any] = {}
        self.alerts: List[Dict[str, Any]] = []
    
    async def start(self) -> None:
        """Start monitoring."""
        if not self.config.enabled:
            self.logger.info("Monitoring is disabled")
            return
        
        if self.is_monitoring:
            self.logger.warning("Monitor is already running")
            return
        
        self.is_monitoring = True
        self._monitor_task = asyncio.create_task(self._monitoring_loop())
        self.logger.info("Monitor started")
    
    async def stop(self) -> None:
        """Stop monitoring."""
        if not self.is_monitoring:
            return
        
        self.is_monitoring = False
        
        if self._monitor_task:
            self._monitor_task.cancel()
            try:
                await self._monitor_task
            except asyncio.CancelledError:
                pass
        
        self.logger.info("Monitor stopped")
    
    async def _monitoring_loop(self) -> None:
        """Main monitoring loop."""
        try:
            while self.is_monitoring:
                try:
                    # Perform health checks
                    await self._perform_health_checks()
                    
                    # Wait for next check cycle
                    await asyncio.sleep(self.config.interval)
                    
                except Exception as e:
                    self.logger.error(f"Error in monitoring loop: {e}")
                    await asyncio.sleep(self.config.interval)
                    
        except asyncio.CancelledError:
            self.logger.debug("Monitoring loop cancelled")
        except Exception as e:
            self.logger.error(f"Fatal error in monitoring: {e}")
    
    async def _perform_health_checks(self) -> None:
        """Perform health checks based on configuration."""
        self.health_status = {
            "timestamp": datetime.utcnow().isoformat(),
            "overall": "healthy",
            "checks": {}
        }
        
        for check_name in self.config.health_checks:
            try:
                if check_name == "cpu":
                    result = await self._check_cpu()
                elif check_name == "memory":
                    result = await self._check_memory()
                elif check_name == "disk":
                    result = await self._check_disk()
                elif check_name == "network":
                    result = await self._check_network()
                elif check_name == "load":
                    result = await self._check_load()
                else:
                    self.logger.warning(f"Unknown health check: {check_name}")
                    continue
                
                self.health_status["checks"][check_name] = result
                
                # Update overall status
                if result["status"] == "critical":
                    self.health_status["overall"] = "critical"
                elif result["status"] == "warning" and self.health_status["overall"] == "healthy":
                    self.health_status["overall"] = "warning"
                
            except Exception as e:
                self.logger.error(f"Health check {check_name} failed: {e}")
                self.health_status["checks"][check_name] = {
                    "status": "error",
                    "message": str(e),
                    "timestamp": datetime.utcnow().isoformat()
                }
                self.health_status["overall"] = "critical"
    
    async def _check_cpu(self) -> Dict[str, Any]:
        """Check CPU usage."""
        cpu_percent = psutil.cpu_percent(interval=1)
        threshold = self.config.thresholds.get("cpu_percent", 80.0)
        
        status = "healthy"
        if cpu_percent > threshold:
            status = "warning"
        if cpu_percent > threshold * 1.2:  # 20% over threshold
            status = "critical"
        
        return {
            "status": status,
            "cpu_percent": cpu_percent,
            "threshold": threshold,
            "timestamp": datetime.utcnow().isoformat()
        }
    
    async def _check_memory(self) -> Dict[str, Any]:
        """Check memory usage."""
        memory = psutil.virtual_memory()
        threshold = self.config.thresholds.get("memory_percent", 85.0)
        
        status = "healthy"
        if memory.percent > threshold:
            status = "warning"
        if memory.percent > threshold * 1.15:  # 15% over threshold
            status = "critical"
        
        return {
            "status": status,
            "memory_percent": memory.percent,
            "memory_available": memory.available,
            "memory_total": memory.total,
            "threshold": threshold,
            "timestamp": datetime.utcnow().isoformat()
        }
    
    async def _check_disk(self) -> Dict[str, Any]:
        """Check disk usage."""
        disk_usage = {}
        overall_status = "healthy"
        
        for partition in psutil.disk_partitions():
            try:
                usage = psutil.disk_usage(partition.mountpoint)
                threshold = self.config.thresholds.get("disk_percent", 90.0)
                
                status = "healthy"
                if usage.percent > threshold:
                    status = "warning"
                if usage.percent > 95.0:  # Critical at 95%
                    status = "critical"
                
                disk_usage[partition.mountpoint] = {
                    "percent": usage.percent,
                    "used": usage.used,
                    "free": usage.free,
                    "total": usage.total,
                    "status": status
                }
                
                # Update overall status
                if status == "critical":
                    overall_status = "critical"
                elif status == "warning" and overall_status == "healthy":
                    overall_status = "warning"
                    
            except PermissionError:
                # Skip partitions we can't access
                continue
        
        return {
            "status": overall_status,
            "disk_usage": disk_usage,
            "timestamp": datetime.utcnow().isoformat()
        }
    
    async def _check_network(self) -> Dict[str, Any]:
        """Check network connectivity."""
        try:
            net_io = psutil.net_io_counters()
            
            return {
                "status": "healthy",
                "bytes_sent": net_io.bytes_sent,
                "bytes_recv": net_io.bytes_recv,
                "packets_sent": net_io.packets_sent,
                "packets_recv": net_io.packets_recv,
                "timestamp": datetime.utcnow().isoformat()
            }
        except AttributeError:
            return {
                "status": "unknown",
                "message": "Network statistics not available",
                "timestamp": datetime.utcnow().isoformat()
            }
    
    async def _check_load(self) -> Dict[str, Any]:
        """Check system load average."""
        try:
            load_avg = psutil.getloadavg()
            cpu_count = psutil.cpu_count()
            
            # Normalize load average by CPU count
            normalized_load = [load / cpu_count for load in load_avg]
            
            status = "healthy"
            if max(normalized_load) > 2.0:  # Load > 2x CPU count
                status = "warning"
            if max(normalized_load) > 4.0:  # Load > 4x CPU count
                status = "critical"
            
            return {
                "status": status,
                "load_average": list(load_avg),
                "normalized_load": normalized_load,
                "cpu_count": cpu_count,
                "timestamp": datetime.utcnow().isoformat()
            }
        except AttributeError:
            return {
                "status": "unknown",
                "message": "Load average not available on this platform",
                "timestamp": datetime.utcnow().isoformat()
            }
    
    def get_health_status(self) -> Dict[str, Any]:
        """Get current health status."""
        return self.health_status.copy()
    
    def get_status(self) -> Dict[str, Any]:
        """Get monitor status."""
        return {
            "enabled": self.config.enabled,
            "monitoring": self.is_monitoring,
            "interval": self.config.interval,
            "health_checks": self.config.health_checks,
            "overall_health": self.health_status.get("overall", "unknown"),
            "last_check": self.health_status.get("timestamp")
        }
    
    def get_alerts(self) -> List[Dict[str, Any]]:
        """Get current alerts."""
        return self.alerts.copy()
    
    def clear_alerts(self) -> None:
        """Clear all alerts."""
        self.alerts.clear()


class AlertManager:
    """Manages alerts and notifications."""
    
    def __init__(self, logger: RDMALogger):
        self.logger = logger
        self.alerts: List[Dict[str, Any]] = []
        self.alert_handlers: List[callable] = []
    
    def add_alert(self, level: str, component: str, message: str, data: Optional[Dict] = None):
        """Add an alert."""
        alert = {
            "timestamp": datetime.utcnow().isoformat(),
            "level": level,
            "component": component,
            "message": message,
            "data": data or {}
        }
        
        self.alerts.append(alert)
        self.logger.warning(f"ALERT: {json.dumps(alert)}")
        
        # Notify handlers
        for handler in self.alert_handlers:
            try:
                handler(alert)
            except Exception as e:
                self.logger.error(f"Error in alert handler: {e}")
    
    def add_alert_handler(self, handler: callable):
        """Add an alert handler."""
        self.alert_handlers.append(handler)
    
    def clear_alerts(self, level: Optional[str] = None, component: Optional[str] = None):
        """Clear alerts, optionally filtered by level or component."""
        if level is None and component is None:
            self.alerts.clear()
        else:
            self.alerts = [
                alert for alert in self.alerts
                if (level is None or alert["level"] != level) and
                   (component is None or alert["component"] != component)
            ]
    
    def get_alerts(self, level: Optional[str] = None, component: Optional[str] = None) -> List[Dict[str, Any]]:
        """Get alerts, optionally filtered."""
        if level is None and component is None:
            return self.alerts.copy()
        
        return [
            alert for alert in self.alerts
            if (level is None or alert["level"] == level) and
               (component is None or alert["component"] == component)
        ]