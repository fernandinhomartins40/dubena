#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf -- "$TMP"' EXIT

mkdir -p "$TMP/certs/live/gasemcasa.com" \
             "$TMP/certs/live/homolog-erp.gasemcasa.com.br"
openssl req -x509 -newkey rsa:2048 -nodes -days 1 \
    -subj '/CN=gate.local' \
    -keyout "$TMP/privkey.pem" -out "$TMP/fullchain.pem" >/dev/null 2>&1

for domain in gasemcasa.com homolog-erp.gasemcasa.com.br; do
    cp "$TMP/fullchain.pem" "$TMP/certs/live/$domain/fullchain.pem"
    cp "$TMP/privkey.pem" "$TMP/certs/live/$domain/privkey.pem"
done

image='nginx:1.29-alpine@sha256:5616878291a2eed594aee8db4dade5878cf7edcb475e59193904b198d9b830de'

grep -q '127.0.0.1:3130' "$ROOT/deploy/nginx/gasemcasa-com.legado.conf"
grep -q '127.0.0.1:3130' "$ROOT/deploy/nginx/gasemcasa-com.novo.conf"
! grep -Eq '127\.0\.0\.1:312[01]' "$ROOT/deploy/nginx/gasemcasa-com.legado.conf" "$ROOT/deploy/nginx/gasemcasa-com.novo.conf"
grep -q '127.0.0.1:3120' "$ROOT/deploy/nginx/homolog-erp.conf"
grep -q '127.0.0.1:3130:80' "$ROOT/erp-novo/docker-compose.producao.yml"
grep -q '127.0.0.1:3131:8080' "$ROOT/erp-novo/docker-compose.producao.yml"
grep -q 'Content-Security-Policy' "$ROOT/erp-novo/docker/nginx/security-headers.conf"
grep -q 'Strict-Transport-Security' "$ROOT/erp-novo/docker/nginx/security-headers.conf"

for config in gasemcasa-com.legado.conf gasemcasa-com.novo.conf homolog-erp.conf; do
    docker run --rm \
        -v "$ROOT/deploy/nginx/$config:/etc/nginx/conf.d/default.conf:ro" \
        -v "$ROOT/deploy/nginx/erpnovo-proxy-params.conf:/etc/nginx/erpnovo-proxy-params.conf:ro" \
        -v "$TMP/certs:/etc/letsencrypt:ro" \
        "$image" nginx -t
done
