import { ConfigContext, ExpoConfig } from "expo/config"

/**
 * Configuração dinâmica do Expo (F0 — segurança).
 *
 * Segredos e URLs saem do código-fonte e passam a vir de variáveis de ambiente
 * (lidas de `.env` em dev e dos EAS secrets em build). O `app.json` mantém só a
 * configuração estática e não-sensível; aqui injetamos:
 *  - a Google Maps API key (iOS/Android) a partir de GOOGLE_MAPS_API_KEY;
 *  - o bloco `extra` consumido em runtime via expo-constants (ver src/constants/app.ts).
 *
 * Variáveis esperadas (ver .env.example):
 *  - APP_ENV               local | homolog | prod   (default: prod)
 *  - API_URL               base da API do ERP-NOVO (ex.: https://erp.exemplo.com/api/)
 *  - GOOGLE_MAPS_API_KEY   chave do Google Maps/Geocode
 *  - APP_DEBUG             "true" para habilitar atalhos de debug (default: false)
 */
export default ({ config }: ConfigContext): ExpoConfig => {
    const env = process.env.APP_ENV ?? "prod"
    const apiUrl = process.env.API_URL ?? ""
    const googleMapsApiKey = process.env.GOOGLE_MAPS_API_KEY ?? ""
    const debug = process.env.APP_DEBUG === "true"
    // F1: empresa (tenant) que este build do app atende. O login do cliente envia
    // este empresa_id ao ERP-NOVO para resolver o cliente pelo telefone verificado.
    const empresaId = process.env.EMPRESA_ID ? Number(process.env.EMPRESA_ID) : null

    return {
        ...(config as ExpoConfig),
        ios: {
            ...config.ios,
            config: {
                ...config.ios?.config,
                googleMapsApiKey,
            },
        },
        android: {
            ...config.android,
            config: {
                ...config.android?.config,
                googleMaps: { apiKey: googleMapsApiKey },
            },
        },
        extra: {
            ...config.extra,
            appEnv: env,
            apiUrl,
            googleMapsApiKey,
            debug,
            empresaId,
        },
    }
}
