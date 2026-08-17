#!/usr/bin/env bash
#
# Agenda o backup diário no cron do HOST (T3.4 passo 5).
#
# Deliberadamente no cron do host e não no `schedule:work` do Laravel: o backup
# precisa rodar exatamente quando a aplicação está quebrada — que é justamente
# quando o scheduler do Laravel não roda.
#
# Idempotente: reexecutar não duplica a entrada.
#
set -Eeuo pipefail

DIR_DEPLOY="${DIR_DEPLOY:-/opt/dubena-deploy}"
LOG="${LOG:-/var/log/erpnovo-backup.log}"
HORARIO="${HORARIO:-15 3 * * *}"   # 03:15 UTC = 00:15 em Brasília
MARCA='# erpnovo-backup (deploy/backup/instalar-cron.sh)'

LINHA="${HORARIO} cd ${DIR_DEPLOY} && bash backup/backup.sh >> ${LOG} 2>&1 ${MARCA}"

atual="$(crontab -l 2>/dev/null || true)"

if grep -qF "$MARCA" <<<"$atual"; then
  echo 'Entrada já existe — substituindo pela versão atual.'
  atual="$(grep -vF "$MARCA" <<<"$atual")"
fi

printf '%s\n%s\n' "$atual" "$LINHA" | grep -v '^$' | crontab -

echo 'Cron instalado:'
crontab -l | grep -F "$MARCA"

# Rotação do log do próprio backup: sem isto o arquivo cresce para sempre.
if [[ -d /etc/logrotate.d ]]; then
  cat > /etc/logrotate.d/erpnovo-backup <<ROT
${LOG} {
    weekly
    rotate 8
    compress
    missingok
    notifempty
    copytruncate
}
ROT
  echo "logrotate configurado para ${LOG}"
fi

echo
echo "Próxima execução: ${HORARIO} (UTC)"
echo "Acompanhe com: tail -f ${LOG}"
