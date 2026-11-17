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
    path?: string
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

/**
 * Reta usada quando o backend não mandou o traçado pelas ruas (Routes API sem
 * chave ou fora do ar). Tracejada e cinza de propósito: é estimativa de direção,
 * não caminho — sólida e laranja ela se passava por rota e cortava quarteirões.
 */
export const TRACADO_ESTIMADO = "#9CA3AF"
export const TRACEJADO = [12, 10]

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
        path: extra.reverb?.path ?? "",
    },
}

/** Paleta Supergasbras (alinhada ao design system do ERP-NOVO). */
export const COLORS = {
    primary: "#FF6200",
    /**
     * Laranja de AÇÃO e de texto (PIE P2-C-17). O #FF6200 da marca dá 3,00:1
     * com branco e 2,75:1 sobre o fundo #F5F5F5 — abaixo do 4,5:1 do WCAG AA,
     * justamente nos botões que o entregador lê sob sol. #C2410C dá 5,18:1 com
     * branco e 4,75:1 sobre o fundo. A marca segue no cabeçalho e no ícone.
     */
    primaryStrong: "#C2410C",
    accent: "#DBFB3B",
    graphite: "#2B2B2B",
    bg: "#F5F5F5",
    card: "#FFFFFF",
    border: "#E5E5E5",
    text: "#1A1A1A",
    // #6B7280 dava 4,43:1 sobre o fundo #F5F5F5 (P2-C-17); #5B6270 dá 5,62:1.
    muted: "#5B6270",
    danger: "#DC2626",
    success: "#16A34A",
    white: "#FFFFFF",
}
