"""
RDMA Security Management

Provides authentication, authorization, and encryption capabilities.
"""

import hashlib
import secrets
import base64
from typing import Dict, Any, Optional, List
from datetime import datetime, timedelta
import json

from .config import SecurityConfig
from .logging import RDMALogger
from .exceptions import AuthenticationError, PermissionError


class TokenManager:
    """Manages authentication tokens."""
    
    def __init__(self, config: SecurityConfig):
        self.config = config
        self.tokens: Dict[str, Dict[str, Any]] = {}
        self.failed_attempts: Dict[str, int] = {}
        self.locked_accounts: Dict[str, datetime] = {}
    
    def generate_token(self, user_id: str, permissions: List[str] = None) -> str:
        """Generate a new authentication token."""
        token = secrets.token_urlsafe(32)
        
        self.tokens[token] = {
            "user_id": user_id,
            "permissions": permissions or [],
            "created_at": datetime.utcnow(),
            "expires_at": datetime.utcnow() + timedelta(seconds=self.config.token_expiry)
        }
        
        return token
    
    def validate_token(self, token: str) -> Optional[Dict[str, Any]]:
        """Validate and return token information."""
        if token not in self.tokens:
            return None
        
        token_info = self.tokens[token]
        
        # Check expiration
        if datetime.utcnow() > token_info["expires_at"]:
            del self.tokens[token]
            return None
        
        return token_info
    
    def revoke_token(self, token: str) -> bool:
        """Revoke a token."""
        if token in self.tokens:
            del self.tokens[token]
            return True
        return False
    
    def cleanup_expired_tokens(self) -> int:
        """Remove expired tokens and return count removed."""
        expired_tokens = []
        current_time = datetime.utcnow()
        
        for token, info in self.tokens.items():
            if current_time > info["expires_at"]:
                expired_tokens.append(token)
        
        for token in expired_tokens:
            del self.tokens[token]
        
        return len(expired_tokens)
    
    def record_failed_attempt(self, identifier: str) -> None:
        """Record a failed authentication attempt."""
        self.failed_attempts[identifier] = self.failed_attempts.get(identifier, 0) + 1
        
        # Lock account if max attempts reached
        if self.failed_attempts[identifier] >= self.config.max_failed_attempts:
            self.locked_accounts[identifier] = datetime.utcnow()
            self.failed_attempts[identifier] = 0  # Reset counter
    
    def is_locked(self, identifier: str) -> bool:
        """Check if an account is locked."""
        if identifier not in self.locked_accounts:
            return False
        
        locked_time = self.locked_accounts[identifier]
        current_time = datetime.utcnow()
        
        # Check if lock has expired
        if current_time > locked_time + timedelta(seconds=self.config.lockout_duration):
            del self.locked_accounts[identifier]
            return False
        
        return True
    
    def reset_failed_attempts(self, identifier: str) -> None:
        """Reset failed attempts counter."""
        if identifier in self.failed_attempts:
            del self.failed_attempts[identifier]
    
    def get_failed_attempts(self, identifier: str) -> int:
        """Get number of failed attempts for an identifier."""
        return self.failed_attempts.get(identifier, 0)


class PermissionManager:
    """Manages user permissions and roles."""
    
    def __init__(self, config: SecurityConfig):
        self.config = config
        self.permissions: Dict[str, List[str]] = {}
        self.roles: Dict[str, List[str]] = {}
    
    def add_permission(self, user_id: str, permission: str) -> None:
        """Add a permission to a user."""
        if user_id not in self.permissions:
            self.permissions[user_id] = []
        
        if permission not in self.permissions[user_id]:
            self.permissions[user_id].append(permission)
    
    def remove_permission(self, user_id: str, permission: str) -> bool:
        """Remove a permission from a user."""
        if user_id in self.permissions and permission in self.permissions[user_id]:
            self.permissions[user_id].remove(permission)
            return True
        return False
    
    def has_permission(self, user_id: str, permission: str) -> bool:
        """Check if a user has a specific permission."""
        user_permissions = self.permissions.get(user_id, [])
        return permission in user_permissions
    
    def get_user_permissions(self, user_id: str) -> List[str]:
        """Get all permissions for a user."""
        return self.permissions.get(user_id, []).copy()
    
    def add_role(self, role_name: str, permissions: List[str]) -> None:
        """Create or update a role with permissions."""
        self.roles[role_name] = permissions.copy()
    
    def assign_role(self, user_id: str, role_name: str) -> None:
        """Assign a role to a user."""
        if role_name in self.roles:
            for permission in self.roles[role_name]:
                self.add_permission(user_id, permission)
    
    def revoke_role(self, user_id: str, role_name: str) -> None:
        """Revoke a role from a user."""
        if role_name in self.roles:
            for permission in self.roles[role_name]:
                self.remove_permission(user_id, permission)
    
    def validate_command(self, user_id: str, command: str, params: Dict[str, Any]) -> bool:
        """Validate if a user can execute a command."""
        # Check if command is in allowed commands
        if self.config.allowed_commands and command not in self.config.allowed_commands:
            return False
        
        # Check if user has permission for this command
        command_permission = f"command:{command}"
        if not self.has_permission(user_id, command_permission):
            # Check for wildcard permission
            if not self.has_permission(user_id, "command:*"):
                return False
        
        return True


