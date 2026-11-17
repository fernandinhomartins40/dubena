import { useCallback, useEffect, useMemo } from "react"
import { colors, defaultStyles, fontSize, fontStyle, screenPadding } from "@/styles/theme"
import { Alert, Linking, Platform, Pressable, ScrollView, StyleSheet, Text, View } from "react-native"
import MapView, { Marker, Polyline, PROVIDER_GOOGLE } from "react-native-maps"
import Timeline from "@/components/molecules/Timeline"
import useFlashStore from "@/store/flashStore"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import Toast from "react-native-toast-message"
import Button from "@/components/atoms/button"
import StoreService from "@/services/store.service"
import { acoesDoPedido, textoConfirmaCancelamento } from "@/helpers/pedidoApp"
import OrderService from "@/services/order.service"
import { decodePolyline } from "@/helpers/polyline"
import { TimelineStep } from "@/types/types"
import { useRouter } from "expo-router"
import OrderItems from "@/components/molecules/OrderItems"
import { StatusBar } from "expo-status-bar"
import * as NavigationBar from "expo-navigation-bar"
import { Bike, ChevronLeft, Home as HomeIcon } from "lucide-react-native"
import { useAcompanharPedido } from "@/hooks/useAcompanharPedido"

const TrackScreen = () => {
    const { pendingOrder, setPendingOrder, clearCart, setPixOrder } = useFlashStore()
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
    // "Em rota" vem da marca declarada da situação (`situacao_papel`), não do
    // texto dela (PIE R3-E, mesma classe do P2-C-04). Ver helpers/pedidoApp.ts.
    const acoes = acoesDoPedido(order)
    const aberto = acoes.aberto
    const emRota = acoes.emRota

    // P2-B-16: WhatsApp e telefone da revenda (antes o botão era um placeholder vazio).
    const { data: revenda } = useQuery({ queryKey: ["reseller"], queryFn: () => StoreService.GetReseller() })

    // P2-B-04: pagar o PIX de novo e cancelar o próprio pedido aberto.
    const pagarPix = useMutation({
        mutationFn: () => OrderService.GerarPix(pendingOrder!.id),
        onSuccess: (pix) => {
            setPixOrder({ id: pendingOrder!.id, pix } as any)
            router.push("/(auth)/pix")
        },
        onError: (e: any) => Toast.show({ type: "error", text1: e?.message ?? "Não foi possível gerar o PIX." }),
    })
    const cancelar = useMutation({
        mutationFn: () => OrderService.Cancel(pendingOrder!.id),
        onSuccess: () => {
            Toast.show({ type: "success", text1: "Pedido cancelado." })
            queryClient.invalidateQueries({ queryKey: ["order-track", pendingOrder?.id] })
        },
        onError: (e: any) => Toast.show({ type: "error", text1: e?.message ?? "Não foi possível cancelar o pedido." }),
    })
    const confirmarCancelamento = () =>
        Alert.alert("Cancelar pedido", textoConfirmaCancelamento(order), [
            { text: "Não", style: "cancel" },
            { text: "Sim, cancelar", style: "destructive", onPress: () => cancelar.mutate() },
        ])

    // P8 — tempo real: assina o canal do pedido. Ao receber mudança de status,
    // invalida a query (reflete na hora). A posição do entregador alimenta o mapa.
    const aoMudarStatus = useCallback(() => {
        queryClient.invalidateQueries({ queryKey: ["order-track", pendingOrder?.id] })
        queryClient.invalidateQueries({ queryKey: ["rota-entregador", pendingOrder?.id] })
    }, [queryClient, pendingOrder?.id])

    const { posicao, aoVivo } = useAcompanharPedido(pendingOrder?.id ?? null, aoMudarStatus)

    // L6 — rota do entregador pelas ruas + ETA. A POSIÇÃO vem dos pings do app do
    // entregador (zero custo Google); o TRAÇADO usa o cache persistente do backend
    // (1 chamada por célula de ~100 m — o polling de 10s não gera custo extra).
    // Ativa para QUALQUER pedido aberto: se o entregador já tem posição, o mapa
    // entra mesmo antes de a situação virar "saiu para entrega".
    const { data: rota } = useQuery({
        queryKey: ["rota-entregador", pendingOrder?.id],
        queryFn: () => OrderService.RotaEntregador(pendingOrder!.id),
        enabled: !!pendingOrder && aberto,
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
    const mostrarMapa = aberto && !!posEntregador

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
                completed: emRota || concluido,
                isCurrent: aberto && !emRota,
            },
            {
                title: "Saiu para entrega",
                description: "Seu pedido está a caminho — acompanhe o entregador no mapa",
                completed: concluido,
                isCurrent: emRota,
            },
            {
                title: "Entregue",
                description: "Seu pedido foi entregue",
                completed: concluido,
                isCurrent: false,
            },
        ],
        [order, emRota, aberto, concluido],
    )

    useEffect(() => {
        if (concluido || cancelado) {
            queryClient.invalidateQueries({ queryKey: ["order-history"], refetchType: "all" })
            setPendingOrder(null)
            clearCart()
            router.replace("/(auth)/(tabs)/home")
        }
    }, [order])

    const falarComRevenda = () => {
        if (revenda?.whatsapp) {
            Linking.openURL(`whatsapp://send?phone=${revenda.whatsapp}`).catch(() =>
                revenda.telefone ? Linking.openURL(`tel:${revenda.telefone}`) : undefined,
            )
        } else if (revenda?.telefone) {
            Linking.openURL(`tel:${revenda.telefone}`)
        }
    }

    const voltar = () => (router.canGoBack() ? router.back() : router.replace("/(auth)/(tabs)/home"))

    // P2-B-16: o atalho "Acompanhar" da Home abria esta tela sem pedido e ela
    // mostrava um "Pedido em Andamento" vazio com "Pedido Realizado" como passo
    // atual — um pedido fantasma, e sem botão de voltar.
    if (!pendingOrder) {
        return (
            <View style={[defaultStyles.container, styles.vazio]}>
                <StatusBar style="dark" />
                <Text style={styles.vazioTitulo} accessibilityRole="header">
                    Você não tem pedido em andamento
                </Text>
                <Text style={styles.vazioTexto}>Quando você fizer um pedido, acompanhe a entrega por aqui.</Text>
                <View style={{ width: 220, marginTop: 16 }}>
                    <Button title="Voltar ao início" uppercase={false} onPress={voltar} />
                </View>
            </View>
        )
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
                    <Pressable
                        onPress={voltar}
                        hitSlop={12}
                        style={styles.voltar}
                        accessibilityRole="button"
                        accessibilityLabel="Voltar"
                    >
                        <ChevronLeft size={24} color={colors.text} />
                    </Pressable>
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

                <View style={{ gap: 10, marginHorizontal: 8, marginTop: 10 }}>
                    {acoes.podePagarPix && (
                        <Button
                            title="Pagar com PIX"
                            uppercase={false}
                            disabled={pagarPix.isPending}
                            onPress={() => pagarPix.mutate()}
                        />
                    )}
                    {(revenda?.whatsapp || revenda?.telefone) && (
                        <Button
                            type="outline"
                            uppercase={false}
                            title="Falar com a revenda"
                            onPress={falarComRevenda}
                            textStyle={{ color: colors.primaryDark }}
                        />
                    )}
                    {acoes.podeCancelar && (
                        <Button
                            type="clear"
                            uppercase={false}
                            title="Cancelar pedido"
                            disabled={cancelar.isPending}
                            onPress={confirmarCancelamento}
                            textStyle={{ color: colors.errorColor }}
                        />
                    )}
                </View>
            </ScrollView>
        </View>
    )
}

const styles = StyleSheet.create({
    vazio: { alignItems: "center", justifyContent: "center", padding: 24 },
    vazioTitulo: { fontSize: fontSize.lg, textAlign: "center", color: colors.text, ...fontStyle.semiBold },
    vazioTexto: { fontSize: fontSize.sm, textAlign: "center", color: colors.textMuted, marginTop: 6, ...fontStyle.regular },
    voltar: { position: "absolute", left: 0, top: Platform.select({ ios: 44, default: 29 }), width: 44, height: 44, alignItems: "center", justifyContent: "center", zIndex: 1 },
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
