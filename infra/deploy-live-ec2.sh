#!/usr/bin/env bash
set -e

echo "=========================================================="
echo "🚀 Setting up Arcade Portal on AWS EC2 (Ubuntu 24.04)"
echo "=========================================================="

# Robust Public IP lookup via Amazon's official checkip service
PUBLIC_IP=$(curl -s https://checkip.amazonaws.com | tr -d '\r\n')
if [ -z "$PUBLIC_IP" ] || [[ "$PUBLIC_IP" == *"<"* ]]; then
    PUBLIC_IP="13.238.201.255"
fi
echo "🌐 Server Public IP: $PUBLIC_IP"

AWS_KEY="${1:-$AWS_ACCESS_KEY_ID}"
AWS_SEC="${2:-$AWS_SECRET_ACCESS_KEY}"

# 1. Configure 2GB Swap Memory (Crucial for 1GB RAM on t3.micro)
if [ ! -f /swapfile ]; then
    echo "💾 Creating 2GB swap space..."
    sudo fallocate -l 2G /swapfile
    sudo chmod 600 /swapfile
    sudo mkswap /swapfile
    sudo swapon /swapfile
    echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
fi

# 2. Install System Dependencies
echo "📦 Installing Nginx, PHP 8.3 FPM, MySQL, Composer, Git..."
sudo apt update
sudo DEBIAN_FRONTEND=noninteractive apt install -y \
    nginx mysql-server composer git unzip curl \
    php8.3-fpm php8.3-mysql php8.3-curl php8.3-zip php8.3-xml php8.3-mbstring php8.3-bcmath

# Install Node.js 20
if ! command -v node &> /dev/null; then
    echo "📦 Installing Node.js 20..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
    sudo apt install -y nodejs
fi

# 3. Configure MySQL Database
echo "🗄️ Configuring MySQL Database and User..."
sudo mysql <<EOF
CREATE DATABASE IF NOT EXISTS game_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'portal_user'@'localhost' IDENTIFIED BY 'PortalSecure2026!';
GRANT ALL PRIVILEGES ON game_portal.* TO 'portal_user'@'localhost';
FLUSH PRIVILEGES;
EOF

# 4. Clone or Pull Web Platform Codebase
sudo mkdir -p /var/www
if [ ! -d "/var/www/web-platform" ]; then
    echo "📥 Cloning web-platform repository..."
    cd /var/www
    sudo git clone https://github.com/Afif2473/web-platform.git
fi

sudo chown -R ubuntu:ubuntu /var/www/web-platform
cd /var/www/web-platform
git fetch origin main
git reset --hard origin/main

# 5. Build Laravel Backend
echo "⚙️ Configuring Laravel 11 Backend..."
cd /var/www/web-platform/backend

cat <<ENV > .env
APP_NAME="Arcade Portal"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_TIMEZONE=UTC
APP_URL=http://${PUBLIC_IP}

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=game_portal
DB_USERNAME=portal_user
DB_PASSWORD=PortalSecure2026!

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=s3
QUEUE_CONNECTION=database

CACHE_STORE=database
CACHE_PREFIX=

AWS_ACCESS_KEY_ID=${AWS_KEY}
AWS_SECRET_ACCESS_KEY=${AWS_SEC}
AWS_DEFAULT_REGION=ap-southeast-2
AWS_BUCKET=game-portal-assets-208588112005
AWS_USE_PATH_STYLE_ENDPOINT=false

GAME_ASSETS_DISK=s3
GAME_CDN_URL=https://dsdy2lr3h1so9.cloudfront.net
ENV

composer install --no-dev --optimize-autoloader
php artisan key:generate --force
php artisan config:clear
php artisan migrate --seed --force

# 6. Build Vue 3 Frontend
echo "🎨 Building Vue 3 Frontend SPA..."
cd /var/www/web-platform/frontend
cat <<ENV > .env
VITE_API_BASE_URL=http://${PUBLIC_IP}/api
VITE_GAME_CDN_ORIGIN=https://dsdy2lr3h1so9.cloudfront.net
ENV

npm install
npm run build

# 7. Set Permissions
echo "🔒 Setting storage and web permissions..."
sudo chown -R www-data:www-data /var/www/web-platform/backend/storage /var/www/web-platform/backend/bootstrap/cache
sudo chmod -R 775 /var/www/web-platform/backend/storage /var/www/web-platform/backend/bootstrap/cache

# 8. Configure Nginx
echo "🌐 Configuring Nginx Web Server..."
sudo tee /etc/nginx/sites-available/game-portal > /dev/null << 'NGINX'
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;

    root /var/www/web-platform/frontend/dist;
    index index.html;

    client_max_body_size 64M;

    location / {
        try_files $uri $uri/ /index.html;
    }

    # API routes to Laravel
    location ^~ /api {
        alias /var/www/web-platform/backend/public;
        try_files $uri $uri/ @laravel;

        location ~ \.php$ {
            include snippets/fastcgi-php.conf;
            fastcgi_param SCRIPT_FILENAME /var/www/web-platform/backend/public/index.php;
            fastcgi_param SCRIPT_NAME /index.php;
            fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        }
    }

    location @laravel {
        rewrite ^/api/(.*)$ /api/index.php?$query_string last;
    }

    location ~ /\.ht {
        deny all;
    }
}
NGINX

sudo ln -sf /etc/nginx/sites-available/game-portal /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl restart nginx
sudo systemctl restart php8.3-fpm

echo "=========================================================="
echo "🎉 SUCCESS! Your Arcade Portal is LIVE!"
echo "👉 Open in your browser: http://${PUBLIC_IP}"
echo "=========================================================="
