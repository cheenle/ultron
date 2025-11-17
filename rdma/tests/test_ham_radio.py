"""
Tests for RDMA Amateur Radio Module
"""

import pytest
import asyncio
import tempfile
import json
from pathlib import Path
from unittest.mock import Mock, patch, MagicMock

from rdma.ham_radio import (
    ADIFProcessor, CallsignValidator, DXCCDatabase, WSJTXProtocol,
    HamRadioManager, HamRadioProtocol, QSOState, DecodePacket
)
from rdma.logging import RDMALogger
from rdma.config import LoggingConfig


class TestADIFProcessor:
    """Test ADIF processing functionality."""
    
    def test_parse_adif_basic(self):
        """Test basic ADIF parsing."""
        processor = ADIFProcessor()
        
        adif_data = '<call:4>K1ABC <gridsquare:4>FN42 <eor>'
        result = processor.parse_adif(adif_data)
        
        assert len(result) == 1
        assert result[0]['call'] == 'K1ABC'
        assert result[0]['gridsquare'] == 'FN42'
    
    def test_parse_adif_multiple_qsos(self):
        """Test parsing multiple QSOs."""
        processor = ADIFProcessor()
        
        adif_data = '''<call:4>K1ABC <gridsquare:4>FN42 <eor>
<call:4>W2DEF <gridsquare:4>FN30 <eor>
<call:4>K3GHI <gridsquare:4>EM55 <eor>
'''
        result = processor.parse_adif(adif_data)
        
        assert len(result) == 3
        assert result[0]['call'] == 'K1ABC'
        assert result[1]['call'] == 'W2DEF'
        assert result[2]['call'] == 'K3GHI'
    
    def test_parse_adif_empty(self):
        """Test parsing empty ADIF data."""
        processor = ADIFProcessor()
        
        result = processor.parse_adif('')
        assert len(result) == 0
    
    def test_generate_adif(self):
        """Test ADIF generation."""
        processor = ADIFProcessor()
        
        qsos = [
            {'call': 'K1ABC', 'gridsquare': 'FN42'},
            {'call': 'W2DEF', 'gridsquare': 'FN30'}
        ]
        
        result = processor.generate_adif(qsos)
        
        assert len(result) == 2
        assert 'K1ABC' in result[0]
        assert 'FN42' in result[0]
        assert '<eor>' in result[0]


class TestCallsignValidator:
    """Test callsign validation functionality."""
    
    def test_valid_callsigns(self):
        """Test valid callsign formats."""
        validator = CallsignValidator()
        
        valid_calls = ['K1ABC', 'W2DEF', 'VE3GHI', 'JA1XYZ', 'PY2ABC']
        
        for call in valid_calls:
            assert validator.validate(call) is True
    
    def test_invalid_callsigns(self):
        """Test invalid callsign formats."""
        validator = CallsignValidator()
        
        invalid_calls = ['INVALID', '123ABC', 'KABC', 'K12345', '']
        
        for call in invalid_calls:
            assert validator.validate(call) is False
    
    def test_extract_prefix(self):
        """Test callsign prefix extraction."""
        validator = CallsignValidator()
        
        assert validator.extract_prefix('K1ABC/P') == 'K1ABC'
        assert validator.extract_prefix('W2DEF/M') == 'W2DEF'
        assert validator.extract_prefix('VE3GHI/QRP') == 'VE3GHI'
        assert validator.extract_prefix('K1ABC') == 'K1ABC'


