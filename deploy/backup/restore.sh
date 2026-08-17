#!/usr/bin/env bash
#
# Restore do ERP-NOVO (T3.5 do PLANO_PRODUCAO).
#
# **Um backup nunca testado não é um backup.** Este script existe para ser
# EXECUTADO, não para ficar guardado: é ele que transforma a T3.4 de promessa em
# fato verificado, e é o cronômetro dele que define o RTO real do rollback.
#
# MODO PADRÃO (seguro): restaura num banco DESCARTÁVEL, em container próprio e
# porta própria, sem tocar em nada em execução. É o modo do teste periódico.
#
# MODO --em-producao: restaura POR CIMA do banco de produção. É desastre
# declarado, exige a palavra de confirmação e faz um dump de segurança antes de
# começar — porque restaurar o backup errado sobre a base viva é a única forma
# de piorar um incidente que já estava ruim.
#
# USO
#   bash deploy/backup/restore.sh /opt/backups-erpnovo/erpnovo_XXX.dump
#   bash deploy/backup/restore.sh <dump> --em-producao   # pede confirmação
#
set -Eeuo pipefail

ARQUIVO="${1:-}"
MODO_PRODUCAO=0
[[ "${2:-}" == "--em-producao" ]] && MODO_PRODUCAO=1

CONTAINER_TESTE="${CONTAINER_TESTE:-erpnovo-restore-teste}"
PORTA_TESTE="${PORTA_TESTE:-55433}"
CONTAINER_DB="${CONTAINER_DB:-erpnovo-db}"
CONTAINER_APP="${CONTAINER_APP:-erpnovo-app}"
DB_USER="${DB_USER:-erp}"
DB_NAME="${DB_NAME:-erp_novo}"
SENHA_TESTE="${SENHA_TESTE:-restore_teste}"

INICIO=$(date +%s)

log()  { printf '[restore %s] %s\n' "$(date -u +%H:%M:%S)" "$*"; }
erro() { printf '[restore ERRO] %s\n' "$*" >&2; exit 1; }
trap 'erro "falhou na linha $LINENO"' ERR

[[ -n "$ARQUIVO" ]] || erro "uso: $0 <arquivo.dump> [--em-producao]"
[[ -f "$ARQUIVO" ]] || erro "arquivo não encontrado: $ARQUIVO"

# ── Verifica o checksum ANTES de restaurar ─────────────────────────────────
SHA_ESPERADO="${ARQUIVO%.dump}.sha256"
if [[ -f "$SHA_ESPERADO" ]]; then
  ( cd "$(dirname "$ARQUIVO")" && sha256sum -c "$(basename "$SHA_ESPERADO")" >/dev/null 2>&1 ) \
    && log 'checksum confere' \
    || erro 'CHECKSUM NÃO CONFERE — o arquivo está corrompido, não restaure'
else
  log 'AVISO: sem arquivo .sha256 — não foi possível verificar integridade'
fi

log "arquivo: $(basename "$ARQUIVO") ($(du -h "$ARQUIVO" | cut -f1))"

# ══════════════════════════════════════════════════════════════════════════
# MODO PRODUÇÃO — destrutivo
# ══════════════════════════════════════════════════════════════════════════
if [[ "$MODO_PRODUCAO" -eq 1 ]]; then
  cat <<'AVISO'

  ╔══════════════════════════════════════════════════════════════════════╗
  ║  RESTORE SOBRE A PRODUÇÃO                                            ║
  ║                                                                      ║
  ║  Isto SUBSTITUI o banco de produção pelo conteúdo do backup.         ║
  ║  Tudo gravado depois do backup será PERDIDO.                         ║
  ║                                                                      ║
  ║  Antes de continuar, confirme que:                                   ║
  ║    - este é mesmo o backup certo (confira o timestamp no nome);      ║
  ║    - a aplicação está parada ou em manutenção;                       ║
  ║    - você sabe o que foi gravado desde o horário do backup.          ║
  ╚══════════════════════════════════════════════════════════════════════╝

