FROM php:8.5-fpm-bookworm AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        default-mysql-client git unzip libicu-dev libzip-dev libpng-dev \
        libjpeg62-turbo-dev libfreetype6-dev libsqlite3-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath gd intl opcache pcntl pdo_mysql pdo_sqlite zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

FROM php-base AS dependencies

COPY . .
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    && php artisan filament:assets

FROM node:22-bookworm-slim AS node-dependencies

WORKDIR /var/www/html
COPY package.json package-lock.json ./
RUN npm ci

FROM node-dependencies AS assets

COPY . .
COPY --from=dependencies /var/www/html/vendor ./vendor
RUN npm run build

FROM php-base AS app

ARG APP_UID=1000
ARG APP_GID=1000

COPY --from=dependencies /var/www/html /var/www/html
COPY --from=assets /var/www/html/public/build /var/www/html/public/build
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R "${APP_UID}:${APP_GID}" storage bootstrap/cache

CMD ["php-fpm"]

FROM nginx:1.27-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
