# RDMA - Remote Digital Management Agent

A comprehensive Python-based remote management and monitoring system that includes full integration of the ULTRON amateur radio automation functionality.

## 🎯 Overview

RDMA is a modern, modular remote management system that provides:

- **Remote Management**: Multi-protocol support (MQTT, HTTP, WebSocket)
- **Amateur Radio Integration**: Complete ULTRON functionality for JTDX/WSJT-X/MSHV
- **System Monitoring**: Real-time metrics and health checks
- **Task Management**: Command execution and scheduling
- **Security**: Authentication, authorization, and encryption
- **Extensibility**: Plugin architecture for custom functionality

## 📡 Amateur Radio Features

RDMA includes the complete ULTRON amateur radio automation system:

### Core Capabilities
- **UDP Protocol**: Full WSJT-X/JTDX/MSHV compatibility
- **Auto QSO**: Automatic CQ calling and response handling
- **DXCC Targeting**: Entity-based QSO prioritization
- **ADIF Logging**: Amateur Data Interchange Format support
- **Signal Processing**: -20dB threshold filtering
- **State Management**: Complete QSO lifecycle tracking

### Commands
```bash
# Start amateur radio manager
rdma ham start --port 2237 --signal-threshold -20

# Get DXCC information
rdma ham dxcc K1ABC

# Check if callsign was worked
rdma ham worked W2DEF

# Monitor real-time activity
rdma ham monitor

# Show current status
rdma ham status
```

## 🚀 Installation

### Prerequisites
- Python 3.8 or higher
- pip package manager

### Install from Source
```bash
git clone https://github.com/your-org/rdma.git
cd rdma
pip install -e .
```

### Install with Development Dependencies
```bash
pip install -e ".[dev]"
```

## ⚙️ Configuration

### Basic Configuration
Create a configuration file `rdma.yaml`:

```yaml
agent_id: "my-rdma-agent"
instance_name: "main"

ham_radio:
  enabled: true
  udp_port: 2237
  udp_forward_port: 2277
  signal_threshold: -20
  timeout_seconds: 90
  log_file: "wsjtx_log.adi"
  base_file: "base.json"
  auto_cq: true
  dxcc_whitelist_only: false
  dxcc_whitelist:
    "1": "UNITED STATES"
    "110": "SPAIN"
    "284": "BULGARIA"

protocols:
  mqtt:
    enabled: true
    host: "localhost"
    port: 1883
    options:
      subscribe_topics: ["rdma/requests"]
      publish_topic: "rdma/responses"
  
  http:
    enabled: true
    host: "0.0.0.0"
    port: 8080

monitoring:
  enabled: true
  interval: 60
  metrics_enabled: true
  health_checks: ["cpu", "memory", "disk"]

logging:
  level: "INFO"
  file: "logs/rdma.log"
  console: true
```

### DXCC Configuration
Configure DXCC targeting in your config file:

```yaml
ham_radio:
  dxcc_whitelist_only: false  # false = priority mode, true = whitelist only
  dxcc_whitelist:
    "1": "UNITED STATES"
    "110": "SPAIN"
    "284": "BULGARIA"
    "206": "HUNGARY"
    "280": "GERMANY"
  
  band_whitelist:
    "20m":
      "1": "UNITED STATES"
      "110": "SPAIN"
    "40m":
      "284": "BULGARIA"
      "206": "HUNGARY"
```

## 🎮 Usage

### Start the Agent
```bash
# Start with default configuration
rdma start

# Start with custom configuration
rdma start --config /path/to/config.yaml

# Start as daemon
rdma start --daemon
```

### Amateur Radio Operations
```bash
# Start ham radio manager
rdma ham start --port 2237 --signal-threshold -20

# Get DXCC info for a callsign
rdma ham dxcc JA1XYZ

# Check if callsign was worked before
rdma ham worked K1ABC

# Show current status
rdma ham status

# Monitor real-time decodes
rdma ham monitor

# Manually log a QSO
rdma ham log W2DEF
```

