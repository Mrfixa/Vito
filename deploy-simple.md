# Vito — Quick Test Setup

Replace `YOUR_DB_PASS` everywhere before running.

---

## 1 — Install

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y git php8.5 php8.5-cli php8.5-mysql php8.5-xml \
  php8.5-curl php8.5-mbstring php8.5-zip php8.5-bcmath php8.5-gd \
  php8.5-redis mysql-server redis-server unzip

curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

---

## 2 — Database

```bash
sudo mysql -u root
```

```sql
CREATE DATABASE vito CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'vito'@'localhost' IDENTIFIED BY 'YOUR_DB_PASS';
GRANT ALL PRIVILEGES ON vito.* TO 'vito'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## 3 — Clone & Install

```bash
git clone https://github.com/Mrfixa/Vito.git /var/www/vito \
  --branch claude/analyze-mart-qr-code-FySPn --single-branch

cd /var/www/vito/drivemond-admin-new-install-3.1

composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
cp .env.example .env
```

---

## 4 — Edit .env

```bash
nano .env
```

Change only these:

```
APP_URL=http://YOUR_SERVER_IP:8000
DB_DATABASE=vito
DB_USERNAME=vito
DB_PASSWORD=YOUR_DB_PASS
```

---

## 5 — Run

```bash
php artisan key:generate
php artisan passport:keys --force
php artisan migrate --force
php artisan db:seed --force
php artisan passport:client --personal --no-interaction
php artisan storage:link
php artisan serve --host=0.0.0.0 --port=8000
```

API is live at `http://YOUR_SERVER_IP:8000`

> Open port 8000 in your EC2 security group inbound rules.

---

## 6 — Build Test APKs (on your dev machine)

Point both apps at your server, then build debug APKs.

**User app:**

```bash
cd Vito/drivemond-user-app-3.1/HexaRide-User-app-release-3.1

# Swap the URL (run once)
sed -i "s|https://dacatlon.shop|http://YOUR_SERVER_IP:8000|g" lib/util/app_constants.dart

flutter pub get
flutter build apk --debug \
  --dart-define=MAPS_API_KEY=YOUR_MAPS_KEY \
  --dart-define=STRIPE_PUBLISHABLE_KEY=YOUR_STRIPE_KEY
```

APK: `build/app/outputs/flutter-apk/app-debug.apk`

**Driver app:**

```bash
cd Vito/drivemond-driver-app-3.1/HexaRide-Driver-app-release-3.1

sed -i "s|https://dacatlon.shop|http://YOUR_SERVER_IP:8000|g" lib/util/app_constants.dart

flutter pub get
flutter build apk --debug \
  --dart-define=MAPS_API_KEY=YOUR_MAPS_KEY \
  --dart-define=STRIPE_PUBLISHABLE_KEY=YOUR_STRIPE_KEY
```

APK: `build/app/outputs/flutter-apk/app-debug.apk`

Install both APKs on your phone and test.
