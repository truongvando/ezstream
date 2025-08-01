#!/usr/bin/env python3
"""
EZStream Agent Process Manager
Manages FFmpeg processes, monitoring, and reconnect logic
"""

import os
import time
import logging
import threading
import subprocess
from typing import Dict, Optional, List, Any
from dataclasses import dataclass, field
from enum import Enum

from config import get_config
from utils import kill_process_tree
from status_reporter import get_status_reporter


class ProcessState(Enum):
    """FFmpeg process states"""
    STARTING = "STARTING"
    RUNNING = "RUNNING"
    RECONNECTING = "RECONNECTING"
    STOPPING = "STOPPING"
    STOPPED = "STOPPED"
    ERROR = "ERROR"
    DEAD = "DEAD"


@dataclass
class ProcessInfo:
    """FFmpeg process information"""
    stream_id: int
    process: Optional[subprocess.Popen] = None
    state: ProcessState = ProcessState.STARTING
    start_time: float = field(default_factory=time.time)
    last_restart_time: Optional[float] = None
    restart_count: int = 0
    error_message: Optional[str] = None
    is_stopping: bool = False
    
    # Performance metrics
    uptime: float = 0
    total_reconnects: int = 0
    health_score: float = 1.0


@dataclass
class ReconnectConfig:
    """Reconnect configuration"""
    enabled: bool = True
    max_attempts: int = -1              # -1 = unlimited
    base_delay: float = 2.0
    max_delay: float = 300.0            # 5 minutes max delay
    exponential_factor: float = 1.5
    reset_after_success: float = 300.0  # Reset counter after 5 minutes of success


