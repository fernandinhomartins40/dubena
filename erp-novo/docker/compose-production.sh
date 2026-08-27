#!/bin/sh
set -eu

env_file="${ENV_PRODUCAO:-/opt/dubena-env/erp-novo-producao.env}"
compose_file="$(dirname "$0")/../docker-compose.producao.yml"

[ -r "$env_file" ] || {
    echo "Arquivo de ambiente de produção ausente ou ilegível: $env_file" >&2
    exit 78
}
app_image="${APP_IMAGE:-$(sed -n 's/^APP_IMAGE=//p' "$env_file" | tail -n 1)}"
web_image="${WEB_IMAGE:-$(sed -n 's/^WEB_IMAGE=//p' "$env_file" | tail -n 1)}"
for image in "$app_image" "$web_image"; do
    case "$image" in
        *@sha256:*) digest="${image##*@sha256:}" ;;
        *) echo "APP_IMAGE e WEB_IMAGE devem ser referências imutáveis por sha256" >&2; exit 78 ;;
    esac
    [ "${#digest}" -eq 64 ] || { echo "Digest sha256 deve ter 64 caracteres" >&2; exit 78; }
    case "$digest" in *[!0-9a-f]*) echo "Digest sha256 deve ser hexadecimal minúsculo" >&2; exit 78;; esac
done

export APP_IMAGE="$app_image" WEB_IMAGE="$web_image"

exec docker compose \
    --env-file "$env_file" \
    -f "$compose_file" \
    "$@"
