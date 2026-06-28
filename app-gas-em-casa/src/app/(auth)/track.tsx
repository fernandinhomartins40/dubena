import { useEffect, useMemo } from "react"
import { colors, defaultStyles, fontSize, fontStyle, screenPadding } from "@/styles/theme"
import { Platform, ScrollView, StyleSheet, Text, View } from "react-native"
import Timeline from "@/components/molecules/Timeline"
import useFlashStore from "@/store/flashStore"
import { useQuery, useQueryClient } from "@tanstack/react-query"
import OrderService from "@/services/order.service"
import { TimelineStep } from "@/types/types"
import { useRouter } from "expo-router"
import OrderItems from "@/components/molecules/OrderItems"
import { StatusBar } from "expo-status-bar"
import * as NavigationBar from "expo-navigation-bar"

const TrackScreen = () => {
    const { pendingOrder, setPendingOrder, clearCart } = useFlashStore()
    const router = useRouter()
    const queryClient = useQueryClient()
    const { data: order } = useQuery({
        queryKey: ["order-track", pendingOrder?.id],
        queryFn: () => OrderService.Track(pendingOrder!.id),
        enabled: !!pendingOrder,
        refetchInterval: 30 * 1000,
    })

    if (Platform.OS === "android") NavigationBar.setButtonStyleAsync("dark")

    const efeito = order?.efeito as string | undefined
    const concluido = efeito === "CONCLUIDO"
    const cancelado = efeito === "CANCELADO"
    // O ERP-NOVO expõe `efeito` (PENDENTE/CONCLUIDO/CANCELADO) + `situacao` (texto).
    // "Em atendimento" é inferido por uma situação intermediária (não pendente/concluído).
    const emAtendimento = !!order && !concluido && !cancelado && efeito !== "PENDENTE"

    const tracklines = useMemo<TimelineStep[]>(
        () => [
            {
                title: "Pedido Realizado",
                description: "Seu pedido já foi realizado",
                completed: !!order,
                isCurrent: !order,
            },
            {
                title: "Pedido Recebido pela Revenda",
                description:
                    "Seu pedido foi recebido pela revenda, em alguns minutos sairá para entrega",
                completed: emAtendimento || concluido,
                isCurrent: efeito === "PENDENTE",
            },
            {
                title: "Em Atendimento",
                description: "Seu pedido está a caminho, aguarde a chegada do entregador",
                completed: concluido,
                isCurrent: emAtendimento,
            },
            {
                title: "Entregue",
                description: "Seu pedido foi entregue",
                completed: concluido,
                isCurrent: false,
            },
        ],
        [order],
    )

    useEffect(() => {
        if (concluido || cancelado) {
            queryClient.invalidateQueries({ queryKey: ["order-history"], refetchType: "all" })
            setPendingOrder(null)
            clearCart()
            router.replace("/(auth)/(tabs)/home")
        }
    }, [order])

    const goToWhatsApp = () => {
        // WhatsApp da revenda virá da config/reseller; placeholder até religar (F7).
    }

    const renderOrderItems = () => {
        if (!order) return null
        return <OrderItems products={order.itens ?? []} totalPrice={String(order.valor_venda ?? "")} />
    }

    return (
        <View style={defaultStyles.container}>
            <StatusBar style="dark" />

            <ScrollView
                contentContainerStyle={{
                    flexDirection: "column",
                    paddingHorizontal: screenPadding.horizontal,
                    gap: 10,
                    paddingBottom: 32,
                }}
                showsVerticalScrollIndicator={false}
            >
                <View
                    style={{
                        paddingTop: Platform.select({ ios: 50, default: 35 }),
                    }}
                >
                    <Text
                        style={{
                            fontSize: fontSize.lg,
                            textAlign: "center",
                            ...fontStyle.semiBold,
                        }}
                    >
                        Acompanhar Pedido
                    </Text>
                </View>

                <View
                    style={[
                        {
                            marginTop: 15,
                            flexDirection: "column",
                            gap: 2,
                            backgroundColor: "#FFF",
                        },
                        styles.box,
                    ]}
                >
                    <View style={{ flexDirection: "row", alignItems: "center", gap: 6 }}>
                        <Text style={{ fontSize: 18, color: colors.textMuted }}>
                            Pedido em Andamento
                        </Text>
                    </View>

                    {renderOrderItems()}
                </View>

                <View
                    style={[
                        {
                            flexDirection: "column",
                            marginTop: 20,
                        },
                        styles.box,
                    ]}
                >
                    <Timeline tracklist={tracklines} />
                </View>
            </ScrollView>
        </View>
    )
}

const styles = StyleSheet.create({
    box: {
        flexDirection: "column",
        padding: 12,
        marginVertical: 5,
        borderRadius: 16,
        boxShadow: "0px 4px 40px 0px #39253D29",
        marginHorizontal: 8,
    },
})

export default TrackScreen
