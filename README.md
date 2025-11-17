# ULTRON - Automatic Control of JTDX/WSJT-X/MSHV 📻

**Created by:** https://lu9dce.github.io/

**Copyright:** 2023 Eduardo Castillo  

**Contact:** castilloeduardo@outlook.com.ar  

**License:** https://creativecommons.org/licenses/by-nc-nd/4.0/

**I recommend AUTOSPOT:** https://github.com/lu9dce/autospot

**EASY FOR WIN DOWNLOAD** : 📥 [Ultron (WIN)](https://drive.google.com/drive/folders/1JYWeMY5giVzscMdtq1dMDu2BknGj-CeX?usp=sharing)

**DOWNLOAD** : 📥 [Ultron (Main Branch)](https://github.com/lu9dce/ultron/archive/refs/heads/main.zip)

## 🚀 What's New - DXCC & Python Support

**✨ Enhanced DXCC Features:**
- **DXCC Whitelist System**: Target specific DXCC entities for more efficient QSOs
- **DXCC Analyzer**: Analyze your log files to identify worked/unworked entities
- **Smart DXCC Targeting**: Automatically prioritize stations from desired countries
- **Band-specific DXCC**: Different whitelist for different bands
- **📊 NEW: Python DXCC Analyzer** - Enhanced analysis with caching and statistics

**🐍 Python Version Available:**
- **Complete Python rewrite** with enhanced features
- **Cross-platform compatibility** (Windows, Linux, macOS)
- **Modern architecture** with better performance
- **All original PHP features** plus new enhancements
- **🔧 NEW: Modular design** for easy extension and customization
- See [README_PYTHON.md](README_PYTHON.md) for Python version details

"Remember that this software requires knowledge in both operating systems and PHP.

I have tested this program on Slackware 15.0, and other colleagues have tested it on Fedora and Debian, as well as on Windows.

I do not guarantee its functionality if the user lacks knowledge."

## [DONATE](https://www.paypal.com/donate/?hosted_button_id=WHG8FQRMAPA3E)

Ayuda en español en la [Wiki!](https://github.com/lu9dce/ultron/wiki).

![ultron](https://pbs.twimg.com/media/F23jEfzWYAApY9t?format=webp&name=small)

## Description

ULTRON is a sophisticated software tool designed for **remotely or locally controlling programs like JTDX, MSHV, and WSJT-X**. It offers seamless operation on both **Windows and Linux platforms**, supporting both 32-bit and 64-bit versions. The software relies on the **latest version of PHP** for optimal performance.

## Advantages of Using ULTRON

ULTRON offers a multitude of advantages as a **BOT** for controlling programs like JTDX, MSHV, and WSJT-X:

1. **Effortless Remote Control**: ULTRON empowers users with the ability to control radio programs remotely, eliminating the need for physical presence. This is particularly beneficial for scenarios where real-time adjustments and monitoring are required without being tied to a specific location.

2. **Enhanced Efficiency**: By automating repetitive tasks such as CQ calling and message recognition, ULTRON boosts operational efficiency. It can tirelessly manage communication, freeing up operators to focus on more strategic aspects of their radio activities.

3. **Seamless Integration**: ULTRON integrates seamlessly with both Windows and Linux platforms, providing a consistent and user-friendly experience across different operating systems. Its support for various versions ensures compatibility with a wide range of setups.

4. **Real-Time Adaptability**: The real-time functionality of ULTRON enables dynamic changes in software preferences without the need for frequent restarts. Users can switch between programs like JTDX, MSHV, and WSJT-X effortlessly, adapting to changing communication needs on the fly.

5. **Automated Logbook Management**: ULTRON's dedicated logbook management ensures accurate tracking of QSOs. The ability to use a personalized logbook while keeping it separate from other software simplifies record-keeping and QSO verification.

6. **Intelligent Decision-Making**: ULTRON's ability to identify messages, respond to correspondents, and manage waitlists demonstrates its intelligence in making informed decisions during communication. It streamlines the QSO process, increasing the chances of successful interactions.

7. **Signal Strength Assessment**: ULTRON's consideration of signal strength enhances QSO success rates. By taking into account signals weaker than -20dB, it assists in prioritizing communications with better chances of success.

8. **Visual Feedback**: For Raspberry Pi users, the LED control and audible tone features provide visual and audio feedback, enhancing user awareness of ongoing operations and the status of the communication process.

In summary, employing ULTRON as a BOT for radio program control offers an array of benefits, ranging from operational efficiency and adaptability to intelligent decision-making and enhanced communication success rates. Its seamless integration, real-time capabilities, and intelligent automation make ULTRON a valuable asset in the world of amateur radio communication.

**Try ULTRON today and elevate your amateur radio experience!**

## Requirements

### PHP Version Requirements
- Latest version of **PHP** installed
- List of required **PHP modules** (specified at the end of the script)
- Properly configured radio software for optimal performance

### Python Version Requirements
- Python 3.7 or higher
- Network permissions for UDP communication
- Terminal with ANSI color support (recommended)

### General Recommendations for Optimal Usage:
- Disable the Tx watchdog
- Configure the UDP server to target the program's IP location
- Enable transmission of logged QSO ADIF data
- Do not filter UDP data
- Adjust firewall settings to facilitate data flow
- For DXCC features: ensure `base.json` and `dxcc_config` files are properly configured

## 📋 Details

- ULTRON operates in **real-time**, allowing seamless software switches without requiring restarts. It automatically detects your **call sign**, **IP address**, and communication ports.
- ULTRON uses its own **logbook**, but you can provide your own by placing it in the "**wsjtx_log.adi**" folder within ULTRON. This logbook remains separate from other software.
- In addition to calling CQ, ULTRON recognizes messages like **73** / **RRR** or **RR73** and determines if correspondents are busy or unresponsive.
- If a correspondent doesn't respond, they will be **waitlisted for 30 minutes** before a QSO retry.
- Signals weaker than **-20dB** are considered less likely to result in successful QSOs.
- The logged ADIF message is sent to ULTRON when the WSJT-X user accepts the "Log  QSO" dialog by clicking the "OK" button.

## Terminal and Color Support

ULTRON requires a terminal with **ASCII color support**. You can use the **Linux terminal** or the new **Windows 10/11 terminal**, both of which support ASCII color. For color support on Windows, consider using [**ConEmu**](https://conemu.github.io/) for an enhanced experience.

## Raspberry Pi

To control Raspberry Pi LEDs, use the `sudo` command configured without a password prompt. The **green LED** lights up for each decoding and turns off when inactive. The **red LED** exhibits a heartbeat-like effect during QSOs. Conducting a QSO emits an audible tone if a speaker is connected to the Pi's jack.

## 🚀 Quick Start Options

### PHP Version (Classic)
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

### Python Version (Recommended)
**Cross-platform:**
```bash
# Interactive menu
python run_ultron.py

# Standard mode
python ultron.py

# DXCC enhanced mode
python ultron_dxcc.py

# DXCC analysis
python dxcc_analyzer.py
```

## 🚀 Quick Start Guide

### 1. Choose Your Version
**🥇 Recommended: Python Version** (Modern, faster, better error handling)
**🥈 Alternative: PHP Version** (Classic, stable, original implementation)

### 2. First-Time Setup
```bash
# Clone or download ULTRON
git clone https://github.com/lu9dce/ultron.git
cd ultron

# Check your radio software (JTDX/WSJT-X/MSHV):
# - Enable UDP server on port 2237
# - Disable TX watchdog
# - Enable ADIF logging
```

### 3. Configure DXCC (Optional but Recommended)
```bash
# Analyze your current DXCC status
python dxcc_analyzer.py    # Python version
# OR
php dxcc_analyzer.php      # PHP version

# Edit whitelist based on recommendations
nano dxcc_config.py        # Python config
# OR
nano dxcc_config.php       # PHP config
```

### 4. Start ULTRON
```bash
# Interactive menu (recommended)
python run_ultron.py       # Python - cross-platform
./run_ultron.sh           # PHP - Linux/Mac
run_ultron.bat            # PHP - Windows

# Direct start
python ultron_dxcc.py     # Python DXCC version
# OR
php robot_dxcc.php        # PHP DXCC version
```

### 5. Monitor and Enjoy!
- Watch the colorful terminal output
- ULTRON automatically handles CQ calls and responses
- Check `wsjtx_log.adi` for your new contacts
- Monitor `robot_output.log` for detailed activity

## 🛠️ Troubleshooting

### Common Issues

**🔴 Port 2237 Already in Use**
```bash
# Find process using port
sudo lsof -i :2237
# Kill process if needed
sudo kill -9 [PID]
```

**🔴 No UDP Data Received**
- Check JTDX/WSJT-X UDP settings (port 2237)
- Verify firewall allows UDP traffic
- Ensure "Enable UDP" is checked in radio software

**🔴 DXCC Not Working**
- Verify `base.json` exists and is valid
- Check `dxcc_config` file syntax
- Run analyzer to test DXCC data loading

**🔴 Python Version Won't Start**
```bash
# Check Python version
python --version  # Need 3.7+
# Install if needed dependencies
pip install -r requirements.txt
```

**🔴 PHP Version Errors**
- Verify PHP with sockets extension: `php -m | grep sockets`
- Check platform-specific php.ini in `extra/` folder
- Ensure proper file permissions

### Performance Optimization

**For Better DXCC Performance:**
- Keep `base.json` updated with current DXCC data
- Use `worked_dxcc_cache.json` to avoid re-analysis
- Configure band-specific whitelists for targeted operation

**For Network Performance:**
- Use wired connection when possible
- Close unnecessary network applications
- Consider running on same machine as JTDX/WSJT-X

### Getting Help

**📖 Documentation:**
- [Detailed Python Guide](README_PYTHON.md)
- [DXCC Configuration Guide](dxcc_whitelist_guide.md)
- [Developer Documentation](CLAUDE.md)

**🌐 Community:**
- Original project: https://github.com/lu9dce/ultron
- Spanish help: [GitHub Wiki](https://github.com/lu9dce/ultron/wiki)

**📧 Contact:**
- Original author: castilloeduardo@outlook.com.ar
- Issues: Create GitHub issue for bugs/feature requests

### DXCC Analysis Tool
Analyze your log files to find unworked entities:
- **PHP**: `php dxcc_analyzer.php`
- **Python**: `python dxcc_analyzer.py` *(NEW - Enhanced with caching)*

**Enhanced Features:**
- ✅ **Smart Entity Recognition**: Automatic callsign-to-DXCC mapping
- ✅ **Band-specific Analysis**: Track entities per band
- ✅ **Whitelist Generation**: Auto-generate PHP/Python whitelist code
- ✅ **Statistics Export**: Save analysis to `worked_dxcc_cache.json`
- ✅ **Progress Tracking**: Monitor DXCC achievement over time
- ✅ **Multi-format Output**: Compatible with both PHP and Python configs

**Usage Examples:**
```bash
# Basic analysis
python dxcc_analyzer.py

# Generate recommendations
php dxcc_analyzer.php

# View statistics
cat worked_dxcc_cache.json
```

### DXCC Whitelist Configuration
Edit `dxcc_config.php` (PHP) or `dxcc_config.py` (Python):

```php
// PHP Version
$dxcc_whitelist = [
    "1" => "USA",
    "110" => "SPAIN", 
    "284" => "BULGARIA"
];
```

```python
# Python Version
dxcc_whitelist = {
    "1": "USA",
    "110": "SPAIN",
    "284": "BULGARIA"
}
```

## ⚠️ Disclaimer

"I am not liable for the use or inability to use this software or any other."

## Thinking

Ultron was developed by me for me and some friends. It requires the user to have prior knowledge in using PHP and knowing how to use a Windows or Linux terminal. That's no longer up to me, but I guarantee you Ultron works very well. It can operate for years without the need for intervention.

**Why PHP!** Because it runs on any operating system and no compilation is needed. The script is just text with commands. Ultron will execute on any device that can run PHP (PC/CELLPHONE/ROUTER... ETC).

## 🖥️ Supported Platforms

### PHP Version
- Personal Computers (PC) with Windows, macOS, or Linux
- Web servers (Linux, Windows Server, etc.)
- Network devices (routers, switches, etc.) with PHP support
- Internet of Things (IoT) devices with processing capabilities
- Development boards like Raspberry Pi (Linux)
- Smart TVs and multimedia devices with PHP
- Industrial control systems and embedded devices

### Python Version
- All platforms supporting Python 3.7+
- Windows, macOS, Linux natively
- Raspberry Pi and other SBCs
- Docker containers
- Virtual environments

### Cross-Platform Compatibility
Both PHP and Python versions support:
- **UDP Protocol**: Compatible with JTDX, WSJT-X, MSHV
- **ADIF Format**: Standard logbook format
- **Real-time Operation**: Seamless mode switching
- **Color Terminal**: Enhanced user experience

## 📊 Feature Comparison Matrix

| Feature | PHP Version | Python Version | Notes |
|---------|-------------|----------------|-------|
| **Core QSO Automation** | ✅ | ✅ | Both versions fully functional |
| **DXCC Whitelist** | ✅ | ✅ | Target specific countries/entities |
| **DXCC Analyzer** | ✅ | ✅⭐ | Python version enhanced with caching |
| **Band-specific DXCC** | ✅ | ✅ | Different targets per band |
| **ADIF Logging** | ✅ | ✅ | Independent log management |
| **Real-time Operation** | ✅ | ✅ | Seamless mode switching |
| **Cross-platform** | ✅ | ✅⭐ | Better Python compatibility |
| **Performance** | Good | Excellent⭐ | Python optimized for speed |
| **Error Handling** | Basic | Advanced⭐ | Python better error recovery |
| **Memory Usage** | Moderate | Optimized⭐ | Python more efficient |
| **Modern Architecture** | Classic | Modern⭐ | Python modular design |
| **Active Development** | Maintenance | Active⭐ | Python version evolving |
| **New Features** | Limited | Regular⭐ | Python gets updates |

⭐ = **Advantage**

### 🎯 Recommendation
**New users:** Start with Python version for better performance and ongoing development
**Existing users:** Both versions work great - choose based on your preference

## 📚 Additional Resources

- **[Python Version Documentation](README_PYTHON.md)** - Complete Python version guide
- **[DXCC Whitelist Guide](dxcc_whitelist_guide.md)** - Detailed DXCC configuration
- **[CLAUDE.md](CLAUDE.md)** - Development and architecture documentation
- **Original Wiki** - Spanish help: [GitHub Wiki](https://github.com/lu9dce/ultron/wiki)

## 🔄 Version Comparison

| Feature | PHP Version | Python Version |
|---------|-------------|----------------|
| **Basic QSO Automation** | ✅ | ✅ |
| **DXCC Whitelist** | ✅ | ✅ |
| **DXCC Analyzer** | ✅ | ✅ |
| **Cross-platform** | ✅ | ✅ |
| **Performance** | Good | Excellent |
| **Memory Usage** | Moderate | Optimized |
| **Modern Architecture** | Classic | Enhanced |
| **Active Development** | Maintenance | Active |
| **New Features** | Limited | Regular Updates |

**Recommendation**: New users should start with Python version for better performance and ongoing development support.
