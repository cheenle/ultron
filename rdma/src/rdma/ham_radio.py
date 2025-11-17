"""
RDMA Amateur Radio Module

Integration of ULTRON functionality for remote digital amateur radio management.
Supports JTDX, WSJT-X, MSHV and other digital mode software.
"""

import asyncio
import json
import socket
import re
import time
from datetime import datetime
from typing import Dict, List, Optional, Any, Set
from dataclasses import dataclass, field
from pathlib import Path
from enum import Enum
import struct

from .logging import RDMALogger
from .exceptions import RDMAException, ProtocolError


# Constants from ULTRON
UDP_PORT = 2237
UDP_FORWARD_PORT = 2277
SIGNAL_THRESHOLD = -20  # dB
TIMEOUT_SECONDS = 90
VERSION = "RDMA-HAM-20241115"


class RadioMode(Enum):
    """Supported amateur radio digital modes."""
    FT8 = "FT8"
    FT4 = "FT4"
    JT65 = "JT65"
    JT9 = "JT9"
    FST4 = "FST4"
    Q65 = "Q65"
    MSK144 = "MSK144"
    JT4 = "JT4"


@dataclass
class QSOState:
    """QSO state management for amateur radio operations."""
    sendcq: bool = False
    current_call: str = ""
    tempo: int = 0
    tempu: int = 0
    rx_count: int = 0
    tx_count: int = 0
    mega: int = 0
    excluded_calls: Set[str] = field(default_factory=set)
    worked_calls: Set[str] = field(default_factory=set)


@dataclass
class DecodePacket:
    """Decoded packet information from radio software."""
    timestamp: int
    snr: int
    delta_time: float
    delta_frequency: int
    mode: str
    message: str
    is_new: bool = True


@dataclass
class StatusPacket:
    """Status packet information from radio software."""
    software: str
    frequency: int
    mode: str
    dx_call: str
    report: str
    tx_enabled: bool
    transmitting: bool
    decoding: bool
    de_call: str
    de_grid: str
    dx_grid: str


class ADIFProcessor:
    """ADIF (Amateur Data Interchange Format) log processor."""
    
    @staticmethod
    def parse_adif(data: str) -> List[Dict[str, str]]:
        """Parse ADIF format data."""
        qsos = []
        pattern = r'<([A-Z0-9_]+):(\d+)(?::[A-Z])?>([^\u003c]*)'
        matches = re.findall(pattern, data, re.IGNORECASE)
        
        current_qso = {}
        for field, length, content in matches:
            field = field.lower()
            content = content.strip()
            if content:
                current_qso[field] = content
            
            if field == 'eor':
                if current_qso:
                    qsos.append(current_qso.copy())
                current_qso = {}
        
        if current_qso:
            qsos.append(current_qso)
            
        return qsos
    
    @staticmethod
    def generate_adif(qsos: List[Dict[str, str]]) -> List[str]:
        """Generate ADIF format data."""
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


class CallsignValidator:
    """Amateur radio callsign validator."""
    
    @staticmethod
    def validate(callsign: str) -> bool:
        """Validate amateur radio callsign format."""
        pattern = r'^[A-Z]{1,2}\d{1}[A-Z]{1,3}$'
        return bool(re.match(pattern, callsign, re.IGNORECASE))
    
    @staticmethod
    def extract_prefix(callsign: str) -> str:
        """Extract the prefix from a callsign for DXCC lookup."""
        callsign = callsign.upper()
        # Remove any suffixes like /P, /M, /QRP
        callsign = re.sub(r'/[A-Z0-9]+$', '', callsign)
        return callsign


class DXCCDatabase:
    """DXCC (DX Century Club) entity database."""
    
    def __init__(self, db_file: str = "base.json", logger: Optional[RDMALogger] = None):
        self.db_file = Path(db_file)
        self.logger = logger
        self.database = self._load_database()
    
    def _load_database(self) -> List[Dict[str, Any]]:
        """Load DXCC database from JSON file."""
        try:
            if self.db_file.exists():
                with open(self.db_file, 'r', encoding='utf-8') as f:
                    return json.load(f)
        except Exception as e:
            if self.logger:
                self.logger.warning(f"Could not load DXCC database: {e}")
        return []
    
    def locate_call(self, call: str) -> Dict[str, str]:
        """Find DXCC entity information for a callsign."""
        call = CallsignValidator.extract_prefix(call.upper())
        
        # Try matching from longest to shortest prefix
        for i in range(len(call), 0, -1):
            partial_call = call[:i]
            for entry in self.database:
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