### System Management
```bash
# Show system status
rdma status

# Get system metrics
rdma metrics

# Show configuration
rdma config

# Validate configuration
rdma config --validate
```

## 🧪 Development

### Running Tests
```bash
# Run all tests
pytest

# Run amateur radio tests only
pytest tests/test_ham_radio.py

# Run with coverage
pytest --cov=rdma
```

### Code Quality
```bash
# Format code
black src/

# Check linting
flake8 src/

# Type checking
mypy src/
```

## 📁 Project Structure

```
rdma/
├── src/rdma/                    # Main source code
│   ├── __init__.py
│   ├── agent.py                 # Main agent class
│   ├── cli.py                   # Command-line interface
│   ├── config.py                # Configuration management
│   ├── ham_radio.py             # Amateur radio module
│   ├── logging.py               # Logging system
│   ├── monitoring.py            # System monitoring
│   ├── protocols.py             # Communication protocols
│   ├── security.py              # Security management
│   ├── tasks.py                 # Task management
│   └── exceptions.py            # Custom exceptions
├── tests/                       # Test files
│   ├── test_ham_radio.py
│   └── ...
├── config/                      # Configuration examples
├── examples/                    # Usage examples
├── docs/                        # Documentation
└── pyproject.toml              # Project configuration
```

## 🔧 Advanced Usage

### Custom Task Executors
Create custom task executors for specific operations:

```python
from rdma.tasks import TaskExecutor
from rdma.logging import RDMALogger

class CustomExecutor(TaskExecutor):
    async def execute(self, task):
        # Your custom logic here
        pass

# Register with task manager
manager.register_executor("custom", CustomExecutor("custom", logger))
```

### Protocol Extensions
Add support for new protocols:

```python
from rdma.protocols import ProtocolBase, ProtocolConfig

class CustomProtocol(ProtocolBase):
    async def connect(self):
        # Connection logic
        pass
    
    async def send_message(self, message, target=None):
        # Message sending logic
        pass
```

### Monitoring Integration
Integrate with external monitoring systems:

```python
# Custom metrics collection
metrics_collector.log_metric("custom_metric", value, {"tag": "value"})

# Custom health checks
monitor.add_health_check("custom", custom_check_function)
```

## 🔍 Troubleshooting

### Common Issues

**1. UDP Port Binding Error**
```
Error: Permission denied binding to UDP port 2237
```
**Solution**: Run with appropriate permissions or use a higher port number.

**2. DXCC Database Not Found**
```
Warning: Could not load DXCC database
```
**Solution**: Download the DXCC database file and place it in the expected location.

**3. ADIF Log Parsing Errors**
```
Error: Invalid ADIF format
```
**Solution**: Check that your log file follows proper ADIF format specifications.

### Debug Mode
Enable debug logging:
```yaml
logging:
  level: "DEBUG"
  console: true
```

## 📊 Performance

### Optimization Tips
- Use appropriate timeout values for your network conditions
- Configure signal thresholds based on your antenna/receiver setup
- Monitor system resources when running multiple protocols
- Use the built-in health checks to identify performance issues

### Resource Usage
- Minimal CPU usage when idle
- Memory usage scales with number of active connections
- Network bandwidth depends on QSO activity level

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass
6. Submit a pull request

## 📄 License

MIT License - see LICENSE file for details.

## 🙏 Acknowledgments

- **LU9DCE (Eduardo Castillo)** - Original ULTRON PHP implementation
- **WSJT-X Development Team** - Excellent digital mode software
- **Amateur Radio Community** - Continuous feedback and support

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/your-org/rdma/issues)
- **Documentation**: [ReadTheDocs](https://rdma.readthedocs.io)
- **Community**: [Discussions](https://github.com/your-org/rdma/discussions)

---

**73 and enjoy the digital modes!** 📻✨

*This project integrates the complete ULTRON amateur radio automation system into a modern Python framework.*