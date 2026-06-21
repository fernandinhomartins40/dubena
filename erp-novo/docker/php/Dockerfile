# ──────────────────────────────────────────────────────────────────────────
# Runtime do erp-novo (backend reescrito · Laravel 12 · PHP 8.3).
# Banco: PostgreSQL (pgsql). Sem Oracle/MySQL — projeto greenfield PG-only.
# Mesmo padrão da VPS do ctrl-web (php:8.3-fpm + nginx + proxy do host),
# porém em ambiente/porta/banco ISOLADOS (homologação).
# ──────────────────────────────────────────────────────────────────────────
FROM php:8.3-fpm

# php:8.3-fpm = Debian Bookworm.
RUN set -eux; \
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
        opcache; \
    pecl install redis; \
    docker-php-ext-enable redis; \
    rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_MEMORY_LIMIT=-1

WORKDIR /var/www

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
