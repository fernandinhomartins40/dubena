import { APP } from "@/constants/app"
import useAppStore from "@/store/appStore"
import { createHttp, type HttpError, type HttpVerbs } from "@shared/http"

/**
 * Camada HTTP do app do consumidor — hoje um ADAPTADOR fino sobre o núcleo
 * COMPARTILHADO (@shared/http). Antes este arquivo duplicava ~130 linhas idênticas
 * às do app do entregador; agora só liga a fábrica store-agnóstica ao store deste
 * app (token + logout). Um bug corrigido no núcleo vale para os dois apps (M-2).
 */

export type { HttpError, HttpVerbs }

const { PrepareRequest, SendRequest, client } = createHttp({
    baseURL: APP.api_url,
    getToken: () => useAppStore.getState().apiToken,
    onUnauthorized: () => {
        const state = useAppStore.getState()
        if (state.apiToken) state.logout()
    },
})

const Http = { PrepareRequest, SendRequest, client }

export default Http
