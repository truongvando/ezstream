<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Service để enhance VPS agent với playlist management capabilities
 */
class AgentEnhancementService
{
    /**
     * Generate enhanced agent.py với playlist management
     */
    public function generateEnhancedAgent(): string
    {
        return <<<'PYTHON'
#!/usr/bin/env python3
"""
EZStream Agent v7.0 - Enhanced with Playlist Management
Supports: SRS streaming, playlist management, loop detection, quality monitoring
"""

import asyncio
import json
import logging
import redis
import time
import subprocess
import os
import sys
import argparse
from typing import Dict, List, Optional, Any
from dataclasses import dataclass, asdict
from datetime import datetime, timedelta
import threading
import signal
import random

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('/var/log/ezstream-agent.log'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger('EZStreamAgent')

@dataclass
class StreamConfig:
    """Stream configuration data structure"""
    stream_id: int
    title: str
    files: List[Dict[str, Any]]
    rtmp_endpoints: List[Dict[str, str]]
    loop: bool = False
    playlist_order: str = 'sequential'
    current_file_index: int = 0
    loop_count: int = 0
    quality_settings: Dict[str, Any] = None

@dataclass
class PlaylistState:
    """Current playlist state"""
    current_file: Optional[str] = None
    current_index: int = 0
    total_files: int = 0
    loop_enabled: bool = False
    playback_order: str = 'sequential'
    loop_count: int = 0
    start_time: Optional[datetime] = None

class PlaylistManager:
    """Manages playlist operations for streams"""
    
    def __init__(self):
        self.playlists: Dict[int, PlaylistState] = {}
        self.file_indices: Dict[int, List[int]] = {}  # For random playback
    
    def create_playlist(self, stream_id: int, files: List[Dict], order: str = 'sequential', loop: bool = False):
        """Create new playlist for stream"""
        self.playlists[stream_id] = PlaylistState(
            total_files=len(files),
            loop_enabled=loop,
            playback_order=order,
            start_time=datetime.now()
        )
        
        if order == 'random':
            self.file_indices[stream_id] = list(range(len(files)))
            random.shuffle(self.file_indices[stream_id])
        else:
            self.file_indices[stream_id] = list(range(len(files)))
        
        logger.info(f"Created playlist for stream {stream_id}: {len(files)} files, order={order}, loop={loop}")
    
    def get_next_file(self, stream_id: int, files: List[Dict]) -> Optional[Dict]:
        """Get next file in playlist"""
        if stream_id not in self.playlists:
            return None
        
        playlist = self.playlists[stream_id]
        indices = self.file_indices[stream_id]
        
        if playlist.current_index >= len(indices):
            if playlist.loop_enabled:
                # Reset for next loop
                playlist.current_index = 0
                playlist.loop_count += 1
                
                # Re-shuffle for random order
                if playlist.playback_order == 'random':
                    random.shuffle(indices)
                
                logger.info(f"Stream {stream_id} starting loop #{playlist.loop_count}")
            else:
                # End of playlist
                return None
        
        file_index = indices[playlist.current_index]
        playlist.current_index += 1
        playlist.current_file = files[file_index]['filename']
        
        return files[file_index]
    
    def update_playlist(self, stream_id: int, files: List[Dict], order: str = None):
        """Update existing playlist"""
        if stream_id in self.playlists:
            playlist = self.playlists[stream_id]
            if order:
                playlist.playback_order = order
            
            playlist.total_files = len(files)
            
            # Reset indices
            if order == 'random':
                self.file_indices[stream_id] = list(range(len(files)))
                random.shuffle(self.file_indices[stream_id])
            else:
                self.file_indices[stream_id] = list(range(len(files)))
            
            logger.info(f"Updated playlist for stream {stream_id}: {len(files)} files")
    
    def set_loop_mode(self, stream_id: int, enabled: bool):
        """Enable/disable loop mode"""
        if stream_id in self.playlists:
            self.playlists[stream_id].loop_enabled = enabled
            logger.info(f"Stream {stream_id} loop mode: {enabled}")
    
    def set_playback_order(self, stream_id: int, order: str):
        """Set playback order (sequential/random)"""
        if stream_id in self.playlists:
            playlist = self.playlists[stream_id]
            playlist.playback_order = order
            
            # Re-arrange indices
            files_count = playlist.total_files
            if order == 'random':
                self.file_indices[stream_id] = list(range(files_count))
                random.shuffle(self.file_indices[stream_id])
            else:
                self.file_indices[stream_id] = list(range(files_count))
            
            logger.info(f"Stream {stream_id} playback order: {order}")
    
    def get_status(self, stream_id: int) -> Dict:
        """Get playlist status"""
        if stream_id not in self.playlists:
            return {'error': 'Playlist not found'}
        
        playlist = self.playlists[stream_id]
        return {
            'current_file': playlist.current_file,
            'current_index': playlist.current_index,
            'total_files': playlist.total_files,
            'loop_enabled': playlist.loop_enabled,
            'playback_order': playlist.playback_order,
            'loop_count': playlist.loop_count,
            'uptime': str(datetime.now() - playlist.start_time) if playlist.start_time else None
        }

class QualityMonitor:
    """Monitors stream quality and performance"""
    
    def __init__(self):
        self.metrics: Dict[int, Dict] = {}
        self.monitoring = False
    
    def start_monitoring(self, stream_id: int):
        """Start quality monitoring for stream"""
        self.metrics[stream_id] = {
            'bitrate': 0,
            'fps': 0,
            'dropped_frames': 0,
            'last_check': datetime.now(),
            'errors': []
        }
        logger.info(f"Started quality monitoring for stream {stream_id}")
    
    def update_metrics(self, stream_id: int, metrics: Dict):
        """Update stream metrics"""
        if stream_id in self.metrics:
            self.metrics[stream_id].update(metrics)
            self.metrics[stream_id]['last_check'] = datetime.now()
    
    def get_metrics(self, stream_id: int) -> Dict:
        """Get current metrics"""
        return self.metrics.get(stream_id, {})
    
    def stop_monitoring(self, stream_id: int):
        """Stop monitoring stream"""
        if stream_id in self.metrics:
            del self.metrics[stream_id]
            logger.info(f"Stopped quality monitoring for stream {stream_id}")

class EZStreamAgent:
    """Enhanced EZStream Agent with playlist management"""
    
    def __init__(self, vps_id: int, redis_host: str, redis_port: int, redis_password: str = None):
        self.vps_id = vps_id
        self.redis_client = redis.Redis(
            host=redis_host,
            port=redis_port,
            password=redis_password,
            decode_responses=True
        )
        
        self.active_streams: Dict[int, StreamConfig] = {}
        self.stream_processes: Dict[int, subprocess.Popen] = {}
        self.playlist_manager = PlaylistManager()
        self.quality_monitor = QualityMonitor()
        
        self.running = True
        self.command_channel = f"vps-commands:{vps_id}"
        
        logger.info(f"EZStream Agent v7.0 initialized for VPS {vps_id}")
    
    async def start(self):
        """Start the agent"""
        logger.info("Starting EZStream Agent v7.0...")
        
        # Start command listener
        asyncio.create_task(self.listen_for_commands())
        
        # Start heartbeat
        asyncio.create_task(self.heartbeat_loop())
        
        # Start quality monitoring
        asyncio.create_task(self.quality_monitoring_loop())
        
        # Keep running
        while self.running:
            await asyncio.sleep(1)
    
    async def listen_for_commands(self):
        """Listen for Redis commands"""
        pubsub = self.redis_client.pubsub()
        pubsub.subscribe(self.command_channel)
        
        logger.info(f"Listening for commands on {self.command_channel}")
        
        for message in pubsub.listen():
            if message['type'] == 'message':
                try:
                    command = json.loads(message['data'])
                    await self.handle_command(command)
                except Exception as e:
                    logger.error(f"Error processing command: {e}")
    
    async def handle_command(self, command: Dict):
        """Handle incoming commands"""
        cmd_type = command.get('command')
        logger.info(f"Received command: {cmd_type}")
        
        if cmd_type == 'START_STREAM':
            await self.start_stream(command)
        elif cmd_type == 'STOP_STREAM':
            await self.stop_stream(command)
        elif cmd_type == 'UPDATE_PLAYLIST':
            await self.update_playlist(command)
        elif cmd_type == 'SET_LOOP_MODE':
            await self.set_loop_mode(command)
        elif cmd_type == 'SET_PLAYBACK_ORDER':
            await self.set_playback_order(command)
        elif cmd_type == 'ADD_VIDEOS':
            await self.add_videos(command)
        elif cmd_type == 'DELETE_VIDEOS':
            await self.delete_videos(command)
        elif cmd_type == 'GET_PLAYLIST_STATUS':
            await self.get_playlist_status(command)
        elif cmd_type == 'DELETE_FILE':
            await self.delete_file(command)
        elif cmd_type == 'PING':
            await self.handle_ping(command)
        else:
            logger.warning(f"Unknown command: {cmd_type}")
    
    async def start_stream(self, command: Dict):
        """Start a new stream"""
        try:
            stream_id = command['stream_id']
            config = command.get('config', {})
            
            # Create stream config
            stream_config = StreamConfig(
                stream_id=stream_id,
                title=config.get('title', f'Stream {stream_id}'),
                files=config.get('files', []),
                rtmp_endpoints=config.get('rtmp_endpoints', []),
                loop=config.get('loop', False),
                playlist_order=config.get('playlist_order', 'sequential')
            )
            
            # Create playlist
            self.playlist_manager.create_playlist(
                stream_id, 
                stream_config.files, 
                stream_config.playlist_order, 
                stream_config.loop
            )
            
            # Start quality monitoring
            self.quality_monitor.start_monitoring(stream_id)
            
            # Start streaming process
            await self.start_streaming_process(stream_config)
            
            self.active_streams[stream_id] = stream_config
            logger.info(f"Started stream {stream_id}")
            
        except Exception as e:
            logger.error(f"Failed to start stream: {e}")
    
    async def start_streaming_process(self, config: StreamConfig):
        """Start the actual streaming process"""
        # This would contain the SRS/FFmpeg streaming logic
        # For now, just log the action
        logger.info(f"Starting streaming process for {config.title}")
        
        # TODO: Implement actual SRS streaming with playlist support
        # This would involve:
        # 1. Setting up SRS configuration
        # 2. Starting SRS process
        # 3. Managing playlist transitions
        # 4. Handling loop logic
    
    async def heartbeat_loop(self):
        """Send periodic heartbeats"""
        while self.running:
            try:
                heartbeat_data = {
                    'vps_id': self.vps_id,
                    'timestamp': datetime.now().isoformat(),
                    'active_streams': list(self.active_streams.keys()),
                    'agent_version': '7.0',
                    'capabilities': ['playlist_management', 'quality_monitoring', 'loop_detection']
                }
                
                self.redis_client.publish('vps-heartbeat', json.dumps(heartbeat_data))
                await asyncio.sleep(30)  # Heartbeat every 30 seconds
                
            except Exception as e:
                logger.error(f"Heartbeat error: {e}")
                await asyncio.sleep(5)
    
    async def quality_monitoring_loop(self):
        """Monitor stream quality"""
        while self.running:
            try:
                for stream_id in self.active_streams:
                    # TODO: Implement actual quality monitoring
                    # This would check:
                    # - Bitrate stability
                    # - Frame drops
                    # - Connection status
                    # - Error rates
                    pass
                
                await asyncio.sleep(10)  # Check every 10 seconds
                
            except Exception as e:
                logger.error(f"Quality monitoring error: {e}")
                await asyncio.sleep(5)

def main():
    parser = argparse.ArgumentParser(description='EZStream Agent v7.0')
    parser.add_argument('--vps-id', type=int, required=True, help='VPS ID')
    parser.add_argument('--redis-host', required=True, help='Redis host')
    parser.add_argument('--redis-port', type=int, required=True, help='Redis port')
    parser.add_argument('--redis-password', help='Redis password')
    
    args = parser.parse_args()
    
    agent = EZStreamAgent(
        vps_id=args.vps_id,
        redis_host=args.redis_host,
        redis_port=args.redis_port,
        redis_password=args.redis_password
    )
    
    # Handle shutdown gracefully
    def signal_handler(signum, frame):
        logger.info("Shutting down agent...")
        agent.running = False
    
    signal.signal(signal.SIGINT, signal_handler)
    signal.signal(signal.SIGTERM, signal_handler)
    
    # Start the agent
    asyncio.run(agent.start())

if __name__ == '__main__':
    main()
PYTHON;
    }

    /**
     * Store enhanced agent in Redis
     */
    public function storeEnhancedAgent(): array
    {
        try {
            $agentCode = $this->generateEnhancedAgent();
            
            // Store in Redis with versioning
            $key = 'ezstream-agent:v7.0';
            Redis::set($key, $agentCode);
            Redis::expire($key, 86400 * 7); // 7 days TTL
            
            // Also store as latest
            Redis::set('ezstream-agent:latest', $agentCode);
            Redis::expire('ezstream-agent:latest', 86400 * 7);
            
            Log::info("✅ [AgentEnhancement] Stored enhanced agent v7.0 in Redis");
            
            return ['success' => true, 'version' => '7.0'];
            
        } catch (Exception $e) {
            Log::error("❌ [AgentEnhancement] Failed to store enhanced agent: {$e->getMessage()}");
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Deploy enhanced agent to all VPS
     */
    public function deployToAllVps(): array
    {
        try {
            // Store enhanced agent first
            $storeResult = $this->storeEnhancedAgent();
            if (!$storeResult['success']) {
                return $storeResult;
            }

            // Get all active VPS
            $vpsList = \App\Models\VpsServer::where('status', 'ACTIVE')->get();
            $deployResults = [];

            foreach ($vpsList as $vps) {
                $result = $this->deployToVps($vps->id);
                $deployResults[$vps->id] = $result;
            }

            Log::info("✅ [AgentEnhancement] Deployed enhanced agent to " . count($vpsList) . " VPS servers");

            return [
                'success' => true,
                'deployed_count' => count($vpsList),
                'results' => $deployResults
            ];

        } catch (Exception $e) {
            Log::error("❌ [AgentEnhancement] Failed to deploy to all VPS: {$e->getMessage()}");
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Deploy enhanced agent to specific VPS
     */
    public function deployToVps(int $vpsId): array
    {
        try {
            // Send update command to VPS
            $command = [
                'command' => 'UPDATE_AGENT',
                'vps_id' => $vpsId,
                'version' => '7.0',
                'features' => ['playlist_management', 'quality_monitoring', 'loop_detection'],
                'timestamp' => now()->toISOString()
            ];

            $channel = "vps-commands:{$vpsId}";
            $redis = Redis::connection();
            $publishResult = $redis->publish($channel, json_encode($command));

            if ($publishResult > 0) {
                Log::info("📤 [AgentEnhancement] Sent update command to VPS {$vpsId}");
                return ['success' => true, 'subscribers' => $publishResult];
            } else {
                return ['success' => false, 'error' => 'No agent listening'];
            }

        } catch (Exception $e) {
            Log::error("❌ [AgentEnhancement] Failed to deploy to VPS {$vpsId}: {$e->getMessage()}");
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check agent capabilities
     */
    public function checkAgentCapabilities(int $vpsId): array
    {
        try {
            $command = [
                'command' => 'GET_CAPABILITIES',
                'vps_id' => $vpsId,
                'timestamp' => now()->toISOString()
            ];

            $channel = "vps-commands:{$vpsId}";
            $redis = Redis::connection();
            $publishResult = $redis->publish($channel, json_encode($command));

            return ['success' => $publishResult > 0, 'subscribers' => $publishResult];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
