#!/bin/sh
# Entrypoint do erp-novo. Resiliente: não usa `set -e` no miolo para que um passo
# de preparação que falhe não derrube o container — o php-fpm precisa subir para
# o health check. composer install em produção é feito pelo WORKFLOW de deploy.
set +e

cd /var/www

echo "[entrypoint] Preparando erp-novo (Laravel 12)..."

# 1) Estrutura de storage / bootstrap cache.
mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    storage/app/public \
    bootstrap/cache

# 2) Dependências (em produção/homolog é o workflow quem instala; em dev, aqui).
if [ ! -f vendor/autoload.php ]; then
    if [ "$APP_ENV" = "production" ] || [ "$APP_ENV" = "homologation" ]; then
        echo "[entrypoint] vendor/ ausente — será instalado pelo workflow de deploy."
    else
        echo "[entrypoint] vendor/ ausente (dev) — rodando composer install..."
        composer install --no-interaction --prefer-dist --no-progress --no-scripts
    fi
fi

if [ -f vendor/autoload.php ]; then
    # 3) APP_KEY.
    if ! grep -q "^APP_KEY=base64:" .env 2>/dev/null; then
        echo "[entrypoint] Gerando APP_KEY..."
        grep -q "^APP_KEY=" .env 2>/dev/null || echo "APP_KEY=" >> .env
        php artisan key:generate --force 2>/dev/null || true
    fi
    php artisan config:clear 2>/dev/null || true
fi

# 4) Permissões (storage e bootstrap/cache em volumes nomeados em prod).
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

echo "[entrypoint] Pronto. Iniciando: $*"
exec "$@"
