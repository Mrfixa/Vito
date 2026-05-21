# Vito — AWS EC2 Deployment Guide (A to Z)

> **Stack:** Laravel 12 · PHP 8.2 · MySQL 8.0 · Nginx · Laravel Reverb · Redis · Supervisor  
> **Target OS:** Ubuntu 22.04 LTS  
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

### 1.1 Instance settings

| Setting | Recommended value |
|---|---|
| AMI | **Ubuntu Server 22.04 LTS** (64-bit x86) |
| Instance type | `t3.medium` (2 vCPU · 4 GB RAM) — minimum. Use `t3.large` if you expect real traffic. |
| Storage | **30 GB gp3** SSD |
| Key pair | Create new → download `vito.pem` → keep it safe |

### 1.2 Security group — inbound rules

| Protocol | Port | Source | Purpose |
|---|---|---|---|
| TCP | 22 | **Your IP only** | SSH |
| TCP | 80 | 0.0.0.0/0 | HTTP (redirects to HTTPS) |
| TCP | 443 | 0.0.0.0/0 | HTTPS / Laravel app |
| TCP | 6015 | 0.0.0.0/0 | Reverb WebSocket (Flutter connects here) |

> **Tip:** Never open port 22 to 0.0.0.0/0. Restrict it to your current IP.

### 1.3 Elastic IP

In the AWS console go to **EC2 → Elastic IPs → Allocate**, then **Associate** it with your new instance. This gives you a static IP that survives reboots.

### 1.4 Secure your key on your local machine

```bash
chmod 400 ~/Downloads/vito.pem
```

---

## 2. Connect and Harden the Server

```bash
# Connect
ssh -i ~/Downloads/vito.pem ubuntu@<ELASTIC_IP>

# Create a dedicated deploy user
sudo adduser deploy              # set a strong password
sudo usermod -aG sudo deploy

# Copy your SSH key to the new user
sudo rsync --archive --chown=deploy:deploy ~/.ssh /home/deploy

# Disable root SSH login and password auth
sudo sed -i 's/^PermitRootLogin.*/PermitRootLogin no/' /etc/ssh/sshd_config
sudo sed -i 's/^#PasswordAuthentication.*/PasswordAuthentication no/' /etc/ssh/sshd_config
sudo systemctl restart ssh

# Switch to deploy for all remaining steps
sudo su - deploy
```

---

## 3. Install System Dependencies

### 3.1 PHP 8.2 and all required extensions

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

sudo apt install -y \
  php8.2 php8.2-fpm php8.2-mysql php8.2-xml php8.2-curl \
  php8.2-gd php8.2-mbstring php8.2-bcmath php8.2-zip \
  php8.2-intl php8.2-fileinfo php8.2-dom php8.2-redis \
  php8.2-sqlite3 php8.2-tokenizer php8.2-openssl

php -v   # should print PHP 8.2.x
```

### 3.2 MySQL 8.0

```bash
sudo apt install -y mysql-server
sudo mysql_secure_installation
# Recommended answers:
#   Validate password plugin? No (or Yes for stricter enforcement)
#   Remove anonymous users? Yes
#   Disallow root login remotely? Yes
#   Remove test database? Yes
#   Reload privilege tables? Yes
```

### 3.3 Nginx

```bash
sudo apt install -y nginx
sudo systemctl enable nginx
```

### 3.4 Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

### 3.5 Node.js 20 LTS

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node -v    # should print v20.x.x
npm -v
```

### 3.6 Redis

```bash
sudo apt install -y redis-server
sudo systemctl enable redis-server
sudo systemctl start redis-server
redis-cli ping   # should return PONG
```

### 3.7 Supervisor

```bash
sudo apt install -y supervisor
sudo systemctl enable supervisor
```

### 3.8 Certbot (SSL)

```bash
sudo apt install -y certbot python3-certbot-nginx
```

### 3.9 Git and unzip

```bash
sudo apt install -y git unzip
```

---

