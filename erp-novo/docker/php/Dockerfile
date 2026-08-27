# ──────────────────────────────────────────────────────────────────────────
# Runtime do erp-novo (backend reescrito · Laravel 12 · PHP 8.3).
# Banco: PostgreSQL (pgsql). Sem Oracle/MySQL — projeto greenfield PG-only.
# Mesmo padrão da VPS do ctrl-web (php:8.3-fpm + nginx + proxy do host),
# porém em ambiente/porta/banco ISOLADOS (homologação).
# ──────────────────────────────────────────────────────────────────────────
FROM node:22-bookworm-slim@sha256:83f487e0a63425e5b4d146fb5e5be574bcbe1b7b843d3ebafdd95eaf7767a7e5 AS frontend

WORKDIR /build/frontend

COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci \
    --no-audit \
    --no-fund \
    --fetch-retries=5 \
    --fetch-retry-mintimeout=20000 \
    --fetch-retry-maxtimeout=120000

COPY frontend/ ./
RUN npm run build

FROM php:8.3-fpm@sha256:d8236042c96700b2dd4439d7260ee0666b4864076b12b82f993be3847371442b AS php-base

ARG DEBIAN_SNAPSHOT=20260824T000000Z

# A variante Debian é determinada pelo digest; não presumir codename pela tag.
RUN set -eux; \
    sed -i -e "s|http://deb.debian.org/debian-security|http://snapshot.debian.org/archive/debian-security/${DEBIAN_SNAPSHOT}|" -e "s|http://deb.debian.org/debian|http://snapshot.debian.org/archive/debian/${DEBIAN_SNAPSHOT}|" /etc/apt/sources.list.d/debian.sources; \
    printf 'Acquire::Check-Valid-Until "false";\n' > /etc/apt/apt.conf.d/99snapshot; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        zlib1g-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libicu-dev \
        libonig-dev \
        libxml2-dev \
        libsodium-dev \
        libpq-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        mbstring \
        gd \
        zip \
        intl \
        bcmath \
        pcntl \
        exif \
        soap \
        sodium \
        opcache; \
    pecl install redis; \
    docker-php-ext-enable redis; \
    rm -rf /var/lib/apt/lists/*

COPY --from=composer:2@sha256:4d71c3c2109c61d5415544264b59ad4087e4c5b7244481723664138fd36d5040 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_MEMORY_LIMIT=-1

WORKDIR /var/www

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/opcache-production.ini /usr/local/etc/php/opcache-production.ini
COPY docker/php/opcache-mutable.ini /usr/local/etc/php/opcache-mutable.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

FROM php-base AS application

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --classmap-authoritative

COPY . ./
COPY --from=frontend /build/public/app ./public/app

RUN composer dump-autoload --no-dev --no-interaction --no-scripts --classmap-authoritative; \
    php artisan package:discover --ansi; \
    mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/public bootstrap/cache; \
    chown -R www-data:www-data storage bootstrap/cache

# O Nginx recebe o public/ da mesma construção, sem volume vazio ou checkout.
FROM nginx:1.29-alpine@sha256:5616878291a2eed594aee8db4dade5878cf7edcb475e59193904b198d9b830de AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/nginx/security-headers.conf /etc/nginx/erpnovo-security-headers.conf
COPY --from=application /var/www/public /var/www/public

# Target padrão preservado para homolog/dev que não informam `target`.
FROM application AS runtime

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
