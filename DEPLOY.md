# Vito — Server Setup (Ubuntu 26.04 LTS)

> Copy-paste each block in order. Replace every `CHANGE_ME` before running.

---

## Variables (set these once)

```bash
export DOMAIN="api.yourdomain.com"
export LANDING="yourdomain.com"
export DB_PASS="CHANGE_ME_strong_db_password"
export REPO="https://github.com/Mrfixa/Vito.git"
export BRANCH="claude/analyze-mart-qr-code-FySPn"
export APP="/var/www/vito/drivemond-admin-new-install-3.1"
```

---

## Step 1 — EC2 Launch

- **AMI:** Ubuntu 26.04 LTS
- **Type:** t3.medium (minimum)
- **Storage:** 30 GB gp3
- **Security group inbound:** 22 (your IP), 80, 443, 6015

Allocate an Elastic IP and attach it. Point `$DOMAIN` and `$LANDING` DNS A records at it.

---

## Step 2 — First login & basics

```bash
ssh -i your-key.pem ubuntu@<SERVER_IP>

sudo apt update && sudo apt upgrade -y
sudo apt install -y git curl unzip zip software-properties-common

# Firewall
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 6015/tcp
sudo ufw --force enable

# 2 GB swap (good for t3.medium)
sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile
sudo mkswap /swapfile && sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

---

## Step 3 — Install PHP, MySQL, Nginx, Redis, Supervisor

```bash
# PHP 8.3
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-xml \
  php8.3-curl php8.3-mbstring php8.3-zip php8.3-bcmath php8.3-redis \
  php8.3-gd php8.3-intl php8.3-opcache

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# MySQL 8.4
sudo apt install -y mysql-server
sudo mysql_secure_installation

# Nginx, Redis, Supervisor, Certbot
sudo apt install -y nginx redis-server supervisor
sudo snap install --classic certbot
sudo ln -s /snap/bin/certbot /usr/bin/certbot

# Enable services
sudo systemctl enable php8.3-fpm nginx mysql redis-server supervisor
```

---

## Step 4 — MySQL database

```bash
sudo mysql -u root -p <<SQL
CREATE DATABASE vito CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'vito'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON vito.* TO 'vito'@'localhost';
FLUSH PRIVILEGES;
SQL
```

---

## Step 5 — Deploy the app

```bash
sudo mkdir -p /var/www/vito
sudo chown $USER:www-data /var/www/vito

git clone $REPO /var/www/vito --branch $BRANCH --single-branch

cd $APP
composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

cp .env.example .env
```

---

## Step 6 — Configure .env

```bash
nano $APP/.env
```

Minimum required values:

```dotenv
APP_NAME=Vito
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yourdomain.com

DB_DATABASE=vito
DB_USERNAME=vito
DB_PASSWORD=CHANGE_ME_strong_db_password

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

STRIPE_SECRET_KEY=sk_live_XXXX
STRIPE_WEBHOOK_SECRET=whsec_XXXX

REVERB_APP_ID=vito
REVERB_APP_KEY=CHANGE_ME
REVERB_APP_SECRET=CHANGE_ME
REVERB_HOST=0.0.0.0
REVERB_PORT=8080

PUSHER_APP_KEY=CHANGE_ME        # same as REVERB_APP_KEY
PUSHER_APP_SECRET=CHANGE_ME     # same as REVERB_APP_SECRET
PUSHER_HOST=api.yourdomain.com
PUSHER_PORT=6015
PUSHER_SCHEME=https
BROADCAST_DRIVER=reverb
```

---

## Step 7 — Bootstrap Laravel

```bash
cd $APP

php artisan key:generate
php artisan passport:keys --force
php artisan migrate --force
php artisan db:seed --force
php artisan passport:client --personal --no-interaction
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache

sudo chown -R $USER:www-data .
sudo chmod -R 775 storage bootstrap/cache
sudo chmod 600 storage/oauth-private.key storage/oauth-public.key
```

---

## Step 8 — SSL certificate

```bash
# Get certs before configuring Nginx (standalone mode)
sudo certbot certonly --standalone \
  -d $DOMAIN -d $LANDING \
  --non-interactive --agree-tos -m you@yourdomain.com
