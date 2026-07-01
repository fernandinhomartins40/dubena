module.exports = function (api) {
    api.cache(true)
    return {
        presets: ["babel-preset-expo"],
        // Plugin do reanimated (animações de entrada do onboarding). Deve ser o ÚLTIMO.
        plugins: ["react-native-reanimated/plugin"],
    }
}
