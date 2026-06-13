#!/bin/sh
# Entrypoint resiliente (dev e produção). NÃO usa `set -e` no miolo para que
# um passo de preparação que falhe não derrube o container em crash-loop —
# o php-fpm precisa subir de qualquer forma para o health check/diagnóstico.
set +e

cd /var/www

echo "[entrypoint] Preparando aplicação Laravel 5.4 (ctrl-web / ERP)..."

# 1) Estrutura de storage / bootstrap cache.
mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    storage/app/public \
    bootstrap/cache

# 2) Dependências.
#    Em PRODUÇÃO (APP_ENV=production) o `composer install` é feito pelo WORKFLOW
#    de deploy (uma vez, com vendor persistido em volume) — NÃO aqui, pois é
#    pesado e, se morto, derrubaria o container. Em dev, instala se faltar.
if [ ! -f vendor/autoload.php ]; then
    if [ "$APP_ENV" = "production" ]; then
        echo "[entrypoint] vendor/ ausente em produção — será instalado pelo workflow de deploy."
    else
        echo "[entrypoint] vendor/ ausente (dev) — rodando composer install..."
        composer install --no-interaction --prefer-dist --no-progress --no-scripts \
            --ignore-platform-req=ext-oci8
    fi
fi

# 3) Arquivo .env (em produção vem do workflow; em dev, do template).
if [ ! -f .env ] && [ -f .env.docker ]; then
    echo "[entrypoint] .env ausente — copiando de .env.docker"
    cp .env.docker .env
fi

# Os passos abaixo dependem do vendor; só rodam se ele existir.
if [ -f vendor/autoload.php ]; then
    # 4) APP_KEY.
    if ! grep -q "^APP_KEY=base64:" .env 2>/dev/null; then
        echo "[entrypoint] Gerando APP_KEY..."
        grep -q "^APP_KEY=" .env 2>/dev/null || echo "APP_KEY=" >> .env
        php artisan key:generate --force 2>/dev/null || true
    fi
    # 5) Chaves do Passport.
    if [ ! -f storage/oauth-private.key ]; then
        echo "[entrypoint] Gerando chaves do Passport..."
        php artisan passport:keys --force 2>/dev/null || true
    fi
    [ -f storage/oauth-private.key ] && chmod 660 storage/oauth-private.key storage/oauth-public.key 2>/dev/null
    # 7) Limpa caches de config.
    php artisan config:clear 2>/dev/null || true
    php artisan cache:clear 2>/dev/null || true
fi

# 6) Permissões (storage e bootstrap/cache estão em volumes nomeados em prod).
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

echo "[entrypoint] Pronto. Iniciando: $*"
exec "$@"
