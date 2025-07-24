#!/usr/bin/env python3
"""
EZStream Agent Utilities
Shared utility functions and helpers
"""

import os
import re
import time
import json
import hashlib
import logging
import subprocess
from typing import Dict, Any, Optional, List
from urllib.parse import urlparse


def sanitize_filename(filename: str) -> str:
    """Sanitize filename for safe filesystem usage"""
    if not filename:
        return 'unknown_file'
    
    # Remove or replace unsafe characters
    filename = re.sub(r'[<>:"/\\|?*]', '_', filename)
    filename = re.sub(r'[^\w\-_\.]', '_', filename)
    
    # Remove multiple underscores
    filename = re.sub(r'_+', '_', filename)
    
    # Ensure it's not empty and has reasonable length
    filename = filename.strip('_')
    if not filename:
        filename = 'sanitized_file'
    
    # Limit length
    if len(filename) > 200:
        name, ext = os.path.splitext(filename)
        filename = name[:190] + ext
    
    return filename


def calculate_file_hash(file_path: str, chunk_size: int = 8192) -> Optional[str]:
    """Calculate MD5 hash of file"""
    try:
        hash_md5 = hashlib.md5()
        with open(file_path, "rb") as f:
            for chunk in iter(lambda: f.read(chunk_size), b""):
                hash_md5.update(chunk)
        return hash_md5.hexdigest()
    except Exception as e:
        logging.error(f"Error calculating hash for {file_path}: {e}")
        return None


def format_bytes(bytes_value: int) -> str:
    """Format bytes to human readable string"""
    for unit in ['B', 'KB', 'MB', 'GB', 'TB']:
        if bytes_value < 1024.0:
            return f"{bytes_value:.1f} {unit}"
        bytes_value /= 1024.0
    return f"{bytes_value:.1f} PB"


def format_duration(seconds: float) -> str:
    """Format duration in seconds to human readable string"""
    if seconds < 60:
        return f"{seconds:.1f}s"
    elif seconds < 3600:
        minutes = seconds / 60
        return f"{minutes:.1f}m"
    else:
        hours = seconds / 3600
        return f"{hours:.1f}h"


def validate_url(url: str) -> bool:
    """Validate if URL is properly formatted"""
    try:
        result = urlparse(url)
        return all([result.scheme, result.netloc])
    except Exception:
        return False


def validate_rtmp_url(url: str) -> bool:
    """Validate RTMP URL format"""
    return url.startswith('rtmp://') and validate_url(url)


def safe_json_loads(json_str: str, default: Any = None) -> Any:
    """Safely parse JSON string"""
    try:
        return json.loads(json_str)
    except (json.JSONDecodeError, TypeError) as e:
        logging.warning(f"Failed to parse JSON: {e}")
        return default


def safe_json_dumps(obj: Any, default: str = "{}") -> str:
    """Safely serialize object to JSON"""
    try:
        return json.dumps(obj, ensure_ascii=False)
    except (TypeError, ValueError) as e:
        logging.warning(f"Failed to serialize to JSON: {e}")
        return default


def ensure_directory(directory: str) -> bool:
    """Ensure directory exists, create if not"""
    try:
        os.makedirs(directory, exist_ok=True)
        return True
    except Exception as e:
        logging.error(f"Failed to create directory {directory}: {e}")
        return False


def is_process_running(pid: int) -> bool:
    """Check if process with given PID is running"""
    try:
        os.kill(pid, 0)
        return True
    except (OSError, ProcessLookupError):
        return False


def kill_process_tree(pid: int, timeout: int = 5) -> bool:
    """Kill process and all its children"""
    try:
        import psutil
        parent = psutil.Process(pid)
        children = parent.children(recursive=True)
        
        # Terminate children first
        for child in children:
            try:
                child.terminate()
            except psutil.NoSuchProcess:
                pass
        
        # Terminate parent
        parent.terminate()
        
        # Wait for graceful shutdown
        gone, alive = psutil.wait_procs(children + [parent], timeout=timeout)
        
        # Force kill if needed
        for proc in alive:
            try:
                proc.kill()
            except psutil.NoSuchProcess:
                pass
        
        return True
    except Exception as e:
        logging.error(f"Error killing process tree {pid}: {e}")
        return False


def run_command(command: List[str], timeout: int = 30, cwd: Optional[str] = None) -> Dict[str, Any]:
    """Run shell command and return result"""
    try:
        result = subprocess.run(
            command,
            capture_output=True,
            text=True,
            timeout=timeout,
            cwd=cwd
        )
        
        return {
            'success': result.returncode == 0,
            'returncode': result.returncode,
            'stdout': result.stdout,
            'stderr': result.stderr
        }
    except subprocess.TimeoutExpired:
        return {
            'success': False,
            'returncode': -1,
            'stdout': '',
            'stderr': f'Command timed out after {timeout} seconds'
        }
    except Exception as e:
        return {
            'success': False,
            'returncode': -1,
            'stdout': '',
            'stderr': str(e)
        }


def retry_operation(func, max_retries: int = 3, delay: float = 1.0, backoff: float = 2.0):
    """Retry operation with exponential backoff"""
    for attempt in range(max_retries):
        try:
            return func()
        except Exception as e:
            if attempt == max_retries - 1:
                raise e
            
            wait_time = delay * (backoff ** attempt)
            logging.warning(f"Operation failed (attempt {attempt + 1}/{max_retries}), retrying in {wait_time:.1f}s: {e}")
            time.sleep(wait_time)


def throttle_calls(func, min_interval: float = 1.0):
    """Decorator to throttle function calls"""
    last_call_time = {}
    
    def wrapper(*args, **kwargs):
        key = f"{func.__name__}_{hash(str(args))}"
        current_time = time.time()
        
        if key in last_call_time:
            time_since_last = current_time - last_call_time[key]
            if time_since_last < min_interval:
                return None
        
        last_call_time[key] = current_time
        return func(*args, **kwargs)
    
    return wrapper


class PerformanceTimer:
    """Context manager for measuring execution time"""
    
    def __init__(self, name: str = "Operation"):
        self.name = name
        self.start_time = None
        self.end_time = None
    
    def __enter__(self):
        self.start_time = time.time()
        return self
    
    def __exit__(self, exc_type, exc_val, exc_tb):
        self.end_time = time.time()
        duration = self.end_time - self.start_time
        logging.info(f"⏱️ {self.name} completed in {format_duration(duration)}")
    
    @property
    def duration(self) -> Optional[float]:
        if self.start_time and self.end_time:
            return self.end_time - self.start_time
        return None
