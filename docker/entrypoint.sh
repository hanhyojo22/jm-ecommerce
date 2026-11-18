#!/bin/sh

set -eu

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY must be set in .env before starting the Docker environment." >&2
    exit 1
fi

sed -i "s/^Listen 80$/Listen 0.0.0.0:${PORT:-10000}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT:-10000}>/" /etc/apache2/sites-available/000-default.conf

mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs

if [ ! -e public/storage ]; then
    php artisan storage:link
fi

# Free hosts wipe local storage on restart; put the seeded catalog photos back (no-op for files already there).
# shortcut: seller-uploaded images are still lost on restart, set NEON_PRODUCT_IMAGES_BUCKET to keep them.
mkdir -p storage/app/public/products/catalog
cp -rn database/seeders/images/. storage/app/public/products/catalog/

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

# Pharmacy demo data for a test server: branches and medicines. Re-running only adds what is missing.
# Named seeders only: DatabaseSeeder uses factories, and Faker is not installed in this image.
if [ "${RUN_SEEDERS:-false}" = "true" ]; then
    php artisan db:seed --class=PharmacySeeder --force --no-interaction
fi

# Attach bundled images to existing sample medicines without changing stock.
php artisan db:seed --class=PharmacyImageSeeder --force --no-interaction

if [ "${RUN_OPTIMIZATIONS:-true}" = "true" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

# Last, after every root-run step above, so Apache (www-data) can write uploads and caches those steps created.
if [ "$(id -u)" -eq 0 ]; then
    chown -R www-data:www-data storage bootstrap/cache
fi

exec "$@"