AVISO
  read -r -p 'Digite RESTAURAR-PRODUCAO para continuar: ' confirmacao
  [[ "$confirmacao" == 'RESTAURAR-PRODUCAO' ]] || erro 'cancelado pelo operador'

  # Dump de segurança do estado ATUAL: se o backup restaurado for o errado,
  # ainda há caminho de volta.
  SEGURANCA="/opt/backups-erpnovo/PRE_RESTORE_$(date -u +%Y%m%d_%H%M%S).dump"
  log "dump de segurança do estado atual → ${SEGURANCA}"
  docker exec "$CONTAINER_DB" pg_dump -U "$DB_USER" -d "$DB_NAME" -Fc -f /tmp/_pre.dump
  docker cp "${CONTAINER_DB}:/tmp/_pre.dump" "$SEGURANCA"
  docker exec "$CONTAINER_DB" rm -f /tmp/_pre.dump
  log 'dump de segurança OK'

  log 'restaurando…'
  docker cp "$ARQUIVO" "${CONTAINER_DB}:/tmp/_restore.dump"
  # --clean --if-exists derruba os objetos antes de recriar; sem isso o restore
  # colide com o schema existente.
  docker exec "$CONTAINER_DB" pg_restore -U "$DB_USER" -d "$DB_NAME" \
    --clean --if-exists --no-owner --no-privileges /tmp/_restore.dump 2>&1 \
    | grep -viE 'warning|does not exist' || true
  docker exec "$CONTAINER_DB" rm -f /tmp/_restore.dump

  ALVO_USER="$DB_USER"; ALVO_DB="$DB_NAME"; ALVO_EXEC=(docker exec "$CONTAINER_DB")
else
  # ════════════════════════════════════════════════════════════════════════
  # MODO TESTE (padrão) — container descartável
  # ════════════════════════════════════════════════════════════════════════
  log "subindo Postgres descartável (${CONTAINER_TESTE}, porta ${PORTA_TESTE})"
  docker rm -f "$CONTAINER_TESTE" >/dev/null 2>&1 || true
  docker run -d --name "$CONTAINER_TESTE" \
    -e POSTGRES_USER="$DB_USER" -e POSTGRES_PASSWORD="$SENHA_TESTE" -e POSTGRES_DB="$DB_NAME" \
    -p "${PORTA_TESTE}:5432" postgres:15-alpine >/dev/null

  log 'aguardando o banco aceitar conexões…'
  for _ in $(seq 1 60); do
    docker exec "$CONTAINER_TESTE" pg_isready -U "$DB_USER" -d "$DB_NAME" >/dev/null 2>&1 && break
    sleep 2
  done
  docker exec "$CONTAINER_TESTE" pg_isready -U "$DB_USER" -d "$DB_NAME" >/dev/null 2>&1 \
    || erro 'o Postgres de teste não subiu'

  log 'restaurando…'
  docker cp "$ARQUIVO" "${CONTAINER_TESTE}:/tmp/_restore.dump"
  # Sem --clean: o banco nasceu vazio. `|| true` porque pg_restore devolve != 0
  # por avisos de owner/privilégio que não existem no container de teste.
  docker exec "$CONTAINER_TESTE" pg_restore -U "$DB_USER" -d "$DB_NAME" \
    --no-owner --no-privileges /tmp/_restore.dump 2>&1 | tail -5 || true
  docker exec "$CONTAINER_TESTE" rm -f /tmp/_restore.dump

  ALVO_USER="$DB_USER"; ALVO_DB="$DB_NAME"; ALVO_EXEC=(docker exec "$CONTAINER_TESTE")
fi

# ── Verificações pós-restore (T3.5 passo 4) ────────────────────────────────
q() { "${ALVO_EXEC[@]}" psql -U "$ALVO_USER" -d "$ALVO_DB" -Atc "$1" 2>/dev/null || echo 'ERRO'; }