class EncryptionManager:
    """Manages data encryption and decryption."""
    
    def __init__(self, config: SecurityConfig):
        self.config = config
        self._fernet = None
        self._setup_encryption()
    
    def _setup_encryption(self) -> None:
        """Set up encryption with the configured key."""
        if not self.config.encryption_key:
            return
        
        try:
            from cryptography.fernet import Fernet
            
            # Ensure key is properly formatted
            key = self.config.encryption_key
            if len(key) != 44:  # Fernet keys are 44 characters
                # Generate a key from the provided string
                key_bytes = key.encode('utf-8')
                key_hash = hashlib.sha256(key_bytes).digest()
                key = base64.urlsafe_b64encode(key_hash).decode('utf-8')
            
            self._fernet = Fernet(key.encode('utf-8'))
            
        except ImportError:
            # cryptography library not available
            pass
        except Exception as e:
            raise RuntimeError(f"Failed to setup encryption: {e}")
    
    def encrypt(self, data: bytes) -> bytes:
        """Encrypt data."""
        if not self._fernet:
            raise RuntimeError("Encryption not available")
        
        return self._fernet.encrypt(data)
    
    def decrypt(self, encrypted_data: bytes) -> bytes:
        """Decrypt data."""
        if not self._fernet:
            raise RuntimeError("Encryption not available")
        
        return self._fernet.decrypt(encrypted_data)
    
    def encrypt_string(self, text: str) -> str:
        """Encrypt a string."""
        encrypted_bytes = self.encrypt(text.encode('utf-8'))
        return base64.urlsafe_b64encode(encrypted_bytes).decode('utf-8')
    
    def decrypt_string(self, encrypted_text: str) -> str:
        """Decrypt a string."""
        encrypted_bytes = base64.urlsafe_b64decode(encrypted_text.encode('utf-8'))
        decrypted_bytes = self.decrypt(encrypted_bytes)
        return decrypted_bytes.decode('utf-8')


