import { CardBrands } from "@/types/types"
import Constants from "expo-constants"

export const PER_WIDTH = 0.5

export const INTERNAL_BUILD_NUMBER = 4

export const PaymentTypes = { Online: 6 }

export enum OnlinePaymentTypes {
    Credit = "credito",
    Debit = "debito",
}

interface Brands {
    id: number
    type: CardBrands
    desc: string
    types: OnlinePaymentTypes[]
    cardMask: string
    cvvMask: string
    placeholder: string
    cvvPlaceholder: string
}

export const SupportedBrands: Brands[] = [
    {
        id: 0,
        type: "default",
        desc: "Bandeira",
        types: [OnlinePaymentTypes.Credit, OnlinePaymentTypes.Debit],
        cardMask: "[0000000000000000]",
        cvvMask: "[000]",
        placeholder: "0000000000000000",
        cvvPlaceholder: "999",
    },
    {
        id: 1,
        type: "mastercard",
        desc: "MasterCard",
        types: [OnlinePaymentTypes.Credit, OnlinePaymentTypes.Debit],
        cardMask: "[0000] [0000] [0000] [0000]",
        cvvMask: "[000]",
        placeholder: "0000 0000 0000 0000",
        cvvPlaceholder: "999",
    },
    {
        id: 2,
        type: "visa",
        desc: "Visa",
        types: [OnlinePaymentTypes.Credit, OnlinePaymentTypes.Debit],
        cardMask: "[0000] [0000] [0000] [0000]",
        cvvMask: "[000]",
        placeholder: "0000 0000 0000 0000",
        cvvPlaceholder: "999",
    },
    {
        id: 3,
        type: "hipercard",
        desc: "HiperCard",
        types: [OnlinePaymentTypes.Credit],
        cardMask: "[0000] [0000] [0000] [0000]",
        cvvMask: "[000]",
        placeholder: "0000 0000 0000 0000",
        cvvPlaceholder: "999",
    },
    {
        id: 4,
        type: "elo",
        desc: "ELO",
        types: [OnlinePaymentTypes.Credit],
        cardMask: "[0000] [0000] [0000] [0000]",
        cvvMask: "[000]",
        placeholder: "0000 0000 0000 0000",
        cvvPlaceholder: "999",
    },
    {
        id: 5,
        type: "amex",
        desc: "AMEX",
        types: [OnlinePaymentTypes.Credit],
        cardMask: "[0000] [000000] [00000]",
        cvvMask: "[0000]",
        placeholder: "0000 000000 00000",
        cvvPlaceholder: "9999",
    },
]

// (F7) NÃO existe mais "cidade default" em código: a descoberta de revendas usa o
// GPS ou a CIDADE ESCOLHIDA pelo usuário (catálogo marketplace/cidades), e o mapa
// de endereço ancora na REVENDA do token — a plataforma escala para qualquer praça.

/**
 * Configuração de runtime (F0 — segurança).
 *
 * Os valores vêm de `expo-constants` (bloco `extra` de app.config.ts, alimentado
 * por variáveis de ambiente). Nenhum segredo ou URL fica mais hardcoded no código.
 * `api_url` agora aponta para a API do ERP-NOVO (ver .env / app.config.ts).
 *
 * Em dev, se a env não estiver configurada, falhamos cedo e de forma clara em vez
 * de silenciosamente cair em uma URL errada.
 */
type ReverbExtra = {
    key?: string
    host?: string
    port?: number
    scheme?: string
}

type AppExtra = {
    appEnv?: string
    apiUrl?: string
    googleMapsApiKey?: string
    debug?: boolean
    empresaId?: number | null
    reverb?: ReverbExtra
}

const extra = (Constants.expoConfig?.extra ?? {}) as AppExtra

if (!extra.apiUrl) {
    // eslint-disable-next-line no-console
    console.warn(
        "[config] API_URL não configurada. Defina-a no .env (veja .env.example) — as chamadas à API vão falhar.",
    )
}

export const APP = {
    env: extra.appEnv ?? "prod",
    debug: extra.debug ?? false,
    /** Base da API do ERP-NOVO (com barra final). Ex.: https://app.erp.com.br/api/ */
    api_url: extra.apiUrl ?? "",
    /** Chave do Google Maps/Geocode. */
    gap_key: extra.googleMapsApiKey ?? "",
    /** Empresa (tenant) atendida por este build — enviada no login do cliente (F1). */
    empresa_id: extra.empresaId ?? null,
    /** P8: tempo real (Reverb/Pusher). Vazio = sem WebSocket (cai p/ polling). */
    reverb: {
        key: extra.reverb?.key ?? "",
        host: extra.reverb?.host ?? "",
        port: extra.reverb?.port ?? 443,
        scheme: extra.reverb?.scheme ?? "https",
    },
}
