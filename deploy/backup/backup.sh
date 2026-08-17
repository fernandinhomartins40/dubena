#!/usr/bin/env bash
#
# Backup do ERP-NOVO (T3.4 do PLANO_PRODUCAO): banco + storage.
#
# POR QUE ISTO EXISTE. Até 17/08/2026 não havia NENHUMA rotina de backup — nem
# no repositório, nem no host. Os dados vivem em volumes Docker: um
# `docker volume rm` ou a recriação do host levaria embora 443.714 títulos
# (R$ 250.029.904,80), 241.021 NF-e e — o que é insubstituível — os
# certificados A1 fiscais, cuja reemissão custa dinheiro e tempo de cartório.
#
# O QUE ELE COPIA
#   1. o banco inteiro, via `pg_dump -Fc` (formato custom: comprimido, permite
#      restore seletivo por tabela);
#   2. o volume `app_storage`, que contém `storage/app/certificados/empresa_*/`
#      (os .pfx), uploads e logs.
#
# DECISÕES DELIBERADAS
#   - Roda no HOST, via cron, e não pelo `schedule:work` do Laravel: o backup
#     precisa funcionar exatamente quando a aplicação está quebrada.
#   - `pg_dump` roda DENTRO do container do banco (não precisa de cliente pg no
#     host) mas escreve no host, para não encher o volume que está copiando.
#   - Checksum SHA-256 de cada artefato: um backup corrompido que ninguém
#     detectou é pior que backup nenhum, porque cria falsa confiança.
#   - Falha em QUALQUER etapa = exit != 0, para o passo de deploy abortar.
#
# USO
#   bash deploy/backup/backup.sh                  # usa os defaults abaixo
#   DESTINO=/mnt/outro bash deploy/backup/backup.sh
#   bash deploy/backup/backup.sh --sem-storage    # só o banco (mais rápido)
#
set -Eeuo pipefail

# ── Configuração (sobrescrevível por ambiente) ──────────────────────────────
DESTINO="${DESTINO:-/opt/backups-erpnovo}"
CONTAINER_DB="${CONTAINER_DB:-erpnovo-db}"
CONTAINER_APP="${CONTAINER_APP:-erpnovo-app}"
DB_USER="${DB_USER:-erp}"
DB_NAME="${DB_NAME:-erp_novo}"
VOLUME_STORAGE="${VOLUME_STORAGE:-erp-novo_app_storage}"

# Retenção (T3.4 passo 4).
RETENCAO_DIARIA="${RETENCAO_DIARIA:-7}"
RETENCAO_SEMANAL="${RETENCAO_SEMANAL:-4}"
RETENCAO_MENSAL="${RETENCAO_MENSAL:-6}"

COM_STORAGE=1
[[ "${1:-}" == "--sem-storage" ]] && COM_STORAGE=0

CARIMBO="$(date -u +%Y%m%d_%H%M%S)"
SHA_DEPLOY="$(git -C "$(dirname "${BASH_SOURCE[0]}")/../.." rev-parse --short HEAD 2>/dev/null || echo 'sem-git')"
PREFIXO="erpnovo_${CARIMBO}_${SHA_DEPLOY}"

log()  { printf '[backup %s] %s\n' "$(date -u +%H:%M:%S)" "$*"; }
erro() { printf '[backup ERRO] %s\n' "$*" >&2; exit 1; }

trap 'erro "falhou na linha $LINENO"' ERR

# ── Pré-condições ──────────────────────────────────────────────────────────
command -v docker >/dev/null || erro 'docker não encontrado no PATH'
docker inspect "$CONTAINER_DB" >/dev/null 2>&1 || erro "container ${CONTAINER_DB} não existe"

mkdir -p "$DESTINO"

# Espaço livre: aborta se houver menos que o dobro do tamanho do banco. Um
# backup que enche o disco derruba a produção que ele deveria proteger.
TAM_BANCO_MB="$(docker exec "$CONTAINER_DB" psql -U "$DB_USER" -d "$DB_NAME" -Atc \
  "SELECT (pg_database_size('${DB_NAME}')/1024/1024)::bigint" 2>/dev/null || echo 0)"
