#!/bin/bash

# Fix Migration Issues Script
echo "🔧 Fixing YouTube Migration Issues..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}📋 Current migration status:${NC}"
php artisan migrate:status

echo -e "${YELLOW}🗄️ Checking existing tables...${NC}"
php artisan tinker --execute="
use Illuminate\Support\Facades\Schema;
\$tables = ['youtube_channels', 'youtube_videos', 'youtube_video_snapshots', 'youtube_channel_snapshots', 'youtube_alerts', 'youtube_alert_settings', 'youtube_ai_analysis'];
foreach(\$tables as \$table) {
    if(Schema::hasTable(\$table)) {
        echo \"✅ Table exists: \$table\n\";
    } else {
        echo \"❌ Table missing: \$table\n\";
    }
}
"

echo -e "${YELLOW}🔄 Running migrations with force...${NC}"
php artisan migrate --force

echo -e "${YELLOW}🔍 Checking migration status after run...${NC}"
php artisan migrate:status

echo -e "${GREEN}✅ Migration fix completed!${NC}"
