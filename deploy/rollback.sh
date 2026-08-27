#!/usr/bin/env bash
# Rollback por manifesto imutável; nunca reconstrói uma release.
# Uso: rollback.sh --listar | rollback.sh <release-id>
# Rollback de código não desfaz migrations destrutivas; nesse caso, usar restore.
set -Eeuo pipefail

DIR_APP="${DIR_APP:-/opt/actions-runner-dubena/_work/dubena/dubena/erp-novo}"
RELEASES_DIR="${RELEASES_DIR:-/opt/dubena-releases}"
ENV_PRODUCAO="${ENV_PRODUCAO:-/opt/dubena-env/erp-novo-producao.env}"
CONTAINER_APP="${CONTAINER_APP:-erpnovo-prod-app}"
URL_HEALTH="${URL_HEALTH:-http://127.0.0.1:3130/up}"

log()  { printf '[rollback %s] %s\n' "$(date -u +%H:%M:%S)" "$*"; }
erro() { printf '[rollback ERRO] %s\n' "$*" >&2; exit 1; }
trap 'erro "falhou na linha $LINENO"' ERR

listar() {
    find "$RELEASES_DIR" -maxdepth 1 -type f -name '*.env' -printf '%f\n' \
        | sed 's/\.env$//' | sort -r
}

if [[ "${1:-}" == '--listar' ]]; then listar; exit 0; fi

ALVO="${1:-}"
[[ -n "$ALVO" ]] || erro 'informe o release-id; seleção implícita é proibida'
[[ "$ALVO" =~ ^[A-Za-z0-9._-]+$ ]] || erro 'release-id inválido'
MANIFESTO="${RELEASES_DIR}/${ALVO}.env"
[[ -r "$MANIFESTO" ]] || erro "manifesto não encontrado: ${MANIFESTO}"

if grep -Ev '^(RELEASE_ID|APP_IMAGE|WEB_IMAGE)=[A-Za-z0-9._/@:-]+$|^[[:space:]]*$' "$MANIFESTO" | grep -q .; then
    erro 'manifesto contém sintaxe ou chaves não permitidas'
fi
# shellcheck disable=SC1090
source "$MANIFESTO"
: "${APP_IMAGE:?APP_IMAGE ausente no manifesto}"
: "${WEB_IMAGE:?WEB_IMAGE ausente no manifesto}"

validar_imagem() {
    local image="$1" digest
    [[ "$image" == *@sha256:* ]] || erro "imagem sem digest: ${image}"
    digest="${image##*@sha256:}"
    [[ ${#digest} -eq 64 && "$digest" != *[!0-9a-f]* ]] || erro "digest inválido: ${image}"
    docker image inspect "$image" >/dev/null 2>&1 || docker pull "$image" >/dev/null
}

validar_imagem "$APP_IMAGE"
validar_imagem "$WEB_IMAGE"
cd "$DIR_APP" 2>/dev/null || erro "diretório do app não encontrado: ${DIR_APP}"
[[ -r docker/compose-production.sh ]] || erro 'wrapper de produção ausente'

ATUAL="$(docker inspect "$CONTAINER_APP" --format '{{.Image}}' 2>/dev/null || echo desconhecido)"
log "imagem atual: ${ATUAL}; promovendo manifesto ${ALVO}"
export APP_IMAGE WEB_IMAGE ENV_PRODUCAO
sh docker/compose-production.sh up -d --no-build --force-recreate

for _ in $(seq 1 20); do
    status="$(docker inspect -f '{{.State.Status}}' "$CONTAINER_APP" 2>/dev/null || echo none)"
    [[ "$status" == running ]] && break
    sleep 3
done
[[ "$status" == running ]] || erro "app não subiu (status=${status})"

for _ in $(seq 1 12); do
    code="$(curl -s -o /dev/null -w '%{http_code}' "$URL_HEALTH" || echo 000)"
    if [[ "$code" == 200 ]]; then
        log "ROLLBACK CONCLUÍDO para ${ALVO}; health 200"
        exit 0
    fi
    sleep 5
done
erro 'health não respondeu 200; execute o runbook de recuperação'
