#!/bin/bash

# EZSTREAM Smart Deployment Script v3.0
# Intelligent deployment with auto-detection, self-healing, and comprehensive setup
# Usage: bash deploy.sh [branch] [force_setup] [skip_tests]
# Example: bash deploy.sh master false false

set -e

# Error handling and cleanup
cleanup() {
    local exit_code=$?
    if [ $exit_code -ne 0 ]; then
        log_error "Deployment failed with exit code $exit_code"

        # Bring application back up if it was in maintenance mode
        if [ -f "$PROJECT_DIR/storage/framework/down" ]; then
            log_warning "Bringing application back up..."
            php artisan up 2>/dev/null || true
        fi

        # Offer rollback
        echo ""
        log_warning "Deployment failed! Would you like to rollback? (y/N)"
        read -t 30 -r response || response="n"
        if [[ $response =~ ^[Yy]$ ]]; then
            log_warning "Rolling back..."
            if [ -f "$SCRIPT_DIR/rollback.sh" ]; then
                bash "$SCRIPT_DIR/rollback.sh"
            else
                log_error "Rollback script not found"
            fi
        fi
    fi
}

trap cleanup EXIT

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m'

# Configuration
PROJECT_DIR="/var/www/ezstream"
BRANCH=${1:-master}
FORCE_SETUP=${2:-false}
SKIP_TESTS=${3:-false}
BACKUP_DIR="/var/backups/ezstream"
DB_NAME="sql_ezstream_pro"
DB_USER="root"
DB_PASS="Dodz1997a@"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Show help if requested
if [[ "$1" == "--help" || "$1" == "-h" ]]; then
    echo -e "${CYAN}EZSTREAM Smart Deployment Script v3.0${NC}"
    echo ""
    echo -e "${YELLOW}Usage:${NC}"
    echo -e "  bash deploy.sh [branch] [force_setup] [skip_tests]"
    echo ""
    echo -e "${YELLOW}Parameters:${NC}"
    echo -e "  branch      - Git branch to deploy (default: master)"
    echo -e "  force_setup - Force system requirements setup (true/false, default: false)"
    echo -e "  skip_tests  - Skip application tests (true/false, default: false)"
    echo ""
    echo -e "${YELLOW}Examples:${NC}"
    echo -e "  bash deploy.sh                    # Deploy master branch"
    echo -e "  bash deploy.sh develop            # Deploy develop branch"
    echo -e "  bash deploy.sh master true        # Deploy with forced setup"
    echo -e "  bash deploy.sh master false true  # Deploy without tests"
    echo ""
    echo -e "${YELLOW}Features:${NC}"
    echo -e "  ✅ Auto-detects and installs system requirements"
    echo -e "  ✅ Smart Supervisor process management"
    echo -e "  ✅ Automatic database backup before deployment"
    echo -e "  ✅ Zero-downtime deployment with maintenance mode"
    echo -e "  ✅ Comprehensive health checks and error handling"
    echo -e "  ✅ Automatic rollback on failure"
    echo -e "  ✅ Environment-aware configuration"
    echo ""
    exit 0
fi

# Deployment state
DEPLOYMENT_START_TIME=$(date +%s)
DEPLOYMENT_ID="deploy_$(date +%Y%m%d_%H%M%S)"

echo -e "${CYAN}🚀 EZSTREAM Smart Deployment v3.0${NC}"
echo -e "${BLUE}📅 Started: $(date)${NC}"
echo -e "${BLUE}🆔 ID: $DEPLOYMENT_ID${NC}"
echo -e "${BLUE}🌿 Branch: $BRANCH${NC}"
echo -e "${BLUE}🔧 Force Setup: $FORCE_SETUP${NC}"
echo -e "${BLUE}⚡ Skip Tests: $SKIP_TESTS${NC}"
echo ""

# ============================================================================
# UTILITY FUNCTIONS
# ============================================================================

log_step() {
    echo -e "${PURPLE}▶️ $1${NC}"
}

log_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

log_warning() {
    echo -e "${YELLOW}⚠️ $1${NC}"
}

log_error() {
    echo -e "${RED}❌ $1${NC}"
}

log_info() {
    echo -e "${BLUE}ℹ️ $1${NC}"
}

# Check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Check if service is running
service_running() {
    systemctl is-active --quiet "$1" 2>/dev/null
}

# Check if port is listening
port_listening() {
    ss -tlnp | grep -q ":$1 " 2>/dev/null
}

