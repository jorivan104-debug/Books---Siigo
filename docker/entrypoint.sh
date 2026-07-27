#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

# Garantizamos permisos de escritura sobre storage y cache en cada arranque.
mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache database
chown -R www-data:www-data storage bootstrap/cache database || true
chmod -R 775 storage bootstrap/cache database || true

if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
fi

if [ -z "${APP_KEY:-}" ] && ! grep -q "^APP_KEY=base64" .env 2>/dev/null; then
    php artisan key:generate --force || true
fi

# SQLite por defecto en Coolify cuando no hay MySQL reachable.
# En Coolify: DB_CONNECTION=sqlite y (opcional) DB_DATABASE=/var/www/html/database/database.sqlite
DB_CONNECTION_VALUE="${DB_CONNECTION:-sqlite}"
if [ "$DB_CONNECTION_VALUE" = "sqlite" ]; then
    SQLITE_PATH="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
    # Si DB_DATABASE es solo un nombre (no ruta), usar database/
    case "$SQLITE_PATH" in
        /*) ;;
        *) SQLITE_PATH="/var/www/html/database/${SQLITE_PATH}" ;;
    esac
    mkdir -p "$(dirname "$SQLITE_PATH")"
    if [ ! -f "$SQLITE_PATH" ]; then
        touch "$SQLITE_PATH"
        chown www-data:www-data "$SQLITE_PATH" || true
        chmod 664 "$SQLITE_PATH" || true
    fi
    export DB_DATABASE="$SQLITE_PATH"
fi

php artisan config:cache || true
php artisan route:cache || true

if ! php artisan migrate --force --no-interaction; then
    echo "WARNING: migrate falló. Revisa DB_CONNECTION/DB_HOST. Con SQLite usa DB_CONNECTION=sqlite."
fi

exec "$@"
