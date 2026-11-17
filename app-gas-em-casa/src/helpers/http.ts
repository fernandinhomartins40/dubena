import { APP } from "@/constants/app"
import useAppStore from "@/store/appStore"
import { createHttp, type HttpError, type HttpVerbs } from "@shared/http"

/**
 * Camada HTTP do app do consumidor — hoje um ADAPTADOR fino sobre o núcleo
 * COMPARTILHADO (@shared/http). Antes este arquivo duplicava ~130 linhas idênticas
 * às do app do entregador; agora só liga a fábrica store-agnóstica ao store deste
 * app (token + logout). Um bug corrigido no núcleo vale para os dois apps (M-2).
 *
 * PIE RC7/P2-B-03: renovação do token ligada (núcleo do Agente D). Quem compra
 * gás a cada 30–60 dias encontrava o token de 24 h vencido em quase todo pedido,
 * caía numa tela de erro sem saída e pagava outro SMS para entrar. Agora o token
 * é trocado antes de vencer e o 401 tenta UMA renovação antes de desistir.
 * O `refresh` responde `{token, user}` sem `data` — o núcleo lê os dois formatos.
 *
 * 401 que sobra (token revogado, conta desativada — o `TokenDeContaAtiva` do
 * Agente C devolve 401 para conta inativa) é sessão encerrada: `logout()`, e o
 * `vigiarFimDeSessao` (helpers/sessao.ts) limpa cache, WebSocket e carrinho.
 */

export type { HttpError, HttpVerbs }

const { PrepareRequest, SendRequest, client, renovarSessao, marcarTokenNovo } = createHttp({
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

const Http = { PrepareRequest, SendRequest, client, renovarSessao, marcarTokenNovo }

export default Http
