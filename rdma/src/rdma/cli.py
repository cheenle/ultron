"""
RDMA Command Line Interface

Provides command-line access to RDMA functionality including amateur radio features.
"""

import asyncio
import sys
import json
from pathlib import Path
from typing import Optional, Dict, Any

import click
from rich.console import Console
from rich.table import Table
from rich.panel import Panel
from rich.text import Text
from rich.prompt import Prompt, Confirm
from rich.progress import Progress, SpinnerColumn, TextColumn

from .agent import RDMAgent
from .config import ConfigManager
from .logging import RDMALogger
from .exceptions import RDMAException
from ._version import __version__


console = Console()


@click.group()
@click.version_option(version=__version__, prog_name="rdma")
@click.option("--config", "-c", type=click.Path(exists=True), help="Configuration file path")
@click.option("--verbose", "-v", is_flag=True, help="Enable verbose logging")
@click.option("--quiet", "-q", is_flag=True, help="Suppress output")
@click.pass_context
def cli(ctx: click.Context, config: Optional[str], verbose: bool, quiet: bool) -> None:
    """RDMA - Remote Digital Management Agent"""
    ctx.ensure_object(dict)
    ctx.obj["config_path"] = config
    ctx.obj["verbose"] = verbose
    ctx.obj["quiet"] = quiet


@cli.group()
@click.pass_context
def ham(ctx: click.Context) -> None:
    """Amateur radio (ham radio) management commands"""
    pass


@ham.command()
@click.option("--port", "-p", type=int, default=2237, help="UDP port to listen on")
@click.option("--forward-port", "-f", type=int, default=2277, help="UDP port to forward to")
@click.option("--signal-threshold", "-s", type=int, default=-20, help="Signal strength threshold in dB")
@click.option("--timeout", "-t", type=int, default=90, help="QSO timeout in seconds")
@click.option("--log-file", "-l", type=click.Path(), default="wsjtx_log.adi", help="ADIF log file path")
@click.option("--daemon", "-d", is_flag=True, help="Run as daemon")
@click.pass_context
def start(ctx: click.Context, port: int, forward_port: int, signal_threshold: int, 
          timeout: int, log_file: str, daemon: bool) -> None:
    """Start the amateur radio manager"""
    
    if not ctx.obj.get("quiet"):
        console.print(Panel.fit(
            Text("RDMA Amateur Radio Manager", style="bold cyan"),
            subtitle="Starting..."
        ))
    
    # Create configuration
    config = {
        "ham_radio": {
            "enabled": True,
            "udp_port": port,
            "udp_forward_port": forward_port,
            "signal_threshold": signal_threshold,
            "timeout_seconds": timeout,
            "log_file": log_file,
            "base_file": "base.json"
        }
    }
    
    async def _run_manager():
        try:
            # Create agent with ham radio configuration
            agent = RDMAgent()
            
            # Start the agent
            await agent.start()
            
            if not ctx.obj.get("quiet"):
                console.print(f"[green]Amateur radio manager started successfully[/green]")
                console.print(f"Listening on UDP port: {port}")
                console.print(f"Forwarding to port: {forward_port}")
                console.print(f"Signal threshold: {signal_threshold} dB")
                console.print(f"QSO timeout: {timeout} seconds")
                console.print(f"Log file: {log_file}")
                console.print("\n[yellow]Press Ctrl+C to stop[/yellow]")
            
            # Keep running
            try:
                while True:
                    await asyncio.sleep(1)
            except KeyboardInterrupt:
                if not ctx.obj.get("quiet"):
                    console.print("\n[yellow]Shutting down...[/yellow]")
                await agent.stop()
                
        except RDMAException as e:
            console.print(f"[red]Error: {e}[/red]")
            sys.exit(1)
        except Exception as e:
            console.print(f"[red]Unexpected error: {e}[/red]")
            sys.exit(1)
    
    try:
        asyncio.run(_run_manager())
    except KeyboardInterrupt:
        pass


