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

# PIE R5-02: o default era `/opt/dubena-deploy`, que não existe na VPS — o cron
# instalado rodaria `cd` num diretório ausente e falharia toda noite em
# silêncio. O deploy publica cada release em `releases/<sha>` e mantém o
# symlink `homolog-current` apontando para a vigente.
DIR_DEPLOY="${DIR_DEPLOY:-/opt/dubena/erp-novo/releases/homolog-current/deploy}"
LOG="${LOG:-/var/log/erpnovo-backup.log}"
# 04:30 UTC = 01:30 em Brasília. Era 03:15 — o mesmo minuto do backup de outra
# aplicação da VPS compartilhada (e 03:20 tem um terceiro); dois pg_dump juntos
# disputam o disco.
HORARIO="${HORARIO:-30 4 * * *}"
MARCA='# erpnovo-backup (deploy/backup/instalar-cron.sh)'

# O volume vai explícito na linha: o backup.sh só cai nele quando o contêiner da
# app está fora do ar — exatamente a noite em que o backup mais importa.
VOLUME_STORAGE="${VOLUME_STORAGE:-erpnovo-homolog_app_storage}"
LINHA="${HORARIO} cd ${DIR_DEPLOY} && VOLUME_STORAGE=${VOLUME_STORAGE} bash backup/backup.sh >> ${LOG} 2>&1 ${MARCA}"

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
    su root root
}
ROT
  echo "logrotate configurado para ${LOG}"
fi

echo
echo "Próxima execução: ${HORARIO} (UTC)"
echo "Acompanhe com: tail -f ${LOG}"
