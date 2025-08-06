#!/bin/bash

# EZStream SRS Server Setup Script
# Use Docker SRS for consistent deployment

set -e

echo "🎬 EZStream SRS Server Setup (Docker)"
echo "====================================="

# Configuration
CONTAINER_NAME="ezstream-srs"
SRS_IMAGE="ossrs/srs:5"

# Function to check if Docker is available
check_docker() {
    if ! command -v docker &> /dev/null; then
        echo "❌ Docker is not installed!"
        exit 1
    fi

    if ! docker info &> /dev/null; then
        echo "❌ Docker daemon is not running!"
        exit 1
    fi

    echo "✅ Docker is available"
}

# Function to stop and remove existing SRS containers/processes
cleanup_existing_srs() {
    echo "🛑 Cleaning up existing SRS installations..."

    # Stop and remove Docker containers
    docker stop "$CONTAINER_NAME" 2>/dev/null || true
    docker rm "$CONTAINER_NAME" 2>/dev/null || true

    # Stop native SRS processes
    pkill -f "srs -c" || true
    /usr/local/srs/etc/init.d/srs stop 2>/dev/null || true

    echo "✅ Cleanup completed"
}

# Function to start SRS Docker container
start_srs_docker() {
    echo "🚀 Starting SRS Docker container..."

    # Pull latest SRS image
    echo "📥 Pulling SRS Docker image..."
    docker pull "$SRS_IMAGE"

    # Start SRS container with proper configuration
    docker run -d \
        --name "$CONTAINER_NAME" \
        --restart unless-stopped \
        -p 1935:1935 \
        -p 1985:1985 \
        -p 8080:8080 \
        "$SRS_IMAGE"

    echo "✅ SRS Docker container started"
}

# Function to verify SRS is running
verify_srs() {
    echo "🔍 Verifying SRS installation..."

    # Wait for container to start
    echo "⏳ Waiting for SRS to start..."
    sleep 10

    # Check container status
    if docker ps --filter "name=$CONTAINER_NAME" --filter "status=running" | grep -q "$CONTAINER_NAME"; then
        echo "✅ SRS container is running"
    else
        echo "❌ SRS container is not running"
        docker logs "$CONTAINER_NAME" --tail 20
        exit 1
    fi

    # Test SRS API
    echo "🔍 Testing SRS API..."
    for i in {1..30}; do
        if curl -s http://localhost:1985/api/v1/versions > /dev/null 2>&1; then
            echo "✅ SRS API is responding!"
            break
        fi

        if [ $i -eq 30 ]; then
            echo "❌ SRS API not responding after 30 attempts"
            docker logs "$CONTAINER_NAME" --tail 20
            exit 1
        fi

        echo "⏳ Waiting for SRS API... (attempt $i/30)"
        sleep 2
    done

    # Show SRS info
    echo "📊 SRS Server Information:"
    echo "   - Container: $CONTAINER_NAME"
    echo "   - RTMP Port: 1935"
    echo "   - API Port: 1985"
    echo "   - HTTP Port: 8080"
    echo "   - API URL: http://localhost:1985/api/v1/versions"
    echo "   - RTMP URL: rtmp://localhost:1935/live/"

    # Test API response
    echo "📋 SRS Version Info:"
    curl -s http://localhost:1985/api/v1/versions | head -3 || echo "API response truncated"
}

# Main execution
main() {
    echo "🎬 Starting SRS Server setup..."

    check_docker
    cleanup_existing_srs
    start_srs_docker
    verify_srs

    echo "🎉 SRS Server setup completed successfully!"
    echo "🔗 You can access SRS API at: http://localhost:1985/api/v1/summaries"
}

# Handle script arguments
case "${1:-setup}" in
    "setup")
        main
        ;;
    "start")
        echo "🚀 Starting SRS container..."
        docker start "$CONTAINER_NAME" || start_srs_docker
        verify_srs
        ;;
    "stop")
        echo "🛑 Stopping SRS container..."
        docker stop "$CONTAINER_NAME"
        ;;
    "restart")
        echo "🔄 Restarting SRS container..."
        docker restart "$CONTAINER_NAME"
        verify_srs
        ;;
    "status")
        echo "🔍 SRS Container Status:"
        docker ps --filter "name=$CONTAINER_NAME"
        echo ""
        echo "🔍 SRS API Status:"
        curl -s http://localhost:1985/api/v1/summaries | head -3 || echo "API not responding"
        ;;
    "logs")
        echo "📋 SRS Container Logs:"
        docker logs "$CONTAINER_NAME" --tail 50
        ;;
    *)
        echo "Usage: $0 {setup|start|stop|restart|status|logs}"
        exit 1
        ;;
esac
