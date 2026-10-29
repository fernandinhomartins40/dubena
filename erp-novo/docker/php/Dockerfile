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

# 8.3.36: corrige PHP2Shell (CVE-2026-109618/109619, RCE sem autenticação,
# explorada desde 17/10/2026, catálogo KEV da CISA). A 8.3.33 anterior era
# vulnerável. Tag com versão + variante explícitas: o digest continua sendo o
# que vale, a tag só documenta o que ele é.
FROM php:8.3.36-fpm-trixie@sha256:699495096ad60a969b9265aa52808c722b72f5e393f3784b794b168cc095a9e6 AS php-os

# Mesma data da imagem base (28/10): snapshot mais velho que a base faz o apt
# querer rebaixar bibliotecas que a imagem já traz mais novas.
ARG DEBIAN_SNAPSHOT=20261028T120000Z

# A variante Debian é determinada pelo digest; não presumir codename pela tag.
RUN set -eux; \
    sed -i -e "s|http://deb.debian.org/debian-security|http://snapshot.debian.org/archive/debian-security/${DEBIAN_SNAPSHOT}|" -e "s|http://deb.debian.org/debian|http://snapshot.debian.org/archive/debian/${DEBIAN_SNAPSHOT}|" /etc/apt/sources.list.d/debian.sources; \
    printf 'Acquire::Check-Valid-Until "false";\n' > /etc/apt/apt.conf.d/99snapshot; \
    # snapshot.debian.org e um servico publico que responde 503 sob carga e ja
    # derrubou o build sem nada ter mudado no codigo. Retry nao afrouxa a
    # reprodutibilidade: o snapshot continua fixo, so a busca insiste.
    printf 'Acquire::Retries "5";\n' > /etc/apt/apt.conf.d/99retries

# Compilação das extensões em estágio PRÓPRIO. Os headers `-dev` (libicu-dev
# sozinho tem 50 MB, libpq-dev arrasta libssl-dev) e o git só servem para
# compilar; antes ficavam na imagem que vai para a VPS, somando ~150 MB a cada
# pull e superfície extra para o gate Trivy. A imagem final recebe só os .so.
FROM php-os AS php-ext-build

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
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
    docker-php-ext-enable redis

FROM php-os AS php-base

# Só as bibliotecas que os .so compilados acima carregam em runtime. A lista é
# CONFERIDA logo abaixo por `ldd`: se uma extensão nova precisar de outra lib,
# o build falha aqui, e não o php-fpm em produção.
RUN set -eux; \
    apt-get update; \
    # A imagem PHP inclui Perl 5.40.1-6, bloqueado pelo gate Trivy por
    # CVE-2026-13221, CVE-2026-42496 e CVE-2026-8376. Atualizar somente a
    # família Perl para o patch presente no snapshot mantém o build auditável
    # sem transformar a release em um upgrade amplo e não determinístico.
    apt-get install -y --no-install-recommends --only-upgrade \
        perl \
        perl-base \
        libperl5.40 \
        perl-modules-5.40; \
    # `unzip` fica: é o que o Composer usa para extrair pacotes no entrypoint
    # de desenvolvimento. `git` não: com --prefer-dist o Composer baixa zip.
    apt-get install -y --no-install-recommends \
        unzip \
        libzip5 \
        libpng16-16t64 \
        libjpeg62-turbo \
        libfreetype6 \
        libicu76 \
        libonig5 \
        libxml2 \
        libsodium23 \
        libpq5; \
    rm -rf /var/lib/apt/lists/*

COPY --from=php-ext-build /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=php-ext-build /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

RUN set -eux; \
    faltando="$(find /usr/local/lib/php/extensions -name '*.so' -exec ldd {} + | grep 'not found' || true)"; \
    [ -z "$faltando" ] || { echo "bibliotecas ausentes: $faltando" >&2; exit 1; }; \
    for ext in pdo_pgsql pgsql mbstring gd zip intl bcmath pcntl exif soap sodium redis "Zend OPcache"; do \
        php -m | grep -qix "$ext" || { echo "extensao nao carregou: $ext" >&2; exit 1; }; \
    done; \
    php -v 2>&1 | grep -qi warning && exit 1 || true

COPY --from=composer:2@sha256:4d71c3c2109c61d5415544264b59ad4087e4c5b7244481723664138fd36d5040 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_MEMORY_LIMIT=-1

WORKDIR /var/www

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/opcache-production.ini /usr/local/etc/php/opcache-production.ini
COPY docker/php/opcache-mutable.ini /usr/local/etc/php/opcache-mutable.ini
# Carregado depois do www.conf e do zz-docker.conf da imagem (ordem alfabética):
# só ele define o tamanho do pool. Ver o comentário no arquivo.
COPY docker/php/zz-fpm-pool.conf /usr/local/etc/php-fpm.d/zz-fpm-pool.conf
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

FROM php-base AS application

COPY composer.json composer.lock ./
# `api.github.com` ja devolveu 504 para cinco pacotes e derrubou o build sem
# nada ter mudado no codigo. O Composer aborta o comando inteiro no primeiro
# lote que falha, entao a insistencia fica aqui fora: tres tentativas do
# comando completo, com pausa. Nao afrouxa nada — o composer.lock continua
# mandando na versao, so a busca ganha mais uma chance quando a rede oscila.
# O paralelismo menor reduz a chance de esbarrar em rate limit no pico.
ENV COMPOSER_MAX_PARALLEL_HTTP=12
RUN set -eux; \
    for tentativa in 1 2 3; do \
        composer install \
            --no-dev \
            --no-interaction \
            --no-progress \
            --no-scripts \
            --prefer-dist \
            --classmap-authoritative && break; \
        echo "composer install falhou (tentativa ${tentativa}/3); repetindo em 15s" >&2; \
        sleep 15; \
        [ "$tentativa" -lt 3 ] || exit 1; \
    done

COPY . ./
COPY --from=frontend /build/public/app ./public/app

RUN composer dump-autoload --no-dev --no-interaction --no-scripts --classmap-authoritative; \
    php artisan package:discover --ansi; \
    mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/public bootstrap/cache; \
    chown -R www-data:www-data storage bootstrap/cache

# O Nginx recebe o public/ do repositório + a SPA compilada. Antes copiava de
# `application`, o que obrigava o build da `web` a esperar o `composer install`
# inteiro — sem motivo: nada em public/ vem do Composer (o install usa
# --no-scripts, então nem `vendor:publish` roda). Agora a `web` depende só do
# estágio do Vite e constrói em paralelo com a app.
FROM nginx:1.29-alpine@sha256:5616878291a2eed594aee8db4dade5878cf7edcb475e59193904b198d9b830de AS web

# A base fica presa por digest (reprodutível), mas os pacotes do Alpine
# envelhecem dentro dela: em 29/10 o Trivy (CRITICAL, --ignore-unfixed) passou
# a reprovar a imagem por CVE com correção já publicada, e o pipeline parou.
# Aplicar as correções de segurança da mesma série 3.x mantém o digest como
# ponto de partida e tira a imagem do bloqueio sem trocar de versão do nginx.
RUN apk upgrade --no-cache

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/nginx/security-headers.conf /etc/nginx/erpnovo-security-headers.conf
COPY public/ /var/www/public/
COPY --from=frontend /build/public/app /var/www/public/app

# Target padrão preservado para homolog/dev que não informam `target`.
FROM application AS runtime

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
