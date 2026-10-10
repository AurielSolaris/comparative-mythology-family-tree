#!/bin/sh
# Prepares the persistent volumes and caches, migrates, then starts the server.
set -e

cd /var/www/html

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set. Generate one with: php artisan key:generate --show" >&2
    exit 1
fi

# The storage volume starts empty on first run.
mkdir -p storage/app/public storage/framework/cache storage/framework/sessions \
         storage/framework/views storage/framework/testing storage/logs

# SQLite database (and anything else persistent) lives on the /data volume.
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ] && [ -n "$DB_DATABASE" ] && [ ! -f "$DB_DATABASE" ]; then
    mkdir -p "$(dirname "$DB_DATABASE")"
    touch "$DB_DATABASE"
fi

chown -R www-data:www-data storage bootstrap/cache /data

run() {
    su -s /bin/sh www-data -c "$*"
}

run "php artisan migrate --force --no-interaction"
run "php artisan optimize"

exec "$@"
