#!/usr/bin/env python3
"""
简单UDP数据包监听器
用于调试ULTRON数据包接收
"""

import socket
import struct
import time
import threading

def listen_on_port(port, name):
    """在指定端口监听数据包"""
    print(f"🔍 开始在端口 {port} 监听{name}...")
    
    try:
        sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        sock.bind(('0.0.0.0', port))
        sock.settimeout(1.0)
        
        print(f"✅ 端口 {port} 监听成功")
        
        packet_count = 0
        while True:
            try:
                data, addr = sock.recvfrom(1024)
                packet_count += 1
                
                print(f"\n🎯 端口{port}收到数据包 #{packet_count}:")
                print(f"   来源: {addr}")
                print(f"   大小: {len(data)} 字节")
                print(f"   原始数据: {data[:50]}..." if len(data) > 50 else f"   原始数据: {data}")
                
                # 尝试解析WSJT-X协议
                if len(data) >= 16:
                    try:
                        magic = struct.unpack('<I', data[0:4])[0]
                        version = struct.unpack('<I', data[4:8])[0]
                        packet_type = struct.unpack('<I', data[8:12])[0]
                        
                        print(f"   WSJT-X解析:")
                        print(f"     Magic: 0x{magic:08x}")
                        print(f"     Version: {version}")
                        print(f"     Type: {packet_type} ({'Status' if packet_type == 1 else 'Decode' if packet_type == 2 else 'Unknown'})")
                        
                        if packet_type == 2:  # Decode packet
                            print(f"     🔔 这是解码数据包!")
                            
                    except Exception as e:
                        print(f"   解析错误: {e}")
                
            except socket.timeout:
                continue
            except Exception as e:
                print(f"❌ 监听错误: {e}")
                
    except Exception as e:
        print(f"❌ 无法监听端口 {port}: {e}")

def send_test_packets():
    """发送测试数据包"""
    time.sleep(2)  # 等待监听器启动
    
    print("\n📡 开始发送测试数据包...")
    
    try:
        sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        
        # 测试1: 发送到2237 (ULTRON)
        print("\n1. 发送到ULTRON (端口2237)...")
        test_msg = b"TEST_PACKET_TO_ULTRON"
        sock.sendto(test_msg, ('127.0.0.1', 2237))
        print(f"   ✅ 发送了 {len(test_msg)} 字节")
        
        # 测试2: 创建WSJT-X解码包
        print("\n2. 发送WSJT-X解码数据包...")
        packet = bytearray()
        packet.extend(struct.pack('<I', 0xadbccb00))  # magic
        packet.extend(struct.pack('<I', 1))           # version
        packet.extend(struct.pack('<I', 2))           # decode packet
        packet.extend(struct.pack('<I', 4))           # id length
        packet.extend(b"WSJT")
        
        # 基本解码数据
        packet.extend(struct.pack('<I', int(time.time())&0xFFFFFFFF))
        packet.extend(struct.pack('<i', -12))         # SNR
        packet.extend(struct.pack('<f', 0.8))         # delta_time
        packet.extend(struct.pack('<I', 1500))        # delta_frequency
        
        # 模式
        packet.extend(struct.pack('<I', 3))
        packet.extend(b"FT8")
        
        # 消息
        message = "CQ TEST123 FN42"
        packet.extend(struct.pack('<I', len(message)))
        packet.extend(message.encode('utf-8'))
        
        # 结束
        packet.extend(struct.pack('<I', 0))
        packet.extend(struct.pack('<I', 0))
        
        sock.sendto(bytes(packet), ('127.0.0.1', 2237))
        print(f"   ✅ 发送了解码数据包 ({len(packet)} 字节)")
        print(f"   消息: {message}")
        
        sock.close()
        print("\n✅ 测试数据包发送完成")
        
    except Exception as e:
        print(f"❌ 发送测试数据包失败: {e}")

def main():
    """主函数"""
    print("╔══════════════════════════════════════════════════════════════════════════════╗")
    print("║                        ULTRON数据包监听工具                                  ║")
    print("╚══════════════════════════════════════════════════════════════════════════════╝")
    
    # 启动监听器
    listener_thread = threading.Thread(target=listen_on_port, args=(2237, " (ULTRON)"))
    listener_thread.daemon = True
    listener_thread.start()
    
    time.sleep(1)
    
    # 发送测试数据包
    send_test_packets()
    
    print("\n⏳ 监听30秒，按Ctrl+C停止...")
    try:
        time.sleep(30)
    except KeyboardInterrupt:
        print("\n👋 监听停止")
    
    print("\n✅ 测试完成")

if __name__ == "__main__":
    main()