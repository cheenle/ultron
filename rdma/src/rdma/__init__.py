"""
RDMA - Remote Digital Management Agent

A comprehensive remote management and monitoring system for digital infrastructure.
"""

__version__ = "0.1.0"
__author__ = "RDMA Development Team"
__email__ = "rdma@example.com"
__description__ = "Remote Digital Management Agent"

from .agent import RDMAgent
from .config import Config, ConfigManager
from .protocols import ProtocolManager, MQTTProtocol, HTTPProtocol, WebSocketProtocol
from .monitoring import Monitor, MetricsCollector
from .logging import RDMALogger
from .exceptions import RDMAException, ConfigurationError, ProtocolError

__all__ = [
    "RDMAgent",
    "Config",
    "ConfigManager", 
    "ProtocolManager",
    "MQTTProtocol",
    "HTTPProtocol",
    "WebSocketProtocol",
    "Monitor",
    "MetricsCollector",
    "RDMALogger",
    "RDMAException",
    "ConfigurationError",
    "ProtocolError",
]