"""
RDMA Protocol Management Module

Handles various remote management protocols including MQTT, HTTP, WebSocket, etc.
"""

import asyncio
import json
import ssl
from abc import ABC, abstractmethod
from typing import Dict, Any, Optional, Callable, List, Union
from dataclasses import dataclass
import aiohttp
import aiofiles
from aiohttp import web
import asyncio_mqtt as aiomqtt
import websockets
from websockets.server import serve
from websockets.exceptions import WebSocketException

from .exceptions import ProtocolError, ConnectionError, AuthenticationError
from .logging import RDMALogger


@dataclass
class ProtocolConfig:
    """Protocol configuration data class."""
    type: str
    enabled: bool = True
    host: str = "localhost"
    port: int = 8080
    ssl: bool = False
    auth: Optional[Dict[str, Any]] = None
    options: Optional[Dict[str, Any]] = None


class ProtocolBase(ABC):
    """Base class for all protocol implementations."""
    
    def __init__(self, config: ProtocolConfig, logger: RDMALogger):
        self.config = config
        self.logger = logger
        self.is_connected = False
        self._connection = None
        self._message_handlers: List[Callable] = []
    
    @abstractmethod
    async def connect(self) -> None:
        """Connect to the protocol endpoint."""
        pass
    
    @abstractmethod
    async def disconnect(self) -> None:
        """Disconnect from the protocol endpoint."""
        pass
    
    @abstractmethod
    async def send_message(self, message: Dict[str, Any], target: str = None) -> None:
        """Send a message through the protocol."""
        pass
    
    @abstractmethod
    async def receive_message(self) -> Optional[Dict[str, Any]]:
        """Receive a message from the protocol."""
        pass
    
    def add_message_handler(self, handler: Callable) -> None:
        """Add a message handler."""
        self._message_handlers.append(handler)
    
    def remove_message_handler(self, handler: Callable) -> None:
        """Remove a message handler."""
        if handler in self._message_handlers:
            self._message_handlers.remove(handler)
    
    async def _notify_handlers(self, message: Dict[str, Any]) -> None:
        """Notify all registered message handlers."""
        for handler in self._message_handlers:
            try:
                if asyncio.iscoroutinefunction(handler):
                    await handler(message)
                else:
                    handler(message)
            except Exception as e:
                self.logger.error(f"Error in message handler: {e}")
    
    def get_status(self) -> Dict[str, Any]:
        """Get protocol status information."""
        return {
            "type": self.config.type,
            "connected": self.is_connected,
            "host": self.config.host,
            "port": self.config.port,
            "ssl": self.config.ssl
        }


class MQTTProtocol(ProtocolBase):
    """MQTT protocol implementation."""
    
    def __init__(self, config: ProtocolConfig, logger: RDMALogger):
        super().__init__(config, logger)
        self.client: Optional[aiomqtt.Client] = None
        self._subscription_tasks = []
    
    async def connect(self) -> None:
        """Connect to MQTT broker."""
        try:
            self.logger.info(f"Connecting to MQTT broker at {self.config.host}:{self.config.port}")
            
            # Build client parameters
            client_params = {
                "hostname": self.config.host,
                "port": self.config.port,
                "client_id": self.config.options.get("client_id", "rdma-agent") if self.config.options else "rdma-agent"
            }
            
            # Add authentication if configured
            if self.config.auth:
                client_params["username"] = self.config.auth.get("username")
                client_params["password"] = self.config.auth.get("password")
            
            # Add SSL configuration if enabled
            if self.config.ssl:
                tls_params = self.config.options.get("tls", {}) if self.config.options else {}
                client_params["tls_context"] = self._create_ssl_context(tls_params)
            
            # Create and connect client
            self.client = aiomqtt.Client(**client_params)
            await self.client.connect()
            
            self.is_connected = True
            self.logger.info("Connected to MQTT broker successfully")
            
            # Subscribe to default topics
            await self._subscribe_default_topics()
            
            # Start message receiver task
            asyncio.create_task(self._message_receiver())
            
        except Exception as e:
            self.logger.error(f"Failed to connect to MQTT broker: {e}")
            raise ConnectionError(f"MQTT connection failed: {e}")
    
    async def disconnect(self) -> None:
        """Disconnect from MQTT broker."""
        if self.client:
            try:
                await self.client.disconnect()
                self.is_connected = False
                self.logger.info("Disconnected from MQTT broker")
            except Exception as e:
                self.logger.error(f"Error disconnecting from MQTT broker: {e}")
    
    async def send_message(self, message: Dict[str, Any], target: str = None) -> None:
        """Send message via MQTT."""
        if not self.is_connected or not self.client:
            raise ProtocolError("Not connected to MQTT broker")
        
        topic = target or self.config.options.get("publish_topic", "rdma/commands") if self.config.options else "rdma/commands"
        
        try:
            payload = json.dumps(message).encode('utf-8')
            await self.client.publish(topic, payload)
            self.logger.debug(f"Published message to topic {topic}: {message}")
        except Exception as e:
            self.logger.error(f"Error publishing MQTT message: {e}")
            raise ProtocolError(f"Failed to publish MQTT message: {e}")
    
    async def receive_message(self) -> Optional[Dict[str, Any]]:
        """Receive message from MQTT (non-blocking)."""
        # Messages are handled asynchronously via _message_receiver
        return None
    
    async def _subscribe_default_topics(self) -> None:
        """Subscribe to default MQTT topics."""
        topics = self.config.options.get("subscribe_topics", ["rdma/requests"]) if self.config.options else ["rdma/requests"]
        
        for topic in topics:
            try:
                await self.client.subscribe(topic)
                self.logger.info(f"Subscribed to topic: {topic}")
            except Exception as e:
                self.logger.error(f"Error subscribing to topic {topic}: {e}")
    
    async def _message_receiver(self) -> None:
        """Background task to receive MQTT messages."""
        try:
            async for message in self.client.messages:
                try:
                    payload = json.loads(message.payload.decode('utf-8'))
                    self.logger.debug(f"Received MQTT message on topic {message.topic}: {payload}")
                    await self._notify_handlers(payload)
                except json.JSONDecodeError as e:
                    self.logger.error(f"Invalid JSON in MQTT message: {e}")
                except Exception as e:
                    self.logger.error(f"Error processing MQTT message: {e}")
        except Exception as e:
            self.logger.error(f"Error in MQTT message receiver: {e}")
    
    def _create_ssl_context(self, tls_params: Dict[str, Any]) -> ssl.SSLContext:
        """Create SSL context for MQTT connection."""
        context = ssl.create_default_context()
        
        if tls_params.get("ca_certs"):
            context.load_verify_locations(tls_params["ca_certs"])
        
        if tls_params.get("certfile") and tls_params.get("keyfile"):
            context.load_cert_chain(tls_params["certfile"], tls_params["keyfile"])
        
        return context


