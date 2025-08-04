#!/bin/bash

# EZStream SRS Server Setup Script
# Sets up SRS server for BunnyCDN → YouTube streaming

set -e

echo "🎬 EZStream SRS Server Setup"
echo "================================"

# Configuration
SRS_VERSION="5.0-r3"
SRS_CONTAINER_NAME="ezstream-srs"
SRS_CONFIG_PATH="$(pwd)/srs.conf"
SRS_DATA_DIR="$(pwd)/srs-data"
SRS_LOGS_DIR="$(pwd)/logs"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if Docker is installed
check_docker() {
    log_info "Checking Docker installation..."
    if ! command -v docker &> /dev/null; then
        log_error "Docker is not installed. Please install Docker first."
        exit 1
    fi
    
    if ! docker info &> /dev/null; then
        log_error "Docker daemon is not running. Please start Docker."
        exit 1
    fi
    
    log_success "Docker is installed and running"
}

# Create necessary directories
create_directories() {
    log_info "Creating directories..."
    
    mkdir -p "$SRS_DATA_DIR"
    mkdir -p "$SRS_LOGS_DIR"
    mkdir -p "$SRS_DATA_DIR/nginx/html"
    
    log_success "Directories created"
}

# Stop existing SRS container if running
stop_existing_srs() {
    log_info "Checking for existing SRS container..."
    
    if docker ps -a --format "table {{.Names}}" | grep -q "^${SRS_CONTAINER_NAME}$"; then
        log_warning "Stopping existing SRS container..."
        docker stop "$SRS_CONTAINER_NAME" || true
        docker rm "$SRS_CONTAINER_NAME" || true
        log_success "Existing container removed"
    else
        log_info "No existing SRS container found"
    fi
}

# Pull SRS Docker image
pull_srs_image() {
    log_info "Pulling SRS Docker image..."
    docker pull "ossrs/srs:${SRS_VERSION}"
    log_success "SRS image pulled successfully"
}

# Validate SRS configuration
validate_config() {
    log_info "Validating SRS configuration..."
    
    if [ ! -f "$SRS_CONFIG_PATH" ]; then
        log_error "SRS configuration file not found: $SRS_CONFIG_PATH"
        exit 1
    fi
    
    log_success "SRS configuration file found"
}

# Start SRS container
start_srs_container() {
    log_info "Starting SRS container..."
    
    docker run -d \
        --name "$SRS_CONTAINER_NAME" \
        --restart unless-stopped \
        -p 1935:1935 \
        -p 1985:1985 \
        -p 8080:8080 \
        -v "$SRS_CONFIG_PATH:/usr/local/srs/conf/srs.conf" \
        -v "$SRS_DATA_DIR:/usr/local/srs/objs" \
        -v "$SRS_LOGS_DIR:/var/log/srs" \
        "ossrs/srs:${SRS_VERSION}"
    
    log_success "SRS container started"
}

# Wait for SRS to be ready
wait_for_srs() {
    log_info "Waiting for SRS to be ready..."
    
    local max_attempts=30
    local attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        if curl -s http://localhost:1985/api/v1/summaries > /dev/null 2>&1; then
            log_success "SRS is ready!"
            return 0
        fi
        
        log_info "Attempt $attempt/$max_attempts - waiting for SRS..."
        sleep 2
        ((attempt++))
    done
    
    log_error "SRS failed to start within expected time"
    return 1
}

# Test SRS API
test_srs_api() {
    log_info "Testing SRS API..."
    
    local response=$(curl -s http://localhost:1985/api/v1/summaries)
    if echo "$response" | grep -q '"code":0'; then
        log_success "SRS API is working correctly"
        echo "SRS Version: $(echo "$response" | grep -o '"version":"[^"]*"' | cut -d'"' -f4)"
    else
        log_error "SRS API test failed"
        return 1
    fi
}

# Show SRS status
show_status() {
    echo ""
    echo "🎬 SRS Server Status"
    echo "===================="
    echo "Container Name: $SRS_CONTAINER_NAME"
    echo "RTMP Port: 1935"
    echo "HTTP API Port: 1985"
    echo "HTTP Server Port: 8080"
    echo ""
    echo "URLs:"
    echo "- API: http://localhost:1985/api/v1/summaries"
    echo "- Console: http://localhost:8080/"
    echo "- RTMP: rtmp://localhost:1935/live/"
    echo ""
    echo "Configuration: $SRS_CONFIG_PATH"
    echo "Data Directory: $SRS_DATA_DIR"
    echo "Logs Directory: $SRS_LOGS_DIR"
    echo ""
    
    # Show container status
    echo "Container Status:"
    docker ps --filter "name=$SRS_CONTAINER_NAME" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
}

# Show usage information
show_usage() {
    echo ""
    echo "🎬 SRS Management Commands"
    echo "=========================="
    echo "Start SRS:    docker start $SRS_CONTAINER_NAME"
    echo "Stop SRS:     docker stop $SRS_CONTAINER_NAME"
    echo "Restart SRS:  docker restart $SRS_CONTAINER_NAME"
    echo "View logs:    docker logs -f $SRS_CONTAINER_NAME"
    echo "Remove SRS:   docker stop $SRS_CONTAINER_NAME && docker rm $SRS_CONTAINER_NAME"
    echo ""
    echo "Configuration file: $SRS_CONFIG_PATH"
    echo "After modifying config, restart container: docker restart $SRS_CONTAINER_NAME"
}

# Main setup function
main() {
    echo "Starting SRS setup process..."
    echo ""
    
    check_docker
    create_directories
    stop_existing_srs
    pull_srs_image
    validate_config
    start_srs_container
    
    if wait_for_srs && test_srs_api; then
        log_success "SRS Server setup completed successfully!"
        show_status
        show_usage
    else
        log_error "SRS setup failed. Check logs with: docker logs $SRS_CONTAINER_NAME"
        exit 1
    fi
}

# Handle script arguments
case "${1:-setup}" in
    "setup")
        main
        ;;
    "start")
        docker start "$SRS_CONTAINER_NAME"
        log_success "SRS container started"
        ;;
    "stop")
        docker stop "$SRS_CONTAINER_NAME"
        log_success "SRS container stopped"
        ;;
    "restart")
        docker restart "$SRS_CONTAINER_NAME"
        log_success "SRS container restarted"
        ;;
    "status")
        show_status
        ;;
    "logs")
        docker logs -f "$SRS_CONTAINER_NAME"
        ;;
    "remove")
        docker stop "$SRS_CONTAINER_NAME" || true
        docker rm "$SRS_CONTAINER_NAME" || true
        log_success "SRS container removed"
        ;;
    *)
        echo "Usage: $0 {setup|start|stop|restart|status|logs|remove}"
        echo ""
        echo "Commands:"
        echo "  setup   - Initial SRS server setup (default)"
        echo "  start   - Start SRS container"
        echo "  stop    - Stop SRS container"
        echo "  restart - Restart SRS container"
        echo "  status  - Show SRS status"
        echo "  logs    - Show SRS logs"
        echo "  remove  - Remove SRS container"
        exit 1
        ;;
esac
