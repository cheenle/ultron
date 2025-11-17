#!/usr/bin/env python3
"""
ULTRON Python版本安装测试脚本
"""

import sys
import platform
import importlib
from pathlib import Path

def test_python_version():
    """测试Python版本"""
    print("=== Python版本测试 ===")
    version = sys.version_info
    print(f"Python版本: {version.major}.{version.minor}.{version.micro}")
    print(f"平台: {platform.system()} {platform.release()}")
    
    if version.major >= 3 and version.minor >= 7:
        print("✅ Python版本符合要求")
        return True
    else:
        print("❌ Python版本过低，需要3.7或更高版本")
        return False

def test_imports():
    """测试必要的模块导入"""
    print("\n=== 模块导入测试 ===")
    required_modules = [
        'socket', 'json', 'time', 'datetime', 're', 'os', 'sys',
        'threading', 'pathlib', 'dataclasses', 'typing', 'argparse',
        'importlib'
    ]
    
    all_passed = True
    for module in required_modules:
        try:
            importlib.import_module(module)
            print(f"✅ {module}")
        except ImportError as e:
            print(f"❌ {module}: {e}")
            all_passed = False
    
    return all_passed

def test_files():
    """测试必要文件"""
    print("\n=== 文件检查 ===")
    required_files = [
        'ultron.py',
        'ultron_dxcc.py',
        'dxcc_config.py',
        'run_ultron.py',
        'run_ultron.sh',
        'run_ultron.bat'
    ]
    
    all_passed = True
    for file in required_files:
        path = Path(file)
        if path.exists():
            size = path.stat().st_size
            print(f"✅ {file} ({size} bytes)")
        else:
            print(f"❌ {file}: 文件不存在")
            all_passed = False
    
    return all_passed

def test_basic_functionality():
    """测试基本功能"""
    print("\n=== 基本功能测试 ===")
    
    try:
        # 测试颜色输出
        from ultron import Colors
        print(f"{Colors.GREEN}✅ 颜色系统正常{Colors.RESET}")
        
        # 测试ADIF处理器
        from ultron import ADIFProcessor
        processor = ADIFProcessor()
        
        test_data = '<call:4>K1ABC <gridsquare:4>FN42 <eor>'
        result = processor.parse_adif(test_data)
        if result and len(result) > 0 and result[0].get('call') == 'K1ABC':
            print("✅ ADIF解析器正常")
        else:
            print("❌ ADIF解析器异常")
            return False
        
        # 测试呼号验证
        from ultron import CallsignValidator
        validator = CallsignValidator()
        
        if validator.validate('K1ABC') and not validator.validate('INVALID'):
            print("✅ 呼号验证器正常")
        else:
            print("❌ 呼号验证器异常")
            return False
        
        return True
        
    except Exception as e:
        print(f"❌ 功能测试失败: {e}")
        return False

def test_network():
    """测试网络功能"""
    print("\n=== 网络功能测试 ===")
    
    try:
        import socket
        
        # 测试UDP socket创建
        sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        sock.settimeout(0.1)  # 100ms超时
        
        # 测试绑定到本地地址
        sock.bind(('127.0.0.1', 0))  # 让系统自动分配端口
        local_addr = sock.getsockname()
        print(f"✅ UDP socket创建成功 (绑定到 {local_addr[0]}:{local_addr[1]})")
        
        sock.close()
        return True
        
    except Exception as e:
        print(f"❌ 网络测试失败: {e}")
        return False

def main():
    """主测试函数"""
    print("ULTRON Python版本 - 安装测试")
    print("=" * 50)
    
    tests = [
        ("Python版本", test_python_version),
        ("模块导入", test_imports),
        ("文件检查", test_files),
        ("基本功能", test_basic_functionality),
        ("网络功能", test_network)
    ]
    
    passed = 0
    total = len(tests)
    
    for test_name, test_func in tests:
        if test_func():
            passed += 1
    
    print(f"\n=== 测试结果 ===")
    print(f"通过: {passed}/{total}")
    
    if passed == total:
        print("🎉 所有测试通过！ULTRON已准备就绪")
        print("\n下一步:")
        print("1. 配置JTDX/WSJT-X的UDP转发")
        print("2. 运行: python run_ultron.py")
        return 0
    else:
        print("⚠️  部分测试失败，请检查上述错误")
        return 1

if __name__ == "__main__":
    sys.exit(main())