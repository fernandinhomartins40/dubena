import { useEffect } from "react"
import * as Notifications from "expo-notifications"
import useFlashStore from "@/store/flashStore"
import useAppStore from "@/store/appStore"
import { destinoDaNotificacao } from "@/helpers/notificacao"

/**
 * Toque numa notificação (P2-B-08). Só REGISTRA o destino no flashStore: este
 * hook roda no layout raiz, antes de a navegação e a sessão existirem num
 * início a frio. Quem navega é o layout autenticado (`(auth)/_layout.tsx`),
 * que consome `pendingNavigation` quando já tem usuário e histórico.
 */
export default function useNotificationClick() {
    const { setPendingNavigation } = useFlashStore()
    const { hasNotificationId, addNotificationId } = useAppStore()

    const handleResponse = (response: Notifications.NotificationResponse) => {
        const id = response.notification.request.identifier

        if (hasNotificationId(id)) return

        addNotificationId(id)

        const trigger = response.notification.request?.trigger as any
        const data = {
            ...((trigger && "payload" in trigger ? trigger.payload : null) ?? {}),
            ...(response.notification.request.content.data ?? {}),
        } as Record<string, unknown>

        const destino = destinoDaNotificacao(data)
        if (!destino) return

        if (destino.tipo === "imagem") {
            setPendingNavigation({ imageUrl: destino.imageUrl })
        } else {
            setPendingNavigation({ pedidoId: destino.pedidoId, aberto: destino.aberto })
        }
    }

    useEffect(() => {
        const sub = Notifications.addNotificationResponseReceivedListener((res) => {
            return handleResponse(res)
        })

        const checkLastNoti = async () => {
            const lastResponse = await Notifications.getLastNotificationResponseAsync()

            if (lastResponse) {
                handleResponse(lastResponse)
            }
        }

        checkLastNoti()

        return () => sub.remove()
    }, [])
}
