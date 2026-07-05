import { APP } from "@/constants/app"
import useAppStore from "@/store/appStore"
import { createHttp, type HttpError, type HttpVerbs } from "@shared/http"

/**
 * Camada HTTP do app do entregador — ADAPTADOR fino sobre o núcleo COMPARTILHADO
 * (@shared/http), idêntico em filosofia ao do app do consumidor. Antes duplicava
 * ~150 linhas; agora só liga a fábrica store-agnóstica ao store deste app (M-2).
 * Expõe PrepareForm (upload de foto/assinatura da comprovação P7).
 */

export type { HttpError, HttpVerbs }

const { PrepareRequest, PrepareForm, client } = createHttp({
    baseURL: APP.api_url,
    getToken: () => useAppStore.getState().apiToken,
    onUnauthorized: () => {
        const state = useAppStore.getState()
        if (state.apiToken) state.logout()
    },
})

const Http = { PrepareRequest, PrepareForm, client }

export default Http
