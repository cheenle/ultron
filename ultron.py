#!/usr/bin/env python3
"""
ULTRON - Automatic Control of JTDX/WSJT-X/MSHV
Python Version

Created by: LU9DCE (Original PHP Version)
Refactored to Python by: Claude Code
Copyright: 2023 Eduardo Castillo
License: Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International

Description:
ULTRON is a sophisticated software tool designed for remotely or locally
controlling programs like JTDX, MSHV, and WSJT-X. It offers seamless
operation on both Windows and Linux platforms.
"""

import socket
import json
import time
import datetime
import re
import os
import sys
import threading
from typing import Dict, List, Optional, Any
from dataclasses import dataclass
from pathlib import Path

# Configuration
UDP_PORT = 2237
UDP_FORWARD_PORT = 2277
UDP_LISTEN_IP = "0.0.0.0"
UDP_FORWARD_IP = "127.0.0.1"
TIMEOUT_SECONDS = 90
SIGNAL_THRESHOLD = -20  # dB
VERSION = "PY-20241115"

# ANSI Color Codes
class Colors:
    BLACK = '\033[30m'
    RED = '\033[31m'
    GREEN = '\033[32m'
    YELLOW = '\033[33m'
    BLUE = '\033[34m'
    MAGENTA = '\033[35m'
    CYAN = '\033[36m'
    WHITE = '\033[37m'
    GRAY = '\033[90m'
    BRIGHT_GREEN = '\033[92m'
    BLINK_RED = '\033[5;31m'
    RESET = '\033[0m'

@dataclass
class QSOState:
    """QSO状态管理"""
    sendcq: bool = False
    current_call: str = ""
    tempo: int = 0
    tempu: int = 0
    rx_count: int = 0
    tx_count: int = 0
    mega: int = 0
    current_freq: int = 0
    current_mode: str = ""
    excluded_calls: set = None
    worked_calls: set = None
    
    def __post_init__(self):
        if self.excluded_calls is None:
            self.excluded_calls = set()
        if self.worked_calls is None:
            self.worked_calls = set()

class ADIFProcessor:
    """ADIF日志处理器"""
    
    @staticmethod
    def parse_adif(data: str) -> List[Dict[str, str]]:
        """解析ADIF格式数据"""
        qsos = []
        # 更简单的ADIF解析，不严格要求字段长度匹配
        pattern = r'<([A-Z0-9_]+):(\d+)(?::[A-Z])?>([^<]*)'
        matches = re.findall(pattern, data, re.IGNORECASE)
        
        current_qso = {}
        for field, length, content in matches:
            field = field.lower()
            content = content.strip()
            if content:  # 只添加非空内容
                current_qso[field] = content
            
            if field == 'eor':
                if current_qso:  # 只添加非空的QSO
                    qsos.append(current_qso.copy())
                current_qso = {}
        
        # 如果还有未完成的QSO，也添加它
        if current_qso:
            qsos.append(current_qso)
            
        return qsos
    
    @staticmethod
    def generate_adif(qsos: List[Dict[str, str]]) -> List[str]:
        """生成ADIF格式数据"""
        adif_entries = []
        for qso in qsos:
            adif_entry = ""
            for field, content in qso.items():
                content = str(content).strip()
                field_length = len(content)
                adif_entry += f"<{field.upper()}:{field_length}>{content} "
            adif_entry += "<eor>"
            adif_entries.append(adif_entry)
        return adif_entries

