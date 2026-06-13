# ──────────────────────────────────────────────────────────────────────────
# Imagem de runtime para o monitoramento-veiculos (Laravel 5.4)
# PHP 7.1 = faixa suportada pelo Laravel 5.4 (PHP 7.2+ quebra o framework).
# Oracle (oci8) NÃO é instalado no piloto: os dados próprios deste sistema
# estão em MySQL. Rotas que leem o ERP via Oracle (SearchController) ficam
# inoperantes até a Fase 3 (migração do ERP p/ PostgreSQL) — comportamento
# esperado para o piloto da Fase 0.
# ──────────────────────────────────────────────────────────────────────────
FROM php:7.1-fpm

# Debian Stretch está EOL: aponta o apt para o archive para o update funcionar.
RUN set -eux; \
    sed -i 's|deb.debian.org|archive.debian.org|g; s|security.debian.org|archive.debian.org|g; /stretch-updates/d' /etc/apt/sources.list

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
        libxml2-dev; \
    docker-php-ext-configure gd --with-freetype-dir=/usr/include --with-jpeg-dir=/usr/include; \
    docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        gd \
        zip \
        intl \
        bcmath \
        pcntl \
        exif \
        opcache; \
    rm -rf /var/lib/apt/lists/*

# Composer 2.2 (LTS) — última linha que roda sob PHP 7.1.
COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

# Limite de memória maior para o composer install em build/runtime.
ENV COMPOSER_MEMORY_LIMIT=-1

WORKDIR /var/www

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
