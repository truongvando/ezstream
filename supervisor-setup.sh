#!/bin/bash

# 🚀 EZStream Auto-Setup Script
# Tự động cài đặt và cấu hình tất cả services cần thiết

echo "🚀 Setting up EZStream services..."

# 1. Install Supervisor
if ! command -v supervisorctl &> /dev/null; then
    echo "📦 Installing Supervisor..."
    if [[ "$OSTYPE" == "linux-gnu"* ]]; then
        if command -v apt-get &> /dev/null; then
            sudo apt-get update && sudo apt-get install -y supervisor
        elif command -v yum &> /dev/null; then
            sudo yum install -y supervisor
        fi
    fi
fi

# 2. Create Supervisor config for Queue Worker
echo "⚙️ Configuring Queue Worker..."
sudo tee /etc/supervisor/conf.d/ezstream-queue.conf > /dev/null <<EOF
[program:ezstream-queue]
process_name=%(program_name)s_%(process_num)02d
command=php $(pwd)/artisan queue:work --sleep=3 --tries=3 --max-time=3600 --timeout=120
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=$(whoami)
numprocs=2
redirect_stderr=true
stdout_logfile=$(pwd)/storage/logs/queue-worker.log
stopwaitsecs=3600
EOF

# 3. Setup Laravel Scheduler (Cron)
echo "⏰ Setting up Laravel Scheduler..."
(crontab -l 2>/dev/null; echo "* * * * * cd $(pwd) && php artisan schedule:run >> /dev/null 2>&1") | crontab -

# 4. Start services
echo "🔄 Starting services..."
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start ezstream-queue:*

# 5. Enable auto-start
sudo systemctl enable supervisor

echo "✅ Setup complete!"
echo ""
echo "📋 Service Status:"
echo "Queue Worker: $(sudo supervisorctl status ezstream-queue:*)"
echo "Cron Job: $(crontab -l | grep schedule:run)"
echo ""
echo "🔧 Management Commands:"
echo "  Check status: sudo supervisorctl status"
echo "  Restart queue: sudo supervisorctl restart ezstream-queue:*"
echo "  View logs: tail -f storage/logs/queue-worker.log"
echo "  Manual queue: php artisan queue:work"
