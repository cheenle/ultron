#!/usr/bin/env python3
"""
ULTRON启动脚本 - 跨平台版本
支持Windows、Linux和macOS
"""

import os
import sys
import platform
import subprocess
import argparse
from pathlib import Path

def clear_screen():
    """清屏"""
    os.system('cls' if platform.system() == 'Windows' else 'clear')

def print_banner():
    """打印启动横幅"""
    banner = """
╔══════════════════════════════════════════════════════════════════════════════╗
║                                                                              ║
║                                  ULTRON                                      ║
║                    Automatic Control of JTDX/WSJT-X/MSHV                    ║
║                                                                              ║
║                    Python Version - Cross Platform                           ║
║                                                                              ║
╚══════════════════════════════════════════════════════════════════════════════╝
    """
    print(banner)

def check_python_version():
    """检查Python版本"""
    if sys.version_info < (3, 7):
        print("错误: 需要Python 3.7或更高版本")
        sys.exit(1)

def check_dependencies():
    """检查依赖项"""
    try:
        import socket
        import json
        import time
        import datetime
        import re
        import threading
        from pathlib import Path
        from dataclasses import dataclass
        return True
    except ImportError as e:
        print(f"缺少依赖项: {e}")
        return False

def get_python_command():
    """获取Python命令"""
    # 尝试不同的Python命令
    for cmd in ['python3', 'python', 'py']:
        try:
            result = subprocess.run([cmd, '--version'], capture_output=True, text=True)
            if result.returncode == 0:
                return cmd
        except:
            continue
    return 'python3'  # 默认值

def run_standard_mode():
    """运行标准ULTRON"""
    python_cmd = get_python_command()
    print("启动标准ULTRON...")
    subprocess.run([python_cmd, "ultron.py"])

def run_dxcc_mode():
    """运行增强版ULTRON (DXCC白名单)"""
    python_cmd = get_python_command()
    print("启动增强版ULTRON (DXCC白名单功能)...")
    subprocess.run([python_cmd, "ultron_dxcc.py"])

def run_analyze_mode():
    """运行DXCC分析"""
    python_cmd = get_python_command()
    print("分析DXCC通联情况...")
    subprocess.run([python_cmd, "ultron_dxcc.py", "analyze"])

def main():
    """主函数"""
    parser = argparse.ArgumentParser(description='ULTRON - Amateur Radio Automation Tool')
    parser.add_argument('mode', nargs='?', choices=['standard', 'dxcc', 'analyze'], 
                       default='standard', help='运行模式')
    parser.add_argument('--version', action='version', version='ULTRON Python Version 1.0')
    
    args = parser.parse_args()
    
    clear_screen()
    print_banner()
    
    # 检查Python版本
    check_python_version()
    
    # 检查依赖
    if not check_dependencies():
        print("请确保所有Python标准库可用")
        sys.exit(1)
    
    # 检查文件是否存在
    if not Path("ultron.py").exists():
        print("错误: 找不到ultron.py文件")
        sys.exit(1)
    
    if not Path("base.json").exists():
        print("警告: 找不到base.json文件，DXCC功能可能受限")
    
    # 根据模式运行
    try:
        if args.mode == 'standard':
            run_standard_mode()
        elif args.mode == 'dxcc':
            run_dxcc_mode()
        elif args.mode == 'analyze':
            run_analyze_mode()
    except KeyboardInterrupt:
        print("\n用户中断，退出程序")
    except Exception as e:
        print(f"运行错误: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()