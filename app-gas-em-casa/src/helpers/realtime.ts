import { APP } from "@/constants/app"
import useAppStore from "@/store/appStore"
import Echo from "laravel-echo"
import Pusher from "pusher-js/react-native"

/**
 * Tempo real (P8) — Laravel Echo sobre o Reverb (protocolo Pusher).
 *
 * O ERP-NOVO transmite eventos em canais PRIVADOS por tenant/pedido (P5/P6):
 *  - pedido.{id}            → `pedido.status`   (PedidoStatusAtualizado)
 *  - pedido.{id}.entregador → `entregador.posicao` (EntregadorPosicaoAtualizada)
 *
 * A autorização do canal é feita pelo endpoint `broadcasting/auth` do Laravel,
 * que aceita o Bearer do Sanctum (bootstrap com guard auth:sanctum). Assim o
 * sigilo multi-tenant também vale no tempo real: o cliente só assina o canal do
 * pedido que é dele (channels.php valida posse + tenant).
 *
 * Se o Reverb não estiver configurado (`.env` sem REVERB_*), `connect()` devolve
 * null e a tela cai para polling — sem quebrar nada.
 */
let echo: Echo<any> | null = null

export const realtimeDisponivel = (): boolean => Boolean(APP.reverb.host && APP.reverb.key)

export const connectRealtime = (): Echo<any> | null => {
    if (!realtimeDisponivel()) return null
    if (echo) return echo

    const token = useAppStore.getState().apiToken
    ;(global as any).Pusher = Pusher

    echo = new Echo({
        broadcaster: "reverb",
        key: APP.reverb.key,
        wsHost: APP.reverb.host,
        wsPort: APP.reverb.port,
        wssPort: APP.reverb.port,
        wsPath: APP.reverb.path || undefined,
        forceTLS: APP.reverb.scheme === "https",
        enabledTransports: ["ws", "wss"],
        // A rota de autorização dos canais é registrada pelo withBroadcasting
        // FORA do prefixo /api (fica em <APP_URL>/broadcasting/auth). Montá-la
        // a partir do api_url dava 404 e nenhum canal privado era assinado.
        authEndpoint: `${APP.api_url.replace(/api\/?$/, "")}broadcasting/auth`,
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
