# 🎬 STREAM LIBRARY + SRS INTEGRATION GUIDE

## 📋 OVERVIEW

Hệ thống đã được thiết kế để tách biệt **Upload Processing** và **Streaming** thành 2 phases riêng biệt, tránh conflict giữa webhook và agent reports.

## 🔧 SETUP REQUIREMENTS

### 1. Environment Variables

```bash
# .env additions
BUNNYCDN_WEBHOOK_SECRET=sk_live_your_random_secret_key_here_123456789

# Existing BunnyCDN Stream Library config
BUNNYCDN_STREAM_API_KEY=fa483e83-fe7c-46f6-8906695a8d83-b93c-4c4c
BUNNYCDN_VIDEO_LIBRARY_ID=476035
BUNNYCDN_STREAM_CDN_HOSTNAME=vz-4d6ef824-aba.b-cdn.net
```

### 2. BunnyCDN Dashboard Configuration

1. **Login to BunnyCDN Dashboard**
2. **Go to Stream Library → Settings → Webhooks**
3. **Configure Webhook:**
   ```
   Webhook URL: https://your-domain.com/api/bunny/webhook/stream
   Secret Key: sk_live_your_random_secret_key_here_123456789
   ```
4. **Enable Events:**
   - ✅ video.uploaded
   - ✅ video.encoding.started
   - ✅ video.encoding.completed
   - ✅ video.encoding.failed

### 3. Database Migration

```bash
php artisan migrate
```

## 🎯 FLOW ARCHITECTURE

### Phase 1: Upload & Processing
```
User Upload → Stream Library → Video Processing → Webhook → Laravel
```

### Phase 2: Streaming
```
User Create Stream → Laravel Command → Agent → FFmpeg Direct → YouTube
```

## 📊 WEBHOOK SECURITY

### How Webhook Secret Works:

1. **User generates random secret**: `sk_live_abc123xyz789`
2. **Configure in both places**:
   - BunnyCDN Dashboard: Webhook Secret
   - Laravel .env: `BUNNYCDN_WEBHOOK_SECRET`
3. **BunnyCDN signs requests**:
   ```
   X-Bunny-Signature: sha256=calculated_hash
   ```
4. **Laravel verifies signature**:
   ```php
   $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);
   $isValid = hash_equals($expectedSignature, $receivedSignature);
   ```

## 🎬 STREAMING METHODS COMPARISON

| Feature | FFmpeg Method | SRS Method |
|---------|---------------|------------|
| **Upload Destination** | BunnyCDN Storage | BunnyCDN Stream Library |
| **File Format** | Direct MP4/Video | HLS Playlist |
| **Processing** | None | BunnyCDN Encoding |
| **Streaming** | FFmpeg → YouTube | SRS → YouTube |
| **Stability** | Good | Excellent |
| **Multi-stream** | Limited | Native |
| **Cost** | Storage + Bandwidth | Storage + Encoding + Bandwidth |

## 🔄 STATUS REPORTING INTEGRATION

### Agent Status Reporter Updates:

```python
# status_reporter.py now handles both FFmpeg and SRS streams
def heartbeat_loop():
    # Get FFmpeg streams
    ffmpeg_streams = stream_manager.get_active_streams()
    
    # Get SRS streams  
    srs_streams = srs_stream_manager.get_active_streams()
    
    # Combined reporting
    active_stream_ids = ffmpeg_streams + srs_streams
```

### Stream Status Flow:

```
SRS Stream Start → Agent Report → Laravel Update → User UI Refresh
```

## 📁 FILE MANAGEMENT UI

### File Types Display:

- **🎬 Stream Library Files**: Orange badge, đang xử lý
- **💾 Storage Files**: Blue badge, storage type
- **Processing Status**: Real-time updates via webhook

### File Storage Modes:

```
🤖 Auto: Stream Library for optimal quality
💾 Server: Local storage
☁️ CDN: CDN Storage Zone
🎬 Stream Library: Stream Library (optimized streaming)
🔄 Hybrid: Server + CDN backup
```

