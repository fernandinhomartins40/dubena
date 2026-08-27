#!/bin/sh
set -eu

: "${REGISTRY_PREFIX:?defina REGISTRY_PREFIX (ex.: registry/org)}"
: "${RELEASE_ID:?defina RELEASE_ID com o identificador auditável da release}"

case "$RELEASE_ID" in
    *[!A-Za-z0-9._-]*|'') echo "RELEASE_ID inválido" >&2; exit 78 ;;
esac

root="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
artifacts="${RELEASE_ARTIFACTS_DIR:-$root/release-artifacts/$RELEASE_ID}"
app_tag="${REGISTRY_PREFIX%/}/erpnovo-app:${RELEASE_ID}"
web_tag="${REGISTRY_PREFIX%/}/erpnovo-web:${RELEASE_ID}"

mkdir -p "$artifacts"

build_push() {
    target="$1"
    tag="$2"
    metadata="$3"
    docker buildx build \
        --file "$root/docker/php/Dockerfile" \
        --target "$target" \
        --tag "$tag" \
        --provenance=mode=max \
        --sbom=true \
        --push \
        --metadata-file "$metadata" \
        "$root"
}

build_push runtime "$app_tag" "$artifacts/app-build-metadata.json"
build_push web "$web_tag" "$artifacts/web-build-metadata.json"

digest_of() {
    docker buildx imagetools inspect "$1" | awk '/^Digest:/ { print $2; exit }'
}

app_digest="$(digest_of "$app_tag")"
web_digest="$(digest_of "$web_tag")"
case "$app_digest:$web_digest" in
    sha256:*:sha256:*) ;;
    *) echo "Não foi possível resolver os digests publicados" >&2; exit 1 ;;
esac

app_image="${app_tag}@${app_digest}"
web_image="${web_tag}@${web_digest}"

docker pull "$app_image"
docker pull "$web_image"
trivy_image='aquasec/trivy:0.66.0@sha256:086971aaf400beebd94e8300fd8ea623774419597169156cec56eec5b00dfb1e'
docker run --rm \
    -v /var/run/docker.sock:/var/run/docker.sock \
    -v erpnovo-trivy-cache:/root/.cache \
    -v "$artifacts:/reports" \
    "$trivy_image" image --scanners vuln --ignore-unfixed --severity CRITICAL \
    --exit-code 1 --format json --output /reports/app.trivy.json "$app_image"
docker run --rm \
    -v /var/run/docker.sock:/var/run/docker.sock \
    -v erpnovo-trivy-cache:/root/.cache \
    -v "$artifacts:/reports" \
    "$trivy_image" image --scanners vuln --ignore-unfixed --severity CRITICAL \
    --exit-code 1 --format json --output /reports/web.trivy.json "$web_image"
syft_image='anchore/syft:v1.33.0@sha256:f94e5d9fce1f2278491a8e3a63bd5f6ddb81fdfdbb8bf7a1637565c1d5344357'
docker run --rm \
    -v /var/run/docker.sock:/var/run/docker.sock \
    -v erpnovo-syft-cache:/root/.cache \
    "$syft_image" "$app_image" -o spdx-json > "$artifacts/app.spdx.json"
docker run --rm \
    -v /var/run/docker.sock:/var/run/docker.sock \
    -v erpnovo-syft-cache:/root/.cache \
    "$syft_image" "$web_image" -o spdx-json > "$artifacts/web.spdx.json"

{
    printf 'RELEASE_ID=%s\n' "$RELEASE_ID"
    printf 'APP_IMAGE=%s\n' "$app_image"
    printf 'WEB_IMAGE=%s\n' "$web_image"
} > "$artifacts/release.env"

printf 'Release publicada. Promova exatamente as referências de %s/release.env\n' "$artifacts"
