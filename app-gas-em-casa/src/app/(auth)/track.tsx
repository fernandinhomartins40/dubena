import { useCallback, useEffect, useMemo } from "react"
import { colors, defaultStyles, fontSize, fontStyle, screenPadding } from "@/styles/theme"
import { Platform, ScrollView, StyleSheet, Text, View } from "react-native"
import MapView, { Marker, Polyline, PROVIDER_GOOGLE } from "react-native-maps"
import Timeline from "@/components/molecules/Timeline"
import useFlashStore from "@/store/flashStore"
import { useQuery, useQueryClient } from "@tanstack/react-query"
import OrderService from "@/services/order.service"
import { decodePolyline } from "@/helpers/polyline"
import { TimelineStep } from "@/types/types"
import { useRouter } from "expo-router"
import OrderItems from "@/components/molecules/OrderItems"
import { StatusBar } from "expo-status-bar"
import * as NavigationBar from "expo-navigation-bar"
import { Bike, Home as HomeIcon } from "lucide-react-native"
import { useAcompanharPedido } from "@/hooks/useAcompanharPedido"

const TrackScreen = () => {
    const { pendingOrder, setPendingOrder, clearCart } = useFlashStore()
    const router = useRouter()
    const queryClient = useQueryClient()
    const { data: order } = useQuery({
        queryKey: ["order-track", pendingOrder?.id],
        queryFn: () => OrderService.Track(pendingOrder!.id),
        enabled: !!pendingOrder,
        // P8: com tempo real, o polling vira só uma rede de segurança (intervalo
        // maior). Sem Reverb, mantém os 30s de antes.
        refetchInterval: 30 * 1000,
    })

    if (Platform.OS === "android") NavigationBar.setButtonStyleAsync("dark")

    const efeito = order?.efeito as string | undefined
    const concluido = efeito === "CONCLUIDO"
    const cancelado = efeito === "CANCELADO"
    // O ERP-NOVO expõe `efeito` (PENDENTE/CONCLUIDO/CANCELADO) + `situacao` (texto).
    // "Em atendimento" é inferido por uma situação intermediária (não pendente/concluído).
    const emAtendimento = !!order && !concluido && !cancelado && efeito !== "PENDENTE"

    // P8 — tempo real: assina o canal do pedido. Ao receber mudança de status,
    // invalida a query (reflete na hora). A posição do entregador alimenta o mapa.
    const aoMudarStatus = useCallback(() => {
        queryClient.invalidateQueries({ queryKey: ["order-track", pendingOrder?.id] })
    }, [queryClient, pendingOrder?.id])

    const { posicao, aoVivo } = useAcompanharPedido(pendingOrder?.id ?? null, aoMudarStatus)

    // L6 — rota do entregador pelas ruas + ETA. A POSIÇÃO vem dos pings do app do
    // entregador (zero custo Google); o TRAÇADO usa o cache persistente do backend
    // (1 chamada por célula de ~100 m — o polling de 10s não gera custo extra).
    const { data: rota } = useQuery({
        queryKey: ["rota-entregador", pendingOrder?.id],
        queryFn: () => OrderService.RotaEntregador(pendingOrder!.id),
        enabled: !!pendingOrder && emAtendimento,
        refetchInterval: 10000,
    })

    // Posição no mapa: tempo real (Reverb) quando disponível; senão a do polling.
    const posEntregador = posicao
        ? { latitude: posicao.lat, longitude: posicao.lng }
        : rota?.posicao
          ? { latitude: rota.posicao.lat, longitude: rota.posicao.lng }
          : null
    const destino = rota?.destino ? { latitude: rota.destino.lat, longitude: rota.destino.lng } : null
    const tracado = useMemo(
        () => (rota?.polyline ? decodePolyline(rota.polyline) : []),
        [rota?.polyline],
    )
    const mostrarMapa = emAtendimento && !!posEntregador

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

                {mostrarMapa && (
                    <View style={[styles.box, { padding: 0, overflow: "hidden" }]}>
                        <MapView
                            style={styles.map}
                            provider={PROVIDER_GOOGLE}
                            region={{
                                // Centraliza entre o entregador e a casa do cliente.
                                latitude: destino ? (posEntregador!.latitude + destino.latitude) / 2 : posEntregador!.latitude,
                                longitude: destino ? (posEntregador!.longitude + destino.longitude) / 2 : posEntregador!.longitude,
                                latitudeDelta: destino ? Math.max(Math.abs(posEntregador!.latitude - destino.latitude) * 2.4, 0.012) : 0.012,
                                longitudeDelta: destino ? Math.max(Math.abs(posEntregador!.longitude - destino.longitude) * 2.4, 0.012) : 0.012,
                            }}
                            pointerEvents="none"
                        >
                            {tracado.length > 1 && (
                                <Polyline coordinates={tracado} strokeWidth={5} strokeColor={colors.primary} />
                            )}
                            <Marker coordinate={posEntregador!} anchor={{ x: 0.5, y: 0.5 }}>
                                <View style={styles.pinEntregador}>
                                    <Bike size={16} color="#FFF" strokeWidth={2.4} />
                                </View>
                            </Marker>
                            {destino && (
                                <Marker coordinate={destino} anchor={{ x: 0.5, y: 0.5 }}>
                                    <View style={styles.pinCasa}>
                                        <HomeIcon size={14} color={colors.primary} strokeWidth={2.4} />
                                    </View>
                                </Marker>
                            )}
                        </MapView>
                        <View style={{ padding: 12, gap: 2 }}>
                            <View style={{ flexDirection: "row", alignItems: "center", justifyContent: "space-between" }}>
                                <Text style={styles.mapLabel}>
                                    {aoVivo ? "● A caminho — ao vivo" : "● A caminho"}
                                </Text>
                                {rota?.duracao_min != null && (
                                    <Text style={styles.eta}>
                                        chega em ~{Math.max(1, Math.round(rota.duracao_min))} min
                                        {rota.distancia_km != null ? ` · ${rota.distancia_km} km` : ""}
                                    </Text>
                                )}
                            </View>
                            {rota?.entregador?.nome ? (
                                <Text style={styles.entregadorInfo}>
                                    {rota.entregador.nome}
                                    {rota.entregador.veiculo ? ` · ${rota.entregador.veiculo}` : ""}
                                </Text>
                            ) : null}
                        </View>
                    </View>
                )}

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
    map: {
        height: 220,
        width: "100%",
    },
    mapLabel: {
        ...fontStyle.semiBold,
        fontSize: fontSize.sm,
        color: colors.primary,
    },
    entregadorInfo: {
        ...fontStyle.regular,
        fontSize: fontSize.sm,
        color: colors.textMuted,
    },
    eta: {
        ...fontStyle.bold,
        fontSize: fontSize.sm,
        color: colors.text,
    },
    pinEntregador: {
        width: 32,
        height: 32,
        borderRadius: 999,
        backgroundColor: colors.primary,
        alignItems: "center",
        justifyContent: "center",
        borderWidth: 2.5,
        borderColor: "#FFF",
        elevation: 4,
    },
    pinCasa: {
        width: 28,
        height: 28,
        borderRadius: 999,
        backgroundColor: "#FFF",
        alignItems: "center",
        justifyContent: "center",
        borderWidth: 2,
        borderColor: colors.primary,
        elevation: 3,
    },
})

export default TrackScreen
