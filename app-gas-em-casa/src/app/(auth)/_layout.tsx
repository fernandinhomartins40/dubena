import Loader from "@/components/templates/loader"
import useForegroundNotifications from "@/hooks/useForegroundNotifications"
import OrderService from "@/services/order.service"
import StoreService from "@/services/store.service"
import useFlashStore from "@/store/flashStore"
import { useQuery } from "@tanstack/react-query"
import { useRouter } from "expo-router"
import { Stack } from "expo-router/stack"
import { useEffect } from "react"
import { HistoryItem } from "@/types/types"

/**
 * Layout autenticado (F3). Carrega a config do app e o histórico; se houver um pedido
 * em andamento (efeito PENDENTE), retoma o acompanhamento. Sem o conceito de "store"
 * do legado — a empresa vem do token.
 */
export default function Layout() {
    const router = useRouter()
    const { setPendingOrder, setEvaluateOrderId, setAppConfig } = useFlashStore()

    useForegroundNotifications()

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

    useEffect(() => {
        if (!history) return

        // Pedido em andamento → retoma o acompanhamento.
        const pendente = history.find((o) => o.efeito === "PENDENTE")
        if (pendente) {
            setPendingOrder({ id: pendente.id } as any)
            router.replace("/(auth)/track")
            return
        }

        // Último pedido concluído ainda não avaliado → abre avaliação.
        const aAvaliar = history.find((o) => o.efeito === "CONCLUIDO")
        if (aAvaliar) {
            setEvaluateOrderId(aAvaliar.id)
            router.replace("/(auth)/(tabs)/pedidos")
        }
    }, [history])

    if (isLoading) return <Loader />

    return (
        <Stack>
            <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
            <Stack.Screen name="address" options={{ headerShown: false }} />
            <Stack.Screen name="track" options={{ headerShown: false }} />
            <Stack.Screen name="pix" options={{ headerShown: false }} />
        </Stack>
    )
}
