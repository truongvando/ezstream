#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🔧 EZSTREAM Payment System Fix${NC}"
echo "=================================="

PROJECT_DIR="/var/www/ezstream"
cd $PROJECT_DIR

# 1. Update composer autoload
echo -e "${YELLOW}📦 Updating composer autoload...${NC}"
composer dump-autoload

# 2. Clear all caches
echo -e "${YELLOW}🧹 Clearing caches...${NC}"
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 3. Test setting function
echo -e "${YELLOW}🧪 Testing setting function...${NC}"
php artisan tinker --execute="
try {
    echo 'Testing setting function...' . PHP_EOL;
    \$endpoint = setting('payment_api_endpoint', 'NOT_SET');
    echo 'Payment API Endpoint: ' . \$endpoint . PHP_EOL;
    
    \$bankId = setting('payment_bank_id', 'NOT_SET');
    echo 'Bank ID: ' . \$bankId . PHP_EOL;
    
    echo 'Setting function works!' . PHP_EOL;
} catch(Exception \$e) {
    echo 'Error: ' . \$e->getMessage() . PHP_EOL;
    exit(1);
}"

# 4. Fix database enum
echo -e "${YELLOW}🔧 Fixing transactions status enum...${NC}"
php artisan migrate --force

# 5. Check database settings
echo -e "${YELLOW}🗄️ Checking database settings...${NC}"
php artisan tinker --execute="
\$settings = \App\Models\Setting::all();
echo 'Found ' . \$settings->count() . ' settings in database:' . PHP_EOL;
foreach(\$settings as \$setting) {
    echo '  - ' . \$setting->key . ': ' . \$setting->value . PHP_EOL;
}
"

# 6. Check transactions table structure
echo -e "${YELLOW}🗄️ Checking transactions table structure...${NC}"
php artisan tinker --execute="
try {
    \$columns = \DB::select('SHOW COLUMNS FROM transactions LIKE \"status\"');
    if (!empty(\$columns)) {
        echo 'Status column type: ' . \$columns[0]->Type . PHP_EOL;
    }
} catch(Exception \$e) {
    echo 'Error checking table: ' . \$e->getMessage() . PHP_EOL;
}
"

# 7. Test bank check job directly
echo -e "${YELLOW}🏦 Testing bank check job...${NC}"
php artisan bank:check-transactions

# 8. Check schedule registration
echo -e "${YELLOW}⏰ Checking schedule registration...${NC}"
php artisan schedule:list

# 9. Force run schedule
echo -e "${YELLOW}🔄 Force running schedule...${NC}"
php artisan schedule:run

# 10. Check supervisor status
echo -e "${YELLOW}👀 Checking supervisor status...${NC}"
supervisorctl status

echo -e "${GREEN}✅ Payment system fix completed!${NC}"
echo -e "${BLUE}Next steps:${NC}"
echo "1. If setting function works, bank check should work"
echo "2. If schedule still not working, restart supervisor"
echo "3. Monitor logs: tail -f storage/logs/laravel.log"
