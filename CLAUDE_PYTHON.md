# CLAUDE.md - Python Version

This file provides guidance to Claude Code (claude.ai/code) when working with the Python version of ULTRON.

## Project Overview

ULTRON Python is a refactored version of the original PHP-based amateur radio automation tool. It provides complete compatibility with the original functionality while offering modern Python architecture, cross-platform support, and enhanced maintainability.

## Commands

### Running ULTRON Python

**Universal Python Script (Recommended):**
```bash
# Interactive menu
python run_ultron.py

# Specific modes
python run_ultron.py standard    # Standard ULTRON
python run_ultron.py dxcc        # Enhanced DXCC version
python run_ultron.py analyze     # DXCC analysis mode
```

**Platform-Specific Scripts:**
```bash
# Linux/macOS
./run_ultron.sh
./run_ultron.sh dxcc
./run_ultron.sh analyze

# Windows
run_ultron.bat
run_ultron.bat dxcc
run_ultron.bat analyze
```

**Direct Execution:**
```bash
# Standard version
python ultron.py

# DXCC enhanced version
python ultron_dxcc.py

# DXCC analysis only
python ultron_dxcc.py analyze
```

### Testing and Validation

```bash
# Installation test
python test_installation.py

# Basic functionality test
python -c "from ultron import Ultron; print('Import successful')"
```

## Architecture

### Core Components

1. **Main Modules:**
   - `ultron.py` - Standard ULTRON with core functionality
   - `ultron_dxcc.py` - Enhanced version with DXCC targeting
   - `dxcc_config.py` - DXCC whitelist configuration

2. **Key Classes:**
   - `Ultron` - Main application class handling UDP communication and QSO logic
   - `UltronDXCC` - Enhanced version with DXCC targeting capabilities
   - `WSJTXProtocol` - WSJT-X UDP protocol parser
   - `ADIFProcessor` - ADIF log format processor
   - `DXCCDatabase` - DXCC entity lookup and management
   - `QSOState` - QSO state management

3. **Support Classes:**
   - `TerminalUI` - Colored terminal output
   - `CallsignValidator` - Callsign format validation
   - `DXCCAnalyzer` - Log analysis and recommendations
   - `DXCCConfig` - Configuration management

### Protocol Implementation

**UDP Communication:**
- Listens on port 2237, forwards to 2277
- Handles WSJT-X protocol packets (types 0x00, 0x01, 0x02, 0x05, 0x0C)
- Real-time decode processing with signal strength filtering

**State Management:**
- `$sendcq` equivalent: `QSOState.sendcq` (bool)
- `$dxc` equivalent: `QSOState.current_call` (str)
- `$exclu` equivalent: `QSOState.excluded_calls` (set)
- `$tempo/$tempu` equivalent: `QSOState.tempo/tempu` (int timestamps)

### DXCC Targeting System

**Whitelist Configuration:**
- Global DXCC whitelist in `dxcc_config.dxcc_whitelist`
- Band-specific whitelist in `dxcc_config.band_whitelist`
- Two modes: priority mode (default) vs whitelist-only mode

**Analysis Features:**
- Automatic log analysis for worked/unworked entities
- Band-specific progress tracking
- Intelligent whitelist recommendations

## Development Guidelines

### Code Style
- Use type hints for all functions
- Follow PEP 8 naming conventions
- Use dataclasses for structured data
- Implement proper error handling with try/except

### Key Constants
```python
UDP_PORT = 2237              # Main UDP listen port
UDP_FORWARD_PORT = 2277      # Forward port
TIMEOUT_SECONDS = 90         # QSO timeout
SIGNAL_THRESHOLD = -20       # Signal strength filter
colorama.init()              # Windows color support
```

### Platform Compatibility
- Use `pathlib.Path` for file operations
- Use `platform.system()` for OS detection
- Handle different Python executable names (`python`, `python3`, `py`)
- Use ANSI color codes with fallbacks

### Error Handling Patterns
```python
try:
    # operation
except socket.error as e:
    print(f"{Colors.RED}Socket error: {e}{Colors.RESET}")
except json.JSONDecodeError as e:
    print(f"{Colors.YELLOW}JSON parse error: {e}{Colors.RESET}")
except Exception as e:
    print(f"{Colors.RED}Unexpected error: {e}{Colors.RESET}")
```

## Testing

### Unit Testing
```python
# Test ADIF processing
processor = ADIFProcessor()
test_data = "<call:4>K1ABC <gridsquare:4>FN42 <eor>"
result = processor.parse_adif(test_data)
assert result[0]['call'] == 'K1ABC'

# Test DXCC lookup
dxcc_db = DXCCDatabase()
info = dxcc_db.locate_call('K1ABC')
assert info['id'] != 'unknown'

# Test validation
validator = CallsignValidator()
assert validator.validate('K1ABC') == True
assert validator.validate('INVALID') == False
```

### Integration Testing
```bash
# Test UDP communication
python -c "
import socket
sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
sock.bind(('127.0.0.1', 2237))
print('UDP socket ready')
sock.close()
"

# Test file operations
python -c "
from pathlib import Path
log = Path('wsjtx_log.adi')
log.touch()
print('Log file created')
"
```

## Common Modifications

### Adding New Modes
Update the mode mapping in `ultron.py`:
```python
mode_map = {
    '~': 'FT8',
    '+': 'FT4',
    '#': 'JT65',
    '@': 'JT9',
    '`': 'FST4',
    ':': 'Q65',
    '&': 'MSK144',
    '$': 'JT4',
    # Add new modes here
}
```

### Changing Timeout Values
```python
# In ultron.py
TIMEOUT_SECONDS = 120  # Change from 90 to 120
```

### Custom Response Logic
Override `handle_response_logic` in `UltronDXCC` class:
```python
def handle_response_logic(self, parts: list, status: str, dxcc_info: dict):
    # Custom logic here
    super().handle_response_logic(parts, status, dxcc_info)
```

## Performance Considerations

### Memory Optimization
- Use generators for large log files
- Cache DXCC lookups in `tropa` equivalent
- Clear expired state periodically

### Network Efficiency
- Reuse UDP sockets
- Implement packet validation
- Handle network interruptions gracefully

## Security Notes

- Only bind to necessary network interfaces
- Validate all input data
- Use secure file permissions
- No sensitive data in logs

## Deployment

### Linux Service
```ini
# /etc/systemd/system/ultron.service
[Unit]
Description=ULTRON Amateur Radio Automation
After=network.target

[Service]
Type=simple
User=radio
WorkingDirectory=/home/radio/ultron-python
ExecStart=/usr/bin/python3 /home/radio/ultron-python/ultron.py
Restart=always

[Install]
WantedBy=multi-user.target
```

### Windows Task Scheduler
Use the batch file with Windows Task Scheduler for automatic startup.

---

**Remember**: This is amateur radio software - always follow proper operating procedures and regulations.