class ProcessManager:
    """Manages FFmpeg processes with monitoring and reconnect logic"""
    
    def __init__(self):
        self.config = get_config()
        self.status_reporter = get_status_reporter()
        
        # Process tracking
        self.processes: Dict[int, ProcessInfo] = {}
        self.process_lock = threading.RLock()
        
        # Reconnect configuration
        self.reconnect_config = ReconnectConfig()
        
        # Monitoring
        self.monitor_thread = None
        self.monitor_running = False
        
        logging.info("🔧 Process Manager initialized")
        
        # Start process monitoring
        self._start_monitoring()
    
    def start_process(self, stream_id: int, ffmpeg_command: List[str]) -> bool:
        """Start FFmpeg process"""
        try:
            with self.process_lock:
                # Check if already running
                if stream_id in self.processes:
                    logging.warning(f"Process for stream {stream_id} already running")
                    return False
                
                # Create process info
                process_info = ProcessInfo(stream_id=stream_id)
                self.processes[stream_id] = process_info
                
                logging.info(f"🚀 Starting FFmpeg process for stream {stream_id}")
                logging.debug(f"FFmpeg command: {' '.join(ffmpeg_command)}")
                
                # Start process
                process = subprocess.Popen(
                    ffmpeg_command,
                    stdout=subprocess.PIPE,
                    stderr=subprocess.PIPE,
                    stdin=subprocess.PIPE,
                    preexec_fn=os.setsid if hasattr(os, 'setsid') else None
                )
                
                process_info.process = process
                
                # Quick check if process started successfully
                time.sleep(0.5)
                if process.poll() is not None:
                    # Process already terminated
                    _, stderr = process.communicate()
                    error_msg = f"FFmpeg failed to start: {stderr.decode()}"
                    logging.error(f"Stream {stream_id}: {error_msg}")
                    process_info.error_message = error_msg
                    process_info.state = ProcessState.ERROR
                    return False
                
                # Process started successfully
                process_info.state = ProcessState.RUNNING
                logging.info(f"✅ FFmpeg process started for stream {stream_id} (PID: {process.pid})")
                return True
                
        except Exception as e:
            error_msg = f"Error starting FFmpeg process: {e}"
            logging.error(f"Stream {stream_id}: {error_msg}")
            if stream_id in self.processes:
                self.processes[stream_id].error_message = error_msg
                self.processes[stream_id].state = ProcessState.ERROR
            return False
    
    def stop_process(self, stream_id: int, reason: str = "manual") -> bool:
        """Stop FFmpeg process"""
        try:
            with self.process_lock:
                if stream_id not in self.processes:
                    logging.warning(f"No process found for stream {stream_id}")
                    return True
                
                process_info = self.processes[stream_id]
                process_info.is_stopping = True
                process_info.state = ProcessState.STOPPING
                
                logging.info(f"🛑 Stopping FFmpeg process for stream {stream_id} (reason: {reason})")
                
                # Kill FFmpeg process
                success = self._kill_process(process_info)
                
                # Remove from tracking
                del self.processes[stream_id]
                
                logging.info(f"✅ FFmpeg process stopped for stream {stream_id}")
                return success
                
        except Exception as e:
            logging.error(f"❌ Error stopping process for stream {stream_id}: {e}")
            return False
    
    def restart_process(self, stream_id: int, ffmpeg_command: List[str]) -> bool:
        """Restart FFmpeg process"""
        try:
            logging.info(f"🔄 Restarting FFmpeg process for stream {stream_id}")
            
            # Stop current process
            self.stop_process(stream_id, "restart")
            
            # Small delay before restart
            time.sleep(1)
            
            # Start with new command
            return self.start_process(stream_id, ffmpeg_command)
            
        except Exception as e:
            logging.error(f"❌ Error restarting process for stream {stream_id}: {e}")
            return False
    
    def get_process_status(self, stream_id: int) -> Optional[Dict[str, Any]]:
        """Get process status"""
        with self.process_lock:
            if stream_id not in self.processes:
                return None
            
            process_info = self.processes[stream_id]
            return {
                'stream_id': stream_id,
                'state': process_info.state.value,
                'uptime': time.time() - process_info.start_time,
                'restart_count': process_info.restart_count,
                'total_reconnects': process_info.total_reconnects,
                'health_score': process_info.health_score,
                'error_message': process_info.error_message,
                'process_pid': process_info.process.pid if process_info.process else None
            }
    
    def get_active_processes(self) -> List[int]:
        """Get list of active process stream IDs"""
        with self.process_lock:
            return list(self.processes.keys())
    
    def stop_all(self):
        """Stop all processes and cleanup"""
        logging.info("🛑 Stopping all FFmpeg processes...")
        
        # Stop monitoring
        self._stop_monitoring()
        
        # Stop all processes
        with self.process_lock:
            stream_ids = list(self.processes.keys())
        
        for stream_id in stream_ids:
            try:
                self.stop_process(stream_id, "shutdown")
            except Exception as e:
                logging.error(f"❌ Error stopping process {stream_id} during shutdown: {e}")
        
        logging.info("✅ Process Manager stopped")
    
    def _kill_process(self, process_info: ProcessInfo) -> bool:
        """Kill FFmpeg process gracefully"""
        try:
            if not process_info.process:
                return True
            
            process = process_info.process
            stream_id = process_info.stream_id
            
            logging.info(f"Stream {stream_id}: Terminating FFmpeg process (PID: {process.pid})")
            
            # Try graceful termination first
            try:
                process.terminate()
                process.wait(timeout=5)
                logging.info(f"Stream {stream_id}: FFmpeg terminated gracefully")
                return True
            except subprocess.TimeoutExpired:
                logging.warning(f"Stream {stream_id}: FFmpeg didn't terminate gracefully, force killing")
                
                # Force kill if graceful termination failed
                kill_process_tree(process.pid)
                
                try:
                    process.wait(timeout=3)
                    logging.info(f"Stream {stream_id}: FFmpeg force killed")
                    return True
                except subprocess.TimeoutExpired:
                    logging.error(f"Stream {stream_id}: Failed to kill FFmpeg process")
                    return False
                    
        except Exception as e:
            logging.error(f"Stream {process_info.stream_id}: Error killing FFmpeg: {e}")
            return False

    def _start_monitoring(self):
        """Start process monitoring thread"""
        if self.monitor_running:
            return

        self.monitor_running = True
        self.monitor_thread = threading.Thread(target=self._monitor_processes, daemon=True)
        self.monitor_thread.start()
        logging.info("📊 Process monitoring started")

    def _stop_monitoring(self):
        """Stop process monitoring"""
        self.monitor_running = False
        if self.monitor_thread and self.monitor_thread.is_alive():
            self.monitor_thread.join(timeout=5)
        logging.info("📊 Process monitoring stopped")

    def _monitor_processes(self):
        """Monitor all active processes with concurrent health checking"""
        while self.monitor_running:
            try:
                with self.process_lock:
                    processes_to_check = list(self.processes.items())

                if not processes_to_check:
                    time.sleep(self.config.process_monitor_interval)
                    continue

                # Concurrent health checking for better performance
                from concurrent.futures import ThreadPoolExecutor, as_completed

                with ThreadPoolExecutor(max_workers=min(10, len(processes_to_check))) as executor:
                    health_futures = {}

                    for stream_id, process_info in processes_to_check:
                        if process_info.is_stopping:
                            continue

                        future = executor.submit(self._check_process_health, process_info)
                        health_futures[future] = stream_id

                    # Wait for all health checks to complete
                    for future in as_completed(health_futures, timeout=5):
                        stream_id = health_futures[future]
                        try:
                            future.result()
                        except Exception as e:
                            logging.error(f"❌ Health check error for stream {stream_id}: {e}")

                time.sleep(self.config.process_monitor_interval)

            except Exception as e:
                logging.error(f"❌ Error in process monitoring: {e}")
                time.sleep(self.config.process_monitor_interval * 2)  # Wait longer on error

    def _check_process_health(self, process_info: ProcessInfo):
        """Check individual process health and handle failures"""
        try:
            if not process_info.process:
                return

            # Check if process is still running
            poll_result = process_info.process.poll()

            if poll_result is not None:
                # Process has terminated
                self._handle_process_termination(process_info, poll_result)
            else:
                # Process is running - update health metrics
                self._update_health_metrics(process_info)

        except Exception as e:
            logging.error(f"Stream {process_info.stream_id}: Error checking health: {e}")

    def _handle_process_termination(self, process_info: ProcessInfo, exit_code: int):
        """Handle unexpected process termination"""
        stream_id = process_info.stream_id

        if process_info.is_stopping:
            # Expected termination during stop
            return

        # Get error output
        error_output = ""
        try:
            if process_info.process and process_info.process.stderr:
                stderr_data = process_info.process.stderr.read()
                if stderr_data:
                    error_output = stderr_data.decode('utf-8', errors='ignore')
        except:
            pass

        logging.warning(f"⚠️ Process for stream {stream_id} terminated unexpectedly (exit code: {exit_code})")
        if error_output:
            logging.error(f"Stream {stream_id} error output: {error_output}")

        # Analyze error type for better handling
        error_type = self._analyze_error_type(error_output)

        # Update process info with detailed error
        process_info.error_message = f"Process terminated with exit code {exit_code} - {error_type}"
        process_info.state = ProcessState.ERROR

        # Attempt reconnect if enabled
        if self.reconnect_config.enabled and not process_info.is_stopping:
            self._attempt_reconnect(process_info)
        else:
            # Mark as dead if reconnect disabled
            process_info.state = ProcessState.DEAD
            if self.status_reporter:
                self.status_reporter.publish_stream_status(
                    stream_id, 'DEAD', 'Process terminated and reconnect disabled'
                )

    def _attempt_reconnect(self, process_info: ProcessInfo):
        """Attempt to reconnect process with exponential backoff"""
        stream_id = process_info.stream_id

        # Check if we've exceeded max attempts (skip if unlimited)
        if (self.reconnect_config.max_attempts > 0 and
            process_info.restart_count >= self.reconnect_config.max_attempts):
            logging.error(f"💀 Stream {stream_id}: Max reconnect attempts reached ({self.reconnect_config.max_attempts})")
            process_info.state = ProcessState.DEAD
            if self.status_reporter:
                self.status_reporter.publish_stream_status(
                    stream_id, 'DEAD', f'Max reconnect attempts reached ({self.reconnect_config.max_attempts})'
                )
            return

        # Calculate delay with exponential backoff
        delay = min(
            self.reconnect_config.base_delay * (self.reconnect_config.exponential_factor ** process_info.restart_count),
            self.reconnect_config.max_delay
        )

        logging.info(f"🔄 Stream {stream_id}: Attempting reconnect #{process_info.restart_count + 1} in {delay:.1f}s")

        # Update state
        process_info.state = ProcessState.RECONNECTING
        if self.status_reporter:
            self.status_reporter.publish_stream_status(
                stream_id, 'RECONNECTING', f'Reconnecting in {delay:.1f}s (attempt #{process_info.restart_count + 1})'
            )

        # Wait before reconnect
        time.sleep(delay)

        # Check if process was stopped during wait
        if process_info.is_stopping:
            return

        # Update counters
        process_info.restart_count += 1
        process_info.total_reconnects += 1
        process_info.last_restart_time = time.time()

        # Note: Actual restart will be handled by stream_manager
        # This just marks the process as needing restart
        process_info.state = ProcessState.ERROR
        logging.info(f"🔄 Stream {stream_id}: Process marked for restart")

    def _analyze_error_type(self, error_output: str) -> str:
        """Analyze FFmpeg error output to determine error type"""
        if not error_output:
            return "Unknown error"

        error_output_lower = error_output.lower()

        # Network/connection errors
        if any(keyword in error_output_lower for keyword in [
            'connection refused', 'network unreachable', 'timeout',
            'connection reset', 'no route to host', 'connection timed out'
        ]):
            return "Network connection error"

        # RTMP specific errors
        if any(keyword in error_output_lower for keyword in [
            'rtmp', 'handshake failed', 'server disconnected', 'publish failed'
        ]):
            return "RTMP streaming error"

        # Input file errors
        if any(keyword in error_output_lower for keyword in [
            'no such file', 'input/output error', 'invalid data found',
            'does not exist', 'permission denied'
        ]):
            return "Input file error"

        # Codec/format errors
        if any(keyword in error_output_lower for keyword in [
            'codec', 'format', 'unsupported', 'invalid'
        ]):
            return "Codec/format error"

        # Resource errors
        if any(keyword in error_output_lower for keyword in [
            'out of memory', 'resource temporarily unavailable', 'disk full'
        ]):
            return "System resource error"

        return "FFmpeg process error"

    def _update_health_metrics(self, process_info: ProcessInfo):
        """Update process health metrics"""
        current_time = time.time()
        process_info.uptime = current_time - process_info.start_time

        # Reset restart counter after successful period
        if (process_info.last_restart_time and
            current_time - process_info.last_restart_time > self.reconnect_config.reset_after_success):
            if process_info.restart_count > 0:
                logging.info(f"Stream {process_info.stream_id}: Resetting restart counter after successful period")
                process_info.restart_count = 0

        # Calculate health score (simple metric based on restart frequency)
        if process_info.total_reconnects == 0:
            process_info.health_score = 1.0
        else:
            # Lower score for more reconnects
            process_info.health_score = max(0.1, 1.0 - (process_info.total_reconnects * 0.1))


# Global instance management
_process_manager: Optional['ProcessManager'] = None


def init_process_manager() -> 'ProcessManager':
    """Initialize global process manager"""
    global _process_manager
    _process_manager = ProcessManager()
    return _process_manager


def get_process_manager() -> 'ProcessManager':
    """Get global process manager instance"""
    if _process_manager is None:
        raise RuntimeError("Process manager not initialized. Call init_process_manager() first.")
    return _process_manager
