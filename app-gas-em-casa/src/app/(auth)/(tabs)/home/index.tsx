import React, { useEffect, useMemo } from "react"
import Button from "@/components/atoms/button"
import LoaderSimple from "@/components/atoms/LoaderSimple"
import HomeHeader from "@/components/molecules/HomeHeader"
import ProductList from "@/components/organism/productlist"
import ErrorView from "@/components/templates/errorview"
import OrderService from "@/services/order.service"
import useFlashStore from "@/store/flashStore"
import { colors, fontSize, fontStyle, radius, shadow } from "@/styles/theme"
import { CartLines, HistoryItem } from "@/types/types"
import { useQuery } from "@tanstack/react-query"
import { Platform, Pressable, ScrollView, StyleSheet, Text, View } from "react-native"
import { useSafeAreaInsets } from "react-native-safe-area-context"
import { StatusBar } from "expo-status-bar"
import * as NavigationBar from "expo-navigation-bar"
import { useRouter } from "expo-router"
import {
    RotateCcw,
    ClipboardList,
    Truck,
    MapPin,
    MessageCircle,
    ShieldCheck,
    Clock,
    Flame,
    ShoppingCart,
} from "lucide-react-native"

const brl = new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" })

const HomeScreen = () => {
    const {
        catalog,
        condicao,
        cotacao,
        cupom,
        gasdopovo,
        rebuyOrder,
        setCatalog,
        setCondicoes,
        setCondicao,
        setCotacao,
        addToCart,
        setRebuyOrder,
        clearCart,
        qtyTotal,
        cartItensPayload,
    } = useFlashStore()
    const { top, bottom } = useSafeAreaInsets()
    const router = useRouter()

    if (Platform.OS === "android") NavigationBar.setButtonStyleAsync("dark")

    const { data: init, isLoading, isError } = useQuery({
        queryKey: ["init", gasdopovo],
        queryFn: () => OrderService.GetInit(gasdopovo),
    })

    const { data: history } = useQuery<HistoryItem[]>({
        queryKey: ["order-history"],
        queryFn: () => OrderService.GetHistory(),
    })
    const ultimoConcluido = useMemo(
        () => (history ?? []).find((o) => o.efeito === "CONCLUIDO") ?? null,
        [history],
    )

    useEffect(() => {
        if (!init) return
        setCatalog(init.produtos)
        setCondicoes(init.condicoes)
        if (!condicao && init.condicoes.length > 0) setCondicao(init.condicoes[0])
    }, [init])

    // Recompra: repopula o carrinho a partir de um pedido anterior e vai ao carrinho.
    useEffect(() => {
        if (!init || !rebuyOrder) return
        clearCart()
        for (const [id, qty] of Object.entries(rebuyOrder)) {
            for (let i = 0; i < qty; i++) addToCart(Number(id))
        }
        setRebuyOrder(null)
        router.push("/(auth)/carrinho")
    }, [init, rebuyOrder])

    const itens = cartItensPayload()
    const { data: cotacaoData } = useQuery({
        queryKey: ["cotacao", itens, condicao?.id, cupom, gasdopovo],
        queryFn: () =>
            OrderService.Cotar({ itens, condicao_id: condicao?.id ?? null, codigo_cupom: cupom, gasdopovo }),
        enabled: itens.length > 0,
    })

    useEffect(() => {
        setCotacao(cotacaoData ?? null)
    }, [cotacaoData])

    const total = cotacao?.total ?? 0
    const hasItems = qtyTotal() > 0

    const recomprar = () => {
        if (!ultimoConcluido) return
        const lines: CartLines = {}
        for (const item of ultimoConcluido.itens) lines[item.produto_id] = item.quantidade
        setRebuyOrder(lines)
    }

    const irParaCarrinho = () => router.push("/(auth)/carrinho")

    if (isError) {
        return <ErrorView message="Houve um erro desconhecido, por favor contate a revenda." />
    }

    const atalhos = [
        { icon: ClipboardList, label: "Meus pedidos", onPress: () => router.push("/(auth)/(tabs)/pedidos") },
        { icon: Truck, label: "Acompanhar", onPress: () => router.push("/(auth)/track") },
        { icon: MapPin, label: "Endereços", onPress: () => router.push("/(auth)/address") },
        { icon: MessageCircle, label: "Ajuda", onPress: () => router.push("/(auth)/(tabs)/info") },
    ]

    return (
        <View style={styles.screen}>
            <StatusBar style="dark" />

            <ScrollView
                contentContainerStyle={{ paddingTop: top + 8, paddingBottom: 90 + bottom }}
                showsVerticalScrollIndicator={false}
            >
                <HomeHeader />

                {/* Recompra — o caso de uso dominante, em destaque */}
                {ultimoConcluido && ultimoConcluido.itens?.length ? (
                    <Pressable style={styles.rebuyCard} onPress={recomprar}>
                        <View style={styles.rebuyIcon}>
                            <RotateCcw size={20} color={colors.white} strokeWidth={2.4} />
                        </View>
                        <View style={{ flex: 1 }}>
                            <Text style={styles.rebuyTitle}>Pedir de novo</Text>
                            <Text style={styles.rebuySub} numberOfLines={1}>
                                {ultimoConcluido.itens[0].quantidade}x {ultimoConcluido.itens[0].descricao}
                                {ultimoConcluido.itens.length > 1 ? ` +${ultimoConcluido.itens.length - 1}` : ""} ·{" "}
                                {brl.format(ultimoConcluido.valor_venda)}
                            </Text>
                        </View>
                        <View style={styles.rebuyCta}>
                            <Text style={styles.rebuyCtaText}>Repetir</Text>
                        </View>
                    </Pressable>
                ) : null}

                {/* Atalhos rápidos */}
                <View style={styles.shortcuts}>
                    {atalhos.map(({ icon: Icon, label, onPress }) => (
                        <Pressable key={label} style={styles.shortcut} onPress={onPress}>
                            <View style={styles.shortcutIcon}>
                                <Icon size={22} color={colors.primary} strokeWidth={2} />
                            </View>
                            <Text style={styles.shortcutLabel}>{label}</Text>
                        </Pressable>
                    ))}
                </View>

                {/* Banner de promoção/aviso */}
                <View style={styles.promo}>
                    <View style={{ flex: 1 }}>
                        <Text style={styles.promoTitle}>Entrega rápida na sua casa</Text>
                        <Text style={styles.promoSub}>Peça seu gás em poucos toques e acompanhe em tempo real.</Text>
                    </View>
                    <View style={styles.promoIcon}>
                        <Flame size={30} color={colors.primary} strokeWidth={2} />
                    </View>
                </View>

                {/* Produtos */}
                <View style={styles.sectionHeader}>
                    <Text style={styles.sectionTitle}>Escolha seu produto</Text>
                </View>

                <View style={styles.productArea}>
                    {isLoading ? <LoaderSimple /> : <ProductList products={catalog} />}
                </View>

                {/* Bloco informativo */}
                <View style={styles.infoBlock}>
                    <View style={styles.infoRow}>
                        <ShieldCheck size={20} color={colors.success} strokeWidth={2} />
                        <Text style={styles.infoText}>Botijões lacrados e revenda autorizada.</Text>
                    </View>
                    <View style={styles.infoRow}>
                        <Clock size={20} color={colors.primary} strokeWidth={2} />
                        <Text style={styles.infoText}>Acompanhe a entrega em tempo real pelo mapa.</Text>
                    </View>
                </View>
            </ScrollView>

            {/* Barra fixa: ir para o carrinho — acima da tab bar (62 + safe area) */}
            {hasItems && (
                <View style={[styles.checkoutBar, { bottom: 62 + bottom }]}>
                    <View style={styles.cartBadge}>
                        <ShoppingCart size={20} color={colors.white} strokeWidth={2.2} />
                        <View style={styles.cartCount}>
                            <Text style={styles.cartCountText}>{qtyTotal()}</Text>
                        </View>
                    </View>
                    <View style={{ flex: 1 }}>
                        <Text style={styles.checkoutLabel}>Total</Text>
                        <Text style={styles.checkoutTotal}>{brl.format(total)}</Text>
                    </View>
                    <View style={{ maxWidth: 170, flex: 1 }}>
                        <Button title="Ver carrinho" onPress={irParaCarrinho} uppercase={false} />
                    </View>
                </View>
            )}
        </View>
    )
}

