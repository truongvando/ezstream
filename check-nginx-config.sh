#!/bin/bash

# Script kiểm tra nginx config trên VPS
# Chạy trên VPS: ./check-nginx-config.sh <STREAM_ID>

STREAM_ID="$1"

if [ -z "$STREAM_ID" ]; then
    echo "❌ Cần cung cấp Stream ID"
    echo "Sử dụng: $0 <STREAM_ID>"
    echo "Ví dụ: $0 94"
    exit 1
fi

echo "🔍 Checking Nginx Config for Stream #${STREAM_ID}"
echo "=============================================="

# 1. Kiểm tra file config
STREAM_CONFIG="/etc/nginx/rtmp-apps/stream_${STREAM_ID}.conf"
echo "1. Checking stream config file..."
if [ -f "$STREAM_CONFIG" ]; then
    echo "   ✅ Config file exists: $STREAM_CONFIG"
    echo "   📄 Content:"
    echo "   ----------------------------------------"
    cat "$STREAM_CONFIG" | sed 's/^/   /'
    echo "   ----------------------------------------"
    
    # Kiểm tra có stream key không
    if grep -q "rtmp://.*/..*" "$STREAM_CONFIG"; then
        echo "   ✅ Stream key found in RTMP URL"
        RTMP_URL=$(grep "push" "$STREAM_CONFIG" | awk '{print $2}' | tr -d ';')
        echo "   🎯 Full RTMP URL: $RTMP_URL"
    else
        echo "   ❌ No stream key in RTMP URL"
        echo "   💡 URL should be: rtmp://a.rtmp.youtube.com/live2/YOUR_STREAM_KEY"
    fi
else
    echo "   ❌ Config file not found: $STREAM_CONFIG"
    echo "   💡 Agent should create this when stream starts"
fi

# 2. Kiểm tra nginx có reload không
echo ""
echo "2. Checking nginx reload..."
if nginx -t 2>/dev/null; then
    echo "   ✅ Nginx config is valid"
    
    # Kiểm tra nginx có load config mới không
    if nginx -T 2>/dev/null | grep -q "stream_${STREAM_ID}"; then
        echo "   ✅ Stream config is loaded by nginx"
    else
        echo "   ❌ Stream config not loaded by nginx"
        echo "   🔧 Try: nginx -s reload"
    fi
else
    echo "   ❌ Nginx config has errors"
    nginx -t
fi

# 3. Kiểm tra FFmpeg process
echo ""
echo "3. Checking FFmpeg process..."
FFMPEG_COUNT=$(pgrep -f "ffmpeg.*stream_${STREAM_ID}" | wc -l)
if [ "$FFMPEG_COUNT" -gt 0 ]; then
    echo "   ✅ Found $FFMPEG_COUNT FFmpeg process(es)"
    echo "   📊 Process details:"
    ps aux | grep -E "ffmpeg.*stream_${STREAM_ID}" | grep -v grep | while read line; do
        echo "      $line"
    done
    
    # Kiểm tra FFmpeg có push đúng endpoint không
    FFMPEG_CMD=$(ps aux | grep -E "ffmpeg.*stream_${STREAM_ID}" | grep -v grep | head -1)
    if echo "$FFMPEG_CMD" | grep -q "rtmp://127.0.0.1:1935/stream_${STREAM_ID}"; then
        echo "   ✅ FFmpeg pushing to correct local endpoint"
    else
        echo "   ❌ FFmpeg not pushing to expected endpoint"
    fi
else
    echo "   ❌ No FFmpeg process found"
fi

# 4. Test RTMP endpoint
echo ""
echo "4. Testing RTMP endpoint..."
LOCAL_RTMP="rtmp://127.0.0.1:1935/stream_${STREAM_ID}/stream_${STREAM_ID}"
if command -v ffprobe >/dev/null 2>&1; then
    echo "   🔍 Testing: $LOCAL_RTMP"
    timeout 3 ffprobe -v quiet "$LOCAL_RTMP" 2>/dev/null
    if [ $? -eq 0 ]; then
        echo "   ✅ Local RTMP endpoint is accessible"
    else
        echo "   ❌ Local RTMP endpoint not accessible"
    fi
else
    echo "   ⚠️ ffprobe not available"
fi

# 5. Kiểm tra network connections
echo ""
echo "5. Checking network connections..."
echo "   📡 RTMP connections (port 1935):"
netstat -an | grep ":1935" | sed 's/^/      /'

echo "   📡 Outbound connections to YouTube:"
netstat -an | grep "74.125\|172.217\|216.58" | head -5 | sed 's/^/      /'

# 6. Kiểm tra logs
echo ""
echo "6. Checking recent logs..."
echo "   📜 Agent logs for stream $STREAM_ID:"
if [ -f "/var/log/ezstream-agent.log" ]; then
    grep "stream.*${STREAM_ID}" /var/log/ezstream-agent.log | tail -3 | sed 's/^/      /'
else
    echo "      ⚠️ Agent log not found"
fi

echo "   📜 Nginx error logs:"
if [ -f "/var/log/nginx/error.log" ]; then
    tail -3 /var/log/nginx/error.log | sed 's/^/      /'
else
    echo "      ⚠️ Nginx error log not found"
fi

# Summary
echo ""
echo "🎯 Summary:"
echo "=========="

ISSUES=0

if [ ! -f "$STREAM_CONFIG" ]; then
    echo "❌ Stream config missing"
    ((ISSUES++))
fi

if [ -f "$STREAM_CONFIG" ] && ! grep -q "rtmp://.*/..*" "$STREAM_CONFIG"; then
    echo "❌ Stream key missing in config"
    ((ISSUES++))
fi

if [ "$FFMPEG_COUNT" -eq 0 ]; then
    echo "❌ No FFmpeg process"
    ((ISSUES++))
fi

if ! nginx -t 2>/dev/null; then
    echo "❌ Nginx config invalid"
    ((ISSUES++))
fi

if [ "$ISSUES" -eq 0 ]; then
    echo "✅ All checks passed! Stream should be pushing to YouTube."
    echo ""
    echo "💡 Verify on YouTube:"
    echo "   1. Check YouTube Studio > Go Live"
    echo "   2. Look for 'Stream is live' status"
    echo "   3. Monitor viewer count"
else
    echo "⚠️ Found $ISSUES issue(s). Stream may not be working properly."
    echo ""
    echo "🔧 Quick fixes:"
    echo "   1. Restart stream: systemctl restart ezstream-agent"
    echo "   2. Reload nginx: nginx -s reload"
    echo "   3. Check Laravel logs for job errors"
fi
