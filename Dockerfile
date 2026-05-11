FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-scripts \
    --optimize-autoloader \
    --ignore-platform-req=ext-intl

FROM node:22-bookworm-slim AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

FROM php:8.4-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        default-mysql-client \
        git \
        iputils-ping \
        libicu-dev \
        libzip-dev \
        unzip \
        zip \
    && docker-php-ext-install bcmath intl pcntl pdo_mysql sockets zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize \
    && php artisan filament:assets \
    && chmod +x docker/start.sh docker/wait-for-db.sh \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000

CMD ["docker/start.sh"]
