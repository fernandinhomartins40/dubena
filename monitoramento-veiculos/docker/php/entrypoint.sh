#!/bin/sh
set -e

cd /var/www

echo "[entrypoint] Preparando aplicação Laravel 5.4 (monitoramento-veiculos)..."

# 1) Estrutura de storage (o diretório é gitignored e pode não existir no clone).
mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    storage/app/public \
    bootstrap/cache

# 2) Dependências (vendor é gitignored; instala se ausente).
#    --ignore-platform-req=ext-oci8: o driver Oracle NÃO é instalado no piloto
#    (decisão da Fase 0). Será incluído/substituído na Fase 3 (ERP→PostgreSQL).
if [ ! -f vendor/autoload.php ]; then
    echo "[entrypoint] vendor/ ausente — rodando composer install (pode demorar na 1ª vez)..."
    composer install --no-interaction --prefer-dist --no-progress --no-scripts \
        --ignore-platform-req=ext-oci8
fi

# 3) Arquivo .env (usa o template de dev se não existir).
if [ ! -f .env ]; then
    echo "[entrypoint] .env ausente — copiando de .env.docker"
    cp .env.docker .env
fi

# 4) APP_KEY (gera se estiver vazia).
#    Garante uma linha APP_KEY= no .env para o key:generate (5.4) substituir.
#    A APP_KEY NÃO vem do env_file de propósito (env do SO teria precedência
#    sobre o .env e quebraria o Encrypter).
if ! grep -q "^APP_KEY=base64:" .env; then
    echo "[entrypoint] Gerando APP_KEY..."
    grep -q "^APP_KEY=" .env || echo "APP_KEY=" >> .env
    php artisan key:generate --force
fi

# 5) Chaves do Passport (OAuth) — geradas se ausentes (sistema expõe API).
if [ ! -f storage/oauth-private.key ]; then
    echo "[entrypoint] Gerando chaves do Passport..."
    php artisan passport:keys --force 2>/dev/null || true
fi
# Passport exige permissão 600/660 nas chaves.
[ -f storage/oauth-private.key ] && chmod 660 storage/oauth-private.key storage/oauth-public.key || true

# 6) Permissões para o www-data escrever em storage/cache.
chown -R www-data:www-data storage bootstrap/cache || true

# 6) Limpa caches de config para refletir o .env atual.
php artisan config:clear || true
php artisan cache:clear || true

echo "[entrypoint] Pronto. Iniciando: $*"
exec "$@"
