#!/usr/bin/env python3
"""
ULTRON 深度调试工具
分析系统不工作的原因
"""

import socket
import time
import struct
import threading
import sys
import os
from datetime import datetime

class ULTRONDebugger:
    def __init__(self):
        self.listen_port = 2237
        self.forward_port = 2277
        self.running = True
        self.packets_received = []
        self.decode_packets = []
        
    def create_real_decode_packet(self):
        """创建真实的WSJT-X解码数据包"""
        packet = bytearray()
        
        # 头部
        packet.extend(struct.pack('<I', 0xadbccb00))  # magic
        packet.extend(struct.pack('<I', 1))           # version
        packet.extend(struct.pack('<I', 2))           # decode packet type
        packet.extend(struct.pack('<I', 4))           # id length
        packet.extend(b"WSJT")
        
        # 解码数据 - 使用正确的整数范围
        packet.extend(struct.pack('<I', 1234567890))  # new_time
        packet.extend(struct.pack('<i', -15))         # snr (有符号整数)
        packet.extend(struct.pack('<f', 1.5))         # delta_time (浮点数)
        packet.extend(struct.pack('<I', 1000))        # delta_frequency (1000Hz)
        
        # 模式 (FT8)
        packet.extend(struct.pack('<I', 3))
        packet.extend(b"FT8")
        
        # 消息 (CQ CALL)
        message = "CQ K1ABC FN42"
        packet.extend(struct.pack('<I', len(message)))
        packet.extend(message.encode('utf-8'))
        
        # 低密度奇偶校验
        packet.extend(struct.pack('<I', 0))  # low_confidence
        packet.extend(struct.pack('<I', 0))  # off_air
        
        return bytes(packet)
    
    def create_real_status_packet(self):
        """创建真实的WSJT-X状态数据包"""
        packet = bytearray()
        
        # 头部
        packet.extend(struct.pack('<I', 0xadbccb00))  # magic
        packet.extend(struct.pack('<I', 1))           # version
        packet.extend(struct.pack('<I', 1))           # status packet type
        packet.extend(struct.pack('<I', 4))           # id length
        packet.extend(b"WSJT")
        
        # 频率 (14070000 Hz = 14.070 MHz)
        packet.extend(struct.pack('<Q', 14070000))
        
        # 模式 (FT8)
        packet.extend(struct.pack('<I', 3))
        packet.extend(b"FT8")
        
        # 呼号
        packet.extend(struct.pack('<I', 6))
        packet.extend(b"LU9DCE")
        
        # 网格
        packet.extend(struct.pack('<I', 6))
        packet.extend(b"GF05TJ")
        
        # 发射状态
        packet.extend(struct.pack('<I', 0))  # not transmitting
        
        return bytes(packet)
    
    def listen_for_packets(self):
        """监听传入的数据包"""
        print("🔍 开始监听端口2237...")
        
        try:
            sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
            sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
            sock.bind(('0.0.0.0', self.listen_port))
            sock.settimeout(1.0)
            
            print(f"✅ 监听端口 {self.listen_port} 成功")
            
            while self.running:
                try:
                    data, addr = sock.recvfrom(1024)
                    timestamp = datetime.now().strftime("%H:%M:%S.%f")[:-3]
                    
                    packet_info = {
                        'time': timestamp,
                        'addr': addr,
                        'size': len(data),
                        'data': data
                    }
                    
                    self.packets_received.append(packet_info)
                    
                    # 分析数据包类型
                    if len(data) >= 16:
                        magic = struct.unpack('<I', data[0:4])[0]
                        if magic == 0xadbccb00:
                            packet_type = struct.unpack('<I', data[8:12])[0]
                            packet_info['type'] = packet_type
                            
                            if packet_type == 2:  # decode packet
                                self.decode_packets.append(packet_info)
                                print(f"🎯 收到解码数据包! 大小: {len(data)}, 来源: {addr}")
                            elif packet_type == 1:  # status packet
                                print(f"📊 收到状态数据包! 大小: {len(data)}, 来源: {addr}")
                            else:
                                print(f"📦 收到数据包! 类型: {packet_type}, 大小: {len(data)}, 来源: {addr}")
                        else:
                            print(f"❓ 收到未知数据包! 大小: {len(data)}, 来源: {addr}")
                    
                except socket.timeout:
                    continue
                except Exception as e:
                    print(f"❌ 监听错误: {e}")
                    
        except Exception as e:
            print(f"❌ 无法绑定端口 {self.listen_port}: {e}")
            return False
            
        return True
    
    def send_test_packets(self):
        """发送测试数据包到ULTRON"""
        print("\n📡 发送测试数据包到ULTRON...")
        
        try:
            sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
            
            # 测试1: 发送状态数据包
            print("1. 发送状态数据包...")
            status_packet = self.create_real_status_packet()
            sock.sendto(status_packet, ('127.0.0.1', self.listen_port))
            print(f"   ✅ 发送了 {len(status_packet)} 字节")
            time.sleep(1)
            
            # 测试2: 发送解码数据包
            print("2. 发送解码数据包...")
            decode_packet = self.create_real_decode_packet()
            sock.sendto(decode_packet, ('127.0.0.1', self.listen_port))
            print(f"   ✅ 发送了 {len(decode_packet)} 字节")
            time.sleep(1)
            
            # 测试3: 发送多个解码数据包
            print("3. 发送多个解码数据包...")
            for i in range(3):
                # 创建不同的CQ消息
                calls = ["K1ABC", "W2DEF", "JA3XYZ"]
                grids = ["FN42", "FN30", "PM74"]
                
                packet = bytearray()
                packet.extend(struct.pack('<I', 0xadbccb00))
                packet.extend(struct.pack('<I', 1))
                packet.extend(struct.pack('<I', 2))
                packet.extend(struct.pack('<I', 4))
                packet.extend(b"WSJT")
                
                packet.extend(struct.pack('<I', int(time.time())&0xFFFFFFFF))
                packet.extend(struct.pack('<i', -15 + i))  # SNR: -15, -14, -13
                packet.extend(struct.pack('<f', 0.5))      # delta_time
                packet.extend(struct.pack('<I', 1000 + i*100))  # delta_frequency
                
                packet.extend(struct.pack('<I', 3))
                packet.extend(b"FT8")
                
                message = f"CQ {calls[i]} {grids[i]}"
                packet.extend(struct.pack('<I', len(message)))
                packet.extend(message.encode('utf-8'))
                
                packet.extend(struct.pack('<I', 0))
                packet.extend(struct.pack('<I', 0))
                
                sock.sendto(bytes(packet), ('127.0.0.1', self.listen_port))
                print(f"   ✅ 发送了CQ消息: {message}")
                time.sleep(0.5)
            
            sock.close()
            print("✅ 测试数据包发送完成")
            return True
            
        except Exception as e:
            print(f"❌ 发送测试数据包失败: {e}")
            return False
    
    def analyze_ultron_logs(self):
        """分析ULTRON日志文件"""
        print("\n📋 分析ULTRON日志文件...")
        
        log_files = [
            "robot_output.log",
            "wsjtx_log.adi",
            "ultron.log"
        ]
        
        for log_file in log_files:
            if os.path.exists(log_file):
                try:
                    with open(log_file, 'r', encoding='utf-8', errors='replace') as f:
                        lines = f.readlines()
                        
                    print(f"\n📄 {log_file}:")
                    print(f"   总行数: {len(lines)}")
                    
                    if lines:
                        # 显示最后几行
                        print("   最后5行:")
                        for line in lines[-5:]:
                            print(f"   {line.strip()}")
                    
                    # 分析特定内容
                    if log_file == "wsjtx_log.adi":
                        qso_count = len([l for l in lines if "<CALL:" in l])
                        print(f"   QSO记录数: {qso_count}")
                        
                except Exception as e:
                    print(f"❌ 读取 {log_file} 失败: {e}")
            else:
                print(f"ℹ️  {log_file}: 不存在")
    
    def run_diagnostic(self):
        """运行完整诊断"""
        print("╔══════════════════════════════════════════════════════════════════════════════╗")
        print("║                         ULTRON 深度调试工具                                  ║")
        print("╚══════════════════════════════════════════════════════════════════════════════╝")
        print()
        
        # 启动监听线程
        listen_thread = threading.Thread(target=self.listen_for_packets)
        listen_thread.daemon = True
        listen_thread.start()
        
        time.sleep(2)  # 给监听线程启动时间
        
        # 发送测试数据包
        self.send_test_packets()
        
        # 等待接收响应
        print("\n⏳ 等待5秒收集响应...")
        time.sleep(5)
        
        # 停止监听
        self.running = False
        
        # 分析结果
        print(f"\n📊 诊断结果:")
        print(f"   收到数据包总数: {len(self.packets_received)}")
        print(f"   收到解码数据包: {len(self.decode_packets)}")
        
        if self.packets_received:
            print("\n📦 收到的数据包详情:")
            for i, packet in enumerate(self.packets_received[-5:], 1):
                print(f"   {i}. {packet['time']} - 来源: {packet['addr']}, 大小: {packet['size']}")
        
        # 分析日志
        self.analyze_ultron_logs()
        
        # 检查系统状态
        print(f"\n🔧 系统状态分析:")
        if len(self.packets_received) == 0:
            print("   ⚠️  ULTRON可能没有正确处理数据包")
            print("   💡 建议检查:")
            print("      - ULTRON是否在运行")
            print("      - 端口2237是否被占用")
            print("      - 防火墙设置")
        else:
            print("   ✅ ULTRON正在接收数据包")
            if len(self.decode_packets) > 0:
                print("   ✅ ULTRON正在处理解码数据包")
            else:
                print("   ⚠️  ULTRON可能没有正确处理解码数据包")

if __name__ == "__main__":
    debugger = ULTRONDebugger()
    debugger.run_diagnostic()