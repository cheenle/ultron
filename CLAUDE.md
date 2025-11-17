# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

ULTRON is a PHP-based automation tool for controlling amateur radio software (JTDX, WSJT-X, MSHV) via UDP protocol. It operates as a bot that can call CQ, respond to calls, and manage QSOs automatically.

## Commands

### Running ULTRON

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
```bash
# Using the batch script (recommended)
run_ultron.bat

# Or directly with PHP
c:\php\php.exe -c extra\php-win.ini robot.php
```

### Testing
```bash
# Simple connection test
php connection_test.php

# UDP test
php test_udp.php

# Simple functionality test
php simple_test.php
```

## Architecture

### Core Components

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

4. **Data Files:**
   - `dxcc_latest.json` - Current DXCC entity data
   - `dxcc/` - Directory containing DXCC-related data
   - `robot_output.log` - Runtime log output

### Key Functions (robot.php)

- `fg($text, $color)` - Terminal color formatting
- `procqso($data)` - Process incoming QSO data
- `genadi($qsos)` - Generate ADIF log entries
- `qsotovar($array)` - Convert QSO data to variables
- `sendcq()` - Send CQ calls
- `locate($licrx)` - Locate station/entity information
- `vicen($licencia)` - Process callsign validation

### Communication Protocol

ULTRON communicates via UDP with radio software on port 2237 (configurable). It:
- Listens for decode messages
- Sends reply messages
- Manages QSO state tracking
- Handles logbook entries via ADIF format

### State Management

The system maintains several key state variables:
- `$sendcq` - CQ calling state
- `$rxrx` - Receive state
- `$exclu` - Exclusion list for stations
- `$tempo/$tempu` - Timing variables
- `$mega` - Mega-state flag

## Development Notes

- Requires PHP with sockets extension enabled
- Uses UTC timezone by default
- Supports both Windows and Linux platforms
- Terminal must support ASCII color codes
- Raspberry Pi LED control available via sudo commands
- Logbook is separate from main radio software
- Waitlist system for non-responsive stations (30 minutes)