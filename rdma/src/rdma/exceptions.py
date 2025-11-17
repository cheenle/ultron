"""
RDMA Exception Classes

Custom exceptions for the RDMA system.
"""

class RDMAException(Exception):
    """Base exception for all RDMA-related errors."""
    pass

class ConfigurationError(RDMAException):
    """Raised when there's an issue with configuration."""
    pass

class ProtocolError(RDMAException):
    """Raised when there's an issue with protocol communication."""
    pass

class ConnectionError(RDMAException):
    """Raised when there's a connection issue."""
    pass

class AuthenticationError(RDMAException):
    """Raised when there's an authentication failure."""
    pass

class ValidationError(RDMAException):
    """Raised when data validation fails."""
    pass

class TimeoutError(RDMAException):
    """Raised when an operation times out."""
    pass

class ResourceNotFoundError(RDMAException):
    """Raised when a requested resource is not found."""
    pass

class PermissionError(RDMAException):
    """Raised when an operation is not permitted."""
    pass

class ServiceUnavailableError(RDMAException):
    """Raised when a required service is unavailable."""
    pass