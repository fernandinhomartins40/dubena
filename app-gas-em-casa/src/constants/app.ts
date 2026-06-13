import { CardBrands } from "@/types/types"

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

export const DEFAULT_LOCATION = { latitude: -25.3862077, longitude: -51.4867962 }

// ? Local env
// debug: true,
// api_url: "http://192.168.0.108/api-integration/public/api/",
// app_key: "40c20d46182c497aa5147242b91c6923d6a6258e",
// gap_key: "AIzaSyDygo66KV3BCnznA_vVG4s63JXpk8Qd0d8",
// ? Homolog env
// debug: true,
// api_url: "http://qtidevel.ddns.net:8181/api-integration/public/api/",
// app_key: "40c20d46182c497aa5147242b91c6923d6a6258e",
// gap_key: "AIzaSyDygo66KV3BCnznA_vVG4s63JXpk8Qd0d8",
// ? Prod env
// debug: false,
// api_url: "https://gasemcasa.com.br/api-app/public/api/",
// app_key: "40c20d46182c497aa5147242b91c6923d6a6258e",
// gap_key: "AIzaSyDygo66KV3BCnznA_vVG4s63JXpk8Qd0d8",
export const APP = {
    debug: false,
    api_url: "https://gasemcasa.com.br/api-app/public/api/",
    app_key: "40c20d46182c497aa5147242b91c6923d6a6258e",
    gap_key: "AIzaSyDygo66KV3BCnznA_vVG4s63JXpk8Qd0d8",
}