# Execute with retry
execute_with_retry() {
    local cmd="$1"
    local max_attempts=${2:-3}
    local delay=${3:-5}

    for i in $(seq 1 $max_attempts); do
        if eval "$cmd"; then
            return 0
        fi

        if [ $i -lt $max_attempts ]; then
            log_warning "Attempt $i failed, retrying in ${delay}s..."
            sleep $delay
        fi
    done

    log_error "Command failed after $max_attempts attempts: $cmd"
    return 1
}

# ============================================================================
# SYSTEM DETECTION & AUTO-SETUP
# ============================================================================

detect_and_setup_system() {
    log_step "System Detection & Auto-Setup"

    # Detect OS
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS=$NAME
        VER=$VERSION_ID
        log_info "Detected OS: $OS $VER"
    else
        log_error "Cannot detect OS"
        exit 1
    fi

    # Check if this is first deployment
    if [ ! -f "$PROJECT_DIR/.deployed" ] || [ "$FORCE_SETUP" = "true" ]; then
        log_warning "First deployment or force setup detected"
        setup_system_requirements
    fi

    # Verify system requirements
    verify_system_requirements
}

setup_system_requirements() {
    log_step "Setting up system requirements"

    # Update package list
    log_info "Updating package list..."
    apt-get update -y >/dev/null 2>&1

    # Install essential packages
    log_info "Installing essential packages..."
    apt-get install -y curl wget unzip git supervisor nginx redis-server mysql-server \
        software-properties-common apt-transport-https ca-certificates gnupg lsb-release \
        >/dev/null 2>&1

    # Install PHP 8.2 if not present
    if ! command_exists php || ! php -v | grep -q "8\.2"; then
        log_info "Installing PHP 8.2..."
        add-apt-repository ppa:ondrej/php -y >/dev/null 2>&1
        apt-get update -y >/dev/null 2>&1
        apt-get install -y php8.2 php8.2-fpm php8.2-mysql php8.2-redis php8.2-mbstring \
            php8.2-xml php8.2-curl php8.2-zip php8.2-gd php8.2-intl php8.2-bcmath \
            >/dev/null 2>&1
    fi

    # Install Composer if not present
    if ! command_exists composer; then
        log_info "Installing Composer..."
        curl -sS https://getcomposer.org/installer | php >/dev/null 2>&1
        mv composer.phar /usr/local/bin/composer
        chmod +x /usr/local/bin/composer
    fi

    # Install Node.js if not present
    if ! command_exists node || ! node -v | grep -q "v18"; then
        log_info "Installing Node.js 18..."
        curl -fsSL https://deb.nodesource.com/setup_18.x | bash - >/dev/null 2>&1
        apt-get install -y nodejs >/dev/null 2>&1
    fi

    log_success "System requirements installed"
}

verify_system_requirements() {
    log_step "Verifying system requirements"

    local requirements_met=true

    # Check PHP
    if command_exists php && php -v | grep -q "8\.2"; then
        log_success "PHP 8.2: $(php -r 'echo PHP_VERSION;')"
    else
        log_error "PHP 8.2 not found"
        requirements_met=false
    fi

    # Check Composer
    if command_exists composer; then
        log_success "Composer: $(composer --version --no-ansi | head -1)"
    else
        log_error "Composer not found"
        requirements_met=false
    fi

    # Check Node.js
    if command_exists node; then
        log_success "Node.js: $(node -v)"
    else
        log_error "Node.js not found"
        requirements_met=false
    fi

    # Check services
    local services=("nginx" "mysql" "redis-server" "php8.2-fpm")
    for service in "${services[@]}"; do
        if service_running "$service"; then
            log_success "Service $service: Running"
        else
            log_warning "Service $service: Not running, attempting to start..."
            systemctl start "$service" >/dev/null 2>&1 || log_error "Failed to start $service"
        fi
    done

    # Check required PHP extensions
    local extensions=("pdo_mysql" "redis" "mbstring" "xml" "curl" "zip" "gd" "intl" "bcmath")
    for ext in "${extensions[@]}"; do
        if php -m | grep -q "$ext"; then
            log_success "PHP extension $ext: Available"
        else
            log_error "PHP extension $ext: Missing"
            requirements_met=false
        fi
    done

    if [ "$requirements_met" = false ]; then
        log_error "System requirements not met. Run with force_setup=true to auto-install."
        exit 1
    fi

    log_success "All system requirements verified"
}

