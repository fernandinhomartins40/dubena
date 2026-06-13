import Loader from "@/components/templates/Loader"
import useForegroundNotifications from "@/hooks/useForegroundNotifications"
import OrderService from "@/services/order.service"
import StoreService from "@/services/store.service"
import useAppStore from "@/store/appStore"
import useFlashStore from "@/store/flashStore"
import { useQuery } from "@tanstack/react-query"
import { Href, usePathname, useRouter } from "expo-router"
import { Stack } from "expo-router/stack"
import { useEffect } from "react"

// TODO Refazer o component OrderItems para não receber o valor total
export default function Layout() {
    const { user } = useAppStore()
    const { store, setStore, setPendingOrder, setEvaluateOrderId, setPixOrder } = useFlashStore()
    const router = useRouter()
    const name = usePathname()
    const {
        data: stores,
        isLoading,
        isRefetching,
        error,
    } = useQuery({
        queryKey: ["store"],
        queryFn: () => StoreService.GetOpenStore(user?.enderecopadrao_id),
        enabled: store === null && !!user?.enderecopadrao_id,
        retry: 1,
    })
    const { data: order, isLoading: isLoadingOrder } = useQuery({
        queryKey: ["latest-order"],
        queryFn: () => OrderService.GetLatestOrder(user?.id),
        enabled: !!user,
        retry: 1,
    })

    useForegroundNotifications()

    useEffect(() => {
        if (!user?.enderecopadrao_id && name != "/address") {
            router.replace("/(auth)/address")
        }
    }, [])

    useEffect(() => {
        if (typeof stores === "object" && "msg" in stores && user?.enderecopadrao_id) {
            router.push(
                "/(auth)/error?error=Erro desconhecido, por favor contate a revenda" as Href,
            )

            return
        }

        if (stores && stores.length > 0) {
            setStore(stores[0])
        }
    }, [stores])

    useEffect(() => {
        if (!error) return

        if ("msg" in error && String(error.msg).includes("Endereço não foi encontrado")) {
            router.replace("/(auth)/address")
            return
        }

        router.push(`/(auth)/error?error=${error.message}` as Href)
    }, [error])

    useEffect(() => {
        if (!order) return

        if (order.cancelado) return

        let isPending = order.pendente || order.ementrega

        if (isPending && !("pix" in order)) {
            setPendingOrder(order)

            router.replace("/(auth)/track")

            return
        }

        if (isPending && "pix" in order) {
            setPixOrder(order as any)

            router.replace("/(auth)/pix")

            return
        }

        if (!order.avaliado && !order.ignorado) {
            setEvaluateOrderId(order.id)

            router.replace("/(auth)/(tabs)/pedidos")
        }
    }, [order])

    if (isLoading || isLoadingOrder || isRefetching) return <Loader />

    if (error) {
        return (
            <Stack initialRouteName="error">
                <Stack.Screen name="error" options={{ headerShown: false }} />

                <Stack.Screen name="address" options={{ headerShown: false }} />
            </Stack>
        )
    }

    return (
        <Stack>
            <Stack.Screen name="(tabs)" options={{ headerShown: false }} />

            <Stack.Screen name="address" options={{ headerShown: false }} />

            <Stack.Screen name="track" options={{ headerShown: false }} />

            <Stack.Screen name="pix" options={{ headerShown: false }} />
        </Stack>
    )
}
