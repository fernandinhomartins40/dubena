#!/bin/sh
# Gera os AAB de PRODUÇÃO (Play Store) dos apps, localmente, sem EAS.
#
#   sh scripts/build-aab-producao.sh cliente|entregador|ambos
#
# Lê de fora do repositório (C:\Users\<voce>\.dubena-play\):
#   keystore.properties  chave de UPLOAD da Play (o plugin withUploadSigning
#                        aborta o bundleRelease sem ela — não sai AAB com a de debug)
#   build.env            GOOGLE_MAPS_API_KEY e REVERB_APP_KEY (públicas: vão no app,
#                        mas ficam fora do git para poderem ser trocadas sem commit)
#
# Os apps falam com o ERP novo publicado na VPS (APP_URL=https://gasemcasa.com/novo).
# O Reverb entra pelo /reverb/ do nginx do mesmo domínio.
set -eu

ALVO="${1:-ambos}"
RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
CHAVES="${DUBENA_PLAY_DIR:-$HOME/.dubena-play}"

[ -r "$CHAVES/keystore.properties" ] || { echo "Falta $CHAVES/keystore.properties" >&2; exit 78; }
[ -r "$CHAVES/build.env" ] || { echo "Falta $CHAVES/build.env" >&2; exit 78; }

export JAVA_HOME="${JAVA_HOME:-/c/Program Files/Microsoft/jdk-17.0.20.101-hotspot}"
export ANDROID_HOME="${ANDROID_HOME:-$LOCALAPPDATA/Android/Sdk}"
export PATH="$JAVA_HOME/bin:$PATH"

set -a
. "$CHAVES/build.env"
set +a
export APP_ENV=prod APP_DEBUG=false
export API_URL=https://gasemcasa.com/novo/api/
export REVERB_HOST=gasemcasa.com REVERB_PORT=443 REVERB_SCHEME=https REVERB_PATH=/reverb
# Atalho "Modo teste" do entregador NUNCA vai para a loja.
unset LOGIN_TESTE_EMAIL LOGIN_TESTE_SENHA

# Propriedades da chave de upload viram -P (não ficam no gradle.properties global).
PROPS=$(grep -E '^DUBENA_UPLOAD_' "$CHAVES/keystore.properties" | sed 's/^/-P/' | tr '\n' ' ')

gera() {
    app="$1"; saida="$2"
    cd "$RAIZ/$app"
    npx expo prebuild --platform android --clean --no-install
    echo "sdk.dir=$(cygpath -m "$ANDROID_HOME")" > android/local.properties
    cd android
    # shellcheck disable=SC2086
    ./gradlew.bat bundleRelease $PROPS --no-daemon
    mkdir -p "$RAIZ/apks"
    cp app/build/outputs/bundle/release/app-release.aab "$RAIZ/apks/$saida"
    "$JAVA_HOME/bin/jarsigner" -verify -certs "$RAIZ/apks/$saida" | tail -1
    ls -l "$RAIZ/apks/$saida"
}

case "$ALVO" in
    cliente)    EMPRESA_ID=2 gera app-gas-em-casa gasemcasa-producao.aab ;;
    entregador) gera app-entregador gasemcasa-entregador-producao.aab ;;
    ambos)
        EMPRESA_ID=2 gera app-gas-em-casa gasemcasa-producao.aab
        gera app-entregador gasemcasa-entregador-producao.aab ;;
    *) echo "uso: $0 cliente|entregador|ambos" >&2; exit 64 ;;
esac
echo AAB_OK