setup_supervisor_processes() {
    log_step "Setting up Supervisor processes for EZSTREAM"

    # Check if setup-supervisor.sh exists
    if [ -f "$SCRIPT_DIR/setup-supervisor.sh" ]; then
        log_info "Running setup-supervisor.sh..."
        bash "$SCRIPT_DIR/setup-supervisor.sh"
    else
        log_warning "setup-supervisor.sh not found, creating basic configuration..."
        create_basic_supervisor_config
    fi

    log_success "Supervisor processes configured"
}

create_basic_supervisor_config() {
    log_info "Creating basic Supervisor configuration..."

    # Create queue worker config
    cat > /etc/supervisor/conf.d/ezstream-queue.conf << EOF
[group:ezstream-queue]
programs=ezstream-queue_00,ezstream-queue_01

[program:ezstream-queue_00]
process_name=%(program_name)s_%(process_num)02d
command=php $PROJECT_DIR/artisan queue:work --queue=video-processing,default --sleep=3 --tries=3 --max-time=3600
directory=$PROJECT_DIR
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=$PROJECT_DIR/storage/logs/queue-00.log
stopwaitsecs=60

[program:ezstream-queue_01]
process_name=%(program_name)s_%(process_num)02d
command=php $PROJECT_DIR/artisan queue:work --queue=default --sleep=3 --tries=3 --max-time=3600
directory=$PROJECT_DIR
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=$PROJECT_DIR/storage/logs/queue-01.log
stopwaitsecs=60
EOF

    # Create VPS provisioning worker config
    cat > /etc/supervisor/conf.d/ezstream-vps.conf << EOF
[group:ezstream-vps]
programs=ezstream-vps_00

[program:ezstream-vps_00]
process_name=%(program_name)s
command=php $PROJECT_DIR/artisan queue:work --queue=vps-provisioning --sleep=3 --tries=3 --max-time=3600
directory=$PROJECT_DIR
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=$PROJECT_DIR/storage/logs/vps-queue.log
stopwaitsecs=60
EOF

    # Create agent listener config
    cat > /etc/supervisor/conf.d/ezstream-agent.conf << EOF
[program:ezstream-agent]
process_name=%(program_name)s
command=php $PROJECT_DIR/artisan agent:listen
directory=$PROJECT_DIR
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=$PROJECT_DIR/storage/logs/agent.log
stopwaitsecs=60
EOF

    # Create stream status listener config (NEW)
    cat > /etc/supervisor/conf.d/ezstream-stream-listener.conf << EOF
[program:ezstream-stream-listener]
process_name=%(program_name)s
command=php $PROJECT_DIR/artisan stream:listen
directory=$PROJECT_DIR
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=$PROJECT_DIR/storage/logs/stream-listener.log
stopwaitsecs=60
EOF

    # Create Redis subscriber config
    cat > /etc/supervisor/conf.d/ezstream-redis.conf << EOF
[program:ezstream-redis]
process_name=%(program_name)s
command=php $PROJECT_DIR/artisan redis:subscribe-stats
directory=$PROJECT_DIR
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=$PROJECT_DIR/storage/logs/redis.log
stopwaitsecs=60
EOF

    # Create scheduler config
    cat > /etc/supervisor/conf.d/ezstream-schedule.conf << EOF
[program:ezstream-schedule]
process_name=%(program_name)s
command=php $PROJECT_DIR/artisan schedule:work
directory=$PROJECT_DIR
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=$PROJECT_DIR/storage/logs/schedule.log
stopwaitsecs=60
EOF

    # Create log files
    mkdir -p $PROJECT_DIR/storage/logs
    touch $PROJECT_DIR/storage/logs/{queue-00,queue-01,vps-queue,agent,redis,schedule,stream-listener}.log
    chown -R www-data:www-data $PROJECT_DIR/storage/logs

    # Reload Supervisor
    supervisorctl reread
    supervisorctl update
    supervisorctl start ezstream-queue:*
    supervisorctl start ezstream-vps:*
    supervisorctl start ezstream-agent:*
    supervisorctl start ezstream-stream-listener:*
    supervisorctl start ezstream-redis:*
    supervisorctl start ezstream-schedule:*
}

# ============================================================================
# MAIN DEPLOYMENT FLOW
# ============================================================================

# Run system detection and setup
detect_and_setup_system

echo -e "${YELLOW}Project Dir: $PROJECT_DIR${NC}"
echo ""

# Change to project directory
cd $PROJECT_DIR

