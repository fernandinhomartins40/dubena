#!/usr/bin/env bash
#
# Rollback da aplicação para uma versão anterior (T3.8 do PLANO_PRODUCAO).
#
# Antes disto, "voltar" significava fazer um push novo e esperar o build: a
# imagem era `erpnovo-app:homolog` (tag fixa, sem versão) e o código vinha por
# bind-mount do checkout, então nem trocar a tag adiantava. O compose de
# produção (T3.2) taggeia por SHA e embute o código na imagem — é isso que torna
# este script possível.
#
# USO
#   bash deploy/rollback.sh            # volta para a tag imediatamente anterior
#   bash deploy/rollback.sh <sha>      # volta para um SHA específico
#   bash deploy/rollback.sh --listar   # mostra as versões disponíveis
#
# ⚠️  LIMITE IMPORTANTE — MIGRATIONS
#
# Rollback de CÓDIGO não desfaz migrations. Ele funciona quando as migrations da
# versão nova são ADITIVAS (coluna nullable, tabela nova): o código antigo
# simplesmente ignora o que não conhece.
#
# Se a versão nova rodou uma migration DESTRUTIVA (drop/rename de coluna ou
# tabela), o código antigo quebra contra o banco novo, e o caminho é o RESTORE
# (deploy/backup/restore.sh) — com a perda de tudo gravado desde o backup.
#
# REGRA DE PROJETO decorrente: nenhuma migration destrutiva entra em produção no
# mesmo deploy que introduz o código que para de usar a coluna. A remoção vem
# num deploy POSTERIOR, depois de o código antigo estar fora de circulação.
#
set -Eeuo pipefail

COMPOSE="${COMPOSE:-docker-compose.producao.yml}"
DIR_APP="${DIR_APP:-/opt/actions-runner-dubena/_work/dubena/dubena/erp-novo}"
CONTAINER_APP="${CONTAINER_APP:-erpnovo-prod-app}"
URL_HEALTH="${URL_HEALTH:-http://127.0.0.1:3130/up}"

log()  { printf '[rollback %s] %s\n' "$(date -u +%H:%M:%S)" "$*"; }
erro() { printf '[rollback ERRO] %s\n' "$*" >&2; exit 1; }
trap 'erro "falhou na linha $LINENO"' ERR

# ── Versões disponíveis ────────────────────────────────────────────────────
listar_tags() {
  docker images 'erpnovo-app' --format '{{.Tag}}\t{{.CreatedAt}}' \
    | grep -vE '^(producao-atual|homolog|latest|<none>)' \
    | sort -k2 -r
}

if [[ "${1:-}" == '--listar' ]]; then
  echo 'Versões disponíveis (mais recente primeiro):'
  listar_tags | awk '{printf "  %-12s %s %s\n", $1, $2, $3}'
  exit 0
fi

ALVO="${1:-}"

if [[ -z "$ALVO" ]]; then
  # Sem argumento: a segunda mais recente (a primeira é a que está no ar).
  ALVO="$(listar_tags | sed -n '2p' | cut -f1)"
  [[ -n "$ALVO" ]] || erro 'não há versão anterior disponível — use --listar'
  log "nenhum SHA informado; usando a versão anterior: ${ALVO}"
fi

docker image inspect "erpnovo-app:${ALVO}" >/dev/null 2>&1 \
  || erro "imagem erpnovo-app:${ALVO} não existe neste host (veja --listar)"

cd "$DIR_APP" 2>/dev/null || erro "diretório do app não encontrado: ${DIR_APP}"
[[ -f "$COMPOSE" ]] || erro "compose não encontrado: ${DIR_APP}/${COMPOSE}"

ATUAL="$(docker inspect "$CONTAINER_APP" --format '{{.Config.Image}}' 2>/dev/null | sed 's/.*://' || echo '?')"
log "versão atual: ${ATUAL} → alvo: ${ALVO}"

# ── Aviso sobre migrations ─────────────────────────────────────────────────
log 'verificando migrations aplicadas depois da versão alvo…'
RECENTES="$(docker compose -f "$COMPOSE" exec -T db psql -U "${DB_USER:-erp}" -d "${DB_NAME:-erp_novo}" -Atc \
  "SELECT count(*) FROM migrations WHERE migration LIKE '2026_%'" 2>/dev/null || echo '?')"
log "migrations no banco: ${RECENTES} (rollback de código NÃO as desfaz — ver cabeçalho)"

# ── Troca a tag e sobe ─────────────────────────────────────────────────────
log 'aplicando…'
GIT_SHA="$ALVO" docker compose -f "$COMPOSE" up -d --no-build --force-recreate

for i in $(seq 1 20); do
  st="$(docker inspect -f '{{.State.Status}}' "$CONTAINER_APP" 2>/dev/null || echo none)"
  [[ "$st" == 'running' ]] && break
  log "aguardando app (${st})…"
  sleep 3
done
[[ "$st" == 'running' ]] || erro "app não subiu (status=${st})"

# Caches apontam para o código: precisam ser refeitos com o código antigo.
log 'refazendo caches…'
docker compose -f "$COMPOSE" exec -T app php artisan optimize:clear >/dev/null
docker compose -f "$COMPOSE" exec -T app php artisan config:cache >/dev/null
docker compose -f "$COMPOSE" exec -T app php artisan route:cache >/dev/null || true
docker compose -f "$COMPOSE" exec -T app php artisan view:cache >/dev/null || true

# ── Health ─────────────────────────────────────────────────────────────────
for i in $(seq 1 12); do
  code="$(curl -s -o /dev/null -w '%{http_code}' "$URL_HEALTH" || echo 000)"
  if [[ "$code" == '200' ]]; then
    log "health OK (${code})"
    docker tag "erpnovo-app:${ALVO}" 'erpnovo-app:producao-atual' || true
    log "ROLLBACK CONCLUÍDO: ${ATUAL} → ${ALVO}"
    log 'Se o problema PERSISTE, a causa pode ser o banco: ver deploy/backup/README_RESTORE.md'
    exit 0
  fi
  log "aguardando health (${code})…"
  sleep 5
done

erro "health não respondeu 200 após o rollback — a aplicação segue fora. Ver README_RESTORE.md"
