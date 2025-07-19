#!/bin/bash

# Quick restore phpMyAdmin after git pull
echo "🔧 Restoring phpMyAdmin..."

# Download phpMyAdmin if not exists
if [ ! -d "/var/www/phpmyadmin" ]; then
    cd /var/www
    wget https://files.phpmyadmin.net/phpMyAdmin/5.2.1/phpMyAdmin-5.2.1-all-languages.tar.gz
    tar xzf phpMyAdmin-5.2.1-all-languages.tar.gz
    mv phpMyAdmin-5.2.1-all-languages phpmyadmin
    rm phpMyAdmin-5.2.1-all-languages.tar.gz
fi

# Set permissions
chown -R www-data:www-data /var/www/phpmyadmin
chmod -R 755 /var/www/phpmyadmin

# Create config if not exists
if [ ! -f "/var/www/phpmyadmin/config.inc.php" ]; then
    cat > /var/www/phpmyadmin/config.inc.php << 'EOF'
<?php
$cfg['blowfish_secret'] = 'ezstream-secret-key-2025';
$i = 0;
$i++;
$cfg['Servers'][$i]['auth_type'] = 'cookie';
$cfg['Servers'][$i]['host'] = 'localhost';
$cfg['Servers'][$i]['compress'] = false;
$cfg['Servers'][$i]['AllowNoPassword'] = false;
$cfg['UploadDir'] = '';
$cfg['SaveDir'] = '';
EOF
fi

echo "✅ phpMyAdmin restored!"
echo "Access: https://ezstream.pro/phpmyadmin"
