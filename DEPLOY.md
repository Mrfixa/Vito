# Vito — AWS EC2 Deployment Guide (A to Z)

> **Stack:** Laravel 12 · PHP 8.3 · MySQL 8.4 · Nginx · Laravel Reverb · Redis 7 · Supervisor  
> **Target OS:** Ubuntu 26.04 LTS  
> **Instance minimum:** t3.medium (2 vCPU / 4 GB RAM)  
> **Estimated time:** 45–60 minutes on a fresh server

---

## Table of Contents

1. [Launch the EC2 Instance](#1-launch-the-ec2-instance)
2. [Connect and Harden the Server](#2-connect-and-harden-the-server)
3. [Install System Dependencies](#3-install-system-dependencies)
4. [Point Your Domain](#4-point-your-domain)
5. [Create the MySQL Database](#5-create-the-mysql-database)
6. [Deploy the Laravel App](#6-deploy-the-laravel-app)
7. [Configure .env](#7-configure-env)
8. [Run Migrations and Bootstrap Laravel](#8-run-migrations-and-bootstrap-laravel)
9. [File Permissions](#9-file-permissions)
10. [Configure Nginx](#10-configure-nginx)
11. [SSL Certificate (Let's Encrypt)](#11-ssl-certificate-lets-encrypt)
12. [Supervisor — Queue Workers and Reverb](#12-supervisor--queue-workers-and-reverb)
13. [Laravel Scheduler (Cron)](#13-laravel-scheduler-cron)
14. [Firebase Push Notifications](#14-firebase-push-notifications)
15. [Stripe Webhook](#15-stripe-webhook)
16. [Update Flutter Apps](#16-update-flutter-apps)
17. [Smoke Test](#17-smoke-test)
18. [Deploying Updates](#18-deploying-updates)
19. [Troubleshooting](#19-troubleshooting)

---

## 1. Launch the EC2 Instance

### 1.1 AMI & Instance Type

| Setting | Value |
|---|---|
| AMI | Ubuntu Server 26.04 LTS (HVM), SSD Volume Type |
| Architecture | x86_64 or arm64 (Graviton) |
| Instance type | **t3.medium** minimum (t3.large for >50 concurrent users) |
| Storage | 30 GB gp3 root volume |
| Key pair | Create new → download `.pem` |

### 1.2 Security Group — Inbound Rules

| Port | Protocol | Source | Purpose |
|---|---|---|---|
| 22 | TCP | Your IP only | SSH |
| 80 | TCP | 0.0.0.0/0, ::/0 | HTTP (redirect to HTTPS) |
| 443 | TCP | 0.0.0.0/0, ::/0 | HTTPS |
| 6015 | TCP | 0.0.0.0/0, ::/0 | Laravel Reverb WebSocket |

> **Note:** Port 6015 is the public-facing Reverb WebSocket port. Nginx proxies it to Reverb's internal port 8080.

### 1.3 Elastic IP

Allocate an Elastic IP and associate it with the instance so your DNS record doesn't change on restart.

---

## 2. Connect and Harden the Server

```bash
# Fix key permissions and connect
chmod 400 ~/your-key.pem
ssh -i ~/your-key.pem ubuntu@<ELASTIC_IP>
```

### 2.1 Create a deploy user

```bash
sudo adduser deploy
sudo usermod -aG sudo deploy

# Copy your SSH key to the deploy user
sudo mkdir -p /home/deploy/.ssh
sudo cp ~/.ssh/authorized_keys /home/deploy/.ssh/
sudo chown -R deploy:deploy /home/deploy/.ssh
sudo chmod 700 /home/deploy/.ssh
sudo chmod 600 /home/deploy/.ssh/authorized_keys
```

### 2.2 Harden SSH

```bash
sudo nano /etc/ssh/sshd_config
```

Set these values:

```
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
```

```bash
sudo systemctl restart ssh
```

### 2.3 Firewall (UFW)

```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 6015/tcp
sudo ufw --force enable
sudo ufw status
```

### 2.4 Automatic security updates

```bash
sudo apt install -y unattended-upgrades
sudo dpkg-reconfigure --priority=low unattended-upgrades
```

### 2.5 Swap (recommended for t3.medium)

```bash
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

---

## 3. Install System Dependencies

Switch to the deploy user for all remaining steps:

```bash
ssh -i ~/your-key.pem deploy@<ELASTIC_IP>
```

### 3.1 System update

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y git curl wget unzip zip gnupg2 ca-certificates lsb-release \
  software-properties-common apt-transport-https
```

### 3.2 PHP 8.3

Ubuntu 26.04 ships with PHP 8.3. Install it plus all required extensions:

```bash
sudo apt install -y php8.3-fpm php8.3-cli php8.3-common \
  php8.3-mysql php8.3-xml php8.3-curl php8.3-mbstring \
  php8.3-zip php8.3-bcmath php8.3-intl php8.3-redis \
  php8.3-gd php8.3-imagick php8.3-opcache

# Verify
php -v
```

Tune PHP-FPM for production:

```bash
sudo nano /etc/php/8.3/fpm/pool.d/www.conf
```

```ini
pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6
pm.max_requests = 500
```

```bash
sudo systemctl enable php8.3-fpm
sudo systemctl start php8.3-fpm
```

### 3.3 Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

### 3.4 MySQL 8.4

```bash
sudo apt install -y mysql-server

# Secure MySQL
sudo mysql_secure_installation
# → Set root password, remove test DB, disallow remote root, reload privileges

sudo systemctl enable mysql
sudo systemctl start mysql
```

### 3.5 Nginx

```bash
sudo apt install -y nginx
sudo systemctl enable nginx
sudo systemctl start nginx
```

### 3.6 Redis 7

```bash
sudo apt install -y redis-server

sudo nano /etc/redis/redis.conf
# Set: supervised systemd
# Set: maxmemory 256mb
# Set: maxmemory-policy allkeys-lru

sudo systemctl enable redis-server
sudo systemctl start redis-server
redis-cli ping   # → PONG
```

### 3.7 Supervisor

```bash
sudo apt install -y supervisor
sudo systemctl enable supervisor
sudo systemctl start supervisor
```

### 3.8 Certbot (Let's Encrypt)

```bash
sudo snap install --classic certbot
sudo ln -s /snap/bin/certbot /usr/bin/certbot
```

### 3.9 Node.js 22 LTS (for any build tools)

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
node -v   # → v22.x.x
```

---

## 4. Point Your Domain

In your DNS provider (Route 53 or external), create:

| Record | Type | Value |
|---|---|---|
| `api.yourdomain.com` | A | `<ELASTIC_IP>` |
| `yourdomain.com` | A | `<ELASTIC_IP>` (landing page) |

Wait for DNS propagation (usually < 5 minutes with TTL 60).

```bash
# Verify
dig +short api.yourdomain.com
```

---

## 5. Create the MySQL Database

```bash
sudo mysql -u root -p
```

```sql
CREATE DATABASE vito CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'vito'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON vito.* TO 'vito'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## 6. Deploy the Laravel App

```bash
# Create web root
sudo mkdir -p /var/www/vito
sudo chown -R deploy:www-data /var/www/vito

# Clone the repository
cd /var/www
git clone https://github.com/Mrfixa/Vito.git vito-repo
cd vito-repo

# The backend lives in this subdirectory
APP_DIR=/var/www/vito-repo/drivemond-admin-new-install-3.1

# Install PHP dependencies
cd "$APP_DIR"
composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
```

---

## 7. Configure .env

```bash
cd "$APP_DIR"
cp .env.example .env
nano .env
```

Fill in every value:

```dotenv
APP_NAME="Vito"
APP_ENV=production
APP_KEY=                          # generated in step 8
APP_DEBUG=false
APP_URL=https://api.yourdomain.com

LOG_CHANNEL=daily
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vito
DB_USERNAME=vito
DB_PASSWORD=STRONG_PASSWORD_HERE

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=smtp.ses.amazonaws.com   # or your SMTP provider
MAIL_PORT=587
MAIL_USERNAME=your_smtp_user
MAIL_PASSWORD=your_smtp_pass
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"

# Stripe (use live keys in production)
STRIPE_SECRET_KEY=sk_live_XXXX
STRIPE_WEBHOOK_SECRET=whsec_XXXX

# Laravel Reverb (WebSocket)
REVERB_APP_ID=vito
REVERB_APP_KEY=your_reverb_key
REVERB_APP_SECRET=your_reverb_secret
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=http

BROADCAST_DRIVER=reverb

# Pusher config (used by Flutter — points at Reverb)
PUSHER_APP_ID=vito
PUSHER_APP_KEY=your_reverb_key
PUSHER_APP_SECRET=your_reverb_secret
PUSHER_HOST=api.yourdomain.com
PUSHER_PORT=6015
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1
```

---

## 8. Run Migrations and Bootstrap Laravel

```bash
cd "$APP_DIR"

# Generate app key
php artisan key:generate

# Generate Passport OAuth keys
php artisan passport:keys --force

# Run all migrations
php artisan migrate --force

# Seed essential config (business settings, levels, vehicle types, etc.)
php artisan db:seed --force

# Create Passport personal access client
php artisan passport:client --personal --no-interaction

# Cache config/routes/views for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Create storage symlink
php artisan storage:link
```

---

## 9. File Permissions

```bash
cd "$APP_DIR"

sudo chown -R deploy:www-data .
sudo find . -type f -exec chmod 644 {} \;
sudo find . -type d -exec chmod 755 {} \;
sudo chmod -R 775 storage bootstrap/cache
sudo chmod 600 storage/oauth-private.key storage/oauth-public.key
```

---

## 10. Configure Nginx

```bash
sudo nano /etc/nginx/sites-available/vito
```

Paste the full config:

```nginx
# Redirect HTTP → HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name api.yourdomain.com yourdomain.com;
    return 301 https://$host$request_uri;
}

# Main HTTPS server (Laravel API)
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name api.yourdomain.com;

    root /var/www/vito-repo/drivemond-admin-new-install-3.1/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/api.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.yourdomain.com/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_cache   shared:SSL:10m;
    ssl_session_timeout 1d;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    client_max_body_size 50M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Gzip
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml;
    gzip_min_length 256;
}

# Reverb WebSocket (port 6015 → internal 8080)
server {
    listen 6015 ssl http2;
    listen [::]:6015 ssl http2;
    server_name api.yourdomain.com;

    ssl_certificate     /etc/letsencrypt/live/api.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.yourdomain.com/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;

    location / {
        proxy_pass             http://127.0.0.1:8080;
        proxy_http_version     1.1;
        proxy_set_header       Upgrade $http_upgrade;
        proxy_set_header       Connection "Upgrade";
        proxy_set_header       Host $host;
        proxy_set_header       X-Real-IP $remote_addr;
        proxy_set_header       X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header       X-Forwarded-Proto $scheme;
        proxy_read_timeout     3600s;
        proxy_send_timeout     3600s;
    }
}

# Landing page
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name yourdomain.com;

    ssl_certificate     /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;

    root /var/www/vito-repo/landing;
    index index.html;

    location / {
        try_files $uri $uri/ =404;
    }

    # Proxy token validation to the API
    location /api/ {
        proxy_pass https://api.yourdomain.com/api/;
        proxy_set_header Host api.yourdomain.com;
        proxy_ssl_server_name on;
    }
}
```

Enable and test:

```bash
sudo ln -s /etc/nginx/sites-available/vito /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
```

---

## 11. SSL Certificate (Let's Encrypt)

```bash
# Issue certificates for API domain and landing domain
sudo certbot certonly --nginx \
  -d api.yourdomain.com \
  -d yourdomain.com \
  --non-interactive \
  --agree-tos \
  -m you@yourdomain.com

# Test auto-renewal
sudo certbot renew --dry-run
```

After SSL is issued, reload Nginx to pick up the certs:

```bash
sudo systemctl reload nginx
```

---

## 12. Supervisor — Queue Workers and Reverb

### 12.1 Queue worker

```bash
sudo nano /etc/supervisor/conf.d/vito-worker.conf
```

```ini
[program:vito-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/vito-repo/drivemond-admin-new-install-3.1/artisan queue:work redis \
        --sleep=3 --tries=3 --max-time=3600 --queue=default,notifications
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=deploy
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/vito-worker.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=3600
```

### 12.2 Laravel Reverb (WebSocket server)

```bash
sudo nano /etc/supervisor/conf.d/vito-reverb.conf
```

```ini
[program:vito-reverb]
command=php /var/www/vito-repo/drivemond-admin-new-install-3.1/artisan reverb:start \
        --host=127.0.0.1 --port=8080 --no-interaction
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=deploy
redirect_stderr=true
stdout_logfile=/var/log/supervisor/vito-reverb.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
```

### 12.3 Apply

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
sudo supervisorctl status
```

Expected output:
```
vito-reverb           RUNNING   pid XXXX, uptime 0:00:05
vito-worker:00        RUNNING   pid XXXX, uptime 0:00:05
vito-worker:01        RUNNING   pid XXXX, uptime 0:00:05
```

---

## 13. Laravel Scheduler (Cron)

```bash
sudo crontab -u deploy -e
```

Add:

```cron
* * * * * php /var/www/vito-repo/drivemond-admin-new-install-3.1/artisan schedule:run >> /dev/null 2>&1
```

This runs the scheduler every minute. It handles QR token pruning (`qr:prune-tokens`) and any other scheduled jobs.

Verify it's registered:

```bash
cd /var/www/vito-repo/drivemond-admin-new-install-3.1
php artisan schedule:list
```

---

## 14. Firebase Push Notifications

Firebase credentials are stored in the admin panel database, **not** in `.env`.

1. Go to Firebase Console → your project → **Project Settings → Service Accounts**
2. Generate a new private key → download the JSON file
3. Log in to the Vito admin panel: `https://api.yourdomain.com/admin`
4. Navigate to **Business Settings → Third Party API**
5. Upload the Firebase service account JSON under **FCM v1 Credentials**

---

## 15. Stripe Webhook

1. Go to [Stripe Dashboard → Webhooks](https://dashboard.stripe.com/webhooks)
2. Add endpoint: `https://api.yourdomain.com/api/stripe/webhook`
3. Select events: `payment_intent.succeeded`, `payment_intent.payment_failed`
4. Copy the **Signing secret** (`whsec_...`) → paste into `.env` as `STRIPE_WEBHOOK_SECRET`
5. Re-cache config: `php artisan config:cache`

---

## 16. Update Flutter Apps

In both Flutter apps, update the base URL constants before building the production APKs.

**User app** — `drivemond-user-app-3.1/HexaRide-User-app-release-3.1/lib/util/app_constants.dart`:

```dart
static const String baseUrl = 'https://api.yourdomain.com';
static const String reverbHost = 'api.yourdomain.com';
static const int reverbPort = 6015;
```

**Driver app** — `drivemond-driver-app-3.1/HexaRide-Driver-app-release-3.1/lib/util/app_constants.dart`:

```dart
static const String baseUrl = 'https://api.yourdomain.com';
static const String reverbHost = 'api.yourdomain.com';
static const int reverbPort = 6015;
```

Build the production APKs:

```bash
# User app
cd drivemond-user-app-3.1/HexaRide-User-app-release-3.1
flutter build apk --release \
  --dart-define=MAPS_API_KEY=YOUR_MAPS_KEY \
  --dart-define=STRIPE_PUBLISHABLE_KEY=YOUR_STRIPE_PK

# Driver app
cd drivemond-driver-app-3.1/HexaRide-Driver-app-release-3.1
flutter build apk --release \
  --dart-define=MAPS_API_KEY=YOUR_MAPS_KEY \
  --dart-define=STRIPE_PUBLISHABLE_KEY=YOUR_STRIPE_PK
```

---

## 17. Smoke Test

Run these checks in order:

```bash
# 1. PHP-FPM running
sudo systemctl status php8.3-fpm | grep Active

# 2. Nginx running, config valid
sudo nginx -t && sudo systemctl status nginx | grep Active

# 3. MySQL accepting connections
mysql -u vito -p -e "SELECT 1;" vito

# 4. Redis alive
redis-cli ping   # → PONG

# 5. Queue workers running
sudo supervisorctl status

# 6. Laravel health
cd /var/www/vito-repo/drivemond-admin-new-install-3.1
php artisan about | grep -E "Environment|Cache|Debug"

# 7. Test API endpoint
curl -s https://api.yourdomain.com/api/customer/auth/check \
     -H "Content-Type: application/json" \
     -d '{"phone":"test"}' | python3 -m json.tool

# 8. WebSocket reachable
curl -si --http1.1 \
     -H "Upgrade: websocket" \
     -H "Connection: Upgrade" \
     "https://api.yourdomain.com:6015/app/YOUR_REVERB_KEY?protocol=7&client=php&version=1.0&flash=false" \
     | head -5

# 9. Landing page
curl -si https://yourdomain.com | head -3   # → HTTP/2 200

# 10. QR token generate (admin Passport token required)
curl -s -X POST https://api.yourdomain.com/api/qr-token/generate \
     -H "Authorization: Bearer ADMIN_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"role":"customer"}' | python3 -m json.tool
```

---

## 18. Deploying Updates

Create `/var/www/vito-repo/deploy.sh`:

```bash
#!/bin/bash
set -e

APP_DIR="/var/www/vito-repo/drivemond-admin-new-install-3.1"

echo "==> Pulling latest code..."
cd /var/www/vito-repo
git pull origin claude/analyze-mart-qr-code-FySPn   # change to main/master after merge

echo "==> Installing PHP dependencies..."
cd "$APP_DIR"
composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

echo "==> Putting app in maintenance mode..."
php artisan down --render="errors::503" --retry=60

echo "==> Running migrations..."
php artisan migrate --force

echo "==> Clearing and re-caching..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Fixing permissions..."
sudo chown -R deploy:www-data .
sudo chmod -R 775 storage bootstrap/cache

echo "==> Restarting queue workers..."
php artisan queue:restart

echo "==> Restarting Reverb..."
sudo supervisorctl restart vito-reverb

echo "==> Taking app out of maintenance mode..."
php artisan up

echo "==> Done!"
```

```bash
chmod +x /var/www/vito-repo/deploy.sh
```

Run a deploy:

```bash
bash /var/www/vito-repo/deploy.sh
```

---

## 19. Troubleshooting

### Nginx 502 Bad Gateway
```bash
# Check PHP-FPM is running
sudo systemctl status php8.3-fpm
# Check the socket exists
ls -la /run/php/php8.3-fpm.sock
# Tail error log
sudo tail -50 /var/log/nginx/error.log
```

### Laravel 500 errors
```bash
tail -100 /var/www/vito-repo/drivemond-admin-new-install-3.1/storage/logs/laravel-$(date +%Y-%m-%d).log
```

### Queue jobs not processing
```bash
sudo supervisorctl status
sudo supervisorctl tail -f vito-worker
# Force restart
sudo supervisorctl restart vito-worker:*
```

### WebSocket connections failing
```bash
# Check Reverb is listening on 8080
ss -tlnp | grep 8080
# Check Nginx is proxying 6015 → 8080
sudo supervisorctl tail -f vito-reverb
sudo tail -20 /var/log/nginx/error.log
```

### MySQL too many connections
```bash
sudo mysql -e "SHOW STATUS LIKE 'Threads_connected';"
sudo mysql -e "SHOW VARIABLES LIKE 'max_connections';"
# Increase if needed
sudo mysql -e "SET GLOBAL max_connections = 200;"
```

### Redis out of memory
```bash
redis-cli info memory | grep used_memory_human
redis-cli info memory | grep maxmemory_human
# Check eviction policy
redis-cli config get maxmemory-policy
```

### SSL certificate renewal fails
```bash
sudo certbot renew --dry-run
# Check Nginx is running (needed for HTTP-01 challenge)
sudo systemctl status nginx
```

### Disk space
```bash
df -h
# Clean old Laravel logs
find /var/www/vito-repo/drivemond-admin-new-install-3.1/storage/logs \
     -name "*.log" -mtime +30 -delete
# Clean supervisor logs
sudo find /var/log/supervisor -name "*.log" -mtime +7 -delete
```

---

## Quick Reference

| Service | Command |
|---|---|
| Restart Nginx | `sudo systemctl restart nginx` |
| Restart PHP-FPM | `sudo systemctl restart php8.3-fpm` |
| Restart MySQL | `sudo systemctl restart mysql` |
| Restart Redis | `sudo systemctl restart redis-server` |
| Queue status | `sudo supervisorctl status` |
| Restart all workers | `sudo supervisorctl restart all` |
| Laravel logs | `tail -f storage/logs/laravel-$(date +%Y-%m-%d).log` |
| Nginx error log | `sudo tail -f /var/log/nginx/error.log` |
| Deploy update | `bash /var/www/vito-repo/deploy.sh` |
