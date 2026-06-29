import { APP } from "@/constants/app"
import useAppStore from "@/store/appStore"

/**
 * Tempo real (P6/P8) — Laravel Echo sobre o Reverb (protocolo Pusher).
 *
 * O ERP-NOVO transmite eventos em canais PRIVADOS por tenant/pedido (P5):
 *  - pedido.{id}            → PedidoStatusAtualizado / PixConfirmado
 *  - pedido.{id}.entregador → EntregadorPosicaoAtualizada (P6)
 *
 * A autorização do canal é feita pelo endpoint `broadcasting/auth` do Laravel,
 * que aceita o Bearer do Sanctum (configurado no bootstrap com guard auth:sanctum).
 *
 * Se o Reverb não estiver configurado (`.env` sem REVERB_*), `connect()` devolve
 * null e a tela cai para polling — o app funciona offline-first do mesmo jeito.
 *
 * IMPORTANTE: `laravel-echo` e `pusher-js` são carregados de forma PREGUIÇOSA
 * (require dentro de connectRealtime), só quando o tempo real está configurado.
 * Assim o boot do app não depende desses módulos (evita crash no startup quando
 * o Reverb está desligado).
 */
let echo: any = null

export const realtimeDisponivel = (): boolean => Boolean(APP.reverb.host && APP.reverb.key)

export const connectRealtime = (): any => {
    if (!realtimeDisponivel()) return null
    if (echo) return echo

    // Carregamento preguiçoso: só aqui, com o Reverb realmente configurado.
    const Echo = require("laravel-echo").default
    const Pusher = require("pusher-js/react-native").default

    const token = useAppStore.getState().apiToken
    // pusher-js precisa estar acessível ao Echo no escopo global em RN.
    ;(global as any).Pusher = Pusher

    echo = new Echo({
        broadcaster: "reverb",
        key: APP.reverb.key,
        wsHost: APP.reverb.host,
        wsPort: APP.reverb.port,
        wssPort: APP.reverb.port,
        forceTLS: APP.reverb.scheme === "https",
        enabledTransports: ["ws", "wss"],
        // baseURL da API termina em /api/ — o endpoint de auth fica nesse mesmo host.
        authEndpoint: `${APP.api_url}broadcasting/auth`,
        auth: { headers: { Authorization: `Bearer ${token}`, Accept: "application/json" } },
    } as any)

    return echo
}

export const disconnectRealtime = (): void => {
    if (echo) {
        echo.disconnect()
        echo = null
    }
}