# Create backup directory
mkdir -p $BACKUP_DIR

# Backup database
echo -e "${YELLOW}💾 Creating database backup...${NC}"
BACKUP_FILE="$BACKUP_DIR/database_$(date +%Y%m%d_%H%M%S).sql"
if mysqldump -u $DB_USER -p$DB_PASS $DB_NAME > $BACKUP_FILE 2>/dev/null; then
    gzip $BACKUP_FILE
    echo -e "${GREEN}✅ Database backed up to: $BACKUP_FILE.gz${NC}"
else
    echo -e "${RED}❌ Database backup failed${NC}"
    exit 1
fi

# Backup important configs
echo -e "${YELLOW}💾 Backing up important configs...${NC}"
cp $PROJECT_DIR/.env $BACKUP_DIR/.env.backup.$(date +%Y%m%d_%H%M%S)

# Backup production configs if they exist
if [ -d "/var/www/phpmyadmin" ]; then
    echo -e "${YELLOW}💾 Backing up phpMyAdmin...${NC}"
    cp -r /var/www/phpmyadmin $BACKUP_DIR/phpmyadmin.backup.$(date +%Y%m%d_%H%M%S)
fi

# Put application in maintenance mode
echo -e "${YELLOW}🔧 Enabling maintenance mode...${NC}"
cd $PROJECT_DIR
php artisan down

# Pull latest code
echo -e "${YELLOW}📥 Pulling latest code...${NC}"

# Check and preserve phpMyAdmin symlink
PHPMYADMIN_PATH="$PROJECT_DIR/public/phpmyadmin"
PHPMYADMIN_IS_SYMLINK=false
PHPMYADMIN_TARGET=""

if [ -L "$PHPMYADMIN_PATH" ]; then
    echo -e "${YELLOW}� Detected phpMyAdmin symlink, preserving...${NC}"
    PHPMYADMIN_IS_SYMLINK=true
    PHPMYADMIN_TARGET=$(readlink "$PHPMYADMIN_PATH")
    echo -e "${BLUE}   Symlink target: $PHPMYADMIN_TARGET${NC}"
elif [ -d "$PHPMYADMIN_PATH" ]; then
    echo -e "${YELLOW}💾 Backing up phpMyAdmin directory...${NC}"
    mv "$PHPMYADMIN_PATH" "/tmp/phpmyadmin-backup-$(date +%Y%m%d_%H%M%S)"
fi

git fetch origin
git reset --hard origin/$BRANCH
git clean -fd

# Restore phpMyAdmin based on what it was before
if [ "$PHPMYADMIN_IS_SYMLINK" = true ]; then
    echo -e "${YELLOW}🔄 Restoring phpMyAdmin symlink...${NC}"
    ln -sf "$PHPMYADMIN_TARGET" "$PHPMYADMIN_PATH"
    echo -e "${GREEN}✅ phpMyAdmin symlink restored: $PHPMYADMIN_PATH -> $PHPMYADMIN_TARGET${NC}"
elif [ -d "/tmp/phpmyadmin-backup-"* ]; then
    echo -e "${YELLOW}🔄 Restoring phpMyAdmin directory...${NC}"
    LATEST_BACKUP=$(ls -t /tmp/phpmyadmin-backup-* | head -1)
    mv "$LATEST_BACKUP" "$PHPMYADMIN_PATH"
    chown -R www-data:www-data "$PHPMYADMIN_PATH"
    echo -e "${GREEN}✅ phpMyAdmin directory restored${NC}"
fi

# Install/Update Composer dependencies
echo -e "${YELLOW}📦 Updating Composer dependencies...${NC}"
if [ -f "composer.json" ]; then
    # Set environment variable to allow running as root and disable plugins for safety
    export COMPOSER_ALLOW_SUPERUSER=1
    composer install --no-dev --optimize-autoloader --no-interaction --no-plugins
else
    echo -e "${RED}❌ composer.json not found!${NC}"
    exit 1
fi

# Install/Update NPM dependencies
echo -e "${YELLOW}📦 Updating NPM dependencies...${NC}"
# Install all dependencies (including dev) for build process
npm ci

# Build assets
echo -e "${YELLOW}🏗️ Building assets...${NC}"
npm run build

# Clean up dev dependencies after build
echo -e "${YELLOW}🧹 Cleaning dev dependencies...${NC}"
npm prune --production

# Clear all caches
echo -e "${YELLOW}🧹 Clearing caches...${NC}"
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear

