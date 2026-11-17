import { APP } from "@/constants/app"
import useAppStore from "@/store/appStore"
import { createHttp, type HttpError, type HttpVerbs } from "@shared/http"

/**
 * Camada HTTP do app do entregador — ADAPTADOR fino sobre o núcleo COMPARTILHADO
 * (@shared/http), idêntico em filosofia ao do app do consumidor. Antes duplicava
 * ~150 linhas; agora só liga a fábrica store-agnóstica ao store deste app (M-2).
 * Expõe PrepareForm (upload de foto/assinatura da comprovação P7).
 *
 * PIE RC7/P2-C-09: a renovação do token fica ligada. Sem ela, 24 h depois do
 * login o entregador caía para a tela de login no meio da rota e o GPS parava
 * com a jornada aberta no servidor. A fila offline usa o mesmo `client`, então
 * o reenvio também renova em vez de esbarrar no 401.
 */

export type { HttpError, HttpVerbs }

const { PrepareRequest, PrepareForm, client, renovarSessao, marcarTokenNovo } = createHttp({
    baseURL: APP.api_url,
    getToken: () => useAppStore.getState().apiToken,
    onUnauthorized: () => {
        const state = useAppStore.getState()
        if (state.apiToken) state.logout()
    },
    renovacao: {
        endpoint: "app/v1/token/refresh",
        onToken: (token) => useAppStore.getState().setToken(token),
    },
})

const Http = { PrepareRequest, PrepareForm, client, renovarSessao, marcarTokenNovo }

export default Http