class HTTPProtocol(ProtocolBase):
    """HTTP/HTTPS protocol implementation."""
    
    def __init__(self, config: ProtocolConfig, logger: RDMALogger):
        super().__init__(config, logger)
        self.app: Optional[web.Application] = None
        self.runner: Optional[web.AppRunner] = None
        self.site: Optional[web.TCPSite] = None
        self._session: Optional[aiohttp.ClientSession] = None
    
    async def connect(self) -> None:
        """Start HTTP server."""
        try:
            self.logger.info(f"Starting HTTP server on {self.config.host}:{self.config.port}")
            
            # Create aiohttp application
            self.app = web.Application()
            self._setup_routes()
            
            # Create app runner
            self.runner = web.AppRunner(self.app)
            await self.runner.setup()
            
            # Create site (server)
            ssl_context = None
            if self.config.ssl:
                ssl_context = self._create_ssl_context()
            
            self.site = web.TCPSite(
                self.runner,
                self.config.host,
                self.config.port,
                ssl_context=ssl_context
            )
            
            await self.site.start()
            self.is_connected = True
            
            self.logger.info(f"HTTP server started on {self.config.host}:{self.config.port}")
            
            # Create client session for outgoing requests
            self._session = aiohttp.ClientSession()
            
        except Exception as e:
            self.logger.error(f"Failed to start HTTP server: {e}")
            raise ConnectionError(f"HTTP server start failed: {e}")
    
    async def disconnect(self) -> None:
        """Stop HTTP server."""
        try:
            if self.site:
                await self.site.stop()
            if self.runner:
                await self.runner.cleanup()
            if self._session:
                await self._session.close()
            
            self.is_connected = False
            self.logger.info("HTTP server stopped")
            
        except Exception as e:
            self.logger.error(f"Error stopping HTTP server: {e}")
    
    async def send_message(self, message: Dict[str, Any], target: str = None) -> None:
        """Send HTTP request."""
        if not self._session:
            raise ProtocolError("HTTP client session not available")
        
        url = target or f"http://{self.config.host}:{self.config.port}/api/message"
        
        try:
            async with self._session.post(url, json=message) as response:
                if response.status != 200:
                    raise ProtocolError(f"HTTP request failed with status {response.status}")
                self.logger.debug(f"HTTP message sent to {url}")
        except Exception as e:
            self.logger.error(f"Error sending HTTP message: {e}")
            raise ProtocolError(f"Failed to send HTTP message: {e}")
    
    async def receive_message(self) -> Optional[Dict[str, Any]]:
        """Receive message (handled via HTTP endpoints)."""
        return None
    
    def _setup_routes(self) -> None:
        """Set up HTTP routes."""
        # Health check endpoint
        self.app.router.add_get('/health', self._health_handler)
        
        # Message endpoint
        self.app.router.add_post('/api/message', self._message_handler)
        
        # Status endpoint
        self.app.router.add_get('/api/status', self._status_handler)
        
        # Metrics endpoint
        self.app.router.add_get('/api/metrics', self._metrics_handler)
    
    async def _health_handler(self, request: web.Request) -> web.Response:
        """Health check handler."""
        return web.json_response({"status": "healthy", "protocol": "http"})
    
    async def _message_handler(self, request: web.Request) -> web.Response:
        """Message handler."""
        try:
            data = await request.json()
            self.logger.debug(f"Received HTTP message: {data}")
            await self._notify_handlers(data)
            return web.json_response({"status": "received"})
        except Exception as e:
            self.logger.error(f"Error handling HTTP message: {e}")
            return web.json_response({"error": str(e)}, status=400)
    
    async def _status_handler(self, request: web.Request) -> web.Response:
        """Status handler."""
        status = self.get_status()
        return web.json_response(status)
    
    async def _metrics_handler(self, request: web.Request) -> web.Response:
        """Metrics handler."""
        # This would integrate with metrics collector
        return web.json_response({"metrics": "available"})
    
    def _create_ssl_context(self) -> ssl.SSLContext:
        """Create SSL context for HTTPS."""
        context = ssl.create_default_context(ssl.Purpose.CLIENT_AUTH)
        
        if self.config.options:
            ssl_options = self.config.options.get("ssl", {})
            
            if ssl_options.get("certfile") and ssl_options.get("keyfile"):
                context.load_cert_chain(ssl_options["certfile"], ssl_options["keyfile"])
            
            if ssl_options.get("ca_certs"):
                context.load_verify_locations(ssl_options["ca_certs"])
        
        return context


