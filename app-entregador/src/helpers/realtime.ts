import { APP } from "@/constants/app"
import useAppStore from "@/store/appStore"
import Echo from "laravel-echo"
import Pusher from "pusher-js/react-native"

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
 */
let echo: Echo<any> | null = null

export const realtimeDisponivel = (): boolean => Boolean(APP.reverb.host && APP.reverb.key)

export const connectRealtime = (): Echo<any> | null => {
    if (!realtimeDisponivel()) return null
    if (echo) return echo

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
