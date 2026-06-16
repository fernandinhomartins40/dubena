# ──────────────────────────────────────────────────────────────────────────
# Imagem de runtime para o ctrl-web (ERP · Laravel 8).
# PHP 8.1 (Fase 2 / Salto 2b — subida de 7.4 → 8.1).
# Banco: PostgreSQL (saída do Oracle — Fase 3).
# Oracle (oci8) NÃO é instalado: o pacote yajra/laravel-oci8 fica no vendor
# (provider registrado não quebra o boot), mas a extensão ext-oci8 é ignorada
# no composer e a conexão default passa a ser PostgreSQL.
# ──────────────────────────────────────────────────────────────────────────
FROM php:8.1-fpm

# php:8.1-fpm é baseado em Debian Bullseye (repositórios ativos) — não mexer no apt.

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
