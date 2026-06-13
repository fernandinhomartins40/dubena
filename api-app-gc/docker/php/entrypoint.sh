#!/bin/sh
set -e

cd /var/www

echo "[entrypoint] Preparando aplicação Laravel 5.6 (api-app-gc)..."

# 1) Estrutura de storage.
mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    storage/app/public \
    bootstrap/cache

# 2) Dependências (vendor é gitignored; instala se ausente).
if [ ! -f vendor/autoload.php ]; then
    echo "[entrypoint] vendor/ ausente — rodando composer install (pode demorar na 1ª vez)..."
    composer install --no-interaction --prefer-dist --no-progress --no-scripts
fi

# 3) Arquivo .env (usa o template de dev se não existir).
if [ ! -f .env ]; then
    echo "[entrypoint] .env ausente — copiando de .env.docker"
    cp .env.docker .env
fi

# 4) APP_KEY (gera se ausente).
#    A APP_KEY NÃO vem do env_file de propósito (env do SO teria precedência
#    sobre o .env e quebraria o Encrypter).
if ! grep -q "^APP_KEY=base64:" .env; then
    echo "[entrypoint] Gerando APP_KEY..."
    grep -q "^APP_KEY=" .env || echo "APP_KEY=" >> .env
    php artisan key:generate --force
fi

# 5) Chaves do Passport (OAuth) — este sistema é uma API com Passport.
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
exec "$@"
