# ──────────────────────────────────────────────────────────────────────────
# Imagem de runtime para o monitoramento-veiculos (Laravel 5.8).
# PHP 7.4 (alinhado ao ctrl-web; Laravel 5.8 exige >=7.1, usamos 7.4).
# Banco: PostgreSQL (alinhamento de stack — todos os sistemas em Postgres).
# Oracle (oci8) NÃO instalado; composer ignora ext-oci8. A leitura do ERP
# passa a ser feita via Postgres (não mais oracle3).
# ──────────────────────────────────────────────────────────────────────────
FROM php:7.4-fpm

# php:7.4-fpm é baseado em Debian Bullseye (repositórios ativos) — não mexer no apt.

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
        libgmp-dev \
        libxml2-dev \
        libpq-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        pdo_mysql \
        mbstring \
        gd \
        zip \
        intl \
        bcmath \
        gmp \
        pcntl \
        exif \
        soap \
        opcache; \
    rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_MEMORY_LIMIT=-1

WORKDIR /var/www

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