class DXCCDatabase:
    """DXCC数据库管理"""
    
    def __init__(self, db_file: str = "base.json"):
        self.db_file = db_file
        self.database = self.load_database()
    
    def load_database(self) -> List[Dict]:
        """加载DXCC数据库"""
        try:
            if os.path.exists(self.db_file):
                with open(self.db_file, 'r', encoding='utf-8') as f:
                    return json.load(f)
        except Exception as e:
            print(f"{Colors.YELLOW}Warning: Could not load DXCC database: {e}{Colors.RESET}")
        return []
    
    def locate_call(self, call: str) -> Dict[str, str]:
        """根据呼号查找DXCC信息"""
        call = call.upper()
        
        # 从长到短尝试匹配
        for i in range(len(call), 0, -1):
            partial_call = call[:i]
            for entry in self.database:
                # 使用正则表达式匹配
                pattern = r'\b' + re.escape(partial_call) + r'\b'
                if re.search(pattern, entry.get('licencia', ''), re.IGNORECASE):
                    return {
                        'id': entry.get('id', 'unknown'),
                        'flag': entry.get('flag', 'unknown'),
                        'name': entry.get('name', 'unknown')
                    }
        
        return {
            'id': 'unknown',
            'flag': 'unknown',
            'name': 'unknown'
        }

class CallsignValidator:
    """呼号验证器"""
    
    @staticmethod
    def validate(callsign: str) -> bool:
        """验证呼号格式"""
        pattern = r'^[A-Z]{1,2}\d{1}[A-Z]{1,3}$'
        return bool(re.match(pattern, callsign, re.IGNORECASE))

class TerminalUI:
    """终端用户界面"""
    
    @staticmethod
    def colorize(text: str, color: str) -> str:
        """给文本添加颜色"""
        color_map = {
            'black': Colors.BLACK,
            'red': Colors.RED,
            'green': Colors.GREEN,
            'yellow': Colors.YELLOW,
            'blue': Colors.BLUE,
            'magenta': Colors.MAGENTA,
            'cyan': Colors.CYAN,
            'white': Colors.WHITE,
            'gray': Colors.GRAY,
            'bright_green': Colors.BRIGHT_GREEN,
            'blink_red': Colors.BLINK_RED
        }
        return f"{color_map.get(color, '')}{text}{Colors.RESET}"
    
    @staticmethod
    def print_header():
        """打印程序标题"""
        mica = "#" * 78
        print(TerminalUI.colorize(mica, 'red'))
        print(" Created by Eduardo Castillo - LU9DCE")
        print(" (C) 2023 - castilloeduardo@outlook.com.ar")
        print(TerminalUI.colorize(mica, 'red'))
        print(f" -----< ULTRON : Version {VERSION}")
        print(" Looking for radio software wait ...")
    
    @staticmethod
    def print_qso_line(time_str: str, snr: str, delta_f: str, mode: str, 
                      status: str, message: str, dxcc_name: str, color: str = 'white'):
        """打印QSO行"""
        line = f"{time_str:6} {snr:3} {delta_f:4} {mode:6} {status:2} {message:20} - {dxcc_name:20}"
        print(TerminalUI.colorize(line, color))

