#!/usr/bin/env python3
"""
RDMA Amateur Radio Example

This example demonstrates how to use the RDMA amateur radio functionality
for automated QSO management with JTDX/WSJT-X/MSHV.
"""

import asyncio
import json
from pathlib import Path

from rdma import RDMAgent
from rdma.config import Config, HamRadioConfig
from rdma.logging import RDMALogger, LoggingConfig


async def main():
    """Main example function."""
    print("RDMA Amateur Radio Example")
    print("=" * 50)
    
    # Create configuration
    config = Config(
        agent_id="ham-radio-example",
        ham_radio=HamRadioConfig(
            enabled=True,
            udp_port=2237,
            udp_forward_port=2277,
            signal_threshold=-20,
            timeout_seconds=90,
            log_file="example_log.adi",
            base_file="base.json",
            auto_cq=True,
            dxcc_whitelist_only=False,
            dxcc_whitelist={
                "1": "UNITED STATES",
                "110": "SPAIN",
                "284": "BULGARIA"
            }
        ),
        logging=LoggingConfig(
            level="INFO",
            console=True
        )
    )
    
    # Create agent
    agent = RDMAgent()
    
    try:
        # Start the agent
        print("Starting RDMA agent...")
        await agent.start()
        print("✅ RDMA agent started successfully")
        
        # Get ham radio manager
        ham_manager = agent.protocol_manager.get_protocol("ham_radio")
        if not ham_manager:
            print("❌ Ham radio module not available")
            return
        
        print("\n📡 Amateur Radio Features Demo")
        print("-" * 30)
        
        # Demonstrate DXCC lookup
        print("\n1. DXCC Information Lookup")
        print("-" * 25)
        
        test_callsigns = ["K1ABC", "W2DEF", "VE3GHI", "JA1XYZ"]
        
        for callsign in test_callsigns:
            try:
                result = await ham_manager.execute_command(
                    "get_dxcc_info", 
                    {"call": callsign}
                )
                dxcc_info = result["dxcc_info"]
                print(f"  {callsign}: {dxcc_info['name']} (ID: {dxcc_info['id']})")
            except Exception as e:
                print(f"  {callsign}: Error - {e}")
        
        # Demonstrate worked callsign checking
        print("\n2. Worked Callsign Check")
        print("-" * 25)
        
        for callsign in test_callsigns:
            try:
                result = await ham_manager.execute_command(
                    "is_worked", 
                    {"call": callsign}
                )
                worked = result["is_worked"]
                status = "✅ Worked" if worked else "❌ Not worked"
                print(f"  {callsign}: {status}")
            except Exception as e:
                print(f"  {callsign}: Error - {e}")
        
        # Show current status
        print("\n3. Current Status")
        print("-" * 25)
        
        try:
            result = await ham_manager.execute_command("get_status", {})
            status = result["manager"]
            
            print(f"  Running: {status['running']}")
            print(f"  UDP Port: {status['udp_port']}")
            print(f"  Signal Threshold: {status['signal_threshold']} dB")
            print(f"  QSOs RX: {status['qso_state']['rx_count']}")
            print(f"  Excluded Calls: {status['qso_state']['excluded_count']}")
            print(f"  Worked Calls: {status['qso_state']['worked_count']}")
            
        except Exception as e:
            print(f"  Status Error: {e}")
        
        # Simulate logging a QSO
        print("\n4. Manual QSO Logging")
        print("-" * 25)
        
        test_qso = "PY2ABC"  # Brazil
        print(f"  Logging QSO with {test_qso}...")
        
        try:
            ham_manager.manager.add_worked_call(test_qso)
            print(f"  ✅ QSO with {test_qso} logged successfully")
            
            # Verify it was logged
            result = await ham_manager.execute_command("is_worked", {"call": test_qso})
            if result["is_worked"]:
                print(f"  ✅ Confirmed: {test_qso} is now marked as worked")
            
        except Exception as e:
            print(f"  ❌ Error logging QSO: {e}")
        
        # Demonstrate configuration validation
        print("\n5. Configuration Validation")
        print("-" * 30)
        
        try:
            issues = agent.config_manager.validate_config()
            if issues:
                print("  ⚠️  Configuration issues found:")
                for issue in issues:
                    print(f"    - {issue}")
            else:
                print("  ✅ Configuration is valid")
                
        except Exception as e:
            print(f"  ❌ Configuration validation error: {e}")
        
        # Keep running for a while to demonstrate monitoring
        print("\n6. Running for monitoring...")
        print("-" * 30)
        print("  The agent is now running and monitoring UDP port 2237")
        print("  Configure your JTDX/WSJT-X to forward UDP packets to this port")
        print("  Press Ctrl+C to stop")
        
        try:
            # Keep running
            while True:
                await asyncio.sleep(1)
                
        except KeyboardInterrupt:
            print("\n\nStopping agent...")
            
    except KeyboardInterrupt:
        print("\nInterrupted by user")
        
    finally:
        # Stop the agent
        print("Stopping RDMA agent...")
        await agent.stop()
        print("✅ RDMA agent stopped")


