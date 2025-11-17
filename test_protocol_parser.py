#!/usr/bin/env python3
"""
独立测试协议解析器
"""

import sys
import os
sys.path.insert(0, os.path.dirname(__file__))

from ultron import WSJTXProtocol

def test_protocol_parser():
    """测试协议解析功能"""
    print("🧪 测试WSJT-X协议解析器...")
    
    protocol = WSJTXProtocol()
    
    # 测试数据包 - 基于我们捕获的真实JTDX数据
    test_packets = [
        {
            'name': 'JTDX状态包',
            'data': bytes.fromhex('adbc cbda 0000 0002 0000 0001 0000 0004 4a54 4458 0000 0000 006b f0d0 0000 0003 4654 38ffff ffff 0000 0003 2d31 3400 0000 03'),
            'expected_type': 'status'
        },
        {
            'name': 'JTDX解码包',
            'data': bytes.fromhex('adbc cbda 0000 0002 0000 0002 0000 0004 4a54 4458 0102 fefb a8ff ffff ffb3 f9b9 9999 a000 0000 0000 08f0 0000 0001 7e00 0000 17'),
            'expected_type': 'decode'
        },
        {
            'name': '标准WSJT-X包',
            'data': bytes.fromhex('adbc cb00 0000 0001 0000 0002 0000 0004 5753 4a54 0000 0000 006b f0d0 0000 0003 4654 38'),
            'expected_type': 'decode'
        }
    ]
    
    results = []
    
    for i, test in enumerate(test_packets, 1):
        print(f"\n{i}. 测试 {test['name']}:")
        data = test['data']
        print(f"   数据长度: {len(data)} 字节")
        print(f"   十六进制: {data.hex()[:60]}...")
        
        # 手动检查magic number
        import struct
        magic = struct.unpack('<I', data[0:4])[0]
        packet_type = struct.unpack('<I', data[8:12])[0]
        print(f"   Magic: 0x{magic:08x}")
        print(f"   包类型: {packet_type}")
        
        # 测试状态包解析
        if test['expected_type'] == 'status' or packet_type == 1:
            print("   🔄 测试状态包解析...")
            result = protocol.parse_status_packet(data)
            if result:
                print(f"   ✅ 状态包解析成功: {result}")
                results.append(True)
            else:
                print(f"   ❌ 状态包解析失败")
                results.append(False)
        
        # 测试解码包解析
        elif test['expected_type'] == 'decode' or packet_type == 2:
            print("   🔄 测试解码包解析...")
            result = protocol.parse_decode_packet(data)
            if result:
                print(f"   ✅ 解码包解析成功: {result}")
                results.append(True)
            else:
                print(f"   ❌ 解码包解析失败")
                results.append(False)
    
    print(f"\n📊 测试结果:")
    passed = sum(results)
    total = len(results)
    print(f"   通过: {passed}/{total}")
    
    if passed == total:
        print("🎉 所有协议解析测试通过！")
        return True
    else:
        print("⚠️  部分测试失败，需要进一步调试")
        return False

def test_magic_numbers():
    """测试magic number识别"""
    print("\n🧪 测试magic number识别...")
    
    test_magics = [
        (0xadbccb00, "标准WSJT-X"),
        (0xadbccbda, "JTDX类型1"), 
        (0xdacbbcad, "JTDX类型2"),
        (0x12345678, "无效magic")
    ]
    
    valid_magics = [0xadbccb00, 0xadbccbda, 0xdacbbcad]
    
    for magic, name in test_magics:
        is_valid = magic in valid_magics
        status = "✅" if is_valid else "❌"
        print(f"   {status} 0x{magic:08x} ({name}): {'有效' if is_valid else '无效'}")

if __name__ == "__main__":
    print("╔══════════════════════════════════════════════════════════════════════════════╗")
    print("║                        协议解析器测试工具                                    ║")
    print("╚══════════════════════════════════════════════════════════════════════════════╝")
    
    test_magic_numbers()
    success = test_protocol_parser()
    
    if success:
        print("\n🎉 协议解析器工作正常！")
        print("   ULTRON应该能够处理JTDX和WSJT-X数据包了")
    else:
        print("\n🔧 协议解析器需要进一步修复")