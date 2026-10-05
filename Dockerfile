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

FROM php:8.4-apache-bookworm

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install --yes --no-install-recommends libonig-dev libpq-dev libxml2-dev libzip-dev unzip \
    && docker-php-ext-install bcmath mbstring pcntl pdo_pgsql xml zip \
    && pecl install redis \
    && docker-php-ext-enable opcache redis \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=dependencies /app/vendor ./vendor
COPY . ./
COPY --from=frontend /app/public/build ./public/build
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/jmmart-entrypoint

RUN mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && rm -f bootstrap/cache/*.php \
    && php artisan package:discover --ansi \
    && chmod +x /usr/local/bin/jmmart-entrypoint \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["jmmart-entrypoint"]
CMD ["apache2-foreground"]
