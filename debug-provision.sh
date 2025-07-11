#!/bin/bash

# Script debug VPS provision issues
# Chạy trên server chính để debug provision problems

echo "🔍 DEBUGGING VPS PROVISION ISSUES"
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

PROJECT_PATH="/var/www/ezstream"
cd "$PROJECT_PATH" || exit 1

# 1. Check provision logs
echo -e "\n${BLUE}=== 1. CHECKING PROVISION LOGS ===${NC}"
if [ -f "storage/logs/provisioning.log" ]; then
    print_success "Provision log file exists"
    echo "📄 Last 20 lines of provision log:"
    tail -20 storage/logs/provisioning.log
    echo ""
    
    echo "🔍 Recent provision errors:"
    grep -i "error\|failed\|exception" storage/logs/provisioning.log | tail -5
else
    print_error "Provision log file not found"
fi

# 2. Check Laravel logs for Livewire errors
echo -e "\n${BLUE}=== 2. CHECKING LARAVEL LOGS FOR LIVEWIRE ERRORS ===${NC}"
if [ -f "storage/logs/laravel.log" ]; then
    echo "🔍 Recent Livewire errors:"
    grep -A 5 -B 5 "livewire\|VpsServerManager" storage/logs/laravel.log | tail -20
    echo ""
    
    echo "🔍 Recent 500 errors:"
    grep -A 3 -B 3 "500\|Internal Server Error" storage/logs/laravel.log | tail -15
else
    print_error "Laravel log file not found"
fi

# 3. Check VPS servers in database
echo -e "\n${BLUE}=== 3. CHECKING VPS SERVERS IN DATABASE ===${NC}"
echo "📊 VPS servers status:"
php artisan tinker --execute="
\$servers = \App\Models\VpsServer::all();
foreach(\$servers as \$server) {
    echo \"ID: {\$server->id} | Name: {\$server->name} | Status: {\$server->status} | IP: {\$server->ip_address}\" . PHP_EOL;
    if(\$server->status_message) {
        echo \"  Message: {\$server->status_message}\" . PHP_EOL;
    }
}
echo \"Total VPS servers: \" . \$servers->count() . PHP_EOL;
"

# 4. Check SSH connectivity to VPS servers
echo -e "\n${BLUE}=== 4. TESTING SSH CONNECTIVITY ===${NC}"
php artisan tinker --execute="
\$servers = \App\Models\VpsServer::where('status', '!=', 'PROVISION_FAILED')->get();
foreach(\$servers as \$server) {
    echo \"Testing SSH to {\$server->name} ({\$server->ip_address})...\" . PHP_EOL;
    try {
        \$ssh = new \App\Services\SshService();
        if(\$ssh->connect(\$server)) {
            echo \"  ✅ SSH connection successful\" . PHP_EOL;
            \$ssh->disconnect();
        } else {
            echo \"  ❌ SSH connection failed\" . PHP_EOL;
        }
    } catch(Exception \$e) {
        echo \"  ❌ SSH error: \" . \$e->getMessage() . PHP_EOL;
    }
}
"

# 5. Check queue jobs
echo -e "\n${BLUE}=== 5. CHECKING QUEUE JOBS ===${NC}"
echo "📋 Recent failed jobs:"
php artisan queue:failed | head -10

echo -e "\n📋 Queue status:"
php artisan queue:work --once --timeout=1 2>&1 | head -5 || echo "No jobs in queue"

# 6. Check system resources
echo -e "\n${BLUE}=== 6. CHECKING SYSTEM RESOURCES ===${NC}"
echo "💾 Disk usage:"
df -h | grep -E "(Filesystem|/dev/)"

echo -e "\n🧠 Memory usage:"
free -h

echo -e "\n⚡ CPU load:"
uptime

# 7. Check PHP/Composer
echo -e "\n${BLUE}=== 7. CHECKING PHP/COMPOSER ===${NC}"
echo "🐘 PHP version:"
php -v | head -1

echo -e "\n📦 Composer status:"
composer --version

echo -e "\n🔧 PHP extensions:"
php -m | grep -E "(ssh2|curl|json|mbstring)" | head -5

# 8. Test Livewire component
echo -e "\n${BLUE}=== 8. TESTING LIVEWIRE COMPONENT ===${NC}"
echo "🧪 Testing VpsServerManager component:"
php artisan tinker --execute="
try {
    \$component = new \App\Livewire\VpsServerManager();
    echo \"✅ VpsServerManager component loads successfully\" . PHP_EOL;
    
    // Test validation rules
    \$rules = \$component->rules;
    echo \"✅ Validation rules: \" . count(\$rules) . \" rules defined\" . PHP_EOL;
    
} catch(Exception \$e) {
    echo \"❌ VpsServerManager error: \" . \$e->getMessage() . PHP_EOL;
}
"

# 9. Check nginx-rtmp availability
echo -e "\n${BLUE}=== 9. CHECKING NGINX-RTMP AVAILABILITY ===${NC}"
if command -v nginx >/dev/null 2>&1; then
    print_success "Nginx is available"
    if nginx -V 2>&1 | grep -q "rtmp"; then
        print_success "Nginx-RTMP module is available"
    else
        print_warning "Nginx-RTMP module not found"
        print_info "Install with: sudo apt install libnginx-mod-rtmp"
    fi
else
    print_warning "Nginx not installed on main server"
fi

# 10. Check recent provision attempts
echo -e "\n${BLUE}=== 10. RECENT PROVISION ATTEMPTS ===${NC}"
echo "📊 Last 5 provision attempts:"
php artisan tinker --execute="
\$servers = \App\Models\VpsServer::orderBy('created_at', 'desc')->take(5)->get();
foreach(\$servers as \$server) {
    echo \"{\$server->created_at}: {\$server->name} - {\$server->status}\" . PHP_EOL;
    if(\$server->status_message) {
        echo \"  {\$server->status_message}\" . PHP_EOL;
    }
}
"

# Summary and recommendations
echo -e "\n${BLUE}=== SUMMARY & RECOMMENDATIONS ===${NC}"
print_info "Debug completed. Check the output above for issues."
echo ""
echo "🔧 Common fixes:"
echo "1. Clear cache: php artisan optimize:clear"
echo "2. Restart queue: php artisan queue:restart"
echo "3. Check SSH keys and credentials"
echo "4. Verify nginx-rtmp installation on target VPS"
echo "5. Check network connectivity between servers"
echo ""
echo "📞 If provision still fails:"
echo "1. Check provision logs above"
echo "2. Test SSH manually to target VPS"
echo "3. Run provision job manually: php artisan queue:work"
echo "4. Check target VPS system requirements"