class WSJTXProtocol:
    """WSJT-X protocol parser for UDP communication."""
    
    def __init__(self, logger: Optional[RDMALogger] = None):
        self.logger = logger
        self.magic = 0xadbccb00
        self.version = 1
    
    def parse_status_packet(self, data: bytes) -> StatusPacket:
        """Parse status data packet from WSJT-X."""
        hex_data = data.hex()
        
        # Basic packet parsing
        magic = int(hex_data[0:8], 16)
        version = int(hex_data[8:16], 16)
        packet_type = int(hex_data[16:24], 16)
        
        if packet_type != 1:  # Status packet
            raise ProtocolError(f"Not a status packet: {packet_type}")
        
        # Parse status fields
        offset = 32
        id_length = int(hex_data[offset:offset+8], 16) * 2
        offset += 8
        
        software_id = bytes.fromhex(hex_data[offset:offset+id_length]).decode('utf-8')
        offset += id_length
        
        # Parse remaining fields
        freq = int(hex_data[offset:offset+16], 16)
        offset += 16
        
        mode_length = int(hex_data[offset:offset+8], 16) * 2
        offset += 8
        mode = bytes.fromhex(hex_data[offset:offset+mode_length]).decode('utf-8')
        
        return StatusPacket(
            software=software_id,
            frequency=freq,
            mode=mode,
            dx_call="",
            report="",
            tx_enabled=False,
            transmitting=False,
            decoding=False,
            de_call="",
            de_grid="",
            dx_grid=""
        )
    
    def parse_decode_packet(self, data: bytes) -> DecodePacket:
        """Parse decode data packet from WSJT-X."""
        hex_data = data.hex()
        
        magic = int(hex_data[0:8], 16)
        version = int(hex_data[8:16], 16)
        packet_type = int(hex_data[16:24], 16)
        
        if packet_type != 2:  # Decode packet
            raise ProtocolError(f"Not a decode packet: {packet_type}")
        
        offset = 32
        new_decode = int(hex_data[offset:offset+2], 16)
        offset += 2
        
        # Parse decode fields
        time_ms = int(hex_data[offset:offset+8], 16)
        offset += 8
        
        snr = int.from_bytes(bytes.fromhex(hex_data[offset:offset+8]), byteorder='big', signed=True)
        offset += 8
        
        mode_length = int(hex_data[offset:offset+8], 16) * 2
        offset += 8
        mode = bytes.fromhex(hex_data[offset:offset+mode_length]).decode('utf-8')
        offset += mode_length
        
        msg_length = int(hex_data[offset:offset+8], 16) * 2
        offset += 8
        message = bytes.fromhex(hex_data[offset:offset+msg_length]).decode('utf-8')
        
        return DecodePacket(
            timestamp=time_ms,
            snr=snr,
            delta_time=0.0,  # Simplified
            delta_frequency=0,  # Simplified
            mode=mode,
            message=message.strip(),
            is_new=bool(new_decode)
        )
    
    def decode_mode_symbol(self, mode_symbol: str) -> str:
        """Decode mode symbol to full mode name."""
        mode_map = {
            '`': 'FST4',
            '+': 'FT4',
            '~': 'FT8',
            '$': 'JT4',
            '@': 'JT9',
            '#': 'JT65',
            ':': 'Q65',
            '&': 'MSK144'
        }
        return mode_map.get(mode_symbol, mode_symbol)