class SecurityManager:
    """Main security manager for RDMA."""
    
    def __init__(self, config: SecurityConfig, logger: RDMALogger):
        self.config = config
        self.logger = logger
        self.token_manager = TokenManager(config)
        self.permission_manager = PermissionManager(config)
        self.encryption_manager = EncryptionManager(config)
        
        # Initialize default permissions
        self._setup_default_permissions()
    
    def _setup_default_permissions(self) -> None:
        """Set up default permissions and roles."""
        # Create admin role with all permissions
        admin_permissions = [
            "command:*",
            "task:*",
            "config:*",
            "system:*",
            "ham_radio:*"
        ]
        self.permission_manager.add_role("admin", admin_permissions)
        
        # Create user role with limited permissions
        user_permissions = [
            "command:status",
            "command:metrics",
            "ham_radio:get_status",
            "ham_radio:get_dxcc_info",
            "ham_radio:is_worked"
        ]
        self.permission_manager.add_role("user", user_permissions)
    
    async def start(self) -> None:
        """Start the security manager."""
        if not self.config.enabled:
            self.logger.info("Security is disabled")
            return
        
        self.logger.info("Security manager started")
    
    async def stop(self) -> None:
        """Stop the security manager."""
        self.logger.info("Security manager stopped")
    
    def authenticate_user(self, username: str, password: str) -> Optional[str]:
        """Authenticate a user and return token if successful."""
        if not self.config.enabled:
            # Generate a token for disabled security
            return self.token_manager.generate_token(username, ["command:*"])
        
        # Check if account is locked
        if self.token_manager.is_locked(username):
            self.logger.warning(f"Authentication attempt for locked account: {username}")
            raise AuthenticationError("Account is locked due to too many failed attempts")
        
        try:
            # In a real implementation, this would verify against a user database
            # For now, we'll use a simple password check
            # In production, use proper password hashing and user management
            
            # Simple password check (replace with proper authentication)
            if self._verify_password(username, password):
                # Reset failed attempts
                self.token_manager.reset_failed_attempts(username)
                
                # Generate token
                token = self.token_manager.generate_token(username)
                
                self.logger.info(f"User authenticated successfully: {username}")
                return token
            else:
                # Record failed attempt
                self.token_manager.record_failed_attempt(username)
                self.logger.warning(f"Authentication failed for user: {username}")
                raise AuthenticationError("Invalid username or password")
                
        except Exception as e:
            self.logger.error(f"Authentication error for user {username}: {e}")
            raise AuthenticationError("Authentication failed")
    
    def _verify_password(self, username: str, password: str) -> bool:
        """Verify user password (simplified implementation)."""
        # In a real implementation, this would check against a secure user database
        # For demonstration, we'll use a simple check
        # NEVER store passwords in plain text in production!
        
        # Default users for demonstration
        default_users = {
            "admin": "admin123",  # Change this in production!
            "user": "user123",    # Change this in production!
            "guest": "guest123"   # Change this in production!
        }
        
        if username in default_users:
            return default_users[username] == password
        
        return False
    
    def validate_token(self, token: str) -> Optional[Dict[str, Any]]:
        """Validate an authentication token."""
        if not self.config.enabled:
            # Return a default token for disabled security
            return {
                "user_id": "system",
                "permissions": ["command:*"],
                "created_at": datetime.utcnow(),
                "expires_at": datetime.utcnow() + timedelta(days=365)
            }
        
        return self.token_manager.validate_token(token)
    
    def authorize_action(self, token: str, action: str, params: Dict[str, Any] = None) -> bool:
        """Authorize an action based on token."""
        if not self.config.enabled:
            return True  # Allow all actions when security is disabled
        
        token_info = self.validate_token(token)
        if not token_info:
            self.logger.warning(f"Authorization failed: invalid token for action {action}")
            return False
        
        user_id = token_info["user_id"]
        
        # Check permissions
        if self.permission_manager.validate_command(user_id, action, params or {}):
            self.logger.debug(f"Authorization successful: {user_id} - {action}")
            return True
        else:
            self.logger.warning(f"Authorization denied: {user_id} - {action}")
            return False
    
    def encrypt_data(self, data: str) -> str:
        """Encrypt sensitive data."""
        if not self.config.encryption_key:
            return data  # Return as-is if no encryption key
        
        try:
            return self.encryption_manager.encrypt_string(data)
        except Exception as e:
            self.logger.error(f"Encryption failed: {e}")
            raise RuntimeError("Failed to encrypt data")
    
    def decrypt_data(self, encrypted_data: str) -> str:
        """Decrypt sensitive data."""
        if not self.config.encryption_key:
            return encrypted_data  # Return as-is if no encryption key
        
        try:
            return self.encryption_manager.decrypt_string(encrypted_data)
        except Exception as e:
            self.logger.error(f"Decryption failed: {e}")
            raise RuntimeError("Failed to decrypt data")
    
    def get_user_permissions(self, token: str) -> List[str]:
        """Get permissions for a user from token."""
        token_info = self.validate_token(token)
        if not token_info:
            return []
        
        user_id = token_info["user_id"]
        return self.permission_manager.get_user_permissions(user_id)
    
    def create_user_token(self, user_id: str, permissions: List[str] = None) -> str:
        """Create a token for a user."""
        return self.token_manager.generate_token(user_id, permissions)
    
    def revoke_token(self, token: str) -> bool:
        """Revoke a user token."""
        return self.token_manager.revoke_token(token)
    
    def cleanup_expired_tokens(self) -> int:
        """Clean up expired tokens."""
        return self.token_manager.cleanup_expired_tokens()
    
    def get_status(self) -> Dict[str, Any]:
        """Get security manager status."""
        return {
            "enabled": self.config.enabled,
            "auth_required": self.config.auth_required,
            "active_tokens": len(self.token_manager.tokens),
            "failed_attempts": len(self.token_manager.failed_attempts),
            "locked_accounts": len(self.token_manager.locked_accounts)
        }","file_path":"/Users/cheenle/ultron/ultron-main/rdma/src/rdma/security.py"}