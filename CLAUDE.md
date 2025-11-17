# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

ULTRON is a multi-language automation tool for controlling amateur radio software (JTDX, WSJT-X, MSHV) via UDP protocol. Originally a PHP-based project, it now includes a modern Python rewrite with enhanced features. It operates as a bot that can call CQ, respond to calls, manage QSOs automatically, and includes sophisticated DXCC targeting capabilities.

## Commands

### Running ULTRON - Multiple Options

**PHP Version (Classic):**

**Linux:**
```bash
# Standard ULTRON
php -c extra/php-lnx.ini robot.php

# Enhanced ULTRON with DXCC whitelist
php -c extra/php-lnx.ini robot_dxcc.php

# DXCC analysis tool
php -c extra/php-lnx.ini dxcc_analyzer.php

# Interactive menu
./run_ultron.sh
```

**Windows:**
```cmd
# Using the batch script (recommended)
run_ultron.bat

# Or directly with PHP
c:\php\php.exe -c extra\php-win.ini robot.php
```

**Python Version (Modern - Recommended):**

**Cross-platform:**
```bash
# Interactive menu (recommended)
python run_ultron.py

# Standard mode
python ultron.py

# DXCC enhanced mode
python ultron_dxcc.py

# DXCC analysis
python dxcc_analyzer.py
```

**Testing Commands:**
```bash
# PHP testing
php connection_test.php
php test_udp.php
php simple_test.php

# Python testing
python test_protocol_parser.py
python test_udp.py
python test_ultron_live.py
```


## Architecture

### Core Components

**PHP Version:**
1. **Main Robot Files:**
   - `robot.php` - Standard ULTRON with basic functionality
   - `robot_dxcc.php` - Enhanced version with DXCC targeting/whitelist
   - `robot_fixed.php` - Fixed version (backup/legacy)

2. **Configuration Files:**
   - `dxcc_config.php` - DXCC whitelist configuration for targeting specific entities
   - `base.json` - Call sign database for DXCC lookups
   - `extra/php-lnx.ini` & `extra/php-win.ini` - Platform-specific PHP configurations

3. **Analysis Tools:**
   - `dxcc_analyzer.php` - Analyzes log files to identify worked/unworked DXCC entities
   - `wsjtx_log.adi` - ADIF log file (created automatically if missing)

**Python Version:**
1. **Main Robot Files:**
   - `ultron.py` - Standard Python ULTRON
   - `ultron_dxcc.py` - Enhanced version with DXCC targeting
   - `ultron_dxcc.py` - DXCC whitelist version

2. **Configuration Files:**
   - `dxcc_config.py` - DXCC whitelist configuration (Python)
   - `base.json` - Shared DXCC database
   - `requirements.txt` - Python dependencies

3. **Analysis Tools:**
   - `dxcc_analyzer.py` - Python DXCC analysis tool
   - `worked_dxcc_cache.json` - Cached DXCC analysis results

**Shared Data Files:**
   - `dxcc_latest.json` - Current DXCC entity data
   - `dxcc/` - Directory containing DXCC-related data
   - `robot_output.log` - Runtime log output
   - `ultron.pid` - Process ID file

### Key Functions

**PHP Version (robot.php):**
- `fg($text, $color)` - Terminal color formatting
- `procqso($data)` - Process incoming QSO data
- `genadi($qsos)` - Generate ADIF log entries
- `qsotovar($array)` - Convert QSO data to variables
- `sendcq()` - Send CQ calls
- `locate($licrx)` - Locate station/entity information
- `vicen($licencia)` - Process callsign validation

**Python Version (ultron.py):**
- `format_text(text, color)` - Terminal color formatting
- `process_qso(data)` - Process incoming QSO data
- `generate_adif(qsos)` - Generate ADIF log entries
- `qso_to_variables(array)` - Convert QSO data to variables
- `send_cq()` - Send CQ calls
- `locate_station(callsign)` - Locate station/entity information
- `validate_callsign(callsign)` - Process callsign validation
- `analyze_dxcc_log()` - Analyze DXCC entities in log files
- `load_dxcc_whitelist()` - Load DXCC targeting configuration

### Communication Protocol

ULTRON communicates via UDP with radio software on port 2237 (configurable). It:
- Listens for decode messages
- Sends reply messages
- Manages QSO state tracking
- Handles logbook entries via ADIF format
- Supports multiple digital modes (FT8, FT4, JT65, JT9, FST4, Q65, MSK144)

### State Management

**PHP Version:**
- `$sendcq` - CQ calling state
- `$rxrx` - Receive state
- `$exclu` - Exclusion list for stations
- `$tempo/$tempu` - Timing variables
- `$mega` - Mega-state flag

**Python Version:**
- `send_cq` - CQ calling state
- `receive_state` - Receive state
- `exclusion_list` - Exclusion list for stations
- `timing_vars` - Timing variables
- `mega_state` - Mega-state flag
- `dxcc_whitelist_enabled` - DXCC targeting state

### DXCC Features

**DXCC Whitelist System:**
- Global whitelist for all bands
- Band-specific whitelist configuration
- Priority mode (whitelist-only vs. prefer-whitelist)
- Real-time DXCC entity lookup
- Integration with external DXCC databases

**DXCC Analysis:**
- Log file parsing and entity extraction
- Worked/unworked entity identification
- Band-specific analysis
- Statistics generation
- Whitelist recommendations

## Development Notes

**General Requirements:**
- PHP: Requires sockets extension enabled
- Python: Requires Python 3.7+ with standard library
- Both: UTC timezone by default, cross-platform support
- Terminal must support ASCII color codes
- Network permissions for UDP communication

**PHP Specific:**
- Raspberry Pi LED control available via sudo commands
- Platform-specific PHP configurations in `extra/` folder
- Waitlist system for non-responsive stations (30 minutes)

**Python Specific:**
- Enhanced error handling and logging
- Modular architecture for easy extension
- Better memory management for large log files
- Async support for improved performance
- Virtual environment support

**Shared Features:**
- Logbook is separate from main radio software
- ADIF format compatibility
- Real-time operation without restarts
- Multi-platform UDP protocol support
- DXCC targeting and analysis capabilities