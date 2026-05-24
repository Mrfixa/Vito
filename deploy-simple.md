# Vito — Server Setup (Ubuntu 26.04 LTS)

> Replace every `your-domain.com`, `your-api-domain.com`, and `YOUR_DB_PASS` before running.

---

## Step 1 — EC2

- AMI: Ubuntu 26.04 LTS
- Type: t3.medium, Storage: 30 GB gp3
- Inbound ports: 22 (your IP), 80, 443, 6015
- Allocate Elastic IP → point DNS A records at it

---

## Step 2 — Basics

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y git curl unzip software-properties-common

sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 6015/tcp
sudo ufw --force enable

sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile
sudo mkswap /swapfile && sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

---

## Step 3 — PHP 8.5, MySQL, Nginx, Redis, Supervisor

```bash
sudo add-apt-repository ppa:ondrej/php -y && sudo apt update

sudo apt install -y php8.5-fpm php8.5-cli php8.5-mysql php8.5-xml \
  php8.5-curl php8.5-mbstring php8.5-zip php8.5-bcmath php8.5-redis \
  php8.5-gd php8.5-intl php8.5-opcache

curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

sudo apt install -y mysql-server nginx redis-server supervisor
sudo snap install --classic certbot
sudo ln -s /snap/bin/certbot /usr/bin/certbot

sudo systemctl enable php8.5-fpm nginx mysql redis-server supervisor
```

---

## Step 4 — Database

```bash
sudo mysql -u root
```

Then paste (replace `YOUR_DB_PASS`):

```sql
CREATE DATABASE vito CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'vito'@'localhost' IDENTIFIED BY 'YOUR_DB_PASS';
GRANT ALL PRIVILEGES ON vito.* TO 'vito'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## Step 5 — Clone & Install

```bash
sudo mkdir -p /var/www/vito
sudo chown $USER:www-data /var/www/vito

git clone https://github.com/Mrfixa/Vito.git /var/www/vito \
  --branch claude/analyze-mart-qr-code-FySPn --single-branch

cd /var/www/vito/drivemond-admin-new-install-3.1

composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
cp .env.example .env
```

---

## Step 6 — Edit .env

```bash
nano /var/www/vito/drivemond-admin-new-install-3.1/.env
```

Set these values:

```
APP_NAME=Vito
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-api-domain.com

DB_DATABASE=vito
DB_USERNAME=vito
DB_PASSWORD=YOUR_DB_PASS

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

PUSHER_APP_KEY=CHANGE_ME
PUSHER_APP_SECRET=CHANGE_ME
PUSHER_HOST=your-api-domain.com
PUSHER_PORT=6015
PUSHER_SCHEME=https
BROADCAST_DRIVER=reverb
```

---

## Step 7 — Bootstrap

```bash
cd /var/www/vito/drivemond-admin-new-install-3.1

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

## Step 8 — SSL

```bash
sudo certbot certonly --standalone \
  -d your-api-domain.com -d your-domain.com \
  --non-interactive --agree-tos -m you@yourdomain.com
```

---

## Step 9 — Nginx

```bash
sudo nano /etc/nginx/sites-available/vito
```

Paste (replace `your-api-domain.com` and `your-domain.com`):

```nginx
server {
    listen 80; listen [::]:80;
    server_name your-api-domain.com your-domain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2; listen [::]:443 ssl http2;
    server_name your-api-domain.com;
    root /var/www/vito/drivemond-admin-new-install-3.1/public;
    index index.php;
    ssl_certificate     /etc/letsencrypt/live/your-api-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-api-domain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    client_max_body_size 50M;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
    location ~ /\.(?!well-known).* { deny all; }
}

server {
    listen 6015 ssl http2; listen [::]:6015 ssl http2;
    server_name your-api-domain.com;
    ssl_certificate     /etc/letsencrypt/live/your-api-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-api-domain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
        proxy_read_timeout 3600s;
    }
}

server {
    listen 443 ssl http2; listen [::]:443 ssl http2;
    server_name your-domain.com;
    ssl_certificate     /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    root /var/www/vito/landing;
    index index.html;
    location / { try_files $uri $uri/ =404; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/vito /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

---

## Step 10 — Supervisor

```bash
sudo nano /etc/supervisor/conf.d/vito.conf
```

Paste:

```ini
[program:vito-worker]
command=php /var/www/vito/drivemond-admin-new-install-3.1/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
user=ubuntu
numprocs=2
autostart=true
autorestart=true
stdout_logfile=/var/log/supervisor/vito-worker.log

[program:vito-reverb]
command=php /var/www/vito/drivemond-admin-new-install-3.1/artisan reverb:start --host=127.0.0.1 --port=8080 --no-interaction
user=ubuntu
autostart=true
autorestart=true
stdout_logfile=/var/log/supervisor/vito-reverb.log
```

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start all
```

---

## Step 11 — Cron

```bash
crontab -e
```

Add this line:

```
* * * * * php /var/www/vito/drivemond-admin-new-install-3.1/artisan schedule:run >> /dev/null 2>&1
```

---

## Step 12 — Stripe Webhook

1. Dashboard → Webhooks → Add endpoint: `https://your-api-domain.com/api/stripe/webhook`
2. Events: `payment_intent.succeeded`, `payment_intent.payment_failed`
3. Copy signing secret → paste as `STRIPE_WEBHOOK_SECRET` in `.env`
4. Run: `php /var/www/vito/drivemond-admin-new-install-3.1/artisan config:cache`

---

## Step 13 — Firebase

1. Firebase Console → Project Settings → Service Accounts → Generate private key
2. Go to `https://your-api-domain.com/admin`
3. Business Settings → Third Party API → upload the JSON file

---

## Step 14 — Smoke Test

```bash
sudo supervisorctl status
curl -s https://your-api-domain.com/api/customer/auth/check
```

---

## Updating

```bash
cd /var/www/vito && git pull
cd drivemond-admin-new-install-3.1
composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
sudo supervisorctl restart vito-reverb
```

---

## Quick Fixes

| Problem | Fix |
|---|---|
| 502 Bad Gateway | `sudo systemctl restart php8.5-fpm` |
| Laravel errors | `tail -f /var/www/vito/drivemond-admin-new-install-3.1/storage/logs/laravel-$(date +%Y-%m-%d).log` |
| Queue stuck | `sudo supervisorctl restart vito-worker:*` |
| WebSocket down | `sudo supervisorctl restart vito-reverb` |
| Nginx error | `sudo nginx -t` |
| SSL expired | `sudo certbot renew` |
