#!/usr/bin/env python3
"""
实时测试ULTRON系统
发送模拟的WSJT-X数据包来验证修复
"""

import socket
import time
import struct
import threading
import sys
from pathlib import Path

# 添加路径
sys.path.insert(0, str(Path(__file__).parent))

def create_test_decode_packet():
    """创建测试用的WSJT-X解码数据包"""
    # WSJT-X协议头部
    magic = 0xadbccb00
    version = 1
    packet_type = 2  # Decode packet
    
    # 构建数据包
    packet = bytearray()
    
    # 头部 (32字节)
    packet.extend(struct.pack('<I', magic))
    packet.extend(struct.pack('<I', version))
    packet.extend(struct.pack('<I', packet_type))
    packet.extend(struct.pack('<I', 0))  # ID长度占位
    
    # ID (可变长度，这里用4字节)
    software_id = b"WSJT"
    packet.extend(struct.pack('<I', len(software_id)))
    packet.extend(software_id)
    
    # 新解码标志
    packet.extend(struct.pack('<H', 1))  # new_decode = True
    
    # 时间戳 (毫秒)
    packet.extend(struct.pack('<Q', int(time.time() * 1000) & 0xFFFFFFFF))
    
    # SNR
    packet.extend(struct.pack('<i', -15))  # -15dB
    
    # Delta time (DT) - 8字节浮点数
    packet.extend(struct.pack('<d', 0.5))
    
    # Delta frequency
    packet.extend(struct.pack('<i', 1000))  # 1000Hz
    
    # 模式
    mode = b"FT8"
    packet.extend(struct.pack('<I', len(mode)))
    packet.extend(mode)
    
    # 消息
    message = b"CQ K1ABC FN42"
    packet.extend(struct.pack('<I', len(message)))
    packet.extend(message)
    
    # 低置信度标志
    packet.extend(struct.pack('<H', 0))
    
    # 关闭标志
    packet.extend(struct.pack('<H', 0))
    
    return bytes(packet)

def create_problematic_packet():
    """创建有编码问题的数据包"""
    packet = bytearray()
    
    # 头部
    packet.extend(struct.pack('<I', 0xadbccb00))
    packet.extend(struct.pack('<I', 1))
    packet.extend(struct.pack('<I', 2))
    packet.extend(struct.pack('<I', 0))
    
    # ID
    packet.extend(struct.pack('<I', 4))
    packet.extend(b"WSJT")
    
    # 新解码标志
    packet.extend(struct.pack('<H', 1))
    
    # 时间戳
    packet.extend(struct.pack('<Q', int(time.time() * 1000) & 0xFFFFFFFF))
    
    # SNR
    packet.extend(struct.pack('<i', -20))
    
    # Delta time
    packet.extend(struct.pack('<d', 0.3))
    
    # Delta frequency
    packet.extend(struct.pack('<i', 1500))
    
    # 模式 - 使用可能导致编码问题的字节
    packet.extend(struct.pack('<I', 2))
    packet.extend(b'\xf0\xf1')  # 无效的UTF-8序列
    
    # 消息
    packet.extend(struct.pack('<I', 10))
    packet.extend(b"CQ PY2ABC")
    
    # 标志
    packet.extend(struct.pack('<H', 0))
    packet.extend(struct.pack('<H', 0))
    
    return bytes(packet)

def send_test_packets():
    """发送测试数据包"""
    print("发送测试数据包到ULTRON...")
    
    try:
        # 创建UDP socket
        sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        
        # 发送正常的测试数据包
        print("\n1. 发送正常数据包")
        normal_packet = create_test_decode_packet()
        sock.sendto(normal_packet, ('127.0.0.1', 2237))
        print(f"   发送了 {len(normal_packet)} 字节")
        
        time.sleep(2)
        
        # 发送有编码问题的数据包
        print("\n2. 发送有编码问题的数据包")
        problematic_packet = create_problematic_packet()
        sock.sendto(problematic_packet, ('127.0.0.1', 2237))
        print(f"   发送了 {len(problematic_packet)} 字节")
        
        print("\n✅ 测试数据包发送完成")
        
    except Exception as e:
        print(f"❌ 发送错误: {e}")
    finally:
        sock.close()

def monitor_output():
    """监控ULTRON输出"""
    print("\n3. 监控ULTRON输出...")
    print("   请在另一个终端运行: python ultron_dxcc.py")
    print("   或者检查日志文件: wsjtx_log.adi")
    print("   等待10秒...")
    
    time.sleep(10)
    
    # 检查日志文件
    log_file = Path("wsjtx_log.adi")
    if log_file.exists():
        content = log_file.read_text()
        if "K1ABC" in content or "PY2ABC" in content:
            print("✅ 发现测试呼号在日志中!")
        else:
            print("ℹ️  日志中没有发现测试呼号")
    else:
        print("ℹ️  日志文件不存在")

if __name__ == "__main__":
    print("ULTRON实时测试")
    print("=" * 50)
    print("这个脚本会向ULTRON发送模拟的WSJT-X数据包")
    print("请确保ULTRON正在运行: python ultron_dxcc.py")
    print("=" * 50)
    
    # 自动继续测试（非交互模式）
    print("自动继续测试...")
    
    # 启动发送线程
    sender_thread = threading.Thread(target=send_test_packets)
    sender_thread.start()
    
    # 监控输出
    monitor_output()
    
    # 等待发送线程完成
    sender_thread.join()
    
    print("\n" + "=" * 50)
    print("测试完成!")
    print("\n如果ULTRON成功处理了数据包，你应该看到:")
    print("- 解码信息显示（时间、SNR、模式、消息）")
    print("- DXCC信息显示（如果是白名单中的实体）")
    print("- 如果启用了自动响应，可能会看到响应消息")