class TestDXCCDatabase:
    """Test DXCC database functionality."""
    
    def test_load_database(self):
        """Test database loading."""
        # Create temporary test database
        test_db = [
            {
                'id': '1',
                'licencia': 'K W N A',
                'name': 'UNITED STATES',
                'flag': 'us'
            },
            {
                'id': '110',
                'licencia': 'EA EB EC ED EE EF EG EH',
                'name': 'SPAIN',
                'flag': 'es'
            }
        ]
        
        with tempfile.NamedTemporaryFile(mode='w', suffix='.json', delete=False) as f:
            json.dump(test_db, f)
            db_path = f.name
        
        try:
            db = DXCCDatabase(db_path)
            assert len(db.database) == 2
            assert db.database[0]['id'] == '1'
            assert db.database[1]['id'] == '110'
        finally:
            Path(db_path).unlink()
    
    def test_locate_call(self):
        """Test callsign location."""
        test_db = [
            {
                'id': '1',
                'licencia': 'K W N A',
                'name': 'UNITED STATES',
                'flag': 'us'
            }
        ]
        
        with tempfile.NamedTemporaryFile(mode='w', suffix='.json', delete=False) as f:
            json.dump(test_db, f)
            db_path = f.name
        
        try:
            db = DXCCDatabase(db_path)
            
            # Test US callsign
            result = db.locate_call('K1ABC')
            assert result['id'] == '1'
            assert result['name'] == 'UNITED STATES'
            
            # Test unknown callsign
            result = db.locate_call('ZZ1ABC')
            assert result['id'] == 'unknown'
            
        finally:
            Path(db_path).unlink()
    
    def test_locate_call_with_suffix(self):
        """Test callsign location with suffixes."""
        test_db = [
            {
                'id': '1',
                'licencia': 'K W N A',
                'name': 'UNITED STATES',
                'flag': 'us'
            }
        ]
        
        with tempfile.NamedTemporaryFile(mode='w', suffix='.json', delete=False) as f:
            json.dump(test_db, f)
            db_path = f.name
        
        try:
            db = DXCCDatabase(db_path)
            
            # Test with portable suffix
            result = db.locate_call('K1ABC/P')
            assert result['id'] == '1'
            
            # Test with mobile suffix
            result = db.locate_call('K1ABC/M')
            assert result['id'] == '1'
            
        finally:
            Path(db_path).unlink()


class TestWSJTXProtocol:
    """Test WSJT-X protocol parsing."""
    
    def test_decode_mode_symbol(self):
        """Test mode symbol decoding."""
        protocol = WSJTXProtocol()
        
        assert protocol.decode_mode_symbol('~') == 'FT8'
        assert protocol.decode_mode_symbol('+') == 'FT4'
        assert protocol.decode_mode_symbol('#') == 'JT65'
        assert protocol.decode_mode_symbol('@') == 'JT9'
        assert protocol.decode_mode_symbol('`') == 'FST4'
        assert protocol.decode_mode_symbol(':') == 'Q65'
        assert protocol.decode_mode_symbol('&') == 'MSK144'
        assert protocol.decode_mode_symbol('$') == 'JT4'
        assert protocol.decode_mode_symbol('UNKNOWN') == 'UNKNOWN'


