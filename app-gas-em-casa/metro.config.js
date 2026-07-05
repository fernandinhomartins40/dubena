// Metro config — inclui o pacote COMPARTILHADO (../mobile-shared) fora da raiz do
// app (M-2). Sem isto o Metro não empacota arquivos acima do projeto, e o alias
// @shared (definido no tsconfig só para o tsc) não resolveria em runtime.
const { getDefaultConfig } = require("expo/metro-config")
const path = require("path")

const projectRoot = __dirname
const sharedRoot = path.resolve(projectRoot, "..", "mobile-shared")

const config = getDefaultConfig(projectRoot)

// 1) Observa o pacote compartilhado para hot-reload.
config.watchFolders = [...(config.watchFolders ?? []), sharedRoot]

// 2) Resolve o alias @shared/* → mobile-shared/src/* (Metro não lê tsconfig paths).
config.resolver = config.resolver ?? {}
config.resolver.extraNodeModules = {
    ...(config.resolver.extraNodeModules ?? {}),
    "@shared": path.resolve(sharedRoot, "src"),
}
// 3) Deixa o Metro subir para o node_modules do app ao resolver deps do shared.
config.resolver.nodeModulesPaths = [
    ...(config.resolver.nodeModulesPaths ?? []),
    path.resolve(projectRoot, "node_modules"),
]

module.exports = config
