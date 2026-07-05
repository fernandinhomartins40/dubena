// Metro config — inclui o pacote COMPARTILHADO (../mobile-shared) fora da raiz do
// app (M-2). Espelha o do app do consumidor.
const { getDefaultConfig } = require("expo/metro-config")
const path = require("path")

const projectRoot = __dirname
const sharedRoot = path.resolve(projectRoot, "..", "mobile-shared")

const config = getDefaultConfig(projectRoot)

config.watchFolders = [...(config.watchFolders ?? []), sharedRoot]

config.resolver = config.resolver ?? {}
config.resolver.extraNodeModules = {
    ...(config.resolver.extraNodeModules ?? {}),
    "@shared": path.resolve(sharedRoot, "src"),
}
config.resolver.nodeModulesPaths = [
    ...(config.resolver.nodeModulesPaths ?? []),
    path.resolve(projectRoot, "node_modules"),
]

module.exports = config