class TestHamRadioManager:
    """Test HamRadioManager functionality."""
    
    @pytest.fixture
    def logger(self):
        """Create test logger."""
        config = LoggingConfig(level="DEBUG")
        return RDMALogger(config, "test")
    
    @pytest.fixture
    def manager(self, logger):
        """Create test manager."""
        config = {
            'udp_port': 2237,
            'udp_forward_port': 2277,
            'signal_threshold': -20,
            'timeout_seconds': 90,
            'log_file': 'test_log.adi',
            'base_file': 'test_base.json'
        }
        return HamRadioManager(config, logger)
    
    @pytest.mark.asyncio
    async def test_manager_initialization(self, manager):
        """Test manager initialization."""
        assert manager.udp_port == 2237
        assert manager.signal_threshold == -20
        assert manager.timeout_seconds == 90
        assert not manager.is_running
    
    @pytest.mark.asyncio
    async def test_start_stop(self, manager):
        """Test manager start and stop."""
        # Mock socket operations
        with patch('socket.socket') as mock_socket:
            mock_sock = MagicMock()
            mock_socket.return_value = mock_sock
            
            await manager.start()
            assert manager.is_running
            
            await manager.stop()
            assert not manager.is_running
    
    def test_load_worked_calls(self, manager):
        """Test loading worked calls from log."""
        # Create test log file
        adif_content = '''<call:4>K1ABC <eor>
<call:4>W2DEF <eor>
<call:4>VE3GHI <eor>
'''
        
        with tempfile.NamedTemporaryFile(mode='w', suffix='.adi', delete=False) as f:
            f.write(adif_content)
            log_path = f.name
        
        try:
            manager.log_file = Path(log_path)
            manager._load_worked_calls()
            
            assert 'K1ABC' in manager.qso_state.worked_calls
            assert 'W2DEF' in manager.qso_state.worked_calls
            assert 'VE3GHI' in manager.qso_state.worked_calls
            
        finally:
            Path(log_path).unlink()
    
    def test_determine_qso_status(self, manager):
        """Test QSO status determination."""
        # Test excluded callsign
        manager.qso_state.excluded_calls.add('K1ABC')
        status = manager._determine_qso_status(['CQ', 'K1ABC', '73'], -15, {'id': '1'})
        assert status['status'] == 'XX'
        
        # Test low signal
        manager.qso_state.excluded_calls.clear()
        status = manager._determine_qso_status(['CQ', 'W2DEF', '73'], -25, {'id': '110'})
        assert status['status'] == 'Lo'
        
        # Test already worked
        manager.qso_state.worked_calls.add('VE3GHI')
        status = manager._determine_qso_status(['CQ', 'VE3GHI', '73'], -15, {'id': '1'})
        assert status['status'] == '--'
        
        # Test new target
        status = manager._determine_qso_status(['CQ', 'JA1XYZ', '73'], -15, {'id': '339'})
        assert status['status'] == '>>'
    
    def test_get_status(self, manager):
        """Test status reporting."""
        status = manager.get_status()
        
        assert status['running'] is False
        assert status['udp_port'] == 2237
        assert 'qso_state' in status
        assert status['qso_state']['sendcq'] is False
    
    def test_is_worked(self, manager):
        """Test worked callsign checking."""
        manager.qso_state.worked_calls.add('K1ABC')
        
        assert manager.is_worked('K1ABC') is True
        assert manager.is_worked('W2DEF') is False
        assert manager.is_worked('k1abc') is True  # Case insensitive
    
    def test_add_worked_call(self, manager):
        """Test adding worked callsign."""
        with tempfile.NamedTemporaryFile(mode='w', suffix='.adi', delete=False) as f:
            log_path = f.name
        
        try:
            manager.log_file = Path(log_path)
            
            # Add worked call
            manager.add_worked_call('K1ABC')
            
            # Check internal state
            assert 'K1ABC' in manager.qso_state.worked_calls
            
            # Check log file
            log_content = log_path.read_text()
            assert 'K1ABC' in log_content
            assert '<eor>' in log_content
            
        finally:
            Path(log_path).unlink()


class TestHamRadioProtocol:
    """Test HamRadioProtocol functionality."""
    
    @pytest.fixture
    def logger(self):
        """Create test logger."""
        config = LoggingConfig(level="DEBUG")
        return RDMALogger(config, "test")
    
    @pytest.fixture
    def protocol(self, logger):
        """Create test protocol."""
        config = {
            'udp_port': 2237,
            'udp_forward_port': 2277,
            'signal_threshold': -20,
            'timeout_seconds': 90,
            'log_file': 'test_log.adi',
            'base_file': 'test_base.json'
        }
        return HamRadioProtocol(config, logger)
    
    @pytest.mark.asyncio
    async def test_protocol_initialization(self, protocol):
        """Test protocol initialization."""
        assert protocol.config['udp_port'] == 2237
        assert protocol.config['signal_threshold'] == -20
    
    @pytest.mark.asyncio
    async def test_execute_command_get_dxcc_info(self, protocol):
        """Test DXCC info command."""
        # Mock the manager
        with patch.object(protocol.manager, 'get_dxcc_info') as mock_dxcc:
            mock_dxcc.return_value = {'id': '1', 'name': 'UNITED STATES'}
            
            result = await protocol.execute_command('get_dxcc_info', {'call': 'K1ABC'})
            
            assert 'dxcc_info' in result
            assert result['dxcc_info']['id'] == '1'
            mock_dxcc.assert_called_once_with('K1ABC')
    
    @pytest.mark.asyncio
    async def test_execute_command_is_worked(self, protocol):
        """Test is_worked command."""
        # Mock the manager
        with patch.object(protocol.manager, 'is_worked') as mock_worked:
            mock_worked.return_value = True
            
            result = await protocol.execute_command('is_worked', {'call': 'K1ABC'})
            
            assert 'is_worked' in result
            assert result['is_worked'] is True
            mock_worked.assert_called_once_with('K1ABC')
    
    @pytest.mark.asyncio
    async def test_execute_command_get_status(self, protocol):
        """Test get_status command."""
        # Mock the manager
        with patch.object(protocol.manager, 'get_status') as mock_status:
            mock_status.return_value = {'running': True, 'udp_port': 2237}
            
            result = await protocol.execute_command('get_status', {})
            
            assert 'running' in result
            assert result['running'] is True
            mock_status.assert_called_once()
    
    @pytest.mark.asyncio
    async def test_execute_command_unknown(self, protocol):
        """Test unknown command."""
        with pytest.raises(Exception):  # RDMAException
            await protocol.execute_command('unknown_command', {})