class WebSocketProtocol(ProtocolBase):
    """WebSocket protocol implementation."""
    
    def __init__(self, config: ProtocolConfig, logger: RDMALogger):
        super().__init__(config, logger)
        self.server = None
        self.websockets: set = set()
        self._message_queue: asyncio.Queue = asyncio.Queue()
    
    async def connect(self) -> None:
        """Start WebSocket server."""
        try:
            self.logger.info(f"Starting WebSocket server on {self.config.host}:{self.config.port}")
            
            # Start WebSocket server
            self.server = await serve(
                self._websocket_handler,
                self.config.host,
                self.config.port,
                ssl=self._create_ssl_context() if self.config.ssl else None
            )
            
            self.is_connected = True
            self.logger.info(f"WebSocket server started on {self.config.host}:{self.config.port}")
            
            # Keep server running
            await self.server.wait_closed()
            
        except Exception as e:
            self.logger.error(f"Failed to start WebSocket server: {e}")
            raise ConnectionError(f"WebSocket server start failed: {e}")
    
    async def disconnect(self) -> None:
        """Stop WebSocket server."""
        if self.server:
            self.server.close()
            await self.server.wait_closed()
            
            # Close all active connections
            for ws in self.websockets.copy():
                await ws.close()
            
            self.is_connected = False
            self.logger.info("WebSocket server stopped")
    
    async def send_message(self, message: Dict[str, Any], target: str = None) -> None:
        """Send message via WebSocket."""
        if not self.is_connected:
            raise ProtocolError("WebSocket server not running")
        
        if not self.websockets:
            self.logger.warning("No active WebSocket connections to send message")
            return
        
        try:
            message_json = json.dumps(message)
            
            # Send to all connected clients or specific target
            if target:
                # Find specific websocket by some identifier
                for ws in self.websockets:
                    # Add logic to identify specific websocket
                    await ws.send(message_json)
            else:
                # Broadcast to all connected clients
                disconnected = []
                for ws in self.websockets:
                    try:
                        await ws.send(message_json)
                    except websockets.exceptions.ConnectionClosed:
                        disconnected.append(ws)
                
                # Remove disconnected clients
                for ws in disconnected:
                    self.websockets.discard(ws)
            
            self.logger.debug(f"WebSocket message sent: {message}")
            
        except Exception as e:
            self.logger.error(f"Error sending WebSocket message: {e}")
            raise ProtocolError(f"Failed to send WebSocket message: {e}")
    
    async def receive_message(self) -> Optional[Dict[str, Any]]:
        """Receive message from WebSocket queue."""
        try:
            return await asyncio.wait_for(self._message_queue.get(), timeout=0.1)
        except asyncio.TimeoutError:
            return None
    
    async def _websocket_handler(self, websocket, path: str) -> None:
        """Handle WebSocket connections."""
        self.websockets.add(websocket)
        client_info = f"{websocket.remote_address[0]}:{websocket.remote_address[1]}"
        self.logger.info(f"WebSocket client connected: {client_info}")
        
        try:
            async for message in websocket:
                try:
                    data = json.loads(message)
                    self.logger.debug(f"Received WebSocket message from {client_info}: {data}")
                    
                    # Add to message queue and notify handlers
                    await self._message_queue.put(data)
                    await self._notify_handlers(data)
                    
                except json.JSONDecodeError as e:
                    self.logger.error(f"Invalid JSON from WebSocket client {client_info}: {e}")
                    await websocket.send(json.dumps({"error": "Invalid JSON"}))
                except Exception as e:
                    self.logger.error(f"Error processing WebSocket message from {client_info}: {e}")
                    
        except websockets.exceptions.ConnectionClosed:
            self.logger.info(f"WebSocket client disconnected: {client_info}")
        except Exception as e:
            self.logger.error(f"WebSocket handler error for {client_info}: {e}")
        finally:
            self.websockets.discard(websocket)
    
    def _create_ssl_context(self) -> ssl.SSLContext:
        """Create SSL context for WSS."""
        context = ssl.create_default_context(ssl.Purpose.CLIENT_AUTH)
        
        if self.config.options:
            ssl_options = self.config.options.get("ssl", {})
            
            if ssl_options.get("certfile") and ssl_options.get("keyfile"):
                context.load_cert_chain(ssl_options["certfile"], ssl_options["keyfile"])
            
            if ssl_options.get("ca_certs"):
                context.load_verify_locations(ssl_options["ca_certs"])
        
        return context


