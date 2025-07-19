#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🔍 EZSTREAM Scheduler Debug${NC}"
echo "=================================="

PROJECT_DIR="/var/www/ezstream"
cd $PROJECT_DIR

# 1. Check crontab
echo -e "${YELLOW}⏰ Checking crontab...${NC}"
echo "Current crontab:"
crontab -l | grep -v "^#"
echo ""

# 2. Check if schedule:run works
echo -e "${YELLOW}🔄 Testing schedule:run...${NC}"
php artisan schedule:run -v
echo ""

# 3. Check schedule list
echo -e "${YELLOW}📋 Checking schedule list...${NC}"
php artisan schedule:list
echo ""

# 4. Check Laravel environment
echo -e "${YELLOW}🌍 Checking Laravel environment...${NC}"
echo "APP_ENV: $(php artisan tinker --execute='echo app()->environment();')"
echo "APP_DEBUG: $(php artisan tinker --execute='echo config(\"app.debug\") ? \"true\" : \"false\";')"
echo ""

# 5. Check if Kernel.php is loaded
echo -e "${YELLOW}🔧 Checking Kernel.php...${NC}"
php artisan tinker --execute="
try {
    \$kernel = app(\Illuminate\Console\Scheduling\Schedule::class);
    echo 'Schedule instance created successfully' . PHP_EOL;
    
    // Try to get events
    \$reflection = new ReflectionClass(\$kernel);
    \$eventsProperty = \$reflection->getProperty('events');
    \$eventsProperty->setAccessible(true);
    \$events = \$eventsProperty->getValue(\$kernel);
    
    echo 'Number of scheduled events: ' . count(\$events) . PHP_EOL;
} catch(Exception \$e) {
    echo 'Error: ' . \$e->getMessage() . PHP_EOL;
}
"
echo ""

# 6. Check recent cron logs
echo -e "${YELLOW}📜 Checking recent cron logs...${NC}"
echo "Recent schedule:run executions:"
grep "schedule:run" /var/log/syslog | tail -5
echo ""

# 7. Check supervisor
echo -e "${YELLOW}👀 Checking supervisor...${NC}"
supervisorctl status | grep ezstream
echo ""

# 8. Test bank check manually
echo -e "${YELLOW}🏦 Testing bank check manually...${NC}"
echo "Running bank:check-transactions..."
timeout 30 php artisan bank:check-transactions
echo ""

# 9. Check if command exists
echo -e "${YELLOW}🔍 Checking if bank:check-transactions command exists...${NC}"
php artisan list | grep bank
echo ""

echo -e "${GREEN}✅ Scheduler debug completed!${NC}"
echo -e "${BLUE}Recommendations:${NC}"
echo "1. If schedule:list shows no tasks → Kernel.php not loading properly"
echo "2. If cron logs empty → Crontab not working"
echo "3. If supervisor not running → Restart supervisor"
echo "4. If bank command not found → Command not registered"
