#!/usr/bin/env python3
"""
Premium EZStream Monitoring Dashboard
Real-time monitoring for 96GB RAM VPS deployment
"""

import time
import json
import psutil
import requests
import subprocess
import os
from datetime import datetime
from typing import Dict, List, Any

class PremiumMonitoringDashboard:
    """Premium monitoring dashboard for EZStream"""
    
    def __init__(self):
        self.start_time = time.time()
        self.stats_url = "http://localhost:8080/stat"
        self.health_url = "http://localhost:8080/health"
        
    def get_system_metrics(self) -> Dict[str, Any]:
        """Get comprehensive system metrics"""
        try:
            # Memory info
            memory = psutil.virtual_memory()
            
            # CPU info
            cpu_percent = psutil.cpu_percent(interval=1)
            cpu_count = psutil.cpu_count()
            
            # Disk info
            disk = psutil.disk_usage('/')
            
            # Network info
            network = psutil.net_io_counters()
            
            return {
                'memory': {
                    'total_gb': round(memory.total / (1024**3), 2),
                    'used_gb': round(memory.used / (1024**3), 2),
                    'available_gb': round(memory.available / (1024**3), 2),
                    'percent': memory.percent,
                    'buffer_capacity_streams': int(memory.available / (55 * 1024 * 1024))  # 55MB per stream
                },
                'cpu': {
                    'percent': cpu_percent,
                    'count': cpu_count,
                    'load_avg': psutil.getloadavg() if hasattr(psutil, 'getloadavg') else [0, 0, 0]
                },
                'disk': {
                    'total_gb': round(disk.total / (1024**3), 2),
                    'used_gb': round(disk.used / (1024**3), 2),
                    'free_gb': round(disk.free / (1024**3), 2),
                    'percent': round((disk.used / disk.total) * 100, 2)
                },
                'network': {
                    'bytes_sent': network.bytes_sent,
                    'bytes_recv': network.bytes_recv,
                    'packets_sent': network.packets_sent,
                    'packets_recv': network.packets_recv
                }
            }
        except Exception as e:
            return {'error': str(e)}
    
    def get_nginx_stats(self) -> Dict[str, Any]:
        """Get Nginx RTMP statistics"""
        try:
            response = requests.get(self.stats_url, timeout=5)
            if response.status_code == 200:
                # Parse XML response (simplified)
                content = response.text
                
                # Extract basic stats
                stats = {
                    'status': 'running',
                    'uptime': self._extract_stat(content, 'uptime'),
                    'total_streams': self._count_occurrences(content, '<stream>'),
                    'total_clients': self._count_occurrences(content, '<client>'),
                    'bytes_in': self._extract_stat(content, 'bytes_in'),
                    'bytes_out': self._extract_stat(content, 'bytes_out')
                }
                
                return stats
            else:
                return {'status': 'error', 'message': f'HTTP {response.status_code}'}
                
        except Exception as e:
            return {'status': 'error', 'message': str(e)}
    
    def get_agent_status(self) -> Dict[str, Any]:
        """Get EZStream Agent status"""
        try:
            # Check systemd service status
            result = subprocess.run(
                ['systemctl', 'is-active', 'ezstream-agent'],
                capture_output=True, text=True
            )
            
            service_status = result.stdout.strip()
            
            # Get process info
            agent_processes = []
            for proc in psutil.process_iter(['pid', 'name', 'memory_info', 'cpu_percent']):
                try:
                    if 'python' in proc.info['name'] and 'agent' in ' '.join(proc.cmdline()):
                        agent_processes.append({
                            'pid': proc.info['pid'],
                            'memory_mb': round(proc.info['memory_info'].rss / (1024*1024), 2),
                            'cpu_percent': proc.info['cpu_percent']
                        })
                except:
                    continue
            
            return {
                'service_status': service_status,
                'processes': agent_processes,
                'process_count': len(agent_processes)
            }
            
        except Exception as e:
            return {'status': 'error', 'message': str(e)}
    
    def get_hls_storage_info(self) -> Dict[str, Any]:
        """Get HLS storage information"""
        try:
            hls_path = '/tmp/hls'
            
            if not os.path.exists(hls_path):
                return {'status': 'not_found'}
            
            # Get directory size
            total_size = 0
            file_count = 0
            stream_dirs = []
            
            for root, dirs, files in os.walk(hls_path):
                for file in files:
                    file_path = os.path.join(root, file)
                    total_size += os.path.getsize(file_path)
                    file_count += 1
                
                # Count stream directories
                if root != hls_path:
                    stream_dirs.append(os.path.basename(root))
            
            return {
                'total_size_mb': round(total_size / (1024*1024), 2),
                'file_count': file_count,
                'stream_count': len(stream_dirs),
                'streams': stream_dirs[:10]  # Show first 10
            }
            
        except Exception as e:
            return {'status': 'error', 'message': str(e)}
    
    def _extract_stat(self, content: str, stat_name: str) -> str:
        """Extract statistic from XML content"""
        try:
            start = content.find(f'<{stat_name}>')
            if start == -1:
                return 'N/A'
            start += len(f'<{stat_name}>')
            end = content.find(f'</{stat_name}>', start)
            if end == -1:
                return 'N/A'
            return content[start:end]
        except:
            return 'N/A'
    
    def _count_occurrences(self, content: str, pattern: str) -> int:
        """Count occurrences of pattern in content"""
        return content.count(pattern)
    
    def generate_report(self) -> Dict[str, Any]:
        """Generate comprehensive monitoring report"""
        timestamp = datetime.now().isoformat()
        uptime = time.time() - self.start_time
        
        return {
            'timestamp': timestamp,
            'uptime_seconds': round(uptime, 2),
            'system': self.get_system_metrics(),
            'nginx': self.get_nginx_stats(),
            'agent': self.get_agent_status(),
            'hls_storage': self.get_hls_storage_info()
        }
    
    def print_dashboard(self):
        """Print formatted dashboard"""
        report = self.generate_report()
        
        print("🚀 PREMIUM EZSTREAM MONITORING DASHBOARD")
        print("=" * 50)
        print(f"📅 Timestamp: {report['timestamp']}")
        print(f"⏱️  Uptime: {report['uptime_seconds']:.2f}s")
        print()
        
        # System metrics
        system = report['system']
        if 'error' not in system:
            print("💾 SYSTEM METRICS")
            print("-" * 20)
            memory = system['memory']
            print(f"Memory: {memory['used_gb']:.1f}GB / {memory['total_gb']:.1f}GB ({memory['percent']:.1f}%)")
            print(f"Available for streams: {memory['buffer_capacity_streams']} streams")
            print(f"CPU: {system['cpu']['percent']:.1f}% ({system['cpu']['count']} cores)")
            print(f"Disk: {system['disk']['used_gb']:.1f}GB / {system['disk']['total_gb']:.1f}GB ({system['disk']['percent']:.1f}%)")
            print()
        
        # Nginx stats
        nginx = report['nginx']
        print("🌐 NGINX RTMP STATUS")
        print("-" * 20)
        if nginx['status'] == 'running':
            print(f"Status: ✅ Running")
            print(f"Active Streams: {nginx['total_streams']}")
            print(f"Connected Clients: {nginx['total_clients']}")
            print(f"Bytes In: {nginx['bytes_in']}")
            print(f"Bytes Out: {nginx['bytes_out']}")
        else:
            print(f"Status: ❌ {nginx.get('message', 'Unknown error')}")
        print()
        
        # Agent status
        agent = report['agent']
        print("🤖 AGENT STATUS")
        print("-" * 20)
        if 'error' not in agent:
            print(f"Service: {'✅ Active' if agent['service_status'] == 'active' else '❌ Inactive'}")
            print(f"Processes: {agent['process_count']}")
            for proc in agent['processes']:
                print(f"  PID {proc['pid']}: {proc['memory_mb']:.1f}MB, {proc['cpu_percent']:.1f}% CPU")
        else:
            print(f"Error: {agent['message']}")
        print()
        
        # HLS storage
        hls = report['hls_storage']
        print("📺 HLS STORAGE")
        print("-" * 20)
        if hls['status'] != 'error':
            print(f"Total Size: {hls['total_size_mb']:.1f}MB")
            print(f"Files: {hls['file_count']}")
            print(f"Active Streams: {hls['stream_count']}")
            if hls['streams']:
                print(f"Streams: {', '.join(hls['streams'])}")
        else:
            print(f"Error: {hls['message']}")
        print()

if __name__ == "__main__":
    dashboard = PremiumMonitoringDashboard()
    dashboard.print_dashboard()
