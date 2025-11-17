"""
RDMA Configuration Management

Handles all configuration aspects for the RDMA system including:
- Agent configuration
- Protocol settings
- Monitoring parameters
- Amateur radio settings
"""

import os
import json
import yaml
from pathlib import Path
from typing import Dict, Any, Optional, Union, List
from dataclasses import dataclass, field, asdict
from enum import Enum

from .exceptions import ConfigurationError


class LogLevel(Enum):
    """Log level enumeration."""
    DEBUG = "DEBUG"
    INFO = "INFO"
    WARNING = "WARNING"
    ERROR = "ERROR"
    CRITICAL = "CRITICAL"


@dataclass
class LoggingConfig:
    """Logging configuration."""
    level: str = "INFO"
    file: Optional[str] = None
    max_size: int = 10 * 1024 * 1024  # 10MB
    backup_count: int = 5
    format: str = "%(asctime)s - %(name)s - %(levelname)s - %(message)s"
    console: bool = True


@dataclass
class ProtocolConfig:
    """Protocol configuration."""
    enabled: bool = True
    type: str = "mqtt"
    host: str = "localhost"
    port: int = 1883
    ssl: bool = False
    auth: Optional[Dict[str, str]] = None
    options: Dict[str, Any] = field(default_factory=dict)


@dataclass
class MonitoringConfig:
    """Monitoring configuration."""
    enabled: bool = True
    interval: int = 60  # seconds
    metrics_enabled: bool = True
    health_checks: List[str] = field(default_factory=lambda: ["cpu", "memory", "disk"])
    thresholds: Dict[str, float] = field(default_factory=dict)


@dataclass
class HamRadioConfig:
    """Amateur radio (ULTRON integration) configuration."""
    enabled: bool = False
    udp_port: int = 2237
    udp_forward_port: int = 2277
    signal_threshold: int = -20  # dB
    timeout_seconds: int = 90
    log_file: str = "wsjtx_log.adi"
    base_file: str = "base.json"
    auto_cq: bool = True
    dxcc_whitelist_only: bool = False
    dxcc_whitelist: Dict[str, str] = field(default_factory=dict)
    band_whitelist: Dict[str, Dict[str, str]] = field(default_factory=dict)


@dataclass
class SecurityConfig:
    """Security configuration."""
    enabled: bool = True
    auth_required: bool = False
    token_expiry: int = 3600  # seconds
    max_failed_attempts: int = 5
    lockout_duration: int = 300  # seconds
    allowed_commands: List[str] = field(default_factory=lambda: ["status", "metrics"])
    encryption_key: Optional[str] = None


@dataclass
class TaskConfig:
    """Task management configuration."""
    max_concurrent: int = 10
    timeout: int = 300  # seconds
    retry_attempts: int = 3
    retry_delay: int = 5  # seconds
    allowed_tasks: List[str] = field(default_factory=list)


@dataclass
class Config:
    """Main RDMA configuration."""
    agent_id: str = "rdma-agent"
    instance_name: str = "default"
    
    # Core configurations
    logging: LoggingConfig = field(default_factory=LoggingConfig)
    protocols: Dict[str, ProtocolConfig] = field(default_factory=dict)
    monitoring: MonitoringConfig = field(default_factory=MonitoringConfig)
    security: SecurityConfig = field(default_factory=SecurityConfig)
    tasks: TaskConfig = field(default_factory=TaskConfig)
    
    # Amateur radio configuration
    ham_radio: HamRadioConfig = field(default_factory=HamRadioConfig)
    
    # Additional settings
    data_dir: str = "data"
    config_dir: str = "config"
    log_dir: str = "logs"
    temp_dir: str = "temp"
    
    def dict(self) -> Dict[str, Any]:
        """Convert configuration to dictionary."""
        return asdict(self)
    
    @classmethod
    def from_dict(cls, data: Dict[str, Any]) -> 'Config':
        """Create configuration from dictionary."""
        return cls(**data)


