"""
RDMA Logging System

Provides structured logging capabilities for the RDMA system.
"""

import logging
import logging.handlers
import sys
from pathlib import Path
from typing import Optional, Dict, Any
import json
from datetime import datetime

from .config import LoggingConfig


class JSONFormatter(logging.Formatter):
    """JSON formatter for structured logging."""
    
    def format(self, record):
        """Format log record as JSON."""
        log_entry = {
            "timestamp": datetime.utcnow().isoformat(),
            "level": record.levelname,
            "logger": record.name,
            "message": record.getMessage(),
            "module": record.module,
            "function": record.funcName,
            "line": record.lineno,
        }
        
        # Add exception info if present
        if record.exc_info:
            log_entry["exception"] = self.formatException(record.exc_info)
        
        # Add extra fields
        for key, value in record.__dict__.items():
            if key not in ['name', 'msg', 'args', 'levelname', 'levelno', 'pathname',
                          'filename', 'module', 'exc_info', 'exc_text', 'stack_info',
                          'lineno', 'funcName', 'created', 'msecs', 'relativeCreated',
                          'thread', 'threadName', 'processName', 'process', 'getMessage']:
                log_entry[key] = value
        
        return json.dumps(log_entry)


class RDMALogger:
    """RDMA system logger with enhanced functionality."""
    
    def __init__(self, config: LoggingConfig, name: str = "rdma"):
        self.config = config
        self.name = name
        self.logger = self._setup_logger()
    
    def _setup_logger(self) -> logging.Logger:
        """Set up the logger with appropriate handlers."""
        logger = logging.getLogger(self.name)
        logger.setLevel(getattr(logging, self.config.level.upper()))
        
        # Clear existing handlers
        logger.handlers.clear()
        
        # Console handler
        if self.config.console:
            console_handler = logging.StreamHandler(sys.stdout)
            console_formatter = logging.Formatter(self.config.format)
            console_handler.setFormatter(console_formatter)
            logger.addHandler(console_handler)
        
        # File handler
        if self.config.file:
            log_path = Path(self.config.file)
            log_path.parent.mkdir(parents=True, exist_ok=True)
            
            file_handler = logging.handlers.RotatingFileHandler(
                self.config.file,
                maxBytes=self.config.max_size,
                backupCount=self.config.backup_count
            )
            
            # Use JSON formatter for file logs
            file_formatter = JSONFormatter()
            file_handler.setFormatter(file_formatter)
            logger.addHandler(file_handler)
        
        return logger
    
    def debug(self, message: str, **kwargs):
        """Log debug message."""
        self.logger.debug(message, **kwargs)
    
    def info(self, message: str, **kwargs):
        """Log info message."""
        self.logger.info(message, **kwargs)
    
    def warning(self, message: str, **kwargs):
        """Log warning message."""
        self.logger.warning(message, **kwargs)
    
    def error(self, message: str, **kwargs):
        """Log error message."""
        self.logger.error(message, **kwargs)
    
    def critical(self, message: str, **kwargs):
        """Log critical message."""
        self.logger.critical(message, **kwargs)
    
    def exception(self, message: str, exc_info=True, **kwargs):
        """Log exception with traceback."""
        self.logger.exception(message, exc_info=exc_info, **kwargs)
    
    def log_metric(self, metric_name: str, value: float, tags: Optional[Dict[str, str]] = None):
        """Log a metric with optional tags."""
        metric_data = {
            "metric": metric_name,
            "value": value,
            "timestamp": datetime.utcnow().isoformat(),
            "tags": tags or {}
        }
        self.info(f"METRIC: {json.dumps(metric_data)}")
    
    def log_event(self, event_type: str, data: Dict[str, Any]):
        """Log an event with structured data."""
        event_data = {
            "event_type": event_type,
            "data": data,
            "timestamp": datetime.utcnow().isoformat()
        }
        self.info(f"EVENT: {json.dumps(event_data)}")
    
    def set_level(self, level: str):
        """Set logging level."""
        self.logger.setLevel(getattr(logging, level.upper()))
    
    def is_enabled_for(self, level: str) -> bool:
        """Check if logging level is enabled."""
        return self.logger.isEnabledFor(getattr(logging, level.upper()))