LIVRE_MB="$(df -Pm "$DESTINO" | awk 'NR==2{print $4}')"
if [[ "$TAM_BANCO_MB" -gt 0 && "$LIVRE_MB" -lt $((TAM_BANCO_MB * 2)) ]]; then
  erro "espaço insuficiente em ${DESTINO}: ${LIVRE_MB}MB livres, banco tem ${TAM_BANCO_MB}MB"
fi

log "destino=${DESTINO} banco=${TAM_BANCO_MB}MB livre=${LIVRE_MB}MB sha=${SHA_DEPLOY}"

# ── 1) Banco ───────────────────────────────────────────────────────────────
ARQ_DB="${DESTINO}/${PREFIXO}.dump"
log 'pg_dump…'
docker exec "$CONTAINER_DB" pg_dump -U "$DB_USER" -d "$DB_NAME" -Fc -f /tmp/_bk.dump
docker cp "${CONTAINER_DB}:/tmp/_bk.dump" "$ARQ_DB"
docker exec "$CONTAINER_DB" rm -f /tmp/_bk.dump

[[ -s "$ARQ_DB" ]] || erro 'dump do banco saiu vazio'

# Valida que o dump é legível ANTES de declarar sucesso: `pg_restore -l` lê o
# índice do arquivo e falha se ele estiver truncado ou corrompido.
OBJETOS="$(docker run --rm -v "${DESTINO}:/bk:ro" postgres:15-alpine \
  pg_restore -l "/bk/$(basename "$ARQ_DB")" 2>/dev/null | grep -c ';' || echo 0)"
[[ "$OBJETOS" -gt 100 ]] || erro "dump ilegível ou suspeito (${OBJETOS} objetos)"
log "banco: $(du -h "$ARQ_DB" | cut -f1), ${OBJETOS} objetos"

# ── 2) Storage (certificados A1, uploads) ──────────────────────────────────
ARQ_ST=''
if [[ "$COM_STORAGE" -eq 1 ]]; then
  ARQ_ST="${DESTINO}/${PREFIXO}_storage.tar.gz"
  log 'storage (certificados A1 + uploads)…'

  # Copia pelo próprio container da app: dispensa saber o nome do volume e
  # funciona igual em qualquer topologia.
  #
  # O caminho da app é DESCOBERTO, não presumido: a imagem usa /var/www (não
  # /var/www/html, que é o default de muitas imagens PHP). Um caminho fixo aqui
  # produzia "tar falhou" — e, pior, produziria um tar VAZIO com sucesso se o
  # diretório existisse mas fosse o errado.
  if docker inspect "$CONTAINER_APP" >/dev/null 2>&1; then
    RAIZ_APP="$(docker inspect "$CONTAINER_APP" \
      --format '{{range .Mounts}}{{if eq .Destination "/var/www/storage"}}/var/www{{end}}{{if eq .Destination "/var/www/html/storage"}}/var/www/html{{end}}{{end}}')"
    RAIZ_APP="${RAIZ_APP:-/var/www}"

    docker exec "$CONTAINER_APP" test -d "${RAIZ_APP}/storage/app" \
      || erro "storage/app não existe em ${RAIZ_APP} dentro de ${CONTAINER_APP}"

    docker exec "$CONTAINER_APP" tar czf /tmp/_st.tar.gz -C "$RAIZ_APP" storage/app \
      || erro 'tar do storage falhou'
    docker cp "${CONTAINER_APP}:/tmp/_st.tar.gz" "$ARQ_ST"
    docker exec "$CONTAINER_APP" rm -f /tmp/_st.tar.gz
  else
    docker run --rm -v "${VOLUME_STORAGE}:/st:ro" -v "${DESTINO}:/bk" alpine \
      tar czf "/bk/$(basename "$ARQ_ST")" -C /st . || erro 'tar do volume falhou'
  fi

  [[ -s "$ARQ_ST" ]] || erro 'tar do storage saiu vazio'
  tar tzf "$ARQ_ST" >/dev/null || erro 'tar do storage ilegível'

  N_CERT="$(tar tzf "$ARQ_ST" | grep -c 'certificados/' || true)"
  log "storage: $(du -h "$ARQ_ST" | cut -f1), ${N_CERT} caminho(s) de certificado"