const styles = StyleSheet.create({
    screen: { flex: 1, backgroundColor: colors.background },
    rebuyCard: {
        flexDirection: "row",
        alignItems: "center",
        gap: 12,
        marginHorizontal: 16,
        marginTop: 14,
        padding: 14,
        borderRadius: radius.lg,
        backgroundColor: colors.surface,
        ...shadow.card,
    },
    rebuyIcon: {
        width: 42,
        height: 42,
        borderRadius: radius.md,
        backgroundColor: colors.primary,
        alignItems: "center",
        justifyContent: "center",
    },
    rebuyTitle: { fontSize: fontSize.md, color: colors.text, ...fontStyle.bold },
    rebuySub: { fontSize: fontSize.xs, color: colors.textMuted, marginTop: 2, ...fontStyle.regular },
    rebuyCta: { backgroundColor: colors.primaryMuted, paddingHorizontal: 16, paddingVertical: 9, borderRadius: radius.pill },
    rebuyCtaText: { color: colors.primary, fontSize: fontSize.xs, ...fontStyle.bold },

    shortcuts: {
        flexDirection: "row",
        justifyContent: "space-between",
        marginHorizontal: 16,
        marginTop: 18,
    },
    shortcut: { alignItems: "center", gap: 6, flex: 1 },
    shortcutIcon: {
        width: 54,
        height: 54,
        borderRadius: radius.lg,
        backgroundColor: colors.surface,
        alignItems: "center",
        justifyContent: "center",
        ...shadow.card,
    },
    shortcutLabel: { fontSize: 11, color: colors.text, ...fontStyle.medium },

    promo: {
        flexDirection: "row",
        alignItems: "center",
        gap: 12,
        marginHorizontal: 16,
        marginTop: 18,
        padding: 16,
        borderRadius: radius.lg,
        backgroundColor: colors.secondary,
    },
    promoTitle: { fontSize: fontSize.md, color: colors.graphite, ...fontStyle.bold },
    promoSub: { fontSize: fontSize.xs, color: colors.graphite, marginTop: 3, ...fontStyle.regular },
    promoIcon: {
        width: 52,
        height: 52,
        borderRadius: radius.md,
        backgroundColor: colors.white,
        alignItems: "center",
        justifyContent: "center",
    },

    sectionHeader: { marginHorizontal: 16, marginTop: 22, marginBottom: 4 },
    sectionTitle: { fontSize: fontSize.base, color: colors.text, ...fontStyle.bold },
    productArea: { minHeight: 300 },

    infoBlock: {
        marginHorizontal: 16,
        marginTop: 20,
        padding: 14,
        borderRadius: radius.lg,
        backgroundColor: colors.surface,
        gap: 12,
        borderWidth: 1,
        borderColor: colors.border,
    },
    infoRow: { flexDirection: "row", alignItems: "center", gap: 10 },
    infoText: { flex: 1, fontSize: fontSize.sm, color: colors.text, ...fontStyle.regular },

    checkoutBar: {
        position: "absolute",
        left: 12,
        right: 12,
        flexDirection: "row",
        alignItems: "center",
        justifyContent: "space-between",
        gap: 12,
        paddingHorizontal: 16,
        paddingVertical: 10,
        borderRadius: radius.lg,
        backgroundColor: colors.surface,
        ...shadow.card,
    },
    checkoutLabel: { fontSize: fontSize.xs, color: colors.textMuted, ...fontStyle.regular },
    checkoutTotal: { fontSize: fontSize.md, color: colors.text, ...fontStyle.bold },
    cartBadge: {
        width: 44,
        height: 44,
        borderRadius: radius.md,
        backgroundColor: colors.primary,
        alignItems: "center",
        justifyContent: "center",
    },
    cartCount: {
        position: "absolute",
        top: -4,
        right: -4,
        minWidth: 18,
        height: 18,
        paddingHorizontal: 4,
        borderRadius: 999,
        backgroundColor: colors.secondary,
        alignItems: "center",
        justifyContent: "center",
    },
    cartCountText: { fontSize: 11, color: colors.graphite, ...fontStyle.bold },
})

export default HomeScreen
