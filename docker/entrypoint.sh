#!/bin/sh

set -eu

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY must be set in .env before starting the Docker environment." >&2
    exit 1
fi

mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache

if [ ! -e public/storage ]; then
    php artisan storage:link
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

if [ "${RUN_OPTIMIZATIONS:-true}" = "true" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

exec "$@"
