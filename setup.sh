#!/bin/bash
# Alumpro.Az Setup Script

echo "🏗️  Alumpro.Az Sistem Quraşdırılması"
echo "=================================="

# Check if running as root
if [ "$EUID" -eq 0 ]; then
    echo "❌ Root istifadəçisi ilə işlətməyin"
    exit 1
fi

# Update system
echo "📦 Sistem yenilənir..."
sudo apt update && sudo apt upgrade -y

# Install required packages
echo "📦 Tələb olunan paketlər quraşdırılır..."
sudo apt install -y apache2 mysql-server php php-mysql php-curl php-gd php-mbstring php-xml php-zip composer git unzip

# Enable Apache modules
echo "🔧 Apache modulları aktivləşdirilir..."
sudo a2enmod rewrite
sudo a2enmod ssl
sudo a2enmod headers

# Create project directory
echo "📁 Layihə qovluğu yaradılır..."
sudo mkdir -p /var/www/alumpro
sudo chown -R $USER:$USER /var/www/alumpro
cd /var/www/alumpro

# Set permissions
echo "🔐 İcazələr təyin edilir..."
sudo chown -R www-data:www-data /var/www/alumpro
sudo chmod -R 755 /var/www/alumpro
sudo chmod -R 775 uploads/
sudo chmod -R 775 logs/

# Create uploads directories
mkdir -p uploads/profiles
mkdir -p uploads/gallery
mkdir -p logs

# Install Composer dependencies
echo "📦 Composer dependencies quraşdırılır..."
composer install --no-dev --optimize-autoloader

# Create database
echo "🗄️  Verilənlər bazası yaradılır..."
sudo mysql -e "CREATE DATABASE IF NOT EXISTS alumpro_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER IF NOT EXISTS 'alumpro_user'@'localhost' IDENTIFIED BY 'alumpro_pass_2025';"
sudo mysql -e "GRANT ALL PRIVILEGES ON alumpro_system.* TO 'alumpro_user'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"

# Import database schema
echo "📊 Verilənlər bazası strukturu yaradılır..."
mysql -u alumpro_user -palumpro_pass_2025 alumpro_system < database.sql

# Create Apache virtual host
echo "🌐 Apache virtual host yaradılır..."
sudo tee /etc/apache2/sites-available/alumpro.conf > /dev/null <<EOF
<VirtualHost *:80>
    ServerName alumpro.local
    ServerAlias www.alumpro.local
    DocumentRoot /var/www/alumpro
    
    <Directory /var/www/alumpro>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog \${APACHE_LOG_DIR}/alumpro_error.log
    CustomLog \${APACHE_LOG_DIR}/alumpro_access.log combined
</VirtualHost>
EOF

# Enable site
sudo a2ensite alumpro.conf
sudo a2dissite 000-default.conf

# Add to hosts file
echo "🌐 Hosts faylı yenilənir..."
sudo tee -a /etc/hosts > /dev/null <<EOF
127.0.0.1 alumpro.local
127.0.0.1 www.alumpro.local
EOF

# Restart Apache
echo "🔄 Apache yenidən başladılır..."
sudo systemctl restart apache2

# Setup SSL (Let's Encrypt)
echo "🔒 SSL sertifikatı üçün Certbot quraşdırılır..."
sudo apt install -y certbot python3-certbot-apache

# Create admin user
echo "👤 Admin istifadəçi yaradılır..."
php -r "
require_once 'config/database.php';
\$db = new Database();
\$password = password_hash('admin123', PASSWORD_DEFAULT);
\$db->query('INSERT INTO users (username, phone, password, full_name, role, is_verified, status) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE password = VALUES(password)', 
    ['admin', '+994501234567', \$password, 'System Administrator', 'admin', 1, 'active']);
echo 'Admin user created: admin / admin123\n';
"

# Setup cron jobs
echo "⏰ Cron job-lar quraşdırılır..."
(crontab -l 2>/dev/null; echo "*/5 * * * * /usr/bin/php /var/www/alumpro/cron/whatsapp_processor.php") | crontab -
(crontab -l 2>/dev/null; echo "0 9 * * * /usr/bin/php /var/www/alumpro/cron/daily_reminders.php") | crontab -
(crontab -l 2>/dev/null; echo "0 0 * * 0 /usr/bin/php /var/www/alumpro/cron/weekly_cleanup.php") | crontab -

echo ""
echo "🎉 Quraşdırma tamamlandı!"
echo "========================"
echo ""
echo "📍 URL: http://alumpro.local"
echo "👤 Admin Login:"
echo "   Username: admin"
echo "   Password: admin123"
echo ""
echo "📝 Növbəti addımlar:"
echo "1. Admin panelində Twilio məlumatlarını əlavə edin"
echo "2. SSL sertifikatı üçün: sudo certbot --apache -d yourdomain.com"
echo "3. Sistem ayarlarını yoxlayın"
echo "4. İlk müştəri və məhsulları əlavə edin"
echo ""
echo "📞 Dəstək: info@kodaz.az"
echo "🌐 Website: https://kodaz.az"