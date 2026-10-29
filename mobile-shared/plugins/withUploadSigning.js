// Plugin de configuração do Expo: assina o release Android com a CHAVE DE UPLOAD
// da Play Console, lida de propriedades do Gradle FORA do repositório.
//
// Por que existe: o template do Expo assina o release com a chave de debug
// ("Caution! In production, you need to generate your own keystore"). A Play
// recusa AAB assinado assim, e um AAB enviado com a chave errada amarra o app a
// ela. Como `expo prebuild --clean` regenera o android/, a correção tem de viver
// aqui, não no build.gradle.
//
// Propriedades (em ~/.gradle/gradle.properties ou -P na linha de comando):
//   DUBENA_UPLOAD_STORE_FILE      caminho absoluto do .jks
//   DUBENA_UPLOAD_STORE_PASSWORD
//   DUBENA_UPLOAD_KEY_ALIAS
//   DUBENA_UPLOAD_KEY_PASSWORD
//
// Fail-closed: `bundleRelease` sem as propriedades ABORTA o build. O APK de
// homologação (`assembleRelease`) sem elas continua com a chave de debug — é a
// impressão digital dela que está cadastrada no Firebase para os testes.
const path = require("path")

// O plugin mora no mobile-shared, mas o expo instalado é o do app que roda o prebuild.
const { withAppBuildGradle } = require(require.resolve("expo/config-plugins", { paths: [process.cwd()] }))

const MARCA = "// dubena:upload-signing"

const BLOCO_SIGNING = `
        ${MARCA}
        upload {
            if (findProperty('DUBENA_UPLOAD_STORE_FILE')) {
                storeFile file(findProperty('DUBENA_UPLOAD_STORE_FILE'))
                storePassword findProperty('DUBENA_UPLOAD_STORE_PASSWORD')
                keyAlias findProperty('DUBENA_UPLOAD_KEY_ALIAS')
                keyPassword findProperty('DUBENA_UPLOAD_KEY_PASSWORD')
            }
        }`

const RELEASE_SIGNING = `signingConfig (findProperty('DUBENA_UPLOAD_STORE_FILE') ? signingConfigs.upload : signingConfigs.debug)`

const GUARDA_AAB = `
${MARCA}
gradle.taskGraph.whenReady { graph ->
    def geraAab = graph.allTasks.any { it.name.startsWith('bundle') && it.name.endsWith('Release') }
    if (geraAab && !findProperty('DUBENA_UPLOAD_STORE_FILE')) {
        throw new GradleException("AAB de release exige a chave de upload (DUBENA_UPLOAD_STORE_FILE). Sem ela sairia assinado com a chave de debug, que a Play recusa.")
    }
}
`

module.exports = function withUploadSigning(config) {
    return withAppBuildGradle(config, (cfg) => {
        let g = cfg.modResults.contents
        if (g.includes(MARCA)) return cfg

        const abre = "signingConfigs {"
        const release = /(release\s*\{[^}]*?)signingConfig signingConfigs\.debug/
        if (!g.includes(abre) || !release.test(g)) {
            // Template mudou: melhor quebrar o prebuild do que gerar AAB com a chave errada.
            throw new Error(`withUploadSigning: build.gradle em formato inesperado (${path.basename(cfg.modRequest.projectRoot)})`)
        }
        g = g.replace(abre, abre + BLOCO_SIGNING)
        g = g.replace(release, `$1${RELEASE_SIGNING}`)
        g += GUARDA_AAB
        cfg.modResults.contents = g
        return cfg
    })
}
