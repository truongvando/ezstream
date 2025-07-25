#!/bin/bash

# Script debug nginx streaming trên VPS
# Sử dụng: ./debug-nginx-stream.sh <STREAM_ID>

STREAM_ID="$1"

if [ -z "$STREAM_ID" ]; then
    echo "❌ Cần cung cấp Stream ID"
    echo "Sử dụng: $0 <STREAM_ID>"
    echo "Ví dụ: $0 94"
    exit 1
fi

echo "🔍 Debug Nginx Stream #${STREAM_ID}"
echo "=================================="

# 1. Kiểm tra nginx có chạy không
echo "1. Checking Nginx status..."
if systemctl is-active --quiet nginx; then
    echo "   ✅ Nginx is running"
    nginx_pid=$(pgrep nginx | head -1)
    echo "   📊 Nginx PID: $nginx_pid"
else
    echo "   ❌ Nginx is not running"
    echo "   🔧 Starting nginx..."
    systemctl start nginx
fi

# 2. Kiểm tra nginx config chính
echo ""
echo "2. Checking main nginx config..."
if nginx -t 2>/dev/null; then
    echo "   ✅ Nginx config is valid"
else
    echo "   ❌ Nginx config has errors:"
    nginx -t
fi

# 3. Kiểm tra RTMP module
echo ""
echo "3. Checking RTMP module..."
if nginx -V 2>&1 | grep -q "rtmp"; then
    echo "   ✅ RTMP module is loaded"
else
    echo "   ❌ RTMP module not found"
    echo "   💡 Install with: apt install libnginx-mod-rtmp"
fi

# 4. Kiểm tra thư mục rtmp-apps
echo ""
echo "4. Checking rtmp-apps directory..."
RTMP_APPS_DIR="/etc/nginx/rtmp-apps"
if [ -d "$RTMP_APPS_DIR" ]; then
    echo "   ✅ Directory exists: $RTMP_APPS_DIR"
    echo "   📁 Contents:"
    ls -la "$RTMP_APPS_DIR" | sed 's/^/      /'
else
    echo "   ❌ Directory missing: $RTMP_APPS_DIR"
    echo "   🔧 Creating directory..."
    mkdir -p "$RTMP_APPS_DIR"
fi

# 5. Kiểm tra config file cho stream cụ thể
echo ""
echo "5. Checking stream config file..."
STREAM_CONFIG="$RTMP_APPS_DIR/stream_${STREAM_ID}.conf"
if [ -f "$STREAM_CONFIG" ]; then
    echo "   ✅ Stream config exists: $STREAM_CONFIG"
    echo "   📄 Content:"
    cat "$STREAM_CONFIG" | sed 's/^/      /'
else
    echo "   ❌ Stream config missing: $STREAM_CONFIG"
    echo "   💡 Should be created by agent when stream starts"
fi

# 6. Kiểm tra nginx main config có include rtmp-apps không
echo ""
echo "6. Checking nginx main config includes..."
if grep -q "include /etc/nginx/rtmp-apps/\*.conf" /etc/nginx/nginx.conf; then
    echo "   ✅ Main config includes rtmp-apps"
else
    echo "   ❌ Main config missing rtmp-apps include"
    echo "   💡 Add this line to rtmp block:"
    echo "      include /etc/nginx/rtmp-apps/*.conf;"
fi

# 7. Kiểm tra FFmpeg process
echo ""
echo "7. Checking FFmpeg processes..."
FFMPEG_PROCESSES=$(pgrep -f "ffmpeg.*stream_${STREAM_ID}" | wc -l)
if [ "$FFMPEG_PROCESSES" -gt 0 ]; then
    echo "   ✅ Found $FFMPEG_PROCESSES FFmpeg process(es) for stream $STREAM_ID"
    echo "   📊 Process details:"
    ps aux | grep -E "ffmpeg.*stream_${STREAM_ID}" | grep -v grep | sed 's/^/      /'
else
    echo "   ❌ No FFmpeg processes found for stream $STREAM_ID"
fi

# 8. Kiểm tra port 1935 (RTMP)
echo ""
echo "8. Checking RTMP port 1935..."
if netstat -tulpn | grep -q ":1935"; then
    echo "   ✅ Port 1935 is listening"
    netstat -tulpn | grep ":1935" | sed 's/^/      /'
else
    echo "   ❌ Port 1935 is not listening"
    echo "   💡 RTMP server may not be running"
fi

# 9. Test RTMP connection
echo ""
echo "9. Testing RTMP connection..."
if command -v ffprobe >/dev/null 2>&1; then
    echo "   🔍 Testing local RTMP endpoint..."
    timeout 5 ffprobe -v quiet -print_format json -show_streams "rtmp://127.0.0.1:1935/stream_${STREAM_ID}/stream_${STREAM_ID}" 2>/dev/null
    if [ $? -eq 0 ]; then
        echo "   ✅ RTMP endpoint is accessible"
    else
        echo "   ❌ RTMP endpoint not accessible or no stream"
    fi
else
    echo "   ⚠️ ffprobe not available for testing"
fi

# 10. Kiểm tra nginx error logs
echo ""
echo "10. Checking nginx error logs..."
if [ -f "/var/log/nginx/error.log" ]; then
    echo "   📜 Recent nginx errors:"
    tail -10 /var/log/nginx/error.log | sed 's/^/      /'
else
    echo "   ⚠️ Nginx error log not found"
fi

# 11. Kiểm tra agent logs
echo ""
echo "11. Checking agent logs for stream $STREAM_ID..."
if [ -f "/var/log/ezstream-agent.log" ]; then
    echo "   📜 Recent agent logs for stream $STREAM_ID:"
    grep "stream.*${STREAM_ID}" /var/log/ezstream-agent.log | tail -5 | sed 's/^/      /'
else
    echo "   ⚠️ Agent log not found"
fi

echo ""
echo "🎯 Summary for Stream #${STREAM_ID}:"
echo "=================================="

# Summary checks
ISSUES=0

if ! systemctl is-active --quiet nginx; then
    echo "❌ Nginx not running"
    ((ISSUES++))
fi

if ! nginx -t 2>/dev/null; then
    echo "❌ Nginx config invalid"
    ((ISSUES++))
fi

if [ ! -f "$STREAM_CONFIG" ]; then
    echo "❌ Stream config missing"
    ((ISSUES++))
fi

if [ "$FFMPEG_PROCESSES" -eq 0 ]; then
    echo "❌ No FFmpeg process"
    ((ISSUES++))
fi

if ! netstat -tulpn | grep -q ":1935"; then
    echo "❌ RTMP port not listening"
    ((ISSUES++))
fi

if [ "$ISSUES" -eq 0 ]; then
    echo "✅ All checks passed! Stream should be working."
    echo ""
    echo "💡 Next steps:"
    echo "   1. Check YouTube stream status"
    echo "   2. Verify stream key is correct"
    echo "   3. Monitor bandwidth usage"
else
    echo "⚠️ Found $ISSUES issue(s) that need attention."
    echo ""
    echo "💡 Troubleshooting:"
    echo "   1. Restart nginx: systemctl restart nginx"
    echo "   2. Restart agent: systemctl restart ezstream-agent"
    echo "   3. Check logs: tail -f /var/log/ezstream-agent.log"
fi
