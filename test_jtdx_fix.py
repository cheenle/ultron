#!/usr/bin/env python3
"""
测试JTDX兼容性修复
发送真实的JTDX数据包到ULTRON
"""

import socket
import time
import struct
import subprocess
import threading

def test_jtdx_packets():
    """测试JTDX数据包处理"""
    print("🧪 测试JTDX兼容性修复...")
    
    # 启动ULTRON进程
    print("📡 启动ULTRON...")
    proc = subprocess.Popen(
        ['python', 'ultron_dxcc.py'],
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        text=True,
        bufsize=1
    )
    
    # 等待ULTRON启动
    time.sleep(3)
    
    if proc.poll() is not None:
        print("❌ ULTRON启动失败")
        output = proc.stdout.read()
        print("错误输出:", output[:300])
        return False
    
    print("✅ ULTRON已启动")
    
    # 创建发送socket
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    
    # 测试数据包 - 真实的JTDX数据包格式
    test_packets = [
        # JTDX状态包 (magic: 0xdacbbcad)
        bytes.fromhex('adbc cbda 0000 0002 0000 0001 0000 0004 4a54 4458 0000 0000 006b f0d0 0000 0003 4654 38ffff ffff 0000 0003 2d31 3400 0000 03'),
        
        # JTDX解码包 (magic: 0xdacbbcad)
        bytes.fromhex('adbc cbda 0000 0002 0000 0002 0000 0004 4a54 4458 0102 fefb a8ff ffff ffb3 f9b9 9999 a000 0000 0000 08f0 0000 0001 7e00 0000 17'),
        
        # 另一个JTDX解码包
        bytes.fromhex('adbc cbda 0000 0002 0000 0002 0000 0004 4a54 4458 0102 fefb a8ff ffff ffa9 3fb9 9999 a000 0000 0000 06b3 0000 0001 7e00 0000 17'),
    ]
    
    print("📨 发送测试数据包...")
    
    for i, packet in enumerate(test_packets, 1):
        try:
            sock.sendto(packet, ('127.0.0.1', 2237))
            print(f"   ✅ 发送了数据包 #{i} ({len(packet)} 字节)")
            time.sleep(1)
        except Exception as e:
            print(f"   ❌ 发送数据包 #{i} 失败: {e}")
    
    sock.close()
    
    # 等待响应
    print("⏳ 等待ULTRON处理...")
    time.sleep(5)
    
    # 收集输出
    output_lines = []
    try:
        # 读取可用输出
        import select
        if select.select([proc.stdout], [], [], 0) == ([proc.stdout], [], []):
            line = proc.stdout.readline()
            if line:
                output_lines.append(line.strip())
    except:
        pass
    
    # 检查输出中是否有处理迹象
    has_decode = False
    has_status = False
    
    for line in output_lines:
        if 'decode' in line.lower() or 'status' in line.lower():
            if 'status' in line.lower():
                has_status = True
            if 'decode' in line.lower():
                has_decode = True
            print(f"🔍 {line}")
    
    # 终止ULTRON
    proc.terminate()
    proc.wait(timeout=5)
    
    print(f"\n📊 测试结果:")
    print(f"   状态包处理: {'✅' if has_status else '❌'}")
    print(f"   解码包处理: {'✅' if has_decode else '❌'}")
    print(f"   输出总行数: {len(output_lines)}")
    
    return has_decode or has_status

def main():
    """主测试函数"""
    print("╔══════════════════════════════════════════════════════════════════════════════╗")
    print("║                        JTDX兼容性测试工具                                    ║")
    print("╚══════════════════════════════════════════════════════════════════════════════╝")
    
    success = test_jtdx_packets()
    
    if success:
        print("\n🎉 JTDX兼容性修复成功！")
        print("   ULTRON现在可以正确处理JTDX数据包了")
    else:
        print("\n⚠️  需要进一步调试...")
        print("   检查ULTRON输出获取更多信息")

if __name__ == "__main__":
    main()