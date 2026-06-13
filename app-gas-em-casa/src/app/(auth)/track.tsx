import { useEffect, useMemo } from "react"
import { colors, defaultStyles, fontSize, fontStyle, screenPadding } from "@/styles/theme"
import { Linking, Platform, ScrollView, StyleSheet, Text, View } from "react-native"
import Timeline from "@/components/molecules/Timeline"
import useFlashStore from "@/store/flashStore"
import { useQuery, useQueryClient } from "@tanstack/react-query"
import OrderService from "@/services/order.service"
import useAppStore from "@/store/appStore"
import { TimelineStep } from "@/types/types"
import FontAwesome6 from "@expo/vector-icons/FontAwesome6"
import IconButton from "@/components/atoms/IconButton"
import { useRouter } from "expo-router"
import OrderItems from "@/components/molecules/OrderItems"
import { StatusBar } from "expo-status-bar"
import * as NavigationBar from "expo-navigation-bar"

const TrackScreen = () => {
    const { user } = useAppStore()
    const { pendingOrder, setPendingOrder, clearCart } = useFlashStore()
    const router = useRouter()
    const queryClient = useQueryClient()
    const { data: order } = useQuery({
        queryKey: ["order-track"],
        queryFn: () => OrderService.Track(user?.id),
        enabled: !!user,
        refetchInterval: 1 * 60 * 1000,
    })
    const { data: products, isLoading } = useQuery({
        queryKey: ["order-items"],
        queryFn: () => OrderService.GetItems(pendingOrder?.id),
        enabled: !!pendingOrder,
    })

    if (Platform.OS === "android") NavigationBar.setButtonStyleAsync("dark")

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
                completed: !!(order?.ementrega || order?.entregue),
                isCurrent: !!order?.pendente,
            },
            {
                title: "Em Atendimento",
                description: "Seu pedido está a caminho, aguarde a chegada do entregador",
                completed: !!order?.entregue,
                isCurrent: !!order?.ementrega,
            },
            {
                title: "Entregue",
                description: "Seu pedido foi entregue",
                completed: !!order?.entregue,
                isCurrent: false,
            },
        ],
        [order],
    )

    useEffect(() => {
        if (order?.entregue || order?.cancelado) {
            queryClient.invalidateQueries({
                queryKey: ["latest-order"],
                refetchType: "all",
            })

            setPendingOrder(null)

            clearCart()

            router.replace("/(auth)/(tabs)/home")
        }
    }, [order])

    const goToWhatsApp = () => {
        if (!pendingOrder?.whatsapp) return

        Linking.openURL(`whatsapp://send?phone=${pendingOrder.whatsapp}`)
    }

    const renderOrderItems = () => {
        if (typeof products === "undefined" || isLoading || !pendingOrder) return null

        if (!!pendingOrder.gasdopovo && !!pendingOrder.valorfrete) {
            const frete = parseFloat(pendingOrder.valorfrete)

            return (
                <OrderItems
                    products={products}
                    totalPrice={pendingOrder.total_price}
                    isGasPovo={!!pendingOrder.gasdopovo}
                    deliveryTax={frete}
                />
            )
        }

        return <OrderItems products={products} totalPrice={pendingOrder.total_price} />
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

                {pendingOrder?.whatsapp ? (
                    <View>
                        <IconButton width={70} height={70} onPress={goToWhatsApp}>
                            <FontAwesome6 name="whatsapp" size={40} color="green" />
                        </IconButton>
                    </View>
                ) : (
                    ""
                )}
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
