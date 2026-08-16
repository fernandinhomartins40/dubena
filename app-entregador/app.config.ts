import { ConfigContext, ExpoConfig } from "expo/config"

/**
 * Configuração dinâmica do Expo — App do ENTREGADOR (P7).
 *
 * Mesma filosofia do app do cliente: segredos e URLs saem do código e vêm de
 * variáveis de ambiente (`.env` em dev, EAS secrets em build). O `app.json`
 * guarda só o estático/não-sensível; aqui injetamos:
 *  - a Google Maps API key (iOS/Android) a partir de GOOGLE_MAPS_API_KEY;
 *  - o bloco `extra` consumido em runtime via expo-constants (src/constants/app.ts).
 *
 * Variáveis esperadas (ver .env.example):
 *  - APP_ENV               local | homolog | prod   (default: prod)
 *  - API_URL               base da API do ERP-NOVO (ex.: https://erp.exemplo.com/api/)
 *  - GOOGLE_MAPS_API_KEY   chave do Google Maps
 *  - REVERB_HOST/PORT/...  conexão WebSocket (tempo real — P6/P8)
 *  - APP_DEBUG             "true" para habilitar atalhos de debug (default: false)
 *  - LOGIN_TESTE_EMAIL     credencial do atalho "Modo teste" (opcional, só dev)
 *  - LOGIN_TESTE_SENHA     idem — sem ambas, o atalho não é renderizado
 *
 * O entregador NÃO usa login por telefone (Firebase phone-auth) — ele entra com
 * e-mail/senha do colaborador (POST app/v1/login). Por isso não há EMPRESA_ID:
 * o tenant é derivado do token Sanctum no servidor.
 */
export default ({ config }: ConfigContext): ExpoConfig => {
    const env = process.env.APP_ENV ?? "prod"
    const apiUrl = process.env.API_URL ?? ""
    const googleMapsApiKey = process.env.GOOGLE_MAPS_API_KEY ?? ""
    const debug = process.env.APP_DEBUG === "true"

    // Credencial do atalho "Modo teste" da tela de login. Vem do ambiente e
    // NUNCA do código-fonte: literal aqui vaza uma conta válida no bundle.
    // Ausente = o atalho não aparece, mesmo com APP_DEBUG=true.
    const loginTeste = {
        email: process.env.LOGIN_TESTE_EMAIL ?? "",
        senha: process.env.LOGIN_TESTE_SENHA ?? "",
    }

    // Tempo real (Laravel Reverb / Pusher protocol). Opcional: sem isso, o app cai
    // para polling. Ver src/helpers/realtime.ts.
    const reverb = {
        key: process.env.REVERB_APP_KEY ?? "",
        host: process.env.REVERB_HOST ?? "",
        port: process.env.REVERB_PORT ? Number(process.env.REVERB_PORT) : 443,
        scheme: process.env.REVERB_SCHEME ?? "https",
    }

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
            loginTeste,
            reverb,
        },
    }
}