```

---

## Step 9 — Nginx config

```bash
sudo tee /etc/nginx/sites-available/vito > /dev/null <<NGINX
server {
    listen 80; listen [::]:80;
    server_name $DOMAIN $LANDING;
    return 301 https://\$host\$request_uri;
}

server {
    listen 443 ssl http2; listen [::]:443 ssl http2;
    server_name $DOMAIN;
    root $APP/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/$DOMAIN/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/$DOMAIN/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;

    client_max_body_size 50M;
    add_header Strict-Transport-Security "max-age=31536000" always;

    location / { try_files \$uri \$uri/ /index.php?\$query_string; }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
    }

    location ~ /\.(?!well-known).* { deny all; }
}

# WebSocket (port 6015 → Reverb on 8080)
server {
    listen 6015 ssl http2; listen [::]:6015 ssl http2;
    server_name $DOMAIN;

    ssl_certificate     /etc/letsencrypt/live/$DOMAIN/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/$DOMAIN/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host \$host;
        proxy_read_timeout 3600s;
    }
}

# Landing page
server {
    listen 443 ssl http2; listen [::]:443 ssl http2;
    server_name $LANDING;

    ssl_certificate     /etc/letsencrypt/live/$LANDING/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/$LANDING/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;

    root /var/www/vito/landing;
    index index.html;
    location / { try_files \$uri \$uri/ =404; }
}
NGINX

sudo ln -s /etc/nginx/sites-available/vito /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

---

## Step 10 — Supervisor (queue workers + Reverb)

```bash
sudo tee /etc/supervisor/conf.d/vito.conf > /dev/null <<CONF
[program:vito-worker]
command=php $APP/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
user=$USER
numprocs=2
autostart=true
autorestart=true
stdout_logfile=/var/log/supervisor/vito-worker.log

[program:vito-reverb]
command=php $APP/artisan reverb:start --host=127.0.0.1 --port=8080 --no-interaction
user=$USER
autostart=true
autorestart=true
stdout_logfile=/var/log/supervisor/vito-reverb.log
CONF

sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start all
sudo supervisorctl status
```

---

## Step 11 — Cron scheduler

```bash
(crontab -l 2>/dev/null; echo "* * * * * php $APP/artisan schedule:run >> /dev/null 2>&1") | crontab -
```

---

## Step 12 — Stripe webhook

1. Dashboard → Webhooks → Add endpoint: `https://api.yourdomain.com/api/stripe/webhook`
2. Events: `payment_intent.succeeded`, `payment_intent.payment_failed`
3. Copy signing secret → paste into `.env` as `STRIPE_WEBHOOK_SECRET`
4. Run: `cd $APP && php artisan config:cache`

---

## Step 13 — Firebase push notifications

1. Firebase Console → Project Settings → Service Accounts → Generate private key
2. Log into admin panel: `https://api.yourdomain.com/admin`
3. Business Settings → Third Party API → upload the JSON file

---

## Step 14 — Smoke test

```bash
# All services running?
sudo supervisorctl status
sudo systemctl status nginx php8.3-fpm mysql redis-server | grep Active

# API responding?
curl -s https://$DOMAIN/api/customer/auth/check | python3 -m json.tool

# Landing page?
curl -si https://$LANDING | head -2
```

---

## Updating the app

```bash
cd /var/www/vito
git pull

cd $APP
composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
sudo supervisorctl restart vito-reverb
```

---

## Common fixes

| Problem | Fix |
|---|---|
| 502 Bad Gateway | `sudo systemctl restart php8.3-fpm` |
| Laravel errors | `tail -f $APP/storage/logs/laravel-$(date +%Y-%m-%d).log` |
| Queue stuck | `sudo supervisorctl restart vito-worker:*` |
| WebSocket down | `sudo supervisorctl restart vito-reverb` |
| Nginx config error | `sudo nginx -t` |
| SSL expired | `sudo certbot renew` |
