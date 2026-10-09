#!/usr/bin/env bash
# Deploy do erp-novo executado NA VPS, chamado por SSH pelo GitHub Actions.
#
# POR QUE ASSIM. Até setembro/2026 o deploy rodava num runner self-hosted
# DENTRO da VPS: um processo residente só para esperar job, e o checkout do
# repositório inteiro no disco do servidor. A VPS é compartilhada por ~10
# aplicações; quando ela foi reinstalada o runner sumiu e o deploy ficou um mês
# "queued" sem ninguém perceber. Agora o build acontece no GitHub (um job por
# imagem, com cache), as imagens vão para o GHCR, e a VPS só faz `pull` + `up`.
# Nada compila aqui, nada fica residente esperando job.
#
# Uso (o token do GHCR chega pela ENTRADA PADRÃO, nunca por argumento — argumento
# aparece em `ps` para qualquer usuário do host):
#   remote-deploy.sh <homolog|producao> <release-id> <app-image@sha256> <web-image@sha256>
set -Eeuo pipefail

AMBIENTE="${1:?ambiente (homolog|producao)}"
RELEASE_ID="${2:?release-id}"
APP_IMAGE="${3:?app image}"
WEB_IMAGE="${4:?web image}"

RELEASES_DIR="${RELEASES_DIR:-/opt/dubena-releases}"
BUNDLE_DIR="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
# Quantas releases manter localmente para rollback sem depender do GHCR.
MANTER_RELEASES="${MANTER_RELEASES:-3}"

log()  { printf '[deploy %s %s] %s\n' "$AMBIENTE" "$(date -u +%H:%M:%S)" "$*"; }
erro() { printf '[deploy ERRO] %s\n' "$*" >&2; exit 1; }

[[ "$RELEASE_ID" =~ ^[0-9a-f]{40}$ ]] || erro 'release-id deve ser o SHA completo'
for image in "$APP_IMAGE" "$WEB_IMAGE"; do
    [[ "$image" =~ @sha256:[0-9a-f]{64}$ ]] || erro "imagem sem digest imutável: ${image}"
done

case "$AMBIENTE" in
    homolog)
        WRAPPER="$BUNDLE_DIR/erp-novo/docker/compose-homolog.sh"
        ENV_FILE="${ENV_HOMOLOG:-/opt/dubena-env/erp-novo-homolog.env}"
        CONTAINER_APP=erpnovo-app
        CONTAINER_WEB=erpnovo-web
        HEALTH_URLS=(http://127.0.0.1:3120/novo/up http://127.0.0.1:3120/novo/app/)
        ;;
    producao)
        WRAPPER="$BUNDLE_DIR/erp-novo/docker/compose-production.sh"
        ENV_FILE="${ENV_PRODUCAO:-/opt/dubena-env/erp-novo-producao.env}"
        CONTAINER_APP=erpnovo-prod-app
        CONTAINER_WEB=erpnovo-prod-web
        HEALTH_URLS=(http://127.0.0.1:3130/up)
        ;;
    *) erro "ambiente desconhecido: ${AMBIENTE}" ;;
esac
# Os wrappers leem estes nomes, não ENV_FILE.
export ENV_HOMOLOG="$ENV_FILE" ENV_PRODUCAO="$ENV_FILE"
# Fail-closed antes de qualquer pull: sem o .env o compose subiria com
# defaults e o entrypoint recusaria — melhor parar aqui, com mensagem clara.
[[ -r "$ENV_FILE" ]] || erro "arquivo de ambiente ausente: ${ENV_FILE}"

# Credencial do GHCR só durante o pull, num DOCKER_CONFIG temporário. Gravar no
# ~/.docker do root deixaria um token de escrita de pacotes no disco de um
# servidor compartilhado. O GITHUB_TOKEN expira com o job, mas nem isso fica.
DOCKER_CONFIG="$(mktemp -d)"
export DOCKER_CONFIG
trap 'rm -rf "$DOCKER_CONFIG"' EXIT
# O registry vem da própria referência da imagem (ghcr.io em uso real).
docker login "${APP_IMAGE%%/*}" -u "${GHCR_USER:-github-actions}" --password-stdin >/dev/null

export APP_IMAGE WEB_IMAGE
log "pull ${APP_IMAGE##*/} e ${WEB_IMAGE##*/}"
sh "$WRAPPER" pull app web
rm -rf "$DOCKER_CONFIG"; DOCKER_CONFIG="$(mktemp -d)"

# Manifesto imutável ANTES de promover: é ele que o rollback.sh lê.
mkdir -p "$RELEASES_DIR"
umask 022
printf 'RELEASE_ID=%s\nAPP_IMAGE=%s\nWEB_IMAGE=%s\n' "$RELEASE_ID" "$APP_IMAGE" "$WEB_IMAGE" \
    > "${RELEASES_DIR}/${RELEASE_ID}.env.tmp"
