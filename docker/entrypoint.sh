#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

# Garantizamos permisos de escritura sobre storage y cache en cada arranque.
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R 775 storage bootstrap/cache || true

if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
fi

if [ -z "${APP_KEY:-}" ] && ! grep -q "^APP_KEY=base64" .env 2>/dev/null; then
    php artisan key:generate --force || true
fi

php artisan config:cache || true
php artisan route:cache || true
php artisan migrate --force --no-interaction || true

exec "$@"
