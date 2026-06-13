# ──────────────────────────────────────────────────────────────────────────
# Imagem de runtime para o api-app-gc (Laravel 5.6 — API do app "Gás em Casa").
# PHP 7.4: o composer.lock deste projeto foi atualizado com deps que exigem
# 7.2–7.4 (psr/container, flysystem, type-resolver). 7.4 é o teto do Laravel 5.6
# e satisfaz todas. SEM Oracle (este sistema usa apenas MySQL).
# ──────────────────────────────────────────────────────────────────────────
FROM php:7.4-fpm

# A imagem php:7.4-fpm é baseada em Debian Bullseye (ainda com repositórios
# ativos), então não é necessário ajustar as sources do apt.

# Dependências de sistema para compilar as extensões PHP necessárias.
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
        libxml2-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        gd \
        zip \
        intl \
        bcmath \
        gmp \
        pcntl \
        posix \
        exif \
        soap \
        opcache; \
    rm -rf /var/lib/apt/lists/*

# Composer 2.2 (LTS) — última linha que roda sob PHP 7.1.
COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_MEMORY_LIMIT=-1

WORKDIR /var/www

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