mv "${RELEASES_DIR}/${RELEASE_ID}.env.tmp" "${RELEASES_DIR}/${RELEASE_ID}.env"

if [[ "$AMBIENTE" == producao ]]; then
    log 'backup obrigatório antes de qualquer escrita'
    bash "$BUNDLE_DIR/deploy/backup/backup.sh"
fi

# O banco precisa estar de pé para a migration; o resto sobe depois dela, para
# nenhum worker rodar código novo contra schema velho.
sh "$WRAPPER" up -d --no-build db redis
log 'migration pela role owner'
sh "$WRAPPER" run --rm app php artisan migrate --force --database=pgsql_owner
sh "$WRAPPER" up -d --no-build --remove-orphans

esperado_app="$(docker image inspect "$APP_IMAGE" --format '{{.Id}}')"
esperado_web="$(docker image inspect "$WEB_IMAGE" --format '{{.Id}}')"
[[ "$(docker inspect "$CONTAINER_APP" --format '{{.Image}}')" == "$esperado_app" ]] || erro 'app não está no digest promovido'
[[ "$(docker inspect "$CONTAINER_WEB" --format '{{.Image}}')" == "$esperado_web" ]] || erro 'web não está no digest promovido'

saudavel=0
for tentativa in $(seq 1 20); do
    todos=1
    for url in "${HEALTH_URLS[@]}"; do
        code="$(curl -s -o /dev/null -w '%{http_code}' "$url" || echo 000)"
        [[ "$code" == 200 ]] || { todos=0; log "health ${tentativa}: ${url} -> ${code}"; }
    done
    [[ "$todos" == 1 ]] && { saudavel=1; break; }
    sleep 4
done
if [[ "$saudavel" != 1 ]]; then
    sh "$WRAPPER" logs --tail=80 app web >&2 || true
    erro 'health não respondeu 200'
fi

if [[ "$AMBIENTE" == producao ]]; then
    # Mesmo gate do deploy antigo: produção só fica no ar se a prontidão
    # estrita passa (role sem bypass de RLS, segredos, drivers reais).
    sh "$WRAPPER" exec -T app php artisan golive:check --strict
fi

ln -sfn "${RELEASES_DIR}/${RELEASE_ID}.env" "${RELEASES_DIR}/${AMBIENTE}-current.env"
ln -sfn "$BUNDLE_DIR" "$(dirname "$BUNDLE_DIR")/${AMBIENTE}-current"
log "release ${RELEASE_ID} no ar"

# Limpeza ESCOPADA: só imagens erpnovo-app/erpnovo-web, e nunca as das últimas
# N releases (o rollback precisa delas localmente — o GHCR pode estar fora
# justamente no dia do incidente). `docker image prune -a` está fora de questão:
# a VPS é compartilhada e apagaria imagens de outras aplicações.
# Retém as N mais recentes E a que cada ambiente está rodando: produção costuma
# ficar várias releases atrás da homologação, e é justamente dela que o
# rollback de produção precisa.
mapfile -t manter < <({ ls -1t "$RELEASES_DIR"/*.env 2>/dev/null | grep -v -- '-current.env$' \
    | head -n "$MANTER_RELEASES"; ls -1 "$RELEASES_DIR"/*-current.env 2>/dev/null; } \
    | xargs -r sed -n 's/^\(APP\|WEB\)_IMAGE=//p')
mapfile -t ids_manter < <(for ref in "${manter[@]}"; do docker image inspect "$ref" --format '{{.Id}}' 2>/dev/null; done)
removidas=0
while read -r id; do
    [[ -z "$id" ]] && continue
    printf '%s\n' "${ids_manter[@]}" | grep -qx "$id" && continue
    # Falha (imagem em uso por outro contêiner) é esperada e ignorada.
    docker image rm "$id" >/dev/null 2>&1 && removidas=$((removidas + 1))
done < <(docker image ls --no-trunc --format '{{.Repository}} {{.ID}}' \
    | awk '$1 ~ /\/erpnovo-(app|web)$/ {print $2}' | sort -u)
log "imagens antigas do erp-novo removidas: ${removidas}"

# Bundles de compose antigos: mesma retenção dos manifestos, nunca o que um
# ambiente está usando (o rollback de produção roda a partir dele).
em_uso="$(readlink -f "$(dirname "$BUNDLE_DIR")"/*-current 2>/dev/null || true)"
ls -1dt "$(dirname "$BUNDLE_DIR")"/[0-9a-f]*/ 2>/dev/null | tail -n +"$((MANTER_RELEASES + 1))" \
    | while read -r antigo; do
        printf '%s\n' "$em_uso" | grep -qx "${antigo%/}" || rm -rf "$antigo"
    done