class ProtocolManager:
    """Manages multiple protocol implementations."""
    
    def __init__(self, protocols_config: Dict[str, Any], logger: RDMALogger):
        self.protocols_config = protocols_config
        self.logger = logger
        self.protocols: Dict[str, ProtocolBase] = {}
        self._running_protocols: List[str] = []
    
    async def start(self) -> None:
        """Start all configured protocols."""
        self.logger.info("Starting protocol manager...")
        
        for protocol_name, protocol_config in self.protocols_config.items():
            try:
                if protocol_config.get("enabled", True):
                    await self._start_protocol(protocol_name, protocol_config)
            except Exception as e:
                self.logger.error(f"Failed to start protocol {protocol_name}: {e}")
                # Continue with other protocols even if one fails
    
    async def stop(self) -> None:
        """Stop all running protocols."""
        self.logger.info("Stopping protocol manager...")
        
        for protocol_name in self._running_protocols.copy():
            try:
                await self._stop_protocol(protocol_name)
            except Exception as e:
                self.logger.error(f"Error stopping protocol {protocol_name}: {e}")
    
    async def _start_protocol(self, name: str, config: Dict[str, Any]) -> None:
        """Start a specific protocol."""
        protocol_type = config.get("type", name)
        protocol_config = ProtocolConfig(
            type=protocol_type,
            enabled=config.get("enabled", True),
            host=config.get("host", "localhost"),
            port=config.get("port", 8080),
            ssl=config.get("ssl", False),
            auth=config.get("auth"),
            options=config.get("options", {})
        )
        
        # Create protocol instance based on type
        if protocol_type == "mqtt":
            protocol = MQTTProtocol(protocol_config, self.logger)
        elif protocol_type == "http":
            protocol = HTTPProtocol(protocol_config, self.logger)
        elif protocol_type == "websocket":
            protocol = WebSocketProtocol(protocol_config, self.logger)
        else:
            raise ProtocolError(f"Unknown protocol type: {protocol_type}")
        
        self.protocols[name] = protocol
        
        # Start the protocol
        if protocol_config.enabled:
            await protocol.connect()
            self._running_protocols.append(name)
            self.logger.info(f"Protocol {name} started successfully")
    
    async def _stop_protocol(self, name: str) -> None:
        """Stop a specific protocol."""
        if name in self.protocols:
            protocol = self.protocols[name]
            await protocol.disconnect()
            
            if name in self._running_protocols:
                self._running_protocols.remove(name)
            
            del self.protocols[name]
            self.logger.info(f"Protocol {name} stopped successfully")
    
    def get_protocol(self, name: str) -> Optional[ProtocolBase]:
        """Get a specific protocol by name."""
        return self.protocols.get(name)
    
    async def send_message(self, protocol_name: str, message: Dict[str, Any], target: str = None) -> None:
        """Send a message through a specific protocol."""
        protocol = self.get_protocol(protocol_name)
        if not protocol:
            raise ProtocolError(f"Protocol {protocol_name} not found")
        
        await protocol.send_message(message, target)
    
    def get_status(self) -> Dict[str, Any]:
        """Get protocol manager status."""
        return {
            "protocols": {
                name: protocol.get_status() for name, protocol in self.protocols.items()
            },
            "running_protocols": self._running_protocols.copy()
        }