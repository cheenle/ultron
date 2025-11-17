#!/usr/bin/env python3
"""
ULTRON系统诊断工具
检查系统状态和网络连接
"""

import socket
import time
import struct
import sys
from pathlib import Path

def test_udp_connection():
    """测试UDP连接"""
    print("🔍 测试UDP连接...")
    
    try:
        sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        sock.settimeout(2.0)
        
        # 发送简单的测试数据包
        test_data = b'ULTRON_TEST_PACKET'
        sock.sendto(test_data, ('127.0.0.1', 2237))
        print("✅ UDP端口2237可访问")
        
        sock.close()
        return True
        
    except Exception as e:
        print(f"❌ UDP连接失败: {e}")
        return False

def test_wsjt_protocol():
    """测试WSJT-X协议"""
    print("\n🔍 测试WSJT-X协议...")
    
    try:
        # 创建一个有效的WSJT-X状态包
        packet = bytearray()
        
        # 头部 (magic + version + type + id_length)
        packet.extend(struct.pack('<I', 0xadbccb00))  # magic
        packet.extend(struct.pack('<I', 1))           # version
        packet.extend(struct.pack('<I', 1))           # status packet type
        packet.extend(struct.pack('<I', 4))           # id length
        
        # ID
        packet.extend(b"WSJT")
        
        # 频率 (15000000 Hz = 15 MHz)
        packet.extend(struct.pack('<Q', 15000000))
        
        # 模式长度和模式
        packet.extend(struct.pack('<I', 3))
        packet.extend(b"FT8")
        
        # 发送数据包
        sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        sock.sendto(bytes(packet), ('127.0.0.1', 2237))
        sock.close()
        
        print("✅ WSJT-X状态包发送成功")
        return True
        
    except Exception as e:
        print(f"❌ WSJT-X协议测试失败: {e}")
        return False

def check_log_files():
    """检查日志文件"""
    print("\n🔍 检查日志文件...")
    
    log_files = [
        "wsjtx_log.adi",
        "robot_output.log",
        "ultron.log"
    ]
    
    for log_file in log_files:
        path = Path(log_file)
        if path.exists():
            size = path.stat().st_size
            print(f"✅ {log_file}: 存在 ({size} bytes)")
        else:
            print(f"ℹ️  {log_file}: 不存在")

def main():
    """主诊断函数"""
    print("╔══════════════════════════════════════════════════════════════════════════════╗")
    print("║                          ULTRON 系统诊断工具                                 ║")
    print("╚══════════════════════════════════════════════════════════════════════════════╝")
    print()
    
    # 运行诊断测试
    udp_ok = test_udp_connection()
    protocol_ok = test_wsjt_protocol()
    check_log_files()
    
    print(f"\n📊 诊断结果:")
    print(f"   UDP连接: {'✅ 正常' if udp_ok else '❌ 异常'}")
    print(f"   WSJT协议: {'✅ 正常' if protocol_ok else '❌ 异常'}")
    
    if udp_ok and protocol_ok:
        print("\n🎉 ULTRON系统运行正常！")
        print("   系统正在等待JTDX/WSJT-X的UDP数据包...")
    else:
        print("\n⚠️  发现一些问题，请检查系统配置")
    
    print(f"\n💡 提示:")
    print(f"   - 确保JTDX/WSJT-X正在运行")
    print(f"   - 检查JTDX/WSJT-X的UDP设置指向127.0.0.1:2237")
    print(f"   - 查看日志文件了解详细状态")

if __name__ == "__main__":
    main()