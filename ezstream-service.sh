#!/bin/bash

# 🎛️ EZStream Service Manager
# Quản lý tất cả services như Python main.py

case "$1" in
    start)
        echo "🚀 Starting EZStream services..."
        sudo supervisorctl start ezstream-queue:*
        echo "✅ All services started!"
        ;;
    stop)
        echo "⏹️ Stopping EZStream services..."
        sudo supervisorctl stop ezstream-queue:*
        echo "✅ All services stopped!"
        ;;
    restart)
        echo "🔄 Restarting EZStream services..."
        sudo supervisorctl restart ezstream-queue:*
        echo "✅ All services restarted!"
        ;;
    status)
        echo "📊 EZStream Service Status:"
        ./check-services.sh
        ;;
    logs)
        echo "📋 Queue Worker Logs:"
        tail -f storage/logs/queue-worker.log
        ;;
    setup)
        echo "⚙️ Setting up EZStream services..."
        ./supervisor-setup.sh
        ;;
    *)
        echo "🎛️ EZStream Service Manager"
        echo "Usage: $0 {start|stop|restart|status|logs|setup}"
        echo ""
        echo "Commands:"
        echo "  start   - Start all services"
        echo "  stop    - Stop all services"  
        echo "  restart - Restart all services"
        echo "  status  - Check service status"
        echo "  logs    - View queue worker logs"
        echo "  setup   - Initial setup (run once)"
        echo ""
        echo "Examples:"
        echo "  $0 setup    # First time setup"
        echo "  $0 start    # Start services"
        echo "  $0 status   # Check if running"
        exit 1
        ;;
esac