fi

# ── 3) Checksums ───────────────────────────────────────────────────────────
ARQ_SHA="${DESTINO}/${PREFIXO}.sha256"
( cd "$DESTINO" && sha256sum "$(basename "$ARQ_DB")" $( [[ -n "$ARQ_ST" ]] && basename "$ARQ_ST" ) > "$(basename "$ARQ_SHA")" )
( cd "$DESTINO" && sha256sum -c "$(basename "$ARQ_SHA")" >/dev/null ) || erro 'checksum não confere'
log 'checksums OK'

# ── 4) Cópia fora do host ──────────────────────────────────────────────────
# A auditoria é explícita: volume Docker é ponto único de falha. Sem destino
# remoto configurado, AVISA — não falha, para não bloquear o deploy por algo
# que exige provisionamento externo.
if [[ -n "${BACKUP_REMOTO:-}" ]]; then
  log "replicando para ${BACKUP_REMOTO}…"
  rsync -a --partial "$ARQ_DB" ${ARQ_ST:+"$ARQ_ST"} "$ARQ_SHA" "$BACKUP_REMOTO/" \
    || erro 'rsync para o destino remoto falhou'
  log 'réplica remota OK'
else
  log 'AVISO: BACKUP_REMOTO não definido — a cópia existe SÓ neste host.'
  log 'AVISO: um incidente de host leva junto o backup. Configure um destino externo.'
fi

# ── 5) Retenção ────────────────────────────────────────────────────────────
# Diário: mantém os N mais recentes. Semanal: o de domingo. Mensal: o do dia 1.
podar() {
  local padrao="$1" manter="$2"
  # `|| true` em cada etapa: sob `set -e`, um glob sem correspondência (destino
  # recém-criado) ou um `ls` vazio derrubaria o script DEPOIS de o backup já
  # estar pronto — falhar na faxina não pode invalidar a cópia.
  # shellcheck disable=SC2012
  ls -1t ${DESTINO}/${padrao} 2>/dev/null | tail -n +$((manter + 1)) 2>/dev/null | while read -r velho; do
    [[ -f "$velho" ]] || continue
    log "removendo antigo: $(basename "$velho")"
    rm -f "$velho"
  done || true
}

# Preserva os "aniversariantes" (domingo e dia 1) renomeando-os com sufixo, para
# que a poda diária não os alcance.
for arq in "${DESTINO}"/erpnovo_*.dump; do
  [[ -e "$arq" ]] || continue
  dia="$(basename "$arq" | sed -E 's/erpnovo_([0-9]{8})_.*/\1/')"
  [[ "$dia" =~ ^[0-9]{8}$ ]] || continue
  dow="$(date -u -d "$dia" +%u 2>/dev/null || echo 0)"
  dom="$(date -u -d "$dia" +%d 2>/dev/null || echo 0)"
  if [[ "$dom" == "01" && "$arq" != *_mensal.dump ]]; then
    mv -n "$arq" "${arq%.dump}_mensal.dump" 2>/dev/null || true
  elif [[ "$dow" == "7" && "$arq" != *_semanal.dump ]]; then
    mv -n "$arq" "${arq%.dump}_semanal.dump" 2>/dev/null || true
  fi
done

podar 'erpnovo_*[0-9].dump'        "$RETENCAO_DIARIA"      || true
podar 'erpnovo_*_semanal.dump'     "$RETENCAO_SEMANAL"     || true
podar 'erpnovo_*_mensal.dump'      "$RETENCAO_MENSAL"      || true
podar 'erpnovo_*_storage.tar.gz'   "$RETENCAO_DIARIA"      || true
podar 'erpnovo_*.sha256'           "$((RETENCAO_DIARIA + RETENCAO_SEMANAL + RETENCAO_MENSAL))" || true

log "CONCLUÍDO: $(basename "$ARQ_DB")"
log "restore: bash deploy/backup/restore.sh ${ARQ_DB}"
