#!/usr/bin/env python3
"""
Setup script for RDMA - Remote Digital Management Agent
"""

from setuptools import setup, find_packages

# Use pyproject.toml for configuration
# This file is kept for backward compatibility
setup(
    use_scm_version=True,
    setup_requires=['setuptools_scm'],
    packages=find_packages(where="src"),
    package_dir={"": "src"},
)