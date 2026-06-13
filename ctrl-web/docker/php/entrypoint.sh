#!/bin/sh
set -e

cd /var/www

echo "[entrypoint] Preparando aplicação Laravel 5.4 (ctrl-web / ERP)..."

# 1) Estrutura de storage (gitignored — não existe no clone).
mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    storage/app/public \
    bootstrap/cache

# 2) Dependências.
#    --ignore-platform-req=ext-oci8: Oracle não é instalado (saída do Oracle / Fase 3).
#    O pacote yajra/laravel-oci8 fica no vendor para o provider registrado não
#    quebrar o boot; a conexão default é PostgreSQL.
if [ ! -f vendor/autoload.php ]; then
    echo "[entrypoint] vendor/ ausente — rodando composer install (ERP é grande; pode demorar)..."
    composer install --no-interaction --prefer-dist --no-progress --no-scripts \
        --ignore-platform-req=ext-oci8
fi

# 3) Arquivo .env.
if [ ! -f .env ]; then
    echo "[entrypoint] .env ausente — copiando de .env.docker"
    cp .env.docker .env
fi

# 4) APP_KEY (não vem do env_file de propósito).
if ! grep -q "^APP_KEY=base64:" .env; then
    echo "[entrypoint] Gerando APP_KEY..."
    grep -q "^APP_KEY=" .env || echo "APP_KEY=" >> .env
    php artisan key:generate --force
fi

# 5) Chaves do Passport.
if [ ! -f storage/oauth-private.key ]; then
    echo "[entrypoint] Gerando chaves do Passport..."
    php artisan passport:keys --force 2>/dev/null || true
fi
[ -f storage/oauth-private.key ] && chmod 660 storage/oauth-private.key storage/oauth-public.key || true

# 6) Permissões.
chown -R www-data:www-data storage bootstrap/cache || true

# 7) Limpa caches de config.
php artisan config:clear || true
php artisan cache:clear || true

echo "[entrypoint] Pronto. Iniciando: $*"
echo "[entrypoint] NOTA: 'migrate' ainda NÃO funciona em PostgreSQL — há ~19 migrations"
echo "[entrypoint]       com SQL Oracle-específico. Tradução é o trabalho da Fase 3."
exec "$@"