async def advanced_example():
    """Advanced example with custom configuration and monitoring."""
    print("\n" + "=" * 50)
    print("Advanced Amateur Radio Example")
    print("=" * 50)
    
    # Create custom configuration
    config = Config(
        agent_id="advanced-ham-example",
        instance_name="advanced",
        ham_radio=HamRadioConfig(
            enabled=True,
            udp_port=2238,  # Different port
            udp_forward_port=2278,
            signal_threshold=-18,  # Higher threshold
            timeout_seconds=120,  # Longer timeout
            log_file="advanced_log.adi",
            base_file="base.json",
            auto_cq=True,
            dxcc_whitelist_only=True,  # Strict whitelist mode
            dxcc_whitelist={
                "1": "UNITED STATES",
                "110": "SPAIN",
                "284": "BULGARIA",
                "206": "HUNGARY",
                "280": "GERMANY"
            }
        ),
        monitoring=MonitoringConfig(
            enabled=True,
            interval=30,  # More frequent monitoring
            metrics_enabled=True,
            health_checks=["cpu", "memory", "disk", "network"],
            thresholds={
                "cpu_percent": 70.0,
                "memory_percent": 80.0,
                "disk_percent": 85.0
            }
        ),
        logging=LoggingConfig(
            level="DEBUG",  # More verbose logging
            file="logs/advanced.log",
            console=True
        )
    )
    
    # Create agent
    agent = RDMAgent()
    
    try:
        print("Starting advanced RDMA configuration...")
        await agent.start()
        
        ham_manager = agent.protocol_manager.get_protocol("ham_radio")
        if not ham_manager:
            print("❌ Ham radio module not available")
            return
        
        print("\n🔧 Advanced Features")
        print("-" * 20)
        
        # Get detailed status
        result = await ham_manager.execute_command("get_status", {})
        status = result["manager"]
        
        print(f"Configuration:")
        print(f"  Whitelist Only Mode: {status['qso_state']['sendcq']}")
        print(f"  Auto CQ Enabled: {config.ham_radio.auto_cq}")
        print(f"  Timeout: {status['timeout_seconds']}s")
        print(f"  Signal Threshold: {status['signal_threshold']}dB")
        
        # Monitor for a short time
        print("\nMonitoring for 30 seconds...")
        start_time = asyncio.get_event_loop().time()
        
        while asyncio.get_event_loop().time() - start_time < 30:
            # Get current metrics
            try:
                metrics_result = await agent.metrics_collector.get_current_metrics()
                cpu_percent = metrics_result['system']['cpu_percent']
                memory_percent = metrics_result['system']['memory_percent']
                
                print(f"\r  CPU: {cpu_percent:5.1f}% | Memory: {memory_percent:5.1f}% | "
                      f"RX: {status['qso_state']['rx_count']}", end='', flush=True)
                
            except Exception as e:
                print(f"\r  Metrics error: {e}", end='', flush=True)
            
            await asyncio.sleep(1)
        
        print("\n✅ Advanced monitoring complete")
        
    except KeyboardInterrupt:
        print("\nInterrupted by user")
        
    finally:
        await agent.stop()
        print("✅ Advanced example completed")


if __name__ == "__main__":
    # Run basic example
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        print("\nBasic example interrupted")
    
    # Optionally run advanced example
    # try:
    #     asyncio.run(advanced_example())
    # except KeyboardInterrupt:
    #     print("\nAdvanced example interrupted")"file_path":"/Users/cheenle/ultron/ultron-main/rdma/examples/ham_radio_example.py"}