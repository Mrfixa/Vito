#!/usr/bin/env bash
# Vito backend deployment script
# Usage: bash deploy.sh [--skip-seed]
set -euo pipefail

SKIP_SEED=false
for arg in "$@"; do
  [[ "$arg" == "--skip-seed" ]] && SKIP_SEED=true
done

APP_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$APP_DIR"

echo "==> [1/8] Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> [2/8] Generating application key (no-op if already set)..."
php artisan key:generate --no-interaction || true

echo "==> [3/8] Running database migrations..."
php artisan migrate --force

if [ "$SKIP_SEED" = false ]; then
  echo "==> [4/8] Seeding database (admin user + business settings)..."
  php artisan db:seed --force
else
  echo "==> [4/8] Skipping seed (--skip-seed flag set)."
fi

echo "==> [5/8] Running Vito post-deploy setup (OAuth clients, storage link)..."
php artisan vito:setup

echo "==> [6/8] Caching configuration, routes and views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "==> [7/8] Setting file permissions..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R 755 storage bootstrap/cache
if [ -f storage/oauth-private.key ]; then
  chmod 600 storage/oauth-private.key
  chmod 644 storage/oauth-public.key
fi

echo "==> [8/8] Restarting queue workers..."
php artisan queue:restart

echo ""
echo "Deployment complete."
echo ""
echo "Next steps (one-time, after first deploy):"
echo "  1. Log into https://\$APP_URL/admin  (admin@admin.com / 12345678)"
echo "  2. Change the admin password immediately."
echo "  3. Set your Google Maps API key under Business Settings > Maps."
echo "  4. Set your Stripe keys under Payment Configuration."
echo "  5. Create at least one Zone and one Vehicle Category."
echo "  6. Ensure cron is configured:"
echo "       * * * * * www-data cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1"
echo "  7. Ensure Supervisor is running vito-worker and vito-reverb programs."
