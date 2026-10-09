#!/usr/bin/env bash
# Prepara o SSH do runner do GitHub para falar com a VPS (alias `vps`).
#
# Autenticação por senha (secret VPS_PASSWORD, lido pelo `sshpass -e` da
# variável SSHPASS — nunca por argumento, que apareceria em `ps`). A identidade
# do SERVIDOR é fixada: VPS_HOSTKEY vai para o known_hosts e
# `StrictHostKeyChecking yes` recusa qualquer outro host no mesmo IP. Sem isso
# um intermediário poderia se passar pela VPS e receber a senha.
set -euo pipefail

: "${VPS_IP:?VPS_IP ausente}"
: "${VPS_HOSTKEY:?VPS_HOSTKEY ausente}"

command -v sshpass >/dev/null || { sudo apt-get update -qq; sudo apt-get install -y -qq sshpass; }

install -d -m 700 ~/.ssh
printf '%s\n' "$VPS_HOSTKEY" > ~/.ssh/known_hosts
cat > ~/.ssh/config <<EOF
Host vps
  HostName ${VPS_IP}
  User root
  PreferredAuthentications password
  PubkeyAuthentication no
  StrictHostKeyChecking yes
  ConnectTimeout 20
  ServerAliveInterval 15
EOF
chmod 600 ~/.ssh/config

sshpass -e ssh vps true
echo "SSH com a VPS verificado."
