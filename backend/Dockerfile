# syntax=docker/dockerfile:1

FROM php:8.5-fpm-alpine

# --- System packages + PHP extension build deps -----------------------------
# pdo_mysql: DB access (mysql:8.4). intl: hard-required by filament/support.
# gd/bcmath/sockets/pcntl: Filament image handling + Octane/Reverb CLI.
# redis (pecl/phpredis): .env sets REDIS_CLIENT=phpredis for cache/session/queue.
# mariadb-client: provides `mysqladmin ping` used by start.sh's readiness loop.
# supervisor: runs Octane + Reverb in the single backend container.
# Runtime libs are installed explicitly so `apk del .build-deps` never strips
# them; the -dev variants live in the virtual .build-deps group.
RUN apk add --no-cache \
        bash \
        curl \
        supervisor \
        mariadb-client \
        icu-libs \
        freetype \
        libpng \
        libjpeg-turbo \
        zlib \
        libzip \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        zlib-dev \
        linux-headers \
        libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        bcmath \
        gd \
        intl \
        pcntl \
        sockets \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# --- Composer ---------------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- FrankenPHP binary ------------------------------------------------------
# .env sets OCTANE_SERVER=frankenphp. Octane's FrankenPHP server locates the
# `frankenphp` executable via PATH. This is the static/musl build, which runs
# on Alpine (the glibc `*-gnu` asset is what the developer has checked out
# locally on this machine and will NOT run in this musl container).
ARG FRANKENPHP_VERSION=v1.12.7
ADD --chmod=0755 \
    https://github.com/php/frankenphp/releases/download/${FRANKENPHP_VERSION}/frankenphp-linux-x86_64 \
    /usr/local/bin/frankenphp

WORKDIR /app

# --- Application ------------------------------------------------------------
# .dockerignore keeps vendor/node_modules/.env/generated artifacts out.
COPY . .

# Bootstrap a .env from the example so composer's post-autoload scripts
# (`php artisan package:discover`, `filament:upgrade`) can boot the app during
# the build. Runtime secrets are injected via docker-compose environment
# variables, which take precedence over the .env file in Laravel.
RUN cp .env.example .env \
    && composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader \
    && chmod +x start.sh

EXPOSE 8000 8080

ENTRYPOINT ["sh", "/app/start.sh"]