## 🎵 PLAYLIST STREAMING

### Multiple Videos Support:

```php
// User selects multiple videos
$playlist = [
    'video1' => 'https://vz-4d6ef824-aba.b-cdn.net/video1/playlist.m3u8',
    'video2' => 'https://vz-4d6ef824-aba.b-cdn.net/video2/playlist.m3u8', 
    'video3' => 'https://vz-4d6ef824-aba.b-cdn.net/video3/playlist.m3u8'
];

// Agent creates FFmpeg concat file
// SRS ingests playlist → YouTube
```

### Dynamic Playlist Updates:

```javascript
// User modifies playlist during streaming
updatePlaylist(streamId, [video1, video3, video5])
→ API call → Redis command → Agent
→ Generate new concat file → Restart SRS ingest
→ Minimal downtime transition
```

## 🚀 DEPLOYMENT CHECKLIST

### 1. Laravel Setup:
- [ ] Add webhook secret to .env
- [ ] Run migrations
- [ ] Configure BunnyCDN Stream Library credentials

### 2. BunnyCDN Setup:
- [ ] Configure webhook URL in dashboard
- [ ] Set webhook secret
- [ ] Enable required events
- [ ] Test webhook connection

### 3. VPS Setup:
- [ ] Provision VPS with SRS support
- [ ] Verify Docker installation
- [ ] Test SRS server accessibility
- [ ] Verify agent can connect to SRS API

### 4. Testing:
- [ ] Upload video to Stream Library
- [ ] Verify webhook notifications
- [ ] Test stream creation with processed video
- [ ] Verify SRS streaming functionality
- [ ] Test playlist streaming

## 🔍 TROUBLESHOOTING

### Common Issues:

1. **Webhook not received**:
   - Check webhook URL accessibility
   - Verify webhook secret matches
   - Check BunnyCDN dashboard logs

2. **Video processing stuck**:
   - Check BunnyCDN encoding status
   - Verify video format compatibility
   - Check file size limits

3. **SRS streaming fails**:
   - Verify SRS server is running
   - Check SRS API accessibility
   - Verify HLS URL accessibility

4. **Agent not reporting SRS streams**:
   - Check SRS stream manager initialization
   - Verify status reporter integration
   - Check Redis connectivity

### Debug Commands:

```bash
# Check SRS container status
docker ps --filter 'name=ezstream-srs'

# Test SRS API
curl http://localhost:1985/api/v1/summaries

# Check webhook endpoint
curl -X POST https://your-domain.com/api/bunny/webhook/test

# Monitor agent logs
tail -f /opt/ezstream-agent/logs/agent.log
```

## 💰 COST ANALYSIS

### Per Video (1GB, 24/7 streaming):

**Stream Library Method:**
- Storage: $0.01/month
- Encoding: $0.30 (one-time)
- Streaming: $0.30/month
- **Total: ~$0.61/month**

**Storage Method:**
- Storage: $0.01/month  
- Bandwidth: $0.30/month
- **Total: ~$0.31/month**

**Recommendation**: Use Stream Library for SRS (better stability), Storage for FFmpeg (lower cost).

## 🎯 BENEFITS ACHIEVED

✅ **Unified System** - Single platform handles both methods  
✅ **Real-time Updates** - Webhook notifications for processing  
✅ **User Separation** - Multi-tenant file management  
✅ **Dynamic Playlists** - Add/remove videos during streaming  
✅ **Cost Optimized** - Auto-select storage based on streaming method  
✅ **Fallback Ready** - Graceful degradation if SRS unavailable  
✅ **Status Integration** - Unified reporting for all stream types  

## 📞 SUPPORT

For issues or questions:
1. Check agent logs: `/opt/ezstream-agent/logs/`
2. Check Laravel logs: `storage/logs/laravel.log`
3. Monitor Redis: `redis-cli monitor`
4. Test webhook: Use BunnyCDN dashboard test feature
