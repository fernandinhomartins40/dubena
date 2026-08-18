#!/usr/bin/env bash
#
# T6.3 — virada do Nginx entre o legado e o erp-novo, com reversão em 1 comando.
#
#   bash deploy/nginx/virar.sh novo      # cutover
#   bash deploy/nginx/virar.sh legado    # ROLLBACK NÍVEL 1 (o mais barato)
#   bash deploy/nginx/virar.sh estado    # onde estamos agora
#
# Por que um script e não `ln -sf` na mão: durante a janela, quem executa está
# sob pressão e possivelmente não é quem escreveu o vhost. Um `nginx -s reload`
# com config inválida derruba o site inteiro — e a validação (`nginx -t`) é
# justamente o passo que se esquece com pressa. Aqui ela é obrigatória.
#
# O script é IDEMPOTENTE: virar para o estado em que já se está não faz nada
# além de confirmar. Isso importa no rollback, onde é comum executar duas vezes
# por dúvida.

set -euo pipefail

ALVO="${1:-}"

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
DIR_CONF="${RAIZ}/deploy/nginx"

# Onde o nginx do host lê os vhosts. Sobrescrevível para ensaiar fora da VPS.
SITES_AVAILABLE="${NGINX_SITES_AVAILABLE:-/etc/nginx/sites-available}"
SITES_ENABLED="${NGINX_SITES_ENABLED:-/etc/nginx/sites-enabled}"
VHOST="gasemcasa-com"

vermelho() { printf '\033[0;31m%s\033[0m\n' "$*"; }
verde()    { printf '\033[0;32m%s\033[0m\n' "$*"; }
amarelo()  { printf '\033[0;33m%s\033[0m\n' "$*"; }

uso() {
    cat <<'FIM'
uso: virar.sh <novo|legado|estado>

  novo      todo o tráfego para o erp-novo (:3120); legado segue de pé em /legado/
  legado    volta ao estado de coexistência (rollback nível 1)
  estado    mostra para onde o vhost ativo aponta hoje

variáveis de ambiente (para ensaiar fora da VPS):
  NGINX_SITES_AVAILABLE, NGINX_SITES_ENABLED
FIM
    exit 2
}

estado_atual() {
    local ativo="${SITES_AVAILABLE}/${VHOST}.conf"

    if [[ ! -e "$ativo" ]]; then
        echo "desconhecido (${ativo} não existe)"
        return
    fi

    # O vhost ativo é um symlink para .legado.conf ou .novo.conf. Se alguém
    # editou o arquivo à mão, ele deixa de ser symlink — e isso precisa
    # aparecer, não ser mascarado.
    if [[ -L "$ativo" ]]; then
        local destino
        destino="$(readlink "$ativo")"
        case "$destino" in
            *.novo.conf)   echo "novo" ;;
            *.legado.conf) echo "legado" ;;
            *)             echo "desconhecido (aponta para ${destino})" ;;
        esac
    else
        echo "desconhecido (arquivo comum, não symlink — editado à mão?)"
    fi
}

if [[ -z "$ALVO" ]]; then
    uso
fi

if [[ "$ALVO" == "estado" ]]; then
    echo "vhost ativo: $(estado_atual)"
    exit 0
fi

if [[ "$ALVO" != "novo" && "$ALVO" != "legado" ]]; then
    vermelho "alvo inválido: ${ALVO}"
    uso
fi

ORIGEM="${DIR_CONF}/${VHOST}.${ALVO}.conf"

if [[ ! -f "$ORIGEM" ]]; then
    vermelho "vhost do estado '${ALVO}' não existe: ${ORIGEM}"
    exit 1
fi

ATUAL="$(estado_atual)"

if [[ "$ATUAL" == "$ALVO" ]]; then
    verde "já está em '${ALVO}' — nada a fazer."
    exit 0
fi

amarelo "virando de '${ATUAL}' para '${ALVO}'…"

# 1) Publica os DOIS vhosts em sites-available. Sem isto, virar de volta
#    exigiria copiar arquivo no meio do rollback — exatamente quando não se
#    quer estar editando nada.
install -m 0644 "${DIR_CONF}/${VHOST}.legado.conf" "${SITES_AVAILABLE}/${VHOST}.legado.conf"
install -m 0644 "${DIR_CONF}/${VHOST}.novo.conf"   "${SITES_AVAILABLE}/${VHOST}.novo.conf"

# 2) Guarda o vhost atual antes de trocar. Se ele foi editado à mão na VPS
#    (estado "arquivo comum"), esta cópia é a única forma de recuperá-lo.
ANTERIOR="${SITES_AVAILABLE}/${VHOST}.conf"
if [[ -e "$ANTERIOR" && ! -L "$ANTERIOR" ]]; then
    BACKUP="${ANTERIOR}.bak.$(date +%Y%m%d-%H%M%S)"
    cp -a "$ANTERIOR" "$BACKUP"
    amarelo "vhost anterior não era symlink; cópia guardada em ${BACKUP}"
fi

# 3) Troca o symlink.
ln -sfn "${SITES_AVAILABLE}/${VHOST}.${ALVO}.conf" "$ANTERIOR"
ln -sfn "$ANTERIOR" "${SITES_ENABLED}/${VHOST}.conf"

# 4) VALIDA antes de recarregar. Este é o passo que justifica o script existir:
#    `nginx -s reload` com config inválida tira o site do ar, e o erro só
#    aparece depois. Aqui, config inválida = reverte e aborta, site intacto.
if ! nginx -t; then
    vermelho "nginx -t REPROVOU — revertendo o symlink e abortando."
    ln -sfn "${SITES_AVAILABLE}/${VHOST}.${ATUAL}.conf" "$ANTERIOR" 2>/dev/null || true
    ln -sfn "$ANTERIOR" "${SITES_ENABLED}/${VHOST}.conf" 2>/dev/null || true
    exit 1
fi

# 5) Recarrega sem derrubar conexões em curso.
nginx -s reload

verde "vhost agora em '${ALVO}'. Confirme com:"
echo "  curl -sf -o /dev/null -w '%{http_code}\\n' https://gasemcasa.com/"
echo "  curl -sf -o /dev/null -w '%{http_code}\\n' https://gasemcasa.com/novo/up"

if [[ "$ALVO" == "novo" ]]; then
    echo
    amarelo "Rollback nível 1 (segundos):  bash deploy/nginx/virar.sh legado"
fi