@ham.command()
@click.argument("callsign")
@click.pass_context
def dxcc(ctx: click.Context, callsign: str) -> None:
    """Get DXCC information for a callsign"""
    
    async def _get_dxcc():
        try:
            agent = RDMAgent()
            
            # Get ham radio manager
            ham_manager = agent.protocol_manager.get_protocol("ham_radio")
            if not ham_manager:
                console.print("[red]Ham radio module not available[/red]")
                return
            
            # Execute command
            result = await ham_manager.execute_command("get_dxcc_info", {"call": callsign})
            dxcc_info = result["dxcc_info"]
            
            # Display results
            table = Table(title=f"DXCC Information for {callsign}")
            table.add_column("Field", style="cyan")
            table.add_column("Value", style="green")
            
            table.add_row("Callsign", callsign.upper())
            table.add_row("DXCC ID", dxcc_info.get("id", "Unknown"))
            table.add_row("Entity Name", dxcc_info.get("name", "Unknown"))
            table.add_row("Flag", dxcc_info.get("flag", "Unknown"))
            
            console.print(table)
            
        except RDMAException as e:
            console.print(f"[red]Error: {e}[/red]")
        except Exception as e:
            console.print(f"[red]Unexpected error: {e}[/red]")
    
    asyncio.run(_get_dxcc())


@ham.command()
@click.argument("callsign")
@click.pass_context
def worked(ctx: click.Context, callsign: str) -> None:
    """Check if a callsign has been worked before"""
    
    async def _check_worked():
        try:
            agent = RDMAgent()
            
            # Get ham radio manager
            ham_manager = agent.protocol_manager.get_protocol("ham_radio")
            if not ham_manager:
                console.print("[red]Ham radio module not available[/red]")
                return
            
            # Execute command
            result = await ham_manager.execute_command("is_worked", {"call": callsign})
            is_worked = result["is_worked"]
            
            if is_worked:
                console.print(f"[green]✅ {callsign} has been worked before[/green]")
            else:
                console.print(f"[yellow]❌ {callsign} has not been worked before[/yellow]")
                
        except RDMAException as e:
            console.print(f"[red]Error: {e}[/red]")
        except Exception as e:
            console.print(f"[red]Unexpected error: {e}[/red]")
    
    asyncio.run(_check_worked())


@ham.command()
@click.pass_context
def status(ctx: click.Context) -> None:
    """Show amateur radio manager status"""
    
    async def _show_status():
        try:
            agent = RDMAgent()
            
            # Get ham radio manager
            ham_manager = agent.protocol_manager.get_protocol("ham_radio")
            if not ham_manager:
                console.print("[red]Ham radio module not available[/red]")
                return
            
            # Execute command
            result = await ham_manager.execute_command("get_status", {})
            status = result["manager"]
            
            # Display status
            console.print(Panel.fit(
                Text("Amateur Radio Manager Status", style="bold cyan"),
                subtitle="Current State"
            ))
            
            # Basic info
            console.print(f"[cyan]Running:[/cyan] {'Yes' if status['running'] else 'No'}")
            console.print(f"[cyan]UDP Port:[/cyan] {status['udp_port']}")
            console.print(f"[cyan]Forward Port:[/cyan] {status['udp_forward_port']}")
            console.print(f"[cyan]Signal Threshold:[/cyan] {status['signal_threshold']} dB")
            console.print(f"[cyan]Timeout:[/cyan] {status['timeout_seconds']} seconds")
            console.print(f"[cyan]Log File:[/cyan] {status['log_file']}")
            
            # QSO State
            qso_state = status['qso_state']
            console.print(f"\n[cyan]QSO State:[/cyan]")
            console.print(f"  Auto CQ: {'Yes' if qso_state['sendcq'] else 'No'}")
            console.print(f"  Current Call: {qso_state['current_call'] or 'None'}")
            console.print(f"  RX Count: {qso_state['rx_count']}")
            console.print(f"  TX Count: {qso_state['tx_count']}")
            console.print(f"  Excluded: {qso_state['excluded_count']}")
            console.print(f"  Worked: {qso_state['worked_count']}")
            
        except RDMAException as e:
            console.print(f"[red]Error: {e}[/red]")
        except Exception as e:
            console.print(f"[red]Unexpected error: {e}[/red]")
    
    asyncio.run(_show_status())


