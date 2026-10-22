FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . ./
RUN npm run build

FROM composer:2 AS dependencies

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

# Pinned to a patched patch release; 8.4.27 fixes CVE-2026-109618 and CVE-2026-109619.
FROM php:8.4.27-apache-bookworm

# phpredis 6.3.0, pinned to that tag's commit so a moved tag cannot change what gets compiled.
ARG REDIS_COMMIT=df4fab2de7fc327c54c94a13af2b9542e4fbd720

WORKDIR /var/www/html

RUN apt-get -o Acquire::Retries=5 -o Acquire::http::Timeout=30 update \
    && apt-get -o Acquire::Retries=5 -o Acquire::http::Timeout=30 install --yes --no-install-recommends libonig-dev libpq-dev libxml2-dev libzip-dev unzip \
    && docker-php-ext-install bcmath mbstring pcntl pdo_pgsql xml zip \
    && curl --fail --show-error --location --retry 5 --retry-all-errors --retry-delay 5 --connect-timeout 15 \
        --output /tmp/phpredis.tar.gz \
        "https://github.com/phpredis/phpredis/archive/${REDIS_COMMIT}.tar.gz" \
    && mkdir -p /tmp/phpredis \
    && tar -xzf /tmp/phpredis.tar.gz -C /tmp/phpredis --strip-components=1 \
    && (cd /tmp/phpredis && phpize && ./configure && make -j"$(nproc)" && make install) \
    && rm -rf /tmp/phpredis /tmp/phpredis.tar.gz \
    && docker-php-ext-enable opcache redis \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=dependencies /app/vendor ./vendor
COPY . ./
COPY --from=frontend /app/public/build ./public/build
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/jmmart-entrypoint

RUN mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && ln -s ../storage/app/public public/storage \
    && rm -f bootstrap/cache/*.php \
    && php artisan package:discover --ansi \
    && chmod +x /usr/local/bin/jmmart-entrypoint \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 10000

ENTRYPOINT ["jmmart-entrypoint"]
CMD ["apache2-foreground"]