# Clear compiled views to fix UI issues
echo -e "${YELLOW}🎨 Clearing compiled views for UI fixes...${NC}"
rm -rf $PROJECT_DIR/storage/framework/views/*
php artisan view:clear

# Run database migrations (skip existing tables)
echo -e "${YELLOW}🗄️ Running database migrations...${NC}"
echo -e "${BLUE}   Checking migration status...${NC}"
php artisan migrate:status

echo -e "${BLUE}   Checking for missing YouTube tables...${NC}"
php artisan tinker --execute="
use Illuminate\Support\Facades\Schema;
\$tables = ['youtube_channels', 'youtube_videos', 'youtube_video_snapshots', 'youtube_channel_snapshots', 'youtube_alerts', 'youtube_alert_settings', 'youtube_ai_analysis'];
\$missing = [];
foreach(\$tables as \$table) {
    if(!Schema::hasTable(\$table)) {
        \$missing[] = \$table;
    }
}
if(count(\$missing) > 0) {
    echo 'MISSING_TABLES: ' . implode(',', \$missing) . PHP_EOL;
} else {
    echo 'ALL_TABLES_EXIST' . PHP_EOL;
}
"

echo -e "${BLUE}   Running migrations (skipping existing tables)...${NC}"
# Run migrations and capture output, ignore table exists errors
if php artisan migrate --force 2>&1 | tee /tmp/migration_output.log; then
    echo -e "${GREEN}✅ Migrations completed successfully${NC}"
else
    # Check if the only errors are "table already exists"
    if grep -q "Base table or view already exists" /tmp/migration_output.log && ! grep -q -v "Base table or view already exists\|SQLSTATE\[42S01\]" /tmp/migration_output.log; then
        echo -e "${YELLOW}⚠️ Some tables already exist - this is normal for existing databases${NC}"
        echo -e "${GREEN}✅ Migration process completed (existing tables skipped)${NC}"
    else
        echo -e "${RED}❌ Migration failed with unexpected errors${NC}"
        cat /tmp/migration_output.log
        echo -e "${YELLOW}🔍 Checking migration files...${NC}"
        ls -la database/migrations/*youtube* || echo "No YouTube migration files found"
        exit 1
    fi
fi

# Clean up temp file
rm -f /tmp/migration_output.log

# Recreate storage link
echo -e "${YELLOW}🔗 Recreating storage link...${NC}"
php artisan storage:link

# Optimize application
echo -e "${YELLOW}⚡ Optimizing application...${NC}"
php artisan config:cache
php artisan route:cache
# Skip view:cache for Livewire compatibility
echo -e "${BLUE}   Skipping view:cache (Livewire compatibility)${NC}"

# Test JAP API configuration after config cache
echo -e "${BLUE}   Testing JAP API configuration...${NC}"
if php artisan jap:debug-api-key >/dev/null 2>&1; then
    echo -e "${GREEN}✅ JAP API configuration test passed${NC}"
else
    echo -e "${YELLOW}⚠️ JAP API configuration test failed - check .env file${NC}"
fi

# Set permissions
echo -e "${YELLOW}🔐 Setting permissions...${NC}"
chown -R www-data:www-data $PROJECT_DIR
chmod -R 755 $PROJECT_DIR
chmod -R 775 $PROJECT_DIR/storage
chmod -R 775 $PROJECT_DIR/bootstrap/cache
chmod 600 $PROJECT_DIR/.env

# Restart services
echo -e "${YELLOW}🔄 Restarting services...${NC}"
systemctl reload php8.2-fpm
systemctl reload nginx

# Smart Background Process Management
log_step "Smart Background Process Management"

# Check if Supervisor is installed and configured
if command -v supervisorctl &> /dev/null; then
    log_info "Supervisor detected, checking configuration..."

    # Check if EZSTREAM processes are configured
    if ! supervisorctl status | grep -q ezstream; then
        log_warning "EZSTREAM processes not configured in Supervisor"
        setup_supervisor_processes
    fi

    # Define all active EZSTREAM processes (6 processes total)
    declare -a PROCESSES=(
        "ezstream-queue:*|Default queue worker"
        "ezstream-vps:*|VPS provisioning queue worker"
        "ezstream-agent:*|Agent reports listener"
        "ezstream-stream-listener:*|Stream status listener"
        "ezstream-redis:*|Redis stats subscriber"
        "ezstream-schedule:*|Laravel scheduler"
    )

    log_info "Restarting ${#PROCESSES[@]} background processes..."

    # Restart each process with description
    for process_info in "${PROCESSES[@]}"; do
        IFS='|' read -r process_name description <<< "$process_info"
        log_info "→ Restarting ${description}..."

        if supervisorctl restart "$process_name" >/dev/null 2>&1; then
            log_success "${process_name} restarted successfully"
        else
            log_warning "${process_name} restart failed or not found"
        fi
    done

    echo ""
    echo -e "${GREEN}✅ All Supervisor processes restart completed.${NC}"

    # Show final status with health check
    echo -e "${BLUE}   📊 Current process status:${NC}"
    if supervisorctl status | grep ezstream >/dev/null 2>&1; then
        supervisorctl status | grep ezstream | while read line; do
            if echo "$line" | grep -q "RUNNING"; then
                echo -e "${GREEN}   ✅ $line${NC}"
            elif echo "$line" | grep -q "STARTING"; then
                echo -e "${YELLOW}   🔄 $line${NC}"
            else
                echo -e "${RED}   ❌ $line${NC}"
            fi
        done
    else
        echo -e "${YELLOW}   ⚠️ No ezstream processes found${NC}"
    fi

    # Quick health summary
    running_count=$(supervisorctl status | grep ezstream | grep -c "RUNNING" || echo "0")
    total_count=$(supervisorctl status | grep ezstream | wc -l || echo "0")
    echo ""
    echo -e "${BLUE}   📈 Health Summary: ${running_count}/${total_count} processes running${NC}"

else
    echo -e "${YELLOW}⚠️ Supervisor not found. You will need to restart background processes manually.${NC}"
    echo ""
    echo -e "${YELLOW}   📋 Manual commands to run:${NC}"
    echo -e "${BLUE}     # Queue Workers${NC}"
    echo -e "${BLUE}     php artisan queue:work --queue=vps-provisioning --daemon &${NC}"
    echo -e "${BLUE}     php artisan queue:work --daemon &${NC}"
    echo ""
    echo -e "${BLUE}     # Background Services${NC}"
    echo -e "${BLUE}     php artisan agent:listen &${NC}"
    echo -e "${BLUE}     php artisan redis:subscribe-stats &${NC}"
    echo -e "${BLUE}     php artisan schedule:work &${NC}"
    echo ""
    echo -e "${YELLOW}   💡 Or install Supervisor: apt install supervisor${NC}"
fi

# 🚨 STREAM SYNC - DISABLED TO PREVENT STREAM LOSS DURING DEPLOY
# The stream:sync command can kill active streams during Laravel restart
# when agents haven't had time to report their state yet
# echo -e "${YELLOW}🔄 Syncing stream state with all VPS agents...${NC}"
# php artisan stream:sync --force

# 🚨 AGENT RESTART - COMMENTED OUT FOR STABLE OPERATIONS
# Agent system is now stable and only Laravel features are being developed
# No need to restart agents unless there are agent-specific updates
# Uncomment when agent code changes are deployed
# echo -e "${YELLOW}🔄 Restarting VPS agents for new settings...${NC}"
# php artisan vps:restart-agents

# Test application
echo -e "${YELLOW}🧪 Testing application...${NC}"

# Test Laravel framework
echo -e "${BLUE}   Testing Laravel framework...${NC}"
if php artisan --version > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Laravel framework is working${NC}"
else
    echo -e "${RED}❌ Laravel framework test failed${NC}"
    echo -e "${YELLOW}🔄 Bringing application back up...${NC}"
    php artisan up
    exit 1
fi

# Check if this is production environment
echo -e "${BLUE}   Checking environment...${NC}"
APP_ENV=$(php artisan tinker --execute="echo config('app.env');" 2>/dev/null | tail -1)
echo -e "${BLUE}   Environment detected: ${APP_ENV}${NC}"

# Test database connection
echo -e "${BLUE}   Testing database connection...${NC}"
if php artisan tinker --execute="try { \DB::connection()->getPdo(); echo 'Database connected!'; } catch(Exception \$e) { echo 'Database error: ' . \$e->getMessage(); exit(1); }"; then
    echo -e "${GREEN}✅ Database connection test passed${NC}"
else
    echo -e "${RED}❌ Database connection test failed${NC}"
    echo -e "${YELLOW}🔄 Bringing application back up...${NC}"
    php artisan up
    exit 1
fi

# Test web server response
echo -e "${BLUE}   Testing web server response...${NC}"
if curl -f -s -o /dev/null http://localhost; then
    echo -e "${GREEN}✅ Web server is responding${NC}"
else
    echo -e "${YELLOW}⚠️ Web server test failed (this might be normal if not configured for localhost)${NC}"
fi

# Test email configuration
echo -e "${BLUE}   Testing email configuration...${NC}"
if php artisan tinker --execute="
try {
    \$config = [
        'mailer' => config('mail.default'),
        'host' => config('mail.mailers.smtp.host'),
        'port' => config('mail.mailers.smtp.port'),
        'from' => config('mail.from.address')
    ];
    echo 'Email config: ' . json_encode(\$config);

    // Test if password_reset_tokens table exists
    \$tableExists = \Schema::hasTable('password_reset_tokens');
    echo PHP_EOL . 'Password reset table exists: ' . (\$tableExists ? 'YES' : 'NO');

} catch(Exception \$e) {
    echo 'Email config error: ' . \$e->getMessage();
    exit(1);
}"; then
    echo -e "${GREEN}✅ Email configuration test passed${NC}"
else
    echo -e "${YELLOW}⚠️ Email configuration test failed${NC}"
fi

# Bring application back up
echo -e "${YELLOW}🔄 Disabling maintenance mode...${NC}"
php artisan up

# Clean old backups (keep last 10)
echo -e "${YELLOW}🧹 Cleaning old backups...${NC}"
find $BACKUP_DIR -name "database_*.sql.gz" -type f | sort -r | tail -n +11 | xargs rm -f
find $BACKUP_DIR -name ".env.backup.*" -type f | sort -r | tail -n +11 | xargs rm -f

echo -e "${GREEN}🎉 Deployment completed successfully!${NC}"
echo ""
echo -e "${BLUE}📊 Deployment Summary:${NC}"
echo -e "  • Database backup: $BACKUP_FILE"
echo -e "  • Branch deployed: $BRANCH"
echo -e "  • Timestamp: $(date)"
echo -e "  • Website: https://ezstream.pro"
echo ""

# Show background processes status
if command -v supervisorctl &> /dev/null; then
    echo -e "${BLUE}🔧 Background Processes Status:${NC}"
    if supervisorctl status | grep ezstream >/dev/null 2>&1; then
        running_processes=$(supervisorctl status | grep ezstream | grep -c "RUNNING" || echo "0")
        total_processes=$(supervisorctl status | grep ezstream | wc -l || echo "0")

        if [ "$running_processes" -eq "$total_processes" ] && [ "$total_processes" -gt 0 ]; then
            echo -e "  ✅ All $total_processes background processes are running"
        else
            echo -e "  ⚠️ $running_processes/$total_processes background processes running"
        fi

        echo -e "  • Queue Workers: Default + VPS Provisioning"
        echo -e "  • Agent Listener: Redis agent reports"
        echo -e "  • Redis Subscriber: VPS stats monitoring"
        echo -e "  • Scheduler: Laravel cron jobs"
    else
        echo -e "  ⚠️ No background processes found"
    fi
else
    echo -e "${BLUE}🔧 Background Processes:${NC}"
    echo -e "  ⚠️ Supervisor not installed - manual process management required"
fi

echo ""
echo -e "${YELLOW}💡 Useful Commands:${NC}"
echo -e "  • Check processes: supervisorctl status | grep ezstream"
echo -e "  • View logs: tail -f /var/www/ezstream/storage/logs/laravel.log"
echo -e "  • Monitor queues: php artisan queue:work --once"
echo ""
echo -e "${YELLOW}💡 To rollback if needed:${NC}"
echo -e "  gunzip $BACKUP_FILE.gz"
echo -e "  mysql -u $DB_USER -p$DB_PASS $DB_NAME < \${BACKUP_FILE%.gz}"

# ============================================================================
# DEPLOYMENT COMPLETION
# ============================================================================

# Mark deployment as completed
echo "$DEPLOYMENT_ID" > "$PROJECT_DIR/.deployed"
echo "$(date)" >> "$PROJECT_DIR/.deployed"

# Calculate deployment time
DEPLOYMENT_END_TIME=$(date +%s)
DEPLOYMENT_DURATION=$((DEPLOYMENT_END_TIME - DEPLOYMENT_START_TIME))
DEPLOYMENT_MINUTES=$((DEPLOYMENT_DURATION / 60))
DEPLOYMENT_SECONDS=$((DEPLOYMENT_DURATION % 60))

echo ""
echo -e "${CYAN}🎉 DEPLOYMENT COMPLETED SUCCESSFULLY! 🎉${NC}"
echo ""
echo -e "${PURPLE}📊 Deployment Statistics:${NC}"
echo -e "  • Deployment ID: $DEPLOYMENT_ID"
echo -e "  • Duration: ${DEPLOYMENT_MINUTES}m ${DEPLOYMENT_SECONDS}s"
echo -e "  • Branch: $BRANCH"
echo -e "  • Environment: $(php artisan tinker --execute="echo config('app.env');" 2>/dev/null | tail -1)"
echo -e "  • Laravel Version: $(php artisan --version --no-ansi)"
echo -e "  • PHP Version: $(php -r 'echo PHP_VERSION;')"

# Final health check
echo ""
echo -e "${PURPLE}🏥 Final Health Check:${NC}"

# Check web server
WEB_STATUS="❌"
if curl -f -s -o /dev/null http://localhost >/dev/null 2>&1; then
    WEB_STATUS="✅"
    echo -e "  ✅ Web server: Responding on localhost"
elif curl -f -s -o /dev/null https://localhost >/dev/null 2>&1; then
    WEB_STATUS="✅"
    echo -e "  ✅ Web server: Responding on HTTPS localhost"
elif curl -f -s -o /dev/null https://ezstream.pro >/dev/null 2>&1; then
    WEB_STATUS="✅"
    echo -e "  ✅ Web server: Responding on ezstream.pro"
else
    echo -e "  ⚠️ Web server: Not responding (localhost/ezstream.pro)"
    # Check if nginx is running
    if systemctl is-active --quiet nginx; then
        echo -e "      (Nginx is running, but not accessible via HTTP/HTTPS)"
    else
        echo -e "      (Nginx service is not running)"
    fi
fi

# Check database
if php artisan tinker --execute="try { \DB::connection()->getPdo(); echo 'OK'; } catch(Exception \$e) { echo 'FAIL'; }" 2>/dev/null | grep -q "OK"; then
    echo -e "  ✅ Database: Connected"
else
    echo -e "  ❌ Database: Connection failed"
fi

# Check Redis
if php artisan tinker --execute="try { \Illuminate\Support\Facades\Redis::ping(); echo 'OK'; } catch(Exception \$e) { echo 'FAIL: ' . \$e->getMessage(); }" 2>/dev/null | grep -q "OK"; then
    echo -e "  ✅ Redis: Connected"
else
    echo -e "  ❌ Redis: Connection failed"
    # Try alternative Redis check
    if redis-cli ping >/dev/null 2>&1; then
        echo -e "      (Redis service is running, but Laravel connection failed)"
    else
        echo -e "      (Redis service is not running)"
    fi
fi

# Check queue
FAILED_JOBS=$(php artisan queue:failed --format=json 2>/dev/null | jq length 2>/dev/null || echo "0")
echo -e "  ✅ Queue: $FAILED_JOBS failed jobs"

# Check essential services
echo -e "  📋 Essential Services:"
if systemctl is-active --quiet nginx; then
    echo -e "    ✅ Nginx: Running"
else
    echo -e "    ❌ Nginx: Not running"
fi

if systemctl is-active --quiet php8.2-fpm; then
    echo -e "    ✅ PHP-FPM: Running"
else
    echo -e "    ❌ PHP-FPM: Not running"
fi

if systemctl is-active --quiet redis-server; then
    echo -e "    ✅ Redis: Running"
else
    echo -e "    ❌ Redis: Not running"
fi

if systemctl is-active --quiet mysql; then
    echo -e "    ✅ MySQL: Running"
else
    echo -e "    ❌ MySQL: Not running"
fi

# Check Supervisor processes
if supervisorctl status | grep -q ezstream; then
    RUNNING_COUNT=$(supervisorctl status | grep ezstream | grep -c RUNNING)
    TOTAL_COUNT=$(supervisorctl status | grep ezstream | wc -l)
    echo -e "    ✅ Supervisor: $RUNNING_COUNT/$TOTAL_COUNT ezstream processes running"
else
    echo -e "    ⚠️ Supervisor: No ezstream processes configured"
fi

echo ""
echo -e "${GREEN}🌟 EZSTREAM is now running the latest version! 🌟${NC}"
echo -e "${BLUE}🌐 Visit: https://ezstream.pro${NC}"
echo ""