class ConfigManager:
    """Configuration manager for RDMA."""
    
    DEFAULT_CONFIG_FILES = [
        "rdma.yaml",
        "rdma.yml", 
        "rdma.json",
        "config/rdma.yaml",
        "config/rdma.json",
        "~/.rdma/config.yaml",
        "~/.rdma/config.json",
        "/etc/rdma/config.yaml",
        "/etc/rdma/config.json"
    ]
    
    def __init__(self, config_path: Optional[str] = None):
        self.config_path = self._resolve_config_path(config_path)
        self.config = self._load_default_config()
        self._load_config()
    
    def _resolve_config_path(self, config_path: Optional[str]) -> Optional[Path]:
        """Resolve configuration file path."""
        if config_path:
            path = Path(config_path).expanduser().resolve()
            if path.exists():
                return path
            else:
                raise ConfigurationError(f"Configuration file not found: {config_path}")
        
        # Search for default config files
        for config_file in self.DEFAULT_CONFIG_FILES:
            path = Path(config_file).expanduser().resolve()
            if path.exists():
                return path
        
        return None
    
    def _load_default_config(self) -> Config:
        """Load default configuration."""
        return Config()
    
    def _load_config(self) -> None:
        """Load configuration from file."""
        if not self.config_path:
            # Use default configuration
            return
        
        try:
            if self.config_path.suffix.lower() in ['.yaml', '.yml']:
                self.config = self._load_yaml_config(self.config_path)
            elif self.config_path.suffix.lower() == '.json':
                self.config = self._load_json_config(self.config_path)
            else:
                raise ConfigurationError(f"Unsupported configuration file format: {self.config_path.suffix}")
        except Exception as e:
            raise ConfigurationError(f"Failed to load configuration: {e}")
    
    def _load_yaml_config(self, path: Path) -> Config:
        """Load YAML configuration."""
        with open(path, 'r', encoding='utf-8') as f:
            data = yaml.safe_load(f)
        
        return self._parse_config_data(data)
    
    def _load_json_config(self, path: Path) -> Config:
        """Load JSON configuration."""
        with open(path, 'r', encoding='utf-8') as f:
            data = json.load(f)
        
        return self._parse_config_data(data)
    
    def _parse_config_data(self, data: Dict[str, Any]) -> Config:
        """Parse configuration data into Config object."""
        # Create base config
        config = Config()
        
        # Update with provided data
        if "agent_id" in data:
            config.agent_id = data["agent_id"]
        if "instance_name" in data:
            config.instance_name = data["instance_name"]
        
        # Parse logging configuration
        if "logging" in data:
            config.logging = LoggingConfig(**data["logging"])
        
        # Parse protocols configuration
        if "protocols" in data:
            protocols = {}
            for name, proto_data in data["protocols"].items():
                protocols[name] = ProtocolConfig(**proto_data)
            config.protocols = protocols
        
        # Parse monitoring configuration
        if "monitoring" in data:
            config.monitoring = MonitoringConfig(**data["monitoring"])
        
        # Parse security configuration
        if "security" in data:
            config.security = SecurityConfig(**data["security"])
        
        # Parse tasks configuration
        if "tasks" in data:
            config.tasks = TaskConfig(**data["tasks"])
        
        # Parse ham radio configuration
        if "ham_radio" in data:
            config.ham_radio = HamRadioConfig(**data["ham_radio"])
        
        # Parse directory settings
        for dir_name in ["data_dir", "config_dir", "log_dir", "temp_dir"]:
            if dir_name in data:
                setattr(config, dir_name, data[dir_name])
        
        return config
    
    def get_config(self) -> Config:
        """Get current configuration."""
        return self.config
    
    def save_config(self, path: Optional[str] = None) -> None:
        """Save current configuration to file."""
        save_path = Path(path) if path else self.config_path
        
        if not save_path:
            raise ConfigurationError("No configuration path specified")
        
        try:
            save_path.parent.mkdir(parents=True, exist_ok=True)
            
            if save_path.suffix.lower() in ['.yaml', '.yml']:
                self._save_yaml_config(save_path)
            elif save_path.suffix.lower() == '.json':
                self._save_json_config(save_path)
            else:
                raise ConfigurationError(f"Unsupported configuration file format: {save_path.suffix}")
                
        except Exception as e:
            raise ConfigurationError(f"Failed to save configuration: {e}")
    
    def _save_yaml_config(self, path: Path) -> None:
        """Save configuration as YAML."""
        with open(path, 'w', encoding='utf-8') as f:
            yaml.dump(self.config.dict(), f, default_flow_style=False, indent=2)
    
    def _save_json_config(self, path: Path) -> None:
        """Save configuration as JSON."""
        with open(path, 'w', encoding='utf-8') as f:
            json.dump(self.config.dict(), f, indent=2)
    
    def reload_config(self) -> None:
        """Reload configuration from file."""
        self.config = self._load_default_config()
        self._load_config()
    
    def validate_config(self) -> List[str]:
        """Validate current configuration and return list of issues."""
        issues = []
        
        # Validate agent ID
        if not self.config.agent_id or len(self.config.agent_id.strip()) == 0:
            issues.append("Agent ID cannot be empty")
        
        # Validate protocols
        for name, protocol in self.config.protocols.items():
            if protocol.enabled:
                if not protocol.host or len(protocol.host.strip()) == 0:
                    issues.append(f"Protocol {name}: host cannot be empty")
                if protocol.port <= 0 or protocol.port > 65535:
                    issues.append(f"Protocol {name}: port must be between 1 and 65535")
        
        # Validate ham radio configuration
        if self.config.ham_radio.enabled:
            if self.config.ham_radio.udp_port <= 0 or self.config.ham_radio.udp_port > 65535:
                issues.append("Ham radio UDP port must be between 1 and 65535")
            if self.config.ham_radio.udp_forward_port <= 0 or self.config.ham_radio.udp_forward_port > 65535:
                issues.append("Ham radio UDP forward port must be between 1 and 65535")
            if self.config.ham_radio.signal_threshold > 0:
                issues.append("Ham radio signal threshold must be negative (in dB)")
        
        # Validate logging level
        valid_levels = [level.value for level in LogLevel]
        if self.config.logging.level not in valid_levels:
            issues.append(f"Invalid logging level: {self.config.logging.level}")
        
        return issues
    
    def create_default_config_file(self, path: str, format: str = "yaml") -> None:
        """Create a default configuration file."""
        config_path = Path(path)
        config_path.parent.mkdir(parents=True, exist_ok=True)
        
        default_config = self._generate_default_config()
        
        if format.lower() == "yaml":
            self._save_yaml_config(config_path)
        elif format.lower() == "json":
            with open(config_path, 'w', encoding='utf-8') as f:
                json.dump(default_config, f, indent=2)
        else:
            raise ConfigurationError(f"Unsupported format: {format}")
    
    def _generate_default_config(self) -> Dict[str, Any]:
        """Generate default configuration dictionary."""
        return {
            "agent_id": "rdma-agent",
            "instance_name": "default",
            "logging": {
                "level": "INFO",
                "file": "logs/rdma.log",
                "max_size": 10485760,
                "backup_count": 5,
                "format": "%(asctime)s - %(name)s - %(levelname)s - %(message)s",
                "console": True
            },
            "protocols": {
                "mqtt": {
                    "enabled": True,
                    "type": "mqtt",
                    "host": "localhost",
                    "port": 1883,
                    "ssl": False,
                    "auth": None,
                    "options": {
                        "client_id": "rdma-agent",
                        "subscribe_topics": ["rdma/requests"],
                        "publish_topic": "rdma/responses"
                    }
                },
                "http": {
                    "enabled": True,
                    "type": "http",
                    "host": "0.0.0.0",
                    "port": 8080,
                    "ssl": False,
                    "auth": None,
                    "options": {}
                }
            },
            "monitoring": {
                "enabled": True,
                "interval": 60,
                "metrics_enabled": True,
                "health_checks": ["cpu", "memory", "disk"],
                "thresholds": {
                    "cpu_percent": 80.0,
                    "memory_percent": 85.0,
                    "disk_percent": 90.0
                }
            },
            "security": {
                "enabled": True,
                "auth_required": False,
                "token_expiry": 3600,
                "max_failed_attempts": 5,
                "lockout_duration": 300,
                "allowed_commands": ["status", "metrics"],
                "encryption_key": None
            },
            "tasks": {
                "max_concurrent": 10,
                "timeout": 300,
                "retry_attempts": 3,
                "retry_delay": 5,
                "allowed_tasks": []
            },
            "ham_radio": {
                "enabled": False,
                "udp_port": 2237,
                "udp_forward_port": 2277,
                "signal_threshold": -20,
                "timeout_seconds": 90,
                "log_file": "wsjtx_log.adi",
                "base_file": "base.json",
                "auto_cq": True,
                "dxcc_whitelist_only": False,
                "dxcc_whitelist": {},
                "band_whitelist": {}
            },
            "data_dir": "data",
            "config_dir": "config",
            "log_dir": "logs",
            "temp_dir": "temp"
        }