## 4. Point Your Domain

In your DNS provider's control panel, add the following records:

| Type | Name | Value |
|---|---|---|
| A | `your-domain.com` | `<ELASTIC_IP>` |
| A | `www.your-domain.com` | `<ELASTIC_IP>` |

DNS propagation can take a few minutes to a few hours. You can check with:

```bash
dig +short your-domain.com
# Should return your Elastic IP
```

> **Do not proceed to step 11 (SSL) until DNS resolves correctly.**

---

## 5. Create the MySQL Database

```bash
sudo mysql -u root -p
```

```sql
CREATE DATABASE vito
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER 'vito'@'localhost'
  IDENTIFIED BY 'REPLACE_WITH_STRONG_PASSWORD';

GRANT ALL PRIVILEGES ON vito.* TO 'vito'@'localhost';

FLUSH PRIVILEGES;
EXIT;
```

---

## 6. Deploy the Laravel App

```bash
# Create the web root and set ownership
sudo mkdir -p /var/www/vito
sudo chown deploy:www-data /var/www/vito

cd /var/www/vito

# Clone the repository
git clone https://github.com/Mrfixa/Vito.git .

# Move into the backend directory
cd /var/www/vito/drivemond-admin-new-install-3.1

# Install PHP dependencies (production mode, no dev packages)
composer install \
  --no-dev \
  --optimize-autoloader \
  --no-interaction \
  --ignore-platform-reqs

# Install Node dependencies and compile assets
npm ci
npm run production

# Create .env from the example
cp .env.example .env
php artisan key:generate
```

---

## 7. Configure .env

Open the file for editing:

```bash
nano /var/www/vito/drivemond-admin-new-install-3.1/.env
```

Replace every value shown in `< >` with your real values:

```ini
# ── App ──────────────────────────────────────────────────────────────
APP_NAME=Vito
APP_ENV=production
APP_KEY=                          # already generated — do not change
APP_DEBUG=false
APP_URL=https://your-domain.com

# ── Database ─────────────────────────────────────────────────────────
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vito
DB_USERNAME=vito
DB_PASSWORD=<STRONG_PASSWORD_FROM_STEP_5>

# ── Queue / Cache / Session ───────────────────────────────────────────
# Change queue from "sync" to "database" so jobs are processed by workers
QUEUE_CONNECTION=database
CACHE_DRIVER=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

# ── Redis ─────────────────────────────────────────────────────────────
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# ── Broadcasting (Laravel Reverb) ────────────────────────────────────
BROADCAST_DRIVER=reverb

REVERB_APP_ID=vito
REVERB_APP_KEY=<RANDOM_32_CHAR_STRING>    # e.g. openssl rand -hex 16
REVERB_APP_SECRET=<RANDOM_32_CHAR_STRING>
REVERB_HOST=your-domain.com
REVERB_PORT=6015
REVERB_SCHEME=https

# Reverb server binds to this port internally (Nginx proxies 6015 → 8080)
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

# ── Pusher (Flutter SDK talks to Reverb using Pusher protocol) ────────
PUSHER_APP_ID="${REVERB_APP_ID}"
PUSHER_APP_KEY="${REVERB_APP_KEY}"
PUSHER_APP_SECRET="${REVERB_APP_SECRET}"
PUSHER_HOST="${REVERB_HOST}"
PUSHER_PORT="${REVERB_PORT}"
PUSHER_SCHEME="${REVERB_SCHEME}"
PUSHER_APP_CLUSTER=mt1

# ── Stripe ────────────────────────────────────────────────────────────
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...   # fill in after step 15

# ── Mail ──────────────────────────────────────────────────────────────
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org        # or SES, Postmark, etc.
MAIL_PORT=587
MAIL_USERNAME=<SMTP_USERNAME>
MAIL_PASSWORD=<SMTP_PASSWORD>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME=Vito

# ── Storage ───────────────────────────────────────────────────────────
FILESYSTEM_DRIVER=local           # change to "s3" if using AWS S3

# AWS S3 (optional — only needed if FILESYSTEM_DRIVER=s3)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
```