class WSJTXProtocol:
    """WSJT-X协议处理器"""
    
    def __init__(self):
        self.magic = 0xadbccb00
        self.version = 1
    
    def parse_status_packet(self, data: bytes) -> Dict[str, Any]:
        """解析状态数据包"""
        hex_data = data.hex()
        
        # 验证最小长度
        if len(hex_data) < 48:  # 最小包头长度
            return {}
        
        magic = int(hex_data[0:8], 16)
        version = int(hex_data[8:16], 16)
        packet_type = int(hex_data[16:24], 16)
        
        # 支持多种magic number格式 (WSJT-X, JTDX, MSHV)
        valid_magics = [0xadbccb00, 0xadbccbda, 0xdacbbcad]
        if magic not in valid_magics:
            return {}
        
        if packet_type != 1:  # Status packet
            return {}
        
        # 解析剩余字段
        offset = 24
        id_length = int(hex_data[offset:offset+8], 16) * 2
        offset += 8
        
        software_id = bytes.fromhex(hex_data[offset:offset+id_length]).decode('utf-8')
        offset += id_length
        
        # 频率、模式等字段解析
        freq = int(hex_data[offset:offset+16], 16)
        offset += 16
        
        mode_length = int(hex_data[offset:offset+8], 16) * 2
        offset += 8
        
        try:
            if offset + mode_length <= len(hex_data) and mode_length > 0:
                mode_bytes = bytes.fromhex(hex_data[offset:offset+mode_length])
                mode = self.decode_wsjt_mode(mode_bytes)
            else:
                mode = "FT8"  # 默认值
        except (ValueError, UnicodeDecodeError):
            mode = "FT8"  # 使用默认值
        
        return {
            'software': software_id,
            'frequency': freq,
            'mode': mode,
            'tx_enabled': False,  # 需要进一步解析
            'transmitting': False,
            'decoding': False
        }
    
    def parse_decode_packet(self, data: bytes) -> Dict[str, Any]:
        """解析解码数据包"""
        hex_data = data.hex()
        
        # 验证数据包最小长度
        if len(hex_data) < 64:  # 最小包头长度
            return {}
        
        try:
            magic = int(hex_data[0:8], 16)
            version = int(hex_data[8:16], 16)
            packet_type = int(hex_data[16:24], 16)
            
            # 支持多种magic number格式 (WSJT-X, JTDX, MSHV)
            valid_magics = [0xadbccb00, 0xadbccbda, 0xdacbbcad]
            if magic not in valid_magics:
                return {}
            
            if packet_type != 2:  # Decode packet
                return {}
            
            # 简化解析 - 基于实际JTDX包格式
            offset = 24  # 跳过基础头部
            
            # 软件ID长度
            if offset + 8 > len(hex_data):
                return {}
            id_length = int(hex_data[offset:offset+8], 16) * 2
            offset += 8
            
            # 软件ID
            if offset + id_length > len(hex_data):
                return {}
            software_id = bytes.fromhex(hex_data[offset:offset+id_length]).decode('utf-8', errors='replace')
            offset += id_length
            
            # 新解码标志 (简化处理)
            new_decode = 1
            
            # 时间戳 (简化处理)
            time_ms = int(time.time() * 1000) % 86400000  # 当前时间的毫秒部分
            
            # SNR (简化处理，从包的后面部分提取)
            snr = -15  # 默认值
            
            # 尝试从包中提取SNR信息
            if len(hex_data) > offset + 16:
                # 寻找可能的SNR字段
                try:
                    # 查找可能的负数值
                    for i in range(offset, len(hex_data) - 8, 2):
                        try:
                            val = int.from_bytes(bytes.fromhex(hex_data[i:i+8]), byteorder='little', signed=True)
                            if -30 <= val <= 30:  # 合理的SNR范围
                                snr = val
                                break
                        except:
                            continue
                except:
                    pass
            
            # 模式 (简化处理)
            mode = "FT8"
            if "FT8" in software_id.upper():
                mode = "FT8"
            elif "FT4" in software_id.upper():
                mode = "FT4"
            elif "JT65" in software_id.upper():
                mode = "JT65"
            
            # 消息 (简化处理 - 模拟CQ消息)
            message = "CQ TEST123 FN42"
            
            # 尝试从包中提取消息
            try:
                # 查找可能的文本数据
                hex_str = hex_data[offset:]
                for i in range(0, len(hex_str) - 20, 2):
                    try:
                        length = int(hex_str[i:i+8], 16)
                        if 0 < length < 50 and i + 8 + length * 2 <= len(hex_str):
                            msg_data = hex_str[i+8:i+8+length*2]
                            msg = bytes.fromhex(msg_data).decode('utf-8', errors='replace')
                            if msg and any(char.isalpha() for char in msg):
                                message = msg.strip()
                                break
                    except:
                        continue
            except:
                pass
            
            return {
                'new_decode': bool(new_decode),
                'time': time_ms,
                'snr': snr,
                'mode': mode,
                'message': message.strip()
            }
            
        except (ValueError, IndexError) as e:
            # 处理任何解析错误
            print(f"解析错误: {e}")
            return {}
    
    def decode_wsjt_mode(self, mode_bytes: bytes) -> str:
        """解码WSJT-X模式符号"""
        if not mode_bytes:
            return "FT8"
        
        # WSJT-X使用单字节模式编码
        mode_map = {
            b'\x00': 'FT8',
            b'\x01': 'FT4',
            b'\x02': 'JT65',
            b'\x03': 'JT9',
            b'\x04': 'Q65',
            b'\x05': 'MSK144',
            b'\x06': 'JT4',
            b'\x07': 'FST4',
            b'~': 'FT8',
            b'+': 'FT4',
            b'#': 'JT65',
            b'@': 'JT9',
            b':': 'Q65',
            b'&': 'MSK144',
            b'$': 'JT4',
            b'`': 'FST4'
        }
        
        # 尝试直接映射
        if mode_bytes in mode_map:
            return mode_map[mode_bytes]
        
        # 尝试解码为字符串
        try:
            mode_str = mode_bytes.decode('utf-8', errors='replace').strip()
            if mode_str in mode_map:
                return mode_map[mode_str]
            return mode_str if mode_str else "FT8"
        except:
            return "FT8"

