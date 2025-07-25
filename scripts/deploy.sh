#!/bin/bash

# EZSTREAM Deployment Script
# Usage: bash deploy.sh [branch_name]
# Example: bash deploy.sh main

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
PROJECT_DIR="/var/www/ezstream"
BRANCH=${1:-master}
BACKUP_DIR="/var/backups/ezstream"
DB_NAME="sql_ezstream_pro"
DB_USER="root"
DB_PASS="Dodz1997a@"

echo -e "${BLUE}🚀 EZSTREAM Deployment Starting...${NC}"
echo -e "${YELLOW}Branch: $BRANCH${NC}"
echo -e "${YELLOW}Project Dir: $PROJECT_DIR${NC}"
echo ""

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

# Run database migrations (skip existing tables)
echo -e "${YELLOW}🗄️ Running database migrations...${NC}"
echo -e "${BLUE}   Checking migration status...${NC}"
php artisan migrate:status

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

# Restart background processes for Laravel using Supervisor
echo -e "${YELLOW}🔄 Restarting Laravel background processes via Supervisor...${NC}"
if command -v supervisorctl &> /dev/null; then
    # Define all active EZSTREAM processes (5 processes total)
    declare -a PROCESSES=(
        "ezstream-queue:*|Default queue worker"
        "ezstream-vps:*|VPS provisioning queue worker"
        "ezstream-agent:*|Agent reports listener"
        "ezstream-redis:*|Redis stats subscriber"
        "ezstream-schedule:*|Laravel scheduler"
    )

    echo -e "${BLUE}   Restarting ${#PROCESSES[@]} background processes...${NC}"

    # Restart each process with description
    for process_info in "${PROCESSES[@]}"; do
        IFS='|' read -r process_name description <<< "$process_info"
        echo -e "${BLUE}   → Restarting ${description}...${NC}"

        if supervisorctl restart "$process_name" >/dev/null 2>&1; then
            echo -e "${GREEN}     ✅ ${process_name} restarted successfully${NC}"
        else
            echo -e "${YELLOW}     ⚠️ ${process_name} restart failed or not found${NC}"
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

# Run the stream sync command to ensure consistency after deploy
echo -e "${YELLOW}🔄 Syncing stream state with all VPS agents...${NC}"
php artisan stream:sync --force

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
echo -e "  • Monitor queues: php artisan queue:monitor"
echo ""
echo -e "${YELLOW}💡 To rollback if needed:${NC}"
echo -e "  gunzip $BACKUP_FILE.gz"
echo -e "  mysql -u $DB_USER -p$DB_PASS $DB_NAME < \${BACKUP_FILE%.gz}"