log ''
log '── VERIFICAÇÕES ──'
SOMA="$(q 'SELECT round(sum(valor)::numeric,2) FROM public.financeiros')"
N_FIN="$(q 'SELECT count(*) FROM public.financeiros')"
N_PED="$(q 'SELECT count(*) FROM public.pedidos')"
N_NF="$(q  'SELECT count(*) FROM public.notas_fiscais')"
N_CLI="$(q 'SELECT count(*) FROM public.clientes')"
ORFAOS="$(q 'SELECT count(*) FROM public.pedidos p LEFT JOIN public.clientes c ON c.id=p.cliente_id WHERE p.cliente_id IS NOT NULL AND c.id IS NULL')"
DUPS="$(q "SELECT count(*) FROM (SELECT 1 FROM public.clientes WHERE api_id IS NOT NULL GROUP BY empresa_id,api_id HAVING count(*)>1) x")"
TABELAS="$(q "SELECT count(*) FROM information_schema.tables WHERE table_schema='public'")"

printf '  soma financeiros : %s\n' "$SOMA"
printf '  titulos          : %s\n' "$N_FIN"
printf '  pedidos          : %s\n' "$N_PED"
printf '  notas fiscais    : %s\n' "$N_NF"
printf '  clientes         : %s\n' "$N_CLI"
printf '  orfaos (deve 0)  : %s\n' "$ORFAOS"
printf '  duplicatas (0)   : %s\n' "$DUPS"
printf '  tabelas public   : %s\n' "$TABELAS"

FALHOU=0
[[ "$SOMA"   == 'ERRO' || -z "$SOMA"   ]] && { log 'FALHA: financeiros ilegível'; FALHOU=1; }
[[ "$ORFAOS" != '0' ]] && { log "FALHA: ${ORFAOS} pedido(s) órfão(s) após restore"; FALHOU=1; }
[[ "$DUPS"   != '0' ]] && { log "FALHA: ${DUPS} grupo(s) de cliente duplicado"; FALHOU=1; }
[[ "${TABELAS:-0}" -lt 100 ]] && { log "FALHA: só ${TABELAS} tabelas — restore incompleto"; FALHOU=1; }

# ── Storage / certificados A1 ──────────────────────────────────────────────
ARQ_ST="${ARQUIVO%.dump}_storage.tar.gz"
if [[ -f "$ARQ_ST" ]]; then
  N_CERT="$(tar tzf "$ARQ_ST" | grep -c '\.pfx$' || true)"
  log "storage: ${N_CERT} certificado(s) .pfx no backup"
  if [[ "$MODO_PRODUCAO" -eq 1 ]]; then
    log 'restaurando storage…'
    docker cp "$ARQ_ST" "${CONTAINER_APP}:/tmp/_st.tar.gz"
    docker exec "$CONTAINER_APP" tar xzf /tmp/_st.tar.gz -C /var/www/html
    docker exec "$CONTAINER_APP" rm -f /tmp/_st.tar.gz
    docker exec "$CONTAINER_APP" sh -c 'chown -R www-data:www-data storage/app' || true
    log 'storage restaurado'
  fi
else
  log 'AVISO: sem tar de storage — os certificados A1 NÃO estão neste backup'
fi

DURACAO=$(( $(date +%s) - INICIO ))
log ''
log "TEMPO TOTAL: ${DURACAO}s  ← este é o RTO real; registre-o no runbook"

if [[ "$MODO_PRODUCAO" -eq 0 ]]; then
  log ''
  log "banco de teste ativo em localhost:${PORTA_TESTE} (user=${DB_USER})"
  log "para inspecionar: docker exec -it ${CONTAINER_TESTE} psql -U ${DB_USER} -d ${DB_NAME}"
  log "para descartar:   docker rm -f ${CONTAINER_TESTE}"
fi

[[ "$FALHOU" -eq 0 ]] || erro 'RESTORE COM FALHAS — ver acima'
log 'RESTORE VERIFICADO COM SUCESSO'