class HamRadioManager:
    """Main amateur radio management class integrating ULTRON functionality."""
    
    def __init__(self, config: Dict[str, Any], logger: RDMALogger):
        self.config = config
        self.logger = logger
        self.qso_state = QSOState()
        self.adif_processor = ADIFProcessor()
        self.dxcc_db = DXCCDatabase(logger=logger)
        self.wsjtx_protocol = WSJTXProtocol(logger=logger)
        self.validator = CallsignValidator()
        
        # Configuration
        self.udp_port = config.get('udp_port', UDP_PORT)
        self.udp_forward_port = config.get('udp_forward_port', UDP_FORWARD_PORT)
        self.signal_threshold = config.get('signal_threshold', SIGNAL_THRESHOLD)
        self.timeout_seconds = config.get('timeout_seconds', TIMEOUT_SECONDS)
        self.log_file = Path(config.get('log_file', 'wsjtx_log.adi'))
        
        # Runtime state
        self.is_running = False
        self.socket = None
        self.forward_socket = None
        self._running_tasks = []
        
        # Load worked calls from log
        self._load_worked_calls()
        
        self.logger.info("HamRadioManager initialized successfully")
    
    def _load_worked_calls(self) -> None:
        """Load previously worked callsigns from ADIF log."""
        try:
            if self.log_file.exists():
                content = self.log_file.read_text(encoding='utf-8')
                qsos = self.adif_processor.parse_adif(content)
                for qso in qsos:
                    if 'call' in qso:
                        self.qso_state.worked_calls.add(qso['call'].upper())
                self.logger.info(f"Loaded {len(self.qso_state.worked_calls)} worked callsigns")
        except Exception as e:
            self.logger.warning(f"Could not load worked calls: {e}")
    
    async def start(self) -> None:
        """Start the amateur radio manager."""
        if self.is_running:
            self.logger.warning("HamRadioManager is already running")
            return
        
        self.logger.info("Starting HamRadioManager...")
        self.is_running = True
        
        try:
            # Create UDP sockets
            self.socket = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
            self.socket.bind(('0.0.0.0', self.udp_port))
            self.socket.settimeout(1.0)  # Non-blocking with timeout
            
            self.forward_socket = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
            
            # Start main processing task
            task = asyncio.create_task(self._main_loop())
            self._running_tasks.append(task)
            
            self.logger.info(f"HamRadioManager started on UDP port {self.udp_port}")
            
        except Exception as e:
            self.logger.error(f"Failed to start HamRadioManager: {e}")
            await self.stop()
            raise RDMAException(f"Failed to start HamRadioManager: {e}")
    
    async def stop(self) -> None:
        """Stop the amateur radio manager."""
        if not self.is_running:
            return
        
        self.logger.info("Stopping HamRadioManager...")
        self.is_running = False
        
        # Cancel running tasks
        for task in self._running_tasks:
            task.cancel()
        
        # Wait for tasks to complete
        if self._running_tasks:
            await asyncio.gather(*self._running_tasks, return_exceptions=True)
        
        # Close sockets
        if self.socket:
            self.socket.close()
        if self.forward_socket:
            self.forward_socket.close()
        
        self.logger.info("HamRadioManager stopped successfully")
    
    async def _main_loop(self) -> None:
        """Main processing loop for UDP packets."""
        self.logger.info("Entering main processing loop...")
        
        try:
            while self.is_running:
                try:
                    # Receive UDP packet
                    data, addr = self.socket.recvfrom(512)
                    
                    # Forward packet
                    self.forward_socket.sendto(data, ('127.0.0.1', self.udp_forward_port))
                    
                    # Process packet
                    await self._process_packet(data, addr)
                    
                except socket.timeout:
                    # Check timeouts
                    await self._check_timeouts()
                    continue
                except Exception as e:
                    self.logger.error(f"Error processing UDP packet: {e}")
                    continue
                    
        except Exception as e:
            self.logger.error(f"Error in main loop: {e}")
        finally:
            self.logger.info("Exiting main processing loop")
    
    async def _process_packet(self, data: bytes, addr: tuple) -> None:
        """Process a received UDP packet."""
        try:
            hex_data = data.hex()
            packet_type = hex_data[16:24]
            
            if packet_type == "00000002":  # Decode packet
                await self._handle_decode_packet(data, addr)
            elif packet_type == "00000000":  # Status packet
                await self._handle_status_packet(data, addr)
            elif packet_type == "00000001":  # Status packet (alternative)
                await self._handle_status_packet(data, addr)
                
        except Exception as e:
            self.logger.error(f"Error processing packet: {e}")
    
    async def _handle_decode_packet(self, data: bytes, addr: tuple) -> None:
        """Handle decode packet from radio software."""
        try:
            decode_packet = self.wsjtx_protocol.parse_decode_packet(data)
            
            # Update RX count
            self.qso_state.rx_count += 1
            
            # Parse message
            message = decode_packet.message.strip()
            parts = message.split()
            
            if len(parts) < 2:
                return
            
            # Validate callsign
            if not self.validator.validate(parts[1]):
                return
            
            # Get DXCC info
            dxcc_info = self.dxcc_db.locate_call(parts[1])
            
            # Determine QSO status
            status = self._determine_qso_status(parts, decode_packet.snr, dxcc_info)
            
            # Log the decode
            self._log_decode(decode_packet, parts, dxcc_info, status)
            
            # Handle response logic
            await self._handle_response_logic(parts, status, dxcc_info)
            
        except Exception as e:
            self.logger.error(f"Error handling decode packet: {e}")
    
    async def _handle_status_packet(self, data: bytes, addr: tuple) -> None:
        """Handle status packet from radio software."""
        try:
            # Parse status (simplified for now)
            self.logger.debug(f"Received status packet from {addr}")
            
            # Check for transmission state changes
            current_minute = datetime.utcnow().minute
            if current_minute in [0, 30]:
                # Clear exclusion list every 30 minutes
                self.qso_state.excluded_calls.clear()
                
        except Exception as e:
            self.logger.error(f"Error handling status packet: {e}")
    
    def _determine_qso_status(self, parts: List[str], snr: int, dxcc_info: Dict[str, str]) -> Dict[str, str]:
        """Determine QSO status based on various factors."""
        call = parts[1]
        
        # Check exclusion list
        if call in self.qso_state.excluded_calls:
            return {"status": "XX", "color": "red", "description": "Excluded"}
        
        # Check signal strength
        if snr <= SIGNAL_THRESHOLD:
            return {"status": "Lo", "color": "yellow", "description": "Low signal"}
        
        # Check if already worked
        if call in self.qso_state.worked_calls:
            return {"status": "--", "color": "gray", "description": "Already worked"}
        
        # Check message type
        if (parts[0] == "CQ" or parts[-1] in ["73", "RR73", "RRR"]) and not self.qso_state.sendcq:
            return {"status": ">>", "color": "green", "description": "New target"}
        
        return {"status": "   ", "color": "white", "description": "Monitoring"}
    
    def _log_decode(self, decode_packet: DecodePacket, parts: List[str], 
                   dxcc_info: Dict[str, str], status: Dict[str, str]) -> None:
        """Log decoded information."""
        time_str = datetime.utcnow().strftime("%H%M%S")
        snr_str = str(decode_packet.snr).rjust(3)
        df_str = "0".rjust(4)  # Simplified
        mode_str = decode_packet.mode.ljust(6)
        status_str = status["status"].ljust(2)
        message_str = decode_packet.message[:20].ljust(20)
        dxcc_name = dxcc_info.get('name', 'Unknown')[:20].ljust(20)
        
        log_message = f"{time_str} {snr_str} {df_str} {mode_str} {status_str} {message_str} - {dxcc_name}"
        self.logger.info(f"DECODE: {log_message}")
    
    async def _handle_response_logic(self, parts: List[str], status: Dict[str, str], 
                                   dxcc_info: Dict[str, str]) -> None:
        """Handle automatic response logic."""
        call = parts[1]
        
        # Auto-respond to new targets
        if status["status"] == ">>" and not self.qso_state.sendcq:
            self.qso_state.current_call = call
            self.qso_state.sendcq = True
            self.qso_state.tempo = int(time.time())
            self.qso_state.tempu = self.qso_state.tempo + self.timeout_seconds
            
            self.logger.info(f"Auto-responding to {call} ({dxcc_info.get('name', 'Unknown')})")
            await self._send_reply(call, self._generate_reply_message(call))
    
    def _generate_reply_message(self, call: str) -> str:
        """Generate reply message for a callsign."""
        # Simplified reply generation
        return f"{call} <my_call> <my_grid>"
    
    async def _send_reply(self, call: str, message: str) -> None:
        """Send reply message via UDP."""
        try:
            # This would need proper WSJT-X protocol implementation
            self.logger.info(f"Sending reply to {call}: {message}")
            # Implementation would create proper WSJT-X packet here
        except Exception as e:
            self.logger.error(f"Error sending reply: {e}")
    
    async def _check_timeouts(self) -> None:
        """Check for QSO timeouts."""
        if self.qso_state.sendcq and self.qso_state.tempu:
            current_time = int(time.time())
            if current_time > self.qso_state.tempu:
                self.logger.info(f"QSO timeout for {self.qso_state.current_call}")
                self.qso_state.excluded_calls.add(self.qso_state.current_call)
                self.qso_state.sendcq = False
                self.qso_state.current_call = ""
                self.qso_state.tempo = 0
                self.qso_state.tempu = 0
    
    def get_status(self) -> Dict[str, Any]:
        """Get amateur radio manager status."""
        return {
            "running": self.is_running,
            "udp_port": self.udp_port,
            "udp_forward_port": self.udp_forward_port,
            "signal_threshold": self.signal_threshold,
            "timeout_seconds": self.timeout_seconds,
            "qso_state": {
                "sendcq": self.qso_state.sendcq,
                "current_call": self.qso_state.current_call,
                "rx_count": self.qso_state.rx_count,
                "tx_count": self.qso_state.tx_count,
                "excluded_count": len(self.qso_state.excluded_calls),
                "worked_count": len(self.qso_state.worked_calls)
            },
            "log_file": str(self.log_file)
        }
    
    def get_dxcc_info(self, call: str) -> Dict[str, str]:
        """Get DXCC information for a callsign."""
        return self.dxcc_db.locate_call(call)
    
    def is_worked(self, call: str) -> bool:
        """Check if a callsign has been worked before."""
        return call.upper() in self.qso_state.worked_calls
    
    def add_worked_call(self, call: str) -> None:
        """Add a callsign to worked list."""
        self.qso_state.worked_calls.add(call.upper())
        
        # Persist to log file
        try:
            qso_data = {
                'call': call.upper(),
                'qso_date': datetime.utcnow().strftime('%Y%m%d'),
                'time_on': datetime.utcnow().strftime('%H%M%S'),
                'band': '20m',  # Would need actual band info
                'mode': 'FT8',  # Would need actual mode info
                'eor': ''
            }
            
            adif_entries = self.adif_processor.generate_adif([qso_data])
            with open(self.log_file, 'a', encoding='utf-8') as f:
                f.write(adif_entries[0] + '\n')
                
        except Exception as e:
            self.logger.error(f"Error writing to log file: {e}")