class TestIntegration:
    """Integration tests for amateur radio functionality."""
    
    @pytest.fixture
    def logger(self):
        """Create test logger."""
        config = LoggingConfig(level="DEBUG")
        return RDMALogger(config, "test")
    
    @pytest.mark.asyncio
    async def test_full_qso_flow(self, logger):
        """Test complete QSO flow."""
        config = {
            'udp_port': 2237,
            'udp_forward_port': 2277,
            'signal_threshold': -20,
            'timeout_seconds': 90,
            'log_file': 'integration_test.adi',
            'base_file': 'test_base.json'
        }
        
        manager = HamRadioManager(config, logger)
        
        # Mock socket operations
        with patch('socket.socket') as mock_socket:
            mock_sock = MagicMock()
            mock_socket.return_value = mock_sock
            
            await manager.start()
            
            # Simulate a decode packet
            # This would normally come from UDP, but we'll simulate it
            manager.qso_state.worked_calls.clear()
            
            # Test that new callsigns get proper status
            status = manager._determine_qso_status(
                ['CQ', 'JA1XYZ', '73'], 
                -15, 
                {'id': '339', 'name': 'JAPAN'}
            )
            assert status['status'] == '>>'
            
            # Test that worked callsigns get excluded status
            manager.qso_state.worked_calls.add('K1ABC')
            status = manager._determine_qso_status(
                ['CQ', 'K1ABC', '73'], 
                -15, 
                {'id': '1', 'name': 'UNITED STATES'}
            )
            assert status['status'] == '--'
            
            await manager.stop()
    
    def test_configuration_validation(self):
        """Test configuration validation."""
        # Valid configuration
        config = {
            'udp_port': 2237,
            'udp_forward_port': 2277,
            'signal_threshold': -20,
            'timeout_seconds': 90,
            'log_file': 'test.adi',
            'base_file': 'base.json'
        }
        
        # Should not raise exceptions
        manager = HamRadioManager(config, Mock())
        assert manager.udp_port == 2237
        
        # Invalid port
        config['udp_port'] = 99999
        with pytest.raises(ValueError):
            manager = HamRadioManager(config, Mock())
    
    @pytest.mark.asyncio
    async def test_error_handling(self, logger):
        """Test error handling."""
        config = {
            'udp_port': 2237,
            'udp_forward_port': 2277,
            'signal_threshold': -20,
            'timeout_seconds': 90,
            'log_file': 'test.adi',
            'base_file': 'test_base.json'
        }
        
        manager = HamRadioManager(config, logger)
        
        # Test with invalid database file
        manager.dxcc_db.database = []  # Empty database
        
        # Should handle gracefully
        result = manager.dxcc_db.locate_call('INVALID')
        assert result['id'] == 'unknown'
        
        # Test with malformed ADIF data
        processor = ADIFProcessor()
        result = processor.parse_adif('invalid data')
        assert len(result) == 0  # Should return empty list, not crash


if __name__ == "__main__":
    pytest.main([__file__])