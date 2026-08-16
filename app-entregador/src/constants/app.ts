import Constants from "expo-constants"

/**
 * Configuração de runtime do App do Entregador (P7).
 *
 * Os valores vêm de `expo-constants` (bloco `extra` de app.config.ts, alimentado
 * por variáveis de ambiente). Nenhum segredo ou URL fica hardcoded. `api_url`
 * aponta para a API do ERP-NOVO (mesma do app do cliente — backend único).
 */
type ReverbExtra = {
    key?: string
    host?: string
    port?: number
    scheme?: string
}

type LoginTesteExtra = {
    email?: string
    senha?: string
}

type AppExtra = {
    appEnv?: string
    apiUrl?: string
    googleMapsApiKey?: string
    debug?: boolean
    loginTeste?: LoginTesteExtra
    reverb?: ReverbExtra
}

const extra = (Constants.expoConfig?.extra ?? {}) as AppExtra

if (!extra.apiUrl) {
    // eslint-disable-next-line no-console
    console.warn(
        "[config] API_URL não configurada. Defina-a no .env (veja .env.example) — as chamadas à API vão falhar.",
    )
}

/**
 * Viewport NEUTRO (Brasil inteiro, bem afastado) para mapas que ainda não têm
 * coordenada real (GPS/parada/pedido). É só enquadramento — nunca uma cidade
 * fixa em código: a plataforma atende qualquer praça do país.
 */
export const BRASIL_VIEW = {
    latitude: -14.235,
    longitude: -51.9253,
    latitudeDelta: 40,
    longitudeDelta: 40,
}

/** Intervalo (ms) entre pings de posição enviados ao ERP-NOVO (P6). */
export const POSICAO_INTERVALO_MS = 15000

/** Polling de pedidos (fallback quando o tempo real não está disponível). */
export const PEDIDOS_POLL_MS = 30000

export const APP = {
    env: extra.appEnv ?? "prod",
    debug: extra.debug ?? false,
    /** Base da API do ERP-NOVO (com barra final). Ex.: https://app.erp.com.br/api/ */
    api_url: extra.apiUrl ?? "",
    /** Chave do Google Maps. */
    gap_key: extra.googleMapsApiKey ?? "",
    /**
     * Credencial do atalho "Modo teste" (só dev). Vem do ambiente; vazia por
     * default — a tela de login só mostra o atalho quando ambas estão presentes.
     */
    login_teste: {
        email: extra.loginTeste?.email ?? "",
        senha: extra.loginTeste?.senha ?? "",
    },
    /** Conexão de tempo real (Reverb/Pusher). Vazio = sem WebSocket (cai p/ polling). */
    reverb: {
        key: extra.reverb?.key ?? "",
        host: extra.reverb?.host ?? "",
        port: extra.reverb?.port ?? 443,
        scheme: extra.reverb?.scheme ?? "https",
    },
}

/** Paleta Supergasbras (alinhada ao design system do ERP-NOVO). */
export const COLORS = {
    primary: "#FF6200",
    accent: "#DBFB3B",
    graphite: "#2B2B2B",
    bg: "#F5F5F5",
    card: "#FFFFFF",
    border: "#E5E5E5",
    text: "#1A1A1A",
    muted: "#6B7280",
    danger: "#DC2626",
    success: "#16A34A",
    white: "#FFFFFF",
}