class HamRadioProtocol:
    """Protocol handler for amateur radio integration within RDMA."""
    
    def __init__(self, config: Dict[str, Any], logger: RDMALogger):
        self.config = config
        self.logger = logger
        self.manager = HamRadioManager(config, logger)
    
    async def start(self) -> None:
        """Start the amateur radio protocol handler."""
        await self.manager.start()
    
    async def stop(self) -> None:
        """Stop the amateur radio protocol handler."""
        await self.manager.stop()
    
    def get_status(self) -> Dict[str, Any]:
        """Get protocol status."""
        return {
            "protocol": "ham_radio",
            "enabled": True,
            "manager": self.manager.get_status()
        }
    
    async def execute_command(self, command: str, params: Dict[str, Any]) -> Dict[str, Any]:
        """Execute amateur radio specific commands."""
        if command == "get_dxcc_info":
            call = params.get("call")
            if call:
                return {"dxcc_info": self.manager.get_dxcc_info(call)}
            else:
                raise RDMAException("Missing callsign parameter")
        
        elif command == "is_worked":
            call = params.get("call")
            if call:
                return {"is_worked": self.manager.is_worked(call)}
            else:
                raise RDMAException("Missing callsign parameter")
        
        elif command == "get_status":
            return self.manager.get_status()
        
        else:
            raise RDMAException(f"Unknown ham radio command: {command}")
    
    async def handle_message(self, message: Dict[str, Any]) -> None:
        """Handle incoming message for amateur radio operations."""
        # This would handle messages from other RDMA components
        self.logger.debug(f"Ham radio received message: {message}")
        # Implementation would process the message and potentially trigger radio operations