# 🚀 ULTRON Enhanced - Amateur Radio Automation Tool 📻

**Modern Python + Classic PHP Implementation**

*Building upon the foundation by LU9DCE (Eduardo Castillo)*

[![License: CC BY-NC-ND 4.0](https://img.shields.io/badge/License-CC%20BY--NC--ND%204.0-lightgrey.svg)](https://creativecommons.org/licenses/by-nc-nd/4.0/)
[![Python](https://img.shields.io/badge/Python-3.7+-blue.svg)](https://python.org)
[![PHP](https://img.shields.io/badge/PHP-7.0+-purple.svg)](https://php.net)

## 📖 Table of Contents
- [Overview](#-overview)
- [Key Features](#-key-features)
- [Quick Start](#-quick-start)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Usage](#-usage)
- [DXCC Features](#-dxcc-features)
- [Documentation](#-documentation)
- [Support](#-support)
- [Contributing](#-contributing)

## 🎯 Overview

ULTRON Enhanced is a powerful automation tool for amateur radio operators, designed to control JTDX, WSJT-X, and MSHV software via UDP protocol. This enhanced version maintains full compatibility with the original PHP implementation while adding a modern Python rewrite with advanced DXCC targeting capabilities.

Whether you're a DX hunter looking for specific entities or a contester seeking efficient QSO management, ULTRON provides intelligent automation for FT8, FT4, JT65, and other digital modes.

## ✨ Key Features

### 🤖 **Core Automation**
- **Auto CQ Calling**: Continuously calls CQ when idle
- **Smart Response**: Automatically responds to incoming calls
- **Message Recognition**: Understands standard QSO messages (73, RRR, RR73)
- **Waitlist Management**: 30-minute exclusion for non-responsive stations
- **Signal Assessment**: Prioritizes signals stronger than -20dB

### 🌍 **Advanced DXCC Targeting**
- **Entity Whitelist**: Target specific DXCC countries/entities
- **Band-Specific Targeting**: Different targets per band
- **Smart Analysis**: Analyze logs to find unworked entities
- **Auto-Generated Recommendations**: Get whitelist suggestions
- **Progress Tracking**: Monitor DXCC achievement over time

### 🖥️ **Dual Implementation**
- **🐍 Python Version**: Modern, fast, cross-platform
- **🐘 PHP Version**: Classic, stable, original compatibility
- **Seamless Switching**: Change versions without restart
- **Unified Configuration**: Consistent settings across versions

### 📊 **Enhanced Analytics**
- **Real-time Statistics**: Track contacts and entities
- **ADIF Log Management**: Independent logbook system
- **Performance Metrics**: Monitor success rates
- **Export Capabilities**: JSON, ADIF formats

## 🚀 Quick Start

### 1. Choose Your Version
```bash
# 🥇 Recommended: Python (Modern, Faster)
python run_ultron.py

# 🥈 Alternative: PHP (Classic, Stable)
php -c extra/php-lnx.ini robot.php  # Linux
run_ultron.bat                       # Windows
```

### 2. Basic Setup
```bash
# Clone the repository
git clone https://github.com/cheenle/ultron.git
cd ultron

# Configure your radio software:
# - Enable UDP server on port 2237
# - Disable TX watchdog
# - Enable ADIF logging
```

### 3. Analyze Your DXCC Status (Optional)
```bash
# Python version (recommended)
python dxcc_analyzer.py

# PHP version
php -c extra/php-lnx.ini dxcc_analyzer.php
```

### 4. Configure DXCC Targeting (Optional)
```bash
# Edit configuration file
nano dxcc_config.py      # Python
nano dxcc_config.php     # PHP
```

### 5. Start Automating!
```bash
# Interactive menu (recommended)
python run_ultron.py
```

## 📦 Installation

### Python Version (Recommended)
```bash
# Check Python version (3.7+ required)
python --version

# No additional dependencies needed for basic functionality
# Optional: Install dependencies if using advanced features
pip install -r requirements.txt
```

### PHP Version
```bash
# Check PHP with sockets extension
php -m | grep sockets

# Use platform-specific configuration
php -c extra/php-lnx.ini robot.php    # Linux
php -c extra/php-win.ini robot.php    # Windows
```

## ⚙️ Configuration

### Basic Configuration
Both versions work out-of-the-box with default settings. Key files:
- `base.json` - DXCC entity database
- `wsjtx_log.adi` - Your QSO log (auto-created)
- `robot_output.log` - Activity log

### DXCC Whitelist Setup
Create targeted campaigns for specific entities:

**Python Configuration (`dxcc_config.py`):**
```python
dxcc_whitelist = {
    "1": "USA",          # Target USA
    "110": "SPAIN",      # Target Spain  
    "246": "MALTA",      # Target rare Malta
}
```

**PHP Configuration (`dxcc_config.php`):**
```php
$dxcc_whitelist = array(
    "1" => "USA",
    "110" => "SPAIN",
    "246" => "MALTA"
);
```

### Band-Specific Targeting
Target different entities on different bands:
```python
band_whitelist = {
    "20m": {"1": "USA", "110": "SPAIN"},
    "40m": {"1": "USA", "284": "BULGARIA"}
}
```

## 🎯 DXCC Features

### Smart Entity Recognition
- **Automatic Callsign Analysis**: Converts callsigns to DXCC entities
- **Prefix Matching**: Handles special callsign formats
- **Real-time Lookup**: Instant entity identification during QSOs

### Advanced Analytics
```bash
# Generate comprehensive analysis
python dxcc_analyzer.py

# View detailed statistics
cat worked_dxcc_cache.json
```

**Analysis Includes:**
- ✅ Worked/unworked entities breakdown
- ✅ Band-specific entity tracking  
- ✅ Whitelist recommendations
- ✅ Progress monitoring
- ✅ Export to multiple formats

### Performance Metrics
- **Entity Hit Rate**: Track successful DXCC contacts
- **Band Coverage**: Monitor entity coverage per band
- **Time Analysis**: See when entities are most active
- **Geographic Distribution**: Visual contact distribution

## 📖 Documentation

### 📚 Complete Guides
- **[Python Version Guide](README_PYTHON.md)** - Detailed Python implementation
- **[DXCC Configuration](dxcc_whitelist_guide.md)** - Whitelist setup guide
- **[Developer Documentation](CLAUDE.md)** - Architecture and development

### 🎓 Learning Resources
- **Original Documentation**: [LU9DCE Wiki](https://github.com/lu9dce/ultron/wiki) (Spanish)
- **Feature Comparison**: See matrix below
- **Troubleshooting**: Common issues and solutions

## 📊 Version Comparison

| Feature | Python Version | PHP Version |
|---------|----------------|-------------|
| **Performance** | ⚡ Excellent | 🚀 Good |
| **Memory Usage** | 🧠 Optimized | 📊 Moderate |
| **Error Handling** | 🛡️ Advanced | ⚠️ Basic |
| **Cross-Platform** | 🌍 Universal | 🖥️ Wide |
| **Development** | 🔄 Active | 🏛️ Stable |
| **Architecture** | 🏗️ Modern | 📜 Classic |

**💡 Recommendation**: New users start with Python version for better performance and features.

## 🛠️ Troubleshooting

### Common Issues

**Port 2237 Already in Use**
```bash
sudo lsof -i :2237
sudo kill -9 [PID]
```

**No UDP Data**
- Check JTDX/WSJT-X UDP settings (port 2237)
- Verify firewall allows UDP traffic
- Ensure "Enable UDP" is checked

**DXCC Not Working**
- Verify `base.json` exists and is valid
- Check `dxcc_config` file syntax
- Run analyzer to test DXCC data loading

### Performance Optimization
- Keep `base.json` updated with current DXCC data
- Use `worked_dxcc_cache.json` to avoid re-analysis
- Configure band-specific whitelists for targeted operation

## 🤝 Contributing

We welcome contributions to improve ULTRON Enhanced:

1. **Bug Reports**: Submit issues with detailed information
2. **Feature Requests**: Suggest new capabilities
3. **Code Contributions**: Submit pull requests
4. **Documentation**: Help improve guides and translations

### 🙏 Original Attribution

**Original ULTRON by LU9DCE (Eduardo Castillo)**
- Original Repository: https://github.com/lu9dce/ultron
- Original Documentation: [GitHub Wiki](https://github.com/lu9dce/ultron/wiki)
- Original Contact: castilloeduardo@outlook.com.ar

This enhanced version maintains full compatibility with the original while extending capabilities for modern amateur radio operations.

## 📄 License

Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International

## 🙏 Acknowledgments

- **LU9DCE (Eduardo Castillo)** - Original ULTRON creator
- **WSJT-X Development Team** - Excellent digital mode software
- **Amateur Radio Community** - Continuous feedback and support
- **Open Source Community** - Making enhancements possible

---

**Enjoy automated QSOs and happy DX hunting! 73!**