@ham.command()
@click.pass_context
def monitor(ctx: click.Context) -> None:
    """Monitor real-time amateur radio activity"""
    
    console.print(Panel.fit(
        Text("Amateur Radio Monitor", style="bold cyan"),
        subtitle="Real-time Activity"
    ))
    
    console.print("[yellow]Monitoring amateur radio activity...[/yellow]")
    console.print("[dim]Press Ctrl+C to stop[/dim]\n")
    
    try:
        # This would connect to the running agent and display real-time data
        # For now, show a placeholder
        with Progress(
            SpinnerColumn(),
            TextColumn("[progress.description]{task.description}"),
            console=console,
        ) as progress:
            task = progress.add_task("Monitoring...", total=None)
            
            try:
                while True:
                    # Simulate monitoring
                    progress.update(task, description="Listening for decodes...")
                    time.sleep(2)
                    
                    # In a real implementation, this would show actual decodes
                    console.print("[dim]Waiting for radio activity...[/dim]")
                    
            except KeyboardInterrupt:
                progress.update(task, description="Stopping monitor...")
                
    except KeyboardInterrupt:
        console.print("\n[yellow]Monitor stopped[/yellow]")


@ham.command()
@click.argument("callsign")
@click.pass_context
def log(ctx: click.Context, callsign: str) -> None:
    """Manually log a QSO with a callsign"""
    
    if not Confirm.ask(f"Log QSO with {callsign.upper()}?"):
        return
    
    async def _log_qso():
        try:
            agent = RDMAgent()
            
            # Get ham radio manager
            ham_manager = agent.protocol_manager.get_protocol("ham_radio")
            if not ham_manager:
                console.print("[red]Ham radio module not available[/red]")
                return
            
            # Add to worked calls
            ham_manager.manager.add_worked_call(callsign.upper())
            
            console.print(f"[green]✅ QSO with {callsign.upper()} logged successfully[/green]")
            
        except RDMAException as e:
            console.print(f"[red]Error: {e}[/red]")
        except Exception as e:
            console.print(f"[red]Unexpected error: {e}[/red]")
    
    asyncio.run(_log_qso())


@cli.command()
@click.option("--config", "-c", type=click.Path(), help="Configuration file path")
@click.option("--validate", is_flag=True, help="Validate configuration")
@click.pass_context
def config(ctx: click.Context, config_path: Optional[str], validate: bool) -> None:
    """Configuration management commands"""
    
    try:
        config_manager = ConfigManager(config_path)
        
        if validate:
            # Validate configuration
            try:
                config = config_manager.load_config()
                console.print("[green]✅ Configuration is valid[/green]")
                
                # Show configuration summary
                console.print("\n[cyan]Configuration Summary:[/cyan]")
                console.print(f"  Agent ID: {config.get('agent_id', 'default')}")
                console.print(f"  Protocols: {len(config.get('protocols', {}))}")
                console.print(f"  Monitoring: {'enabled' if config.get('monitoring', {}).get('enabled') else 'disabled'}")
                
            except Exception as e:
                console.print(f"[red]❌ Configuration validation failed: {e}[/red]")
                sys.exit(1)
        else:
            # Show current configuration
            config = config_manager.get_config()
            console.print(Panel(
                json.dumps(config.dict() if hasattr(config, 'dict') else str(config), 
                           indent=2, default=str),
                title="Current Configuration",
                border_style="cyan"
            ))
            
    except RDMAException as e:
        console.print(f"[red]Error: {e}[/red]")
        sys.exit(1)


@cli.command()
@click.pass_context
def version(ctx: click.Context) -> None:
    """Show version information"""
    
    console.print(Panel.fit(
        Text(f"RDMA Version {__version__}", style="bold cyan"),
        subtitle="Remote Digital Management Agent"
    ))
    
    console.print(f"[dim]Python: {sys.version}[/dim]")
    console.print(f"[dim]Platform: {sys.platform}[/dim]")


def main():
    """Main entry point for the CLI."""
    try:
        cli()
    except KeyboardInterrupt:
        console.print("\n[yellow]Interrupted by user[/yellow]")
        sys.exit(130)
    except Exception as e:
        console.print(f"\n[red]Fatal error: {e}[/red]")
        sys.exit(1)


if __name__ == "__main__":
    main()