class HamRadioLogger(RDMALogger):
    """Specialized logger for amateur radio operations."""
    
    def __init__(self, config: LoggingConfig):
        super().__init__(config, "rdma.ham_radio")
    
    def log_decode(self, timestamp: str, snr: int, mode: str, message: str, 
                   callsign: str, dxcc: str, status: str):
        """Log a decoded transmission."""
        decode_data = {
            "timestamp": timestamp,
            "snr": snr,
            "mode": mode,
            "message": message,
            "callsign": callsign,
            "dxcc": dxcc,
            "status": status
        }
        self.info(f"DECODE: {json.dumps(decode_data)}")
    
    def log_qso_start(self, callsign: str, dxcc: str, mode: str, frequency: int):
        """Log QSO start."""
        qso_data = {
            "callsign": callsign,
            "dxcc": dxcc,
            "mode": mode,
            "frequency": frequency,
            "event": "qso_start"
        }
        self.info(f"QSO_START: {json.dumps(qso_data)}")
    
    def log_qso_end(self, callsign: str, success: bool, reason: str = ""):
        """Log QSO end."""
        qso_data = {
            "callsign": callsign,
            "success": success,
            "reason": reason,
            "event": "qso_end"
        }
        self.info(f"QSO_END: {json.dumps(qso_data)}")
    
    def log_cq_call(self, mode: str, message: str):
        """Log CQ call transmission."""
        cq_data = {
            "mode": mode,
            "message": message,
            "event": "cq_call"
        }
        self.info(f"CQ_CALL: {json.dumps(cq_data)}")
    
    def log_dxcc_target(self, callsign: str, dxcc_id: str, reason: str):
        """Log DXCC targeting decision."""
        target_data = {
            "callsign": callsign,
            "dxcc_id": dxcc_id,
            "reason": reason,
            "event": "dxcc_target"
        }
        self.info(f"DXCC_TARGET: {json.dumps(target_data)}")


class ProtocolLogger(RDMALogger):
    """Specialized logger for protocol operations."""
    
    def __init__(self, config: LoggingConfig, protocol_name: str):
        super().__init__(config, f"rdma.protocol.{protocol_name}")
        self.protocol_name = protocol_name
    
    def log_connection(self, host: str, port: int, success: bool, error: Optional[str] = None):
        """Log protocol connection attempt."""
        conn_data = {
            "protocol": self.protocol_name,
            "host": host,
            "port": port,
            "success": success,
            "error": error
        }
        if success:
            self.info(f"CONNECTION: {json.dumps(conn_data)}")
        else:
            self.error(f"CONNECTION_FAILED: {json.dumps(conn_data)}")
    
    def log_message_sent(self, message_type: str, target: str, size: int):
        """Log message transmission."""
        msg_data = {
            "protocol": self.protocol_name,
            "message_type": message_type,
            "target": target,
            "size": size
        }
        self.debug(f"MESSAGE_SENT: {json.dumps(msg_data)}")
    
    def log_message_received(self, message_type: str, source: str, size: int):
        """Log message reception."""
        msg_data = {
            "protocol": self.protocol_name,
            "message_type": message_type,
            "source": source,
            "size": size
        }
        self.debug(f"MESSAGE_RECEIVED: {json.dumps(msg_data)}")
    
    def log_protocol_error(self, error_type: str, error_message: str, context: Optional[Dict] = None):
        """Log protocol error."""
        error_data = {
            "protocol": self.protocol_name,
            "error_type": error_type,
            "error_message": error_message,
            "context": context or {}
        }
        self.error(f"PROTOCOL_ERROR: {json.dumps(error_data)}")


class MetricsLogger(RDMALogger):
    """Specialized logger for metrics collection."""
    
    def __init__(self, config: LoggingConfig):
        super().__init__(config, "rdma.metrics")
    
    def log_system_metrics(self, cpu_percent: float, memory_percent: float, disk_percent: float):
        """Log system metrics."""
        metrics_data = {
            "cpu_percent": cpu_percent,
            "memory_percent": memory_percent,
            "disk_percent": disk_percent,
            "metric_type": "system"
        }
        self.log_metric("system_resources", cpu_percent, {"type": "cpu"})
        self.log_metric("system_resources", memory_percent, {"type": "memory"})
        self.log_metric("system_resources", disk_percent, {"type": "disk"})
    
    def log_protocol_metrics(self, protocol_name: str, messages_sent: int, messages_received: int, errors: int):
        """Log protocol metrics."""
        self.log_metric(f"{protocol_name}_messages_sent", messages_sent)
        self.log_metric(f"{protocol_name}_messages_received", messages_received)
        self.log_metric(f"{protocol_name}_errors", errors)
    
    def log_task_metrics(self, tasks_completed: int, tasks_failed: int, avg_duration: float):
        """Log task execution metrics."""
        self.log_metric("tasks_completed", tasks_completed)
        self.log_metric("tasks_failed", tasks_failed)
        self.log_metric("task_avg_duration", avg_duration)


def setup_logging(config: LoggingConfig, name: str = "rdma") -> RDMALogger:
    """Set up logging with the given configuration."""
    return RDMALogger(config, name)


def get_logger(name: str = "rdma") -> logging.Logger:
    """Get a standard logger instance."""
    return logging.getLogger(name)