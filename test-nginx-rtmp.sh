#!/bin/bash

# Script test nginx-rtmp proxy trên VPS
# Chạy script này trên VPS để test nginx-rtmp setup

echo "🧪 TESTING NGINX-RTMP PROXY SETUP"
echo "================================="

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ️ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️ $1${NC}"
}

# 1. Check nginx installation
echo -e "\n${BLUE}=== 1. CHECKING NGINX INSTALLATION ===${NC}"
if command -v nginx >/dev/null 2>&1; then
    print_success "Nginx is installed"
    nginx -v
else
    print_error "Nginx is not installed"
    exit 1
fi

# 2. Check nginx-rtmp module
echo -e "\n${BLUE}=== 2. CHECKING NGINX-RTMP MODULE ===${NC}"
if nginx -V 2>&1 | grep -q "rtmp"; then
    print_success "Nginx-RTMP module is available"
else
    print_error "Nginx-RTMP module is not installed"
    print_info "Install with: sudo apt install libnginx-mod-rtmp"
    exit 1
fi

# 3. Check nginx configuration
echo -e "\n${BLUE}=== 3. CHECKING NGINX CONFIGURATION ===${NC}"
if nginx -t >/dev/null 2>&1; then
    print_success "Nginx configuration is valid"
else
    print_error "Nginx configuration has errors:"
    nginx -t
    exit 1
fi

# 4. Check nginx status
echo -e "\n${BLUE}=== 4. CHECKING NGINX STATUS ===${NC}"
if systemctl is-active --quiet nginx; then
    print_success "Nginx is running"
else
    print_error "Nginx is not running"
    print_info "Start with: sudo systemctl start nginx"
    exit 1
fi

# 5. Check RTMP port
echo -e "\n${BLUE}=== 5. CHECKING RTMP PORT 1935 ===${NC}"
if netstat -tlnp 2>/dev/null | grep -q ":1935"; then
    print_success "RTMP port 1935 is listening"
    netstat -tlnp | grep ":1935"
else
    print_warning "RTMP port 1935 is not listening"
    print_info "Check nginx-rtmp configuration"
fi

# 6. Check statistics port
echo -e "\n${BLUE}=== 6. CHECKING STATISTICS PORT 8080 ===${NC}"
if netstat -tlnp 2>/dev/null | grep -q ":8080"; then
    print_success "Statistics port 8080 is listening"
    netstat -tlnp | grep ":8080"
else
    print_warning "Statistics port 8080 is not listening"
fi

# 7. Test RTMP statistics endpoint
echo -e "\n${BLUE}=== 7. TESTING RTMP STATISTICS ===${NC}"
if curl -s http://localhost:8080/stat >/dev/null 2>&1; then
    print_success "RTMP statistics endpoint is accessible"
    echo "Access at: http://$(hostname -I | awk '{print $1}'):8080/stat"
else
    print_warning "RTMP statistics endpoint is not accessible"
fi

# 8. Test health endpoint
echo -e "\n${BLUE}=== 8. TESTING HEALTH ENDPOINT ===${NC}"
health_response=$(curl -s http://localhost:8080/health 2>/dev/null)
if [ "$health_response" = "RTMP Proxy OK" ]; then
    print_success "Health endpoint is working: $health_response"
else
    print_warning "Health endpoint response: $health_response"
fi

# 9. Check log directories
echo -e "\n${BLUE}=== 9. CHECKING LOG DIRECTORIES ===${NC}"
if [ -d "/var/log/nginx/rtmp" ]; then
    print_success "RTMP log directory exists"
    ls -la /var/log/nginx/rtmp/
else
    print_warning "RTMP log directory does not exist"
    print_info "Create with: sudo mkdir -p /var/log/nginx/rtmp && sudo chown www-data:www-data /var/log/nginx/rtmp"
fi

# 10. Test simple RTMP publish (if ffmpeg available)
echo -e "\n${BLUE}=== 10. TESTING RTMP PUBLISH ===${NC}"
if command -v ffmpeg >/dev/null 2>&1; then
    print_info "Testing RTMP publish with test pattern..."
    
    # Create a 5-second test stream
    timeout 5 ffmpeg -f lavfi -i testsrc=duration=5:size=640x480:rate=30 \
                     -f lavfi -i sine=frequency=1000:duration=5 \
                     -c:v libx264 -preset ultrafast -tune zerolatency \
                     -c:a aac -f flv rtmp://127.0.0.1:1935/live/test \
                     >/dev/null 2>&1 &
    
    sleep 2
    
    # Check if stream is active
    if curl -s http://localhost:8080/stat | grep -q "test"; then
        print_success "RTMP publish test successful"
    else
        print_warning "RTMP publish test failed or stream not detected"
    fi
    
    # Kill any remaining ffmpeg processes
    pkill -f "rtmp://127.0.0.1:1935/live/test" 2>/dev/null || true
else
    print_warning "FFmpeg not available for RTMP publish test"
fi

# 11. Show nginx processes
echo -e "\n${BLUE}=== 11. NGINX PROCESSES ===${NC}"
ps aux | grep nginx | grep -v grep

# 12. Show recent nginx logs
echo -e "\n${BLUE}=== 12. RECENT NGINX LOGS ===${NC}"
echo "Error log (last 5 lines):"
tail -5 /var/log/nginx/error.log 2>/dev/null || echo "No error log found"

echo -e "\nAccess log (last 5 lines):"
tail -5 /var/log/nginx/access.log 2>/dev/null || echo "No access log found"

# Summary
echo -e "\n${BLUE}=== SUMMARY ===${NC}"
print_success "Nginx-RTMP proxy test completed"
echo "📊 Statistics: http://$(hostname -I | awk '{print $1}'):8080/stat"
echo "🏥 Health check: http://$(hostname -I | awk '{print $1}'):8080/health"
echo "📺 RTMP endpoint: rtmp://$(hostname -I | awk '{print $1}'):1935/live/YOUR_STREAM"
echo ""
echo "🔧 To test streaming:"
echo "ffmpeg -re -i your_video.mp4 -c copy -f flv rtmp://127.0.0.1:1935/live/test"
echo ""
echo "📋 Next steps:"
echo "1. Configure push targets in nginx-rtmp config"
echo "2. Test with actual video files"
echo "3. Monitor statistics during streaming"
