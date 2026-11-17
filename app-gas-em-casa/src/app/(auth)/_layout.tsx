import Loader from "@/components/templates/loader"
import useForegroundNotifications from "@/hooks/useForegroundNotifications"
import OrderService from "@/services/order.service"
import StoreService from "@/services/store.service"
import UserService from "@/services/user.service"
import useFlashStore from "@/store/flashStore"
import { destinoDaNotificacao } from "@/helpers/notificacao"
import { useQuery } from "@tanstack/react-query"
import { Href, useRouter } from "expo-router"
import { Stack } from "expo-router/stack"
import { useEffect, useRef } from "react"
import { Platform } from "react-native"
import { HistoryItem } from "@/types/types"
import { getApp } from "@react-native-firebase/app"
import {
    getInitialNotification,
    getMessaging,
    getToken,
    onNotificationOpenedApp,
    onTokenRefresh,
} from "@react-native-firebase/messaging"

const plataforma = Platform.OS === "ios" ? "ios" : "android"

/**
 * Layout autenticado (F3). Carrega a config do app e o histórico; se houver um pedido
 * em andamento (efeito PENDENTE), retoma o acompanhamento. Sem o conceito de "store"
 * do legado — a empresa vem do token.
 */
export default function Layout() {
    const router = useRouter()
    const { setPendingOrder, setAppConfig, setPendingNavigation, pendingNavigation, clearPendingNavigation } =
        useFlashStore()

    useForegroundNotifications()

    // P2-B-08: registra o device ao abrir e quando o FCM rotaciona o token.
    // Antes só o login mandava o token: quem negou a permissão no login, ou
    // teve o token trocado pelo FCM, ficava sem push até entrar de novo.
    // Best-effort: falha de push não pode derrubar a tela de compra.
    useEffect(() => {
        let cancelado = false
        const messaging = getMessaging(getApp())
        const registrar = (token: string | null | undefined) => {
            if (!token || cancelado) return
            UserService.StoreFcmToken({ token, plataforma }).catch(() => undefined)
        }
        getToken(messaging).then(registrar).catch(() => undefined)
        const pararRefresh = onTokenRefresh(messaging, registrar)

        // Toque em notificação com o app em segundo plano ou fechado: esse
        // caminho é do FCM, não do expo-notifications (que só vê as que o
        // próprio app agendou em primeiro plano).
        const aplicar = (data: Record<string, unknown> | undefined) => {
            const destino = destinoDaNotificacao(data)
            if (destino?.tipo === "pedido") {
                setPendingNavigation({ pedidoId: destino.pedidoId, aberto: destino.aberto })
            }
        }
        const pararAberta = onNotificationOpenedApp(messaging, (msg) => aplicar(msg?.data as any))
        getInitialNotification(messaging)
            .then((msg) => aplicar(msg?.data as any))
            .catch(() => undefined)

        return () => {
            cancelado = true
            pararRefresh()
            pararAberta()
        }
    }, [])

    const { data: appConfig } = useQuery({
        queryKey: ["app-config"],
        queryFn: () => StoreService.GetConfig(),
        retry: 1,
    })

    const { data: history, isLoading } = useQuery<HistoryItem[]>({
        queryKey: ["order-history"],
        queryFn: () => OrderService.GetHistory(),
        retry: 1,
    })

    useEffect(() => {
        if (appConfig) setAppConfig(appConfig)
    }, [appConfig])

    // O retorno ao pedido pendente roda uma vez por carga do histórico — não a
    // cada notificação consumida, senão o toque que leva ao histórico seria
    // desfeito logo em seguida.
    const historicoTratado = useRef<HistoryItem[] | null>(null)

    useEffect(() => {
        if (!history) return

        // P2-B-08: notificação tocada manda primeiro — ela diz QUAL pedido.
        if (pendingNavigation && "pedidoId" in pendingNavigation) {
            const { pedidoId, aberto } = pendingNavigation
            clearPendingNavigation()
            if (aberto) {
                setPendingOrder({ id: pedidoId } as any)
                router.push("/(auth)/track")
            } else {
                router.push("/(auth)/(tabs)/pedidos" as Href)
            }
            return
        }

        if (historicoTratado.current === history) return
        historicoTratado.current = history

        // Pedido em andamento → retoma o acompanhamento (fluxo legítimo).
        const pendente = history.find((o) => o.efeito === "PENDENTE")
        if (pendente) {
            setPendingOrder({ id: pendente.id } as any)
            router.replace("/(auth)/track")
            return
        }

        // IMPORTANTE (UX): NÃO forçar avaliação no boot. Antes, abrir o app com um
        // pedido concluído não avaliado redirecionava para Pedidos e abria um modal
        // que EXIGIA nota — usuários leigos travavam aí e não voltavam a comprar.
        // A avaliação agora é OPCIONAL e oferecida de forma discreta na aba Pedidos
        // (card "Avalie seu último pedido" com "Agora não"). O app abre na Home.
    }, [history, pendingNavigation])

    if (isLoading) return <Loader />

    return (
        <Stack>
            <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
            <Stack.Screen name="carrinho" options={{ headerShown: false }} />
            <Stack.Screen name="perfil-dados" options={{ headerShown: false }} />
            <Stack.Screen name="address" options={{ headerShown: false }} />
            <Stack.Screen name="track" options={{ headerShown: false }} />
            <Stack.Screen name="pix" options={{ headerShown: false }} />
        </Stack>
    )
}
