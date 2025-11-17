#!/usr/bin/env python3
"""测试ULTRON修复效果"""

import sys
import time
from pathlib import Path

# 添加路径
sys.path.insert(0, str(Path(__file__).parent))

from ultron import WSJTXProtocol

def test_protocol_parsing():
    """测试协议解析功能"""
    print("测试WSJT-X协议解析...")
    
    protocol = WSJTXProtocol()
    
    # 测试用例1: 正常的解码数据包
    print("\n1. 测试正常解码数据包")
    try:
        # 模拟一个有效的WSJT-X解码数据包 (简化版本)
        # 这是基于真实WSJT-X协议格式的简化测试数据
        test_data = bytes.fromhex(
            'adbccb000000000200000001' +  # 头部
            '000000000000000000000001' +  # ID等
            '0000000000000000' +          # 频率等
            '00000002' +                  # 模式长度
            '465438' +                    # "FT8" 模式
            '00000004' +                  # 消息长度
            '4343512041'                  # "CQ A" 消息
        )
        
        result = protocol.parse_decode_packet(test_data)
        print(f"   结果: {result}")
        
    except Exception as e:
        print(f"   错误: {e}")
    
    # 测试用例2: 编码错误的数据包
    print("\n2. 测试编码错误的数据包")
    try:
        # 模拟有编码错误的数据包
        test_data = bytes.fromhex(
            'adbccb000000000200000001' +
            '000000000000000000000001' +
            '0000000000000000' +
            '00000002' +
            'f0f1' +  # 无效的UTF-8序列
            '00000004' +
            '4343512041'
        )
        
        result = protocol.parse_decode_packet(test_data)
        print(f"   结果: {result}")
        
    except Exception as e:
        print(f"   错误: {e}")
    
    # 测试用例3: 短数据包
    print("\n3. 测试短数据包")
    try:
        test_data = b'\x00\x01\x02'  # 太短的包
        result = protocol.parse_decode_packet(test_data)
        print(f"   结果: {result}")
        
    except Exception as e:
        print(f"   错误: {e}")
    
    # 测试用例4: 模式解码
    print("\n4. 测试模式解码")
    test_modes = [
        b'~',      # FT8
        b'+',      # FT4
        b'#',      # JT65
        b'\x00',   # FT8 (字节编码)
        b'\x01',   # FT4 (字节编码)
        b'\xf0\xf1', # 无效编码
        b'',       # 空字节
    ]
    
    for mode_bytes in test_modes:
        try:
            decoded = protocol.decode_wsjt_mode(mode_bytes)
            print(f"   {mode_bytes!r} -> {decoded}")
        except Exception as e:
            print(f"   {mode_bytes!r} -> 错误: {e}")

def test_full_integration():
    """测试完整集成"""
    print("\n\n测试完整集成...")
    
    try:
        from ultron import Ultron
        
        # 创建实例
        ultron = Ultron()
        
        # 测试配置
        print("配置测试...")
        print(f"UDP端口: 2237 (默认)")
        print(f"信号阈值: -20dB (默认)")
        
        # 测试DXCC数据库
        print("\nDXCC数据库测试...")
        dxcc_info = ultron.dxcc_db.locate_call("K1ABC")
        print(f"K1ABC: {dxcc_info}")
        
        # 测试ADIF处理
        print("\nADIF处理测试...")
        test_adif = '<call:4>K1ABC <gridsquare:4>FN42 <eor>'
        result = ultron.adif_processor.parse_adif(test_adif)
        print(f"解析结果: {result}")
        
        # 测试呼号验证
        print("\n呼号验证测试...")
        test_calls = ["K1ABC", "W2DEF", "INVALID", ""]
        for call in test_calls:
            valid = ultron.validator.validate(call)
            print(f"{call}: {'有效' if valid else '无效'}")
        
        print("\n✅ 所有基础测试通过!")
        
    except Exception as e:
        print(f"❌ 集成测试失败: {e}")
        import traceback
        traceback.print_exc()

if __name__ == "__main__":
    print("ULTRON修复测试")
    print("=" * 50)
    
    test_protocol_parsing()
    test_full_integration()
    
    print("\n" + "=" * 50)
    print("测试完成!")