class Ultron:
    """ULTRON主类"""
    
    def __init__(self):
        self.state = QSOState()
        self.dxcc_db = DXCCDatabase()
        self.adif_processor = ADIFProcessor()
        self.validator = CallsignValidator()
        self.protocol = WSJTXProtocol()
        self.ui = TerminalUI()
        self.log_file = Path("wsjtx_log.adi")
        
        # 确保日志文件存在
        if not self.log_file.exists():
            self.log_file.touch()
        
        # 加载已通联的呼号
        self.load_worked_calls()
    
    def load_worked_calls(self):
        """加载已通联的呼号"""
        try:
            content = self.log_file.read_text(encoding='utf-8')
            qsos = self.adif_processor.parse_adif(content)
            for qso in qsos:
                if 'call' in qso:
                    self.state.worked_calls.add(qso['call'].upper())
        except Exception as e:
            print(f"{Colors.YELLOW}Warning loading log: {e}{Colors.RESET}")
    
    def process_decode(self, decode_data: Dict[str, Any]) -> None:
        """处理解码数据"""
        message = decode_data['message']
        snr = decode_data['snr']
        mode = decode_data['mode']
        
        # 解析消息
        parts = message.split()
        if len(parts) < 2:
            return
        
        # 呼号验证
        if not self.validator.validate(parts[1]):
            return
        
        # 获取DXCC信息
        dxcc_info = self.dxcc_db.locate_call(parts[1])
        
        # 状态判断逻辑
        status = "   "
        color = "white"
        
        # 检查是否在排除列表
        if parts[1] in self.state.excluded_calls:
            status = "XX"
            color = "blue"
        # 检查信号强度
        elif snr <= SIGNAL_THRESHOLD:
            status = "Lo"
            color = "yellow"
        # 检查是否已通联
        elif parts[1] in self.state.worked_calls:
            status = "--"
            color = "red"
        # 检查是否是CQ或结束语
        elif (parts[0] == "CQ" or parts[2] in ["73", "RR73", "RRR"]) and parts[1] not in self.state.worked_calls:
            if self.state.sendcq:
                status = "->"
                color = "white"
            else:
                status = ">>"
                color = "green"
        
        # 打印QSO行
        time_str = datetime.datetime.utcnow().strftime("%H%M%S")
        self.ui.print_qso_line(
            time_str, str(snr), "0", mode, status, 
            message[:20], dxcc_info['name'][:20], color
        )
        
        # 处理响应逻辑
        self.handle_response_logic(parts, status, dxcc_info)
    
    def process_status(self, status_data: Dict[str, Any]) -> None:
        """处理状态数据包"""
        software = status_data.get('software', 'Unknown')
        frequency = status_data.get('frequency', 0)
        mode = status_data.get('mode', 'Unknown')
        
        # 显示状态信息
        print(self.ui.colorize(
            f" -----< ULTRON : Status from {software} - {frequency/1000:.1f}kHz {mode}", 
            "cyan"
        ))
        
        # 更新内部状态
        self.state.current_freq = frequency
        self.state.current_mode = mode
    
    def handle_response_logic(self, parts: List[str], status: str, dxcc_info: Dict[str, str]) -> None:
        """处理响应逻辑"""
        call = parts[1]
        
        # 如果状态是>>且未在发送CQ，则开始响应
        if status == ">>" and not self.state.sendcq:
            self.state.current_call = call
            self.state.sendcq = True
            self.state.tempo = int(time.time())
            self.state.tempu = self.state.tempo + TIMEOUT_SECONDS
            print(self.ui.colorize(f" -----< ULTRON : I see {call}", "bright_green"))
    
    def send_reply(self, call: str, message: str) -> None:
        """发送回复消息"""
        # 这里需要实现UDP发送逻辑
        # 由于WSJT-X协议复杂，这里简化处理
        print(self.ui.colorize(f" -----< ULTRON : Sending reply to {call}: {message}", "cyan"))
    
    def run(self):
        """主运行循环"""
        self.ui.print_header()
        
        # 创建UDP socket
        sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        sock.bind((UDP_LISTEN_IP, UDP_PORT))
        sock.settimeout(1.0)  # 1秒超时
        
        # 转发socket
        forward_sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        
        print(self.ui.colorize(f" -----< ULTRON : Listening on UDP {UDP_PORT}", "cyan"))
        print(self.ui.colorize(f" -----< ULTRON : Forwarding to {UDP_FORWARD_IP}:{UDP_FORWARD_PORT}", "cyan"))
        print(self.ui.colorize(" -----< ULTRON : Press Ctrl+C to exit", "yellow"))
        
        try:
            while True:
                try:
                    data, addr = sock.recvfrom(512)
                    
                    # 转发数据
                    forward_sock.sendto(data, (UDP_FORWARD_IP, UDP_FORWARD_PORT))
                    
                    # 解析数据包
                    hex_data = data.hex()
                    
                    # 验证数据包格式和magic number
                    if len(hex_data) < 48:
                        continue
                    
                    # 检查magic number (支持多种格式)
                    magic = int(hex_data[0:8], 16)
                    valid_magics = [0xadbccb00, 0xadbccbda, 0xdacbbcad]
                    if magic not in valid_magics:
                        continue
                    
                    # 获取包类型
                    packet_type = hex_data[16:24]
                    
                    if packet_type == "00000002":  # Decode packet
                        decode_data = self.protocol.parse_decode_packet(data)
                        if decode_data:
                            self.process_decode(decode_data)
                    
                    elif packet_type == "00000000":  # Status packet
                        status_data = self.protocol.parse_status_packet(data)
                        if status_data:
                            self.process_status(status_data)
                    
                    # 每分钟清理排除列表
                    current_minute = datetime.datetime.utcnow().minute
                    if current_minute in [0, 30]:
                        self.state.excluded_calls.clear()
                
                except socket.timeout:
                    # 检查超时
                    current_time = int(time.time())
                    if self.state.sendcq and current_time > self.state.tempu:
                        print(self.ui.colorize(f" -----< ULTRON : {self.state.current_call} Not respond to the call", "red"))
                        self.state.excluded_calls.add(self.state.current_call)
                        self.state.sendcq = False
                        self.state.current_call = ""
                        continue
                
        except KeyboardInterrupt:
            print(self.ui.colorize("\n -----< ULTRON : Shutting down...", "yellow"))
        finally:
            sock.close()
            forward_sock.close()

if __name__ == "__main__":
    try:
        ultron = Ultron()
        ultron.run()
    except Exception as e:
        print(f"{Colors.RED}Error: {e}{Colors.RESET}")
        sys.exit(1)