> **Generate random strings:** `openssl rand -hex 16` (gives 32 hex characters)

---

## 8. Run Migrations and Bootstrap Laravel

```bash
cd /var/www/vito/drivemond-admin-new-install-3.1

# Generate Laravel Passport OAuth key pair
php artisan passport:keys --force

# Run all database migrations
php artisan migrate --force

# Create the public storage symlink (for uploaded images, etc.)
php artisan storage:link

# Cache config, routes, and views for production performance
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 9. File Permissions

```bash
sudo chown -R deploy:www-data /var/www/vito/drivemond-admin-new-install-3.1

# Regular files: owner rw, group r, others r
sudo find /var/www/vito/drivemond-admin-new-install-3.1 \
  -type f -exec chmod 644 {} \;

# Directories: owner rwx, group rx, others rx
sudo find /var/www/vito/drivemond-admin-new-install-3.1 \
  -type d -exec chmod 755 {} \;

# Laravel needs to write to these two directories
sudo chmod -R 775 \
  /var/www/vito/drivemond-admin-new-install-3.1/storage \
  /var/www/vito/drivemond-admin-new-install-3.1/bootstrap/cache
```

---

## 10. Configure Nginx

Create the site config:

```bash
sudo nano /etc/nginx/sites-available/vito
```

Paste the following (replace every `your-domain.com`):

```nginx
# ── Redirect HTTP → HTTPS ────────────────────────────────────────────
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
    return 301 https://$host$request_uri;
}

# ── Main HTTPS site ──────────────────────────────────────────────────
server {
    listen 443 ssl http2;
    server_name your-domain.com www.your-domain.com;

    root /var/www/vito/drivemond-admin-new-install-3.1/public;
    index index.php;

    # SSL — paths filled by Certbot in step 11
    ssl_certificate     /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;
    include             /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam         /etc/letsencrypt/ssl-dhparams.pem;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    # Increase for file uploads (profile photos, delivery proofs, etc.)
    client_max_body_size 50M;

    # Laravel routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    # Reverb WebSocket — proxied via /app path on port 443
    # (Flutter apps can also connect on port 6015 — see block below)
    location /app {
        proxy_pass         http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header   Upgrade $http_upgrade;
        proxy_set_header   Connection "upgrade";
        proxy_set_header   Host $host;
        proxy_read_timeout 60s;
    }

    # Block dot-files except .well-known (needed by Certbot)
    location ~ /\.(?!well-known).* {
        deny all;
    }
}

# ── Reverb WebSocket on port 6015 (Flutter apps connect here) ────────
server {
    listen 6015 ssl http2;
    server_name your-domain.com;

    ssl_certificate     /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;
    include             /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam         /etc/letsencrypt/ssl-dhparams.pem;

    location / {
        proxy_pass         http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header   Upgrade $http_upgrade;
        proxy_set_header   Connection "upgrade";
        proxy_set_header   Host $host;
        proxy_read_timeout 60s;
    }
}
```

Enable the site and reload Nginx:

```bash
sudo ln -s /etc/nginx/sites-available/vito /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default   # remove the default placeholder
sudo nginx -t                                  # must print "syntax is ok"
sudo systemctl reload nginx
```

---

## 11. SSL Certificate (Let's Encrypt)

> DNS must be pointing to your server before running this. Verify with `dig +short your-domain.com`.

```bash
sudo certbot --nginx \
  -d your-domain.com \
  -d www.your-domain.com \
  --non-interactive \
  --agree-tos \
  -m your@email.com

