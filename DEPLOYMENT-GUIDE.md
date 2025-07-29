# 🚀 PREMIUM EZSTREAM DEPLOYMENT GUIDE
## For 96GB RAM VPS - No Memory Constraints!

### 📋 Pre-deployment Checklist

1. **VPS Requirements:**
   - ✅ 96GB RAM available
   - ✅ Ubuntu 20.04+ or CentOS 8+
   - ✅ Nginx with RTMP module installed
   - ✅ Python 3.8+ installed
   - ✅ Root/sudo access

2. **Current System Backup:**
   ```bash
   # Backup current nginx config
   sudo cp /etc/nginx/nginx.conf /etc/nginx/nginx.conf.backup
   
   # Backup current agent
   cp -r /opt/ezstream-agent /opt/ezstream-agent.backup
   ```

### 🚀 Deployment Steps

#### Step 1: Prepare Files
```bash
# Make deployment script executable
chmod +x scripts/deploy-premium-solution.sh

# Make monitoring dashboard executable
chmod +x storage/app/ezstream-agent/monitoring_dashboard.py
```

#### Step 2: Deploy Premium Solution
```bash
# Run the deployment script from project root
./scripts/deploy-premium-solution.sh
```

#### Step 3: Verify Deployment
```bash
# Check services status
sudo systemctl status nginx
sudo systemctl status ezstream-agent

# Test RTMP endpoint
telnet localhost 1935

# Test statistics endpoint
curl http://localhost:8080/stat

# Test health endpoint
curl http://localhost:8080/health
```

#### Step 4: Start Monitoring
```bash
# Monitoring dashboard is automatically installed by deployment script

# Run dashboard
ezstream-dashboard

# Run continuous monitoring (if you added the arguments)
python3 storage/app/ezstream-agent/monitoring_dashboard.py
```

### 📊 Expected Performance Metrics

#### Memory Usage:
- **Per Stream**: ~55MB (120s buffer + monitoring)
- **Maximum Streams**: ~1,700 concurrent streams
- **System Overhead**: ~5GB
- **Available for streams**: ~91GB

#### Performance Targets:
- **Restart Speed**: 2-3 seconds for DTS/PTS errors
- **Buffer Coverage**: 2 minutes seamless streaming
- **Uptime**: 99.9%+ with premium configuration
- **Error Recovery**: 95%+ automatic recovery rate

### 🔧 Configuration Details

#### Nginx RTMP Settings:
```nginx
buflen 120s;             # 2 minute buffer
chunk_size 16384;        # 16KB chunks  
max_message 50M;         # 50MB max message
ack_window 50000000;     # 50MB ack window
idle_streams on;         # Keep streams alive
drop_idle_publisher 300s; # 5 minute timeout
```

#### Agent Enhancements:
- ✅ Real-time stderr monitoring (1000 lines buffer)
- ✅ 16 comprehensive error patterns
- ✅ Health score tracking
- ✅ Performance metrics collection
- ✅ Smart restart logic

### 📈 Monitoring Endpoints

| Endpoint | Purpose | Access |
|----------|---------|--------|
| `http://localhost:8080/stat` | RTMP Statistics | Local only |
| `http://localhost:8080/control` | RTMP Control | Local only |
| `http://localhost:8080/health` | Health Check | Local only |
| `http://localhost:8080/hls/` | HLS Streams | Local only |

### 🛠️ Troubleshooting

#### Common Issues:

1. **Nginx fails to start:**
   ```bash
   # Check config syntax
   sudo nginx -t
   
   # Check error logs
   sudo tail -f /var/log/nginx/error.log
   ```

2. **Agent fails to start:**
   ```bash
   # Check agent logs
   sudo journalctl -u ezstream-agent -f
   
   # Check Python dependencies
   python3 -c "import psutil, requests"
   ```

3. **High memory usage:**
   ```bash
   # Monitor memory
   ezstream-dashboard --continuous
   
   # Reduce buffer if needed (edit nginx config)
   buflen 60s;  # Reduce from 120s to 60s
   ```

4. **Streams not restarting:**
   ```bash
   # Check real-time monitoring
   sudo tail -f /var/log/ezstream-agent.log | grep "REALTIME"
   
   # Check error patterns
   grep "DTS\|PTS" /var/log/ezstream-agent.log
   ```

### 🔄 Rollback Procedure

If deployment fails:

```bash
# Stop services
sudo systemctl stop ezstream-agent
sudo systemctl stop nginx

# Restore nginx config
sudo cp /etc/nginx/nginx.conf.backup /etc/nginx/nginx.conf

# Restore agent
sudo rm -rf /opt/ezstream-agent
sudo mv /opt/ezstream-agent.backup /opt/ezstream-agent

# Restart services
sudo systemctl start nginx
sudo systemctl start ezstream-agent
```

### 📞 Support Commands

```bash
# Generate system report
ezstream-dashboard --json > system-report.json

# Monitor performance
ezstream-monitor

# Check stream health
curl -s http://localhost:8080/stat | grep -E "(clients|streams)"

# View agent logs
sudo journalctl -u ezstream-agent --since "1 hour ago"
```

### 🎯 Success Criteria

✅ **Deployment Successful When:**
- Nginx RTMP listening on port 1935
- Agent service running without errors
- Statistics endpoint accessible
- Memory usage < 10GB baseline
- Test stream can start/stop/restart successfully

✅ **Performance Targets Met:**
- Stream restart < 5 seconds
- Memory per stream < 60MB
- CPU usage < 50% under load
- 99%+ uptime over 24 hours

### 🎉 Post-Deployment

1. **Update Laravel configuration** to use new buffer settings
2. **Test with real streams** to verify performance
3. **Set up monitoring alerts** for critical metrics
4. **Document any custom configurations** for your environment

**Ready for premium 24/7 streaming with 96GB RAM power! 🎬✨**
