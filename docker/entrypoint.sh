#!/bin/sh

set -eu

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY must be set in .env before starting the Docker environment." >&2
    exit 1
fi

php artisan migrate --force

if [ ! -e public/storage ]; then
    php artisan storage:link
fi

exec "$@"
