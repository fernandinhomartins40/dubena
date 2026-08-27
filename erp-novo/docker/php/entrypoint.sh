#!/bin/sh
# Entrypoint fail-closed. Produção/homologação nunca fabricam configuração:
# a imagem já deve conter dependências e os segredos vêm somente do processo.
set -eu

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

# 2) Dependências.
if [ ! -f vendor/autoload.php ]; then
    if [ "$APP_ENV" = "production" ] || [ "$APP_ENV" = "homologation" ]; then
        echo "[entrypoint] ERRO: vendor/ ausente na imagem imutável." >&2
        exit 78
    else
        echo "[entrypoint] vendor/ ausente (dev) — rodando composer install..."
        composer install --no-interaction --prefer-dist --no-progress --no-scripts
    fi
fi

# 3) Segredos estruturais. Em ambientes promovíveis, valida o ambiente do
# processo e nunca lê/escreve `.env`.
if [ "$APP_ENV" = "production" ] || [ "$APP_ENV" = "homologation" ]; then
    if ! php -r '$k=getenv("APP_KEY"); $v=is_string($k)&&str_starts_with($k,"base64:")?base64_decode(substr($k,7),true):false; exit(is_string($v)&&strlen($v)===32?0:1);'; then
        echo "[entrypoint] ERRO: APP_KEY ausente ou inválida; esperado base64 de 32 bytes." >&2
        exit 78
    fi

    redis_password="${REDIS_PASSWORD:-}"
    if [ "${#redis_password}" -lt 32 ]; then
        echo "[entrypoint] ERRO: REDIS_PASSWORD deve ter pelo menos 32 caracteres." >&2
        exit 78
    fi
    case "$redis_password" in
        *[!A-Za-z0-9_-]*)
            echo "[entrypoint] ERRO: REDIS_PASSWORD deve usar apenas base64url (A-Z, a-z, 0-9, _ e -)." >&2
            exit 78
            ;;
    esac

    broadcast_connection="${BROADCAST_CONNECTION:-}"
    if [ "$broadcast_connection" != "reverb" ]; then
        echo "[entrypoint] ERRO: ambiente promovível exige BROADCAST_CONNECTION=reverb." >&2
        exit 78
    fi
    reverb_secret="${REVERB_APP_SECRET:-}"
    [ -n "${REVERB_APP_ID:-}" ] && [ -n "${REVERB_APP_KEY:-}" ] && [ "${#reverb_secret}" -ge 32 ] || {
        echo "[entrypoint] ERRO: contrato Reverb incompleto (id/key e secret de 32+ caracteres)." >&2
        exit 78
    }

    key_fingerprint="$(php -r 'echo substr(hash("sha256",(string)getenv("APP_KEY")),0,12);')"
    echo "[entrypoint] Configuração validada (APP_KEY sha256:${key_fingerprint})."
else
    if [ -f .env ] && ! grep -q "^APP_KEY=base64:" .env 2>/dev/null; then
        echo "[entrypoint] Gerando APP_KEY apenas para desenvolvimento..."
        grep -q "^APP_KEY=" .env 2>/dev/null || echo "APP_KEY=" >> .env
        php artisan key:generate --force
    fi
fi

# A imagem é a mesma; o perfil efetivo é escolhido antes de iniciar cada
# processo. Promoção/reversão recria containers, portanto não há bytecode velho.
if [ "$APP_ENV" = "production" ]; then
    ln -sf /usr/local/etc/php/opcache-production.ini /usr/local/etc/php/conf.d/zz-opcache-runtime.ini
else
    ln -sf /usr/local/etc/php/opcache-mutable.ini /usr/local/etc/php/conf.d/zz-opcache-runtime.ini
fi

php artisan config:clear

# 4) Permissões (storage e bootstrap/cache em volumes nomeados em prod).
chown -R www-data:www-data storage bootstrap/cache

echo "[entrypoint] Pronto. Iniciando: $*"
exec "$@"