sudo systemctl reload nginx
```

Certbot auto-renews. Verify the renewal timer is active:

```bash
sudo systemctl status certbot.timer
```

---

## 12. Supervisor — Queue Workers and Reverb

Create the supervisor config:

```bash
sudo nano /etc/supervisor/conf.d/vito.conf
```

```ini
; ── Queue workers ──────────────────────────────────────────────────────
[program:vito-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/vito/drivemond-admin-new-install-3.1/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
directory=/var/www/vito/drivemond-admin-new-install-3.1
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=deploy
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/vito-worker.log
stopwaitsecs=3600

; ── Laravel Reverb WebSocket server ───────────────────────────────────
[program:vito-reverb]
command=php /var/www/vito/drivemond-admin-new-install-3.1/artisan reverb:start --host=0.0.0.0 --port=8080 --no-interaction
directory=/var/www/vito/drivemond-admin-new-install-3.1
autostart=true
autorestart=true
user=deploy
redirect_stderr=true
stdout_logfile=/var/log/supervisor/vito-reverb.log
```

Apply and start:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start vito-worker:*
sudo supervisorctl start vito-reverb
sudo supervisorctl status
```

Expected output:
```
vito-reverb          RUNNING   pid 12345, uptime 0:00:05
vito-worker:00       RUNNING   pid 12346, uptime 0:00:05
vito-worker:01       RUNNING   pid 12347, uptime 0:00:05
```

---

## 13. Laravel Scheduler (Cron)

```bash
crontab -e -u deploy
```

Add this single line at the bottom:

```cron
* * * * * cd /var/www/vito/drivemond-admin-new-install-3.1 && php artisan schedule:run >> /dev/null 2>&1
```

This fires every minute. Laravel's scheduler then decides which commands actually run:

| Command | Frequency | Purpose |
|---|---|---|
| `trip-request:cancel` | Every minute | Cancel stale unaccepted trips |
| `app:process-scheduled-trips` | Every minute | Start pre-booked rides on time |
| `vito:prune-qr-tokens` | Daily | Delete expired QR tokens |

---

## 14. Firebase Push Notifications

Vito stores Firebase credentials in the database — there is no `FCM_KEY` in `.env`. After the site is live:

1. Go to the **Firebase Console** → your project → **Project Settings → Service Accounts**
2. Click **Generate new private key** → download the `.json` file
3. Log into the Vito admin panel at `https://your-domain.com/admin`
4. Navigate to **Business Settings → Third Party → Firebase**
5. Paste the contents of the JSON file into the **Server Key** field and save

The `project_id` from that JSON is used automatically to call the FCM v1 HTTP API.

---

## 15. Stripe Webhook

**Register the webhook in the Stripe Dashboard:**

1. Go to **Stripe Dashboard → Developers → Webhooks → Add endpoint**
2. URL: `https://your-domain.com/api/payment/stripe/webhook`
3. Events to listen for:
   - `payment_intent.succeeded`
   - `payment_intent.payment_failed`
4. Copy the **Signing secret** (`whsec_...`)

**Add it to `.env`:**

```bash
nano /var/www/vito/drivemond-admin-new-install-3.1/.env
# Set: STRIPE_WEBHOOK_SECRET=whsec_...
```

Re-cache config:

```bash
php artisan config:cache
```

---

## 16. Update Flutter Apps

In both Flutter apps, find the constants file (usually `lib/util/app_constants.dart` or similar) and update the base URL and WebSocket settings to match your server:

```dart
// Base API URL
static const String BASE_URL = 'https://your-domain.com';

// Reverb / Pusher WebSocket connection
static const String PUSHER_APP_KEY = 'SAME_VALUE_AS_REVERB_APP_KEY_IN_ENV';
static const String PUSHER_HOST    = 'your-domain.com';
static const int    PUSHER_PORT    = 6015;
static const String PUSHER_SCHEME  = 'https';
static const String PUSHER_CLUSTER = 'mt1';
```

Rebuild and redistribute the APKs:

```bash
flutter build apk --release \
  --dart-define=MAPS_API_KEY=<your_maps_key> \
  --dart-define=STRIPE_PUBLISHABLE_KEY=<your_stripe_pk>
```

---

## 17. Smoke Test

Run each check in order and confirm no errors before going live:

```bash
# 1. All supervisor processes running
sudo supervisorctl status

# 2. Nginx serving your domain over HTTPS
curl -I https://your-domain.com
# Expect: HTTP/2 200

# 3. Laravel app is alive
curl https://your-domain.com/api/health 2>/dev/null || \
  curl -s https://your-domain.com | grep -i "vito\|login\|dashboard" | head -3

# 4. WebSocket port reachable
curl -I https://your-domain.com:6015
# Expect: HTTP/2 400 (Nginx got it; 400 is correct — WebSocket upgrade required)

# 5. Queue workers are processing jobs
php artisan queue:monitor

# 6. Scheduler is wired up
php artisan schedule:list

# 7. Tail the log for any runtime errors
tail -f /var/www/vito/drivemond-admin-new-install-3.1/storage/logs/laravel.log
```

---

## 18. Deploying Updates

Every time you push new code, run this deploy script on the server:

```bash
#!/bin/bash
set -e

APP=/var/www/vito/drivemond-admin-new-install-3.1

echo "→ Pulling latest code..."
cd /var/www/vito && git pull origin master

echo "→ Installing PHP dependencies..."
cd $APP
composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs

echo "→ Building assets..."
npm ci && npm run production

echo "→ Running migrations..."
php artisan migrate --force

echo "→ Clearing and re-caching..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "→ Restarting workers and Reverb..."
sudo supervisorctl restart vito-worker:*
sudo supervisorctl restart vito-reverb

echo "✓ Deploy complete."
```

Save this as `/home/deploy/deploy.sh`, make it executable:

```bash
chmod +x /home/deploy/deploy.sh
```

---

## 19. Troubleshooting

### App shows a blank page or 500 error

```bash
# Check PHP-FPM
sudo systemctl status php8.2-fpm

# Check Nginx error log
sudo tail -50 /var/log/nginx/error.log

# Check Laravel log
tail -50 /var/www/vito/drivemond-admin-new-install-3.1/storage/logs/laravel.log

# Try clearing all caches
cd /var/www/vito/drivemond-admin-new-install-3.1
php artisan optimize:clear
```

### WebSocket / chat not connecting

```bash
# Is Reverb running?
sudo supervisorctl status vito-reverb

# Check Reverb log
tail -50 /var/log/supervisor/vito-reverb.log

# Is port 6015 open on the security group? Check:
curl -v https://your-domain.com:6015

# Verify .env values match what the Flutter app uses
grep -E "REVERB_|PUSHER_" /var/www/vito/drivemond-admin-new-install-3.1/.env
```

### Jobs not processing (trips not updating, notifications not sending)

```bash
# Check queue worker logs
tail -50 /var/log/supervisor/vito-worker.log

# Check for failed jobs
cd /var/www/vito/drivemond-admin-new-install-3.1
php artisan queue:failed

# Retry all failed jobs
php artisan queue:retry all
```

### SSL certificate renewal fails

```bash
# Test renewal dry-run
sudo certbot renew --dry-run

# Manually renew if needed
sudo certbot renew
sudo systemctl reload nginx
```

### Out of disk space

```bash
df -h
# Laravel logs can grow large
php artisan log:clear 2>/dev/null || \
  truncate -s 0 /var/www/vito/drivemond-admin-new-install-3.1/storage/logs/laravel.log
```

---

## Port Reference

| Port | Protocol | Direction | Purpose |
|---|---|---|---|
| 22 | TCP | Inbound | SSH (your IP only) |
| 80 | TCP | Inbound | HTTP → redirect to HTTPS |
| 443 | TCP | Inbound | HTTPS — Laravel app |
| 6015 | TCP | Inbound | Reverb WebSocket — Flutter apps |
| 8080 | TCP | Internal only | Reverb server (Nginx proxies to this) |
| 3306 | TCP | Internal only | MySQL (never expose externally) |
| 6379 | TCP | Internal only | Redis (never expose externally) |
