#!/usr/bin/env bash
# Envia para a VPS só os arquivos de implantação da release (alguns KB), numa
# pasta por release — nunca o repositório. É dessa pasta que o deploy e o
# rollback rodam, sem checkout no servidor.
set -euo pipefail

: "${RELEASE_ID:?RELEASE_ID ausente}"
: "${BUNDLE_BASE:?BUNDLE_BASE ausente}"

tar -czf - \
    erp-novo/docker-compose.homolog.yml erp-novo/docker-compose.producao.yml \
    erp-novo/docker/compose-homolog.sh erp-novo/docker/compose-production.sh \
    deploy/remote-deploy.sh deploy/rollback.sh deploy/backup \
  | sshpass -e ssh vps "set -e; d='${BUNDLE_BASE}/${RELEASE_ID}'; mkdir -p \"\$d\"; tar -xzf - -C \"\$d\""

echo "Bundle da release ${RELEASE_ID} enviado."
