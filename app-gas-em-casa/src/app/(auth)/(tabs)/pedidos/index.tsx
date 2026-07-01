import { colors, defaultStyles, fontSize, fontStyle, screenPadding } from "@/styles/theme"
import { Platform, Pressable, StyleSheet, Text, View } from "react-native"
import { Zap, Star, X, History, Search } from "lucide-react-native"
import { useQuery } from "@tanstack/react-query"
import OrderService from "@/services/order.service"
import LoaderSimple from "@/components/atoms/LoaderSimple"
import { CartLines, HistoryItem } from "@/types/types"
import { FlatList } from "react-native-gesture-handler"
import FastImage from "react-native-fast-image"
import { GasImgUri } from "@/constants/images"
import Button from "@/components/atoms/button"
import { useCallback, useEffect, useMemo, useState } from "react"
import useFlashStore from "@/store/flashStore"
import EvaluateModal from "@/components/organism/EvaluateModal"
import { useRouter } from "expo-router"
import Input from "@/components/atoms/input"

const PedidosScreen = () => {
    const router = useRouter()
    const { evaluateOrderId, setEvaluateOrderId, setRebuyOrder } = useFlashStore()
    const {
        data: history,
        isLoading,
        isRefetching,
    } = useQuery<HistoryItem[]>({
        queryKey: ["order-history"],
        queryFn: () => OrderService.GetHistory(),
    })
    const [orderId, setOrderId] = useState<number | null>(null)
    const [searchText, setSearchText] = useState("")
    // Avaliação OPCIONAL: o banner some quando o usuário avalia ou toca "agora não".
    const [avaliacaoDispensada, setAvaliacaoDispensada] = useState(false)

    useEffect(() => {
        if (evaluateOrderId) {
            setOrderId(evaluateOrderId)
        }
    }, [evaluateOrderId])

    const onCloseModal = useCallback(() => {
        setOrderId(null)
        setEvaluateOrderId(null)
        setAvaliacaoDispensada(true)
    }, [setEvaluateOrderId])

    const formatDate = (iso: string | null) =>
        iso ? new Date(iso).toLocaleDateString("pt-BR") : ""

    const rebuy = (order: HistoryItem) => {
        const lines: CartLines = {}
        for (const item of order.itens) lines[item.produto_id] = item.quantidade
        setRebuyOrder(lines)
        router.replace("/(auth)/(tabs)/home")
    }

    const filteredHistory = useMemo<HistoryItem[]>(() => {
        if (!history) return []
        const search = searchText.trim().toLowerCase()
        if (!search) return history

        return history.filter((hist) => {
            const id = String(hist.id).toLowerCase()
            const status = String(hist.situacao ?? "").toLowerCase()
            const date = formatDate(hist.datahora).toLowerCase()
            return id.includes(search) || status.includes(search) || date.includes(search)
        })
    }, [history, searchText])

    const moeda = (v: number) =>
        new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(v)

    const statusBadge = (efeito: string | null, situacao: string | null) => {
        const map: Record<string, { bg: string; fg: string }> = {
            CONCLUIDO: { bg: colors.successMuted, fg: colors.success },
            CANCELADO: { bg: "#FDECEC", fg: colors.errorColor },
            PENDENTE: { bg: colors.primaryMuted, fg: colors.primary },
        }
        const s = map[efeito ?? ""] ?? { bg: "#EFEFEF", fg: colors.textMuted }
        return (
            <View style={[styles.badge, { backgroundColor: s.bg }]}>
                <Text style={[styles.badgeText, { color: s.fg }]}>{situacao ?? efeito ?? "—"}</Text>
            </View>
        )
    }

    const renderItems = ({ item: hist }: { item: HistoryItem }) => {
        const concluido = hist.efeito === "CONCLUIDO"

        return (
            <View style={styles.card}>
                {/* Cabeçalho: #pedido + data | status */}
                <View style={styles.cardHeader}>
                    <View style={{ flex: 1 }}>
                        <Text style={styles.orderId}>Pedido #{hist.id}</Text>
                        <Text style={styles.orderDate}>{formatDate(hist.datahora)}</Text>
                    </View>
                    {statusBadge(hist.efeito ?? null, hist.situacao ?? null)}
                </View>

                {/* Corpo: thumbnail + lista de itens */}
                <View style={styles.cardBody}>
                    <View style={styles.thumb}>
                        <FastImage source={{ uri: GasImgUri }} style={styles.thumbImg} resizeMode="contain" />
                    </View>
                    <View style={{ flex: 1, gap: 6 }}>
                        {hist.itens.map((it, idx) => (
                            <View key={idx} style={styles.itemRow}>
                                <View style={styles.qtyBadge}>
                                    <Text style={styles.qtyText}>{it.quantidade}</Text>
                                </View>
                                <Text style={styles.itemName} numberOfLines={1}>
                                    {it.descricao}
                                </Text>
                            </View>
                        ))}
                    </View>
                </View>

                {/* Rodapé: total | avaliar */}
                <View style={styles.cardFooter}>
                    <View>
                        <Text style={styles.totalLabel}>Total</Text>
                        <Text style={styles.totalValue}>{moeda(hist.valor_venda)}</Text>
                    </View>
                    {concluido && (
                        <Pressable style={styles.evalBtn} onPress={() => setOrderId(hist.id)}>
                            <Text style={styles.evalBtnText}>Avaliar</Text>
                        </Pressable>
                    )}
                </View>
            </View>
        )
    }

    const renderLastOrder = () => {
        if (!history) return null

        const order = history.find((it) => it.efeito === "CONCLUIDO")
        const firstProduct = order?.itens?.[0]

        if (!order || !firstProduct) return null

        const extraItems = order.itens.length > 1 ? order.itens.length - 1 : 0

        return (
            <View style={styles.lastOrderBox}>
                <View style={styles.lastOrderInfo}>
                    <View style={styles.icon}>
                        <Zap size={18} color={colors.primary} strokeWidth={2} />
                    </View>

                    <View style={styles.lastOrderText}>
                        <Text style={styles.lastOrderTitle}>Repetir último pedido</Text>

                        <Text style={styles.lastOrderSummary} numberOfLines={1}>
                            {firstProduct.quantidade}x {firstProduct.descricao}
                            {extraItems > 0
                                ? ` + ${extraItems} item${extraItems > 1 ? "s" : ""}`
                                : ""}
                        </Text>

                        <Text style={styles.lastOrderTotal}>
                            {new Intl.NumberFormat("pt-BR", {
                                style: "currency",
                                currency: "BRL",
                            }).format(order.valor_venda)}
                        </Text>
                    </View>
                </View>

                <View style={styles.repeatButton}>
                    <Button
                        title="Pedir"
                        onPress={() => rebuy(order)}
                        textStyle={styles.repeatButtonText}
                        buttonStyle={styles.repeatButtonInner}
                    />
                </View>
            </View>
        )
    }

    // Candidato a avaliação: 1º pedido concluído (mais recente). Banner opcional.
    const avaliavel = useMemo(
        () => (history ?? []).find((o) => o.efeito === "CONCLUIDO") ?? null,
        [history],
    )

    const renderAvaliacaoBanner = () => {
        if (avaliacaoDispensada || !avaliavel || orderId) return null
        return (
            <View style={styles.evalBanner}>
                <View style={styles.evalIcon}>
                    <Star size={18} color={colors.primary} fill={colors.primary} strokeWidth={0} />
                </View>
                <View style={{ flex: 1 }}>
                    <Text style={styles.evalTitle}>Avalie seu último pedido</Text>
                    <Text style={styles.evalSub} numberOfLines={1}>
                        Leva 5 segundos e ajuda a melhorar.
                    </Text>
                </View>
                <Pressable onPress={() => setOrderId(avaliavel.id)} style={styles.evalCta} hitSlop={6}>
                    <Text style={styles.evalCtaText}>Avaliar</Text>
                </Pressable>
                <Pressable onPress={() => setAvaliacaoDispensada(true)} hitSlop={10} style={{ paddingLeft: 4 }}>
                    <X size={18} color={colors.textMuted} strokeWidth={2} />
                </Pressable>
            </View>
        )
    }

    const renderListHeader = () => (
        <>
            <View>
                <Text style={[styles.title, fontStyle.semiBold]}>Seus Pedidos</Text>
            </View>

            {renderAvaliacaoBanner()}

            <View>{isLoading || isRefetching ? <LoaderSimple /> : renderLastOrder()}</View>

            <View style={styles.subTitleContainer}>
                <View style={styles.icon}>
                    <History size={20} color={colors.primary} strokeWidth={2} />
                </View>

                <Text style={styles.subTitle}>Histórico de Pedidos</Text>
            </View>

            <View style={styles.searchContainer}>
                <Input
                    value={searchText}
                    onChangeText={setSearchText}
                    placeholder="Buscar por número, status ou data"
                    autoCapitalize="none"
                    autoCorrect={false}
                    returnKeyType="search"
                    textStyle={styles.searchInputText}
                    inputSufix={
                        searchText ? (
                            <Pressable onPress={() => setSearchText("")} hitSlop={10}>
                                <X size={20} color={colors.textMuted} strokeWidth={2} />
                            </Pressable>
                        ) : (
                            <Search size={20} color={colors.textMuted} strokeWidth={2} />
                        )
                    }
                />
            </View>
        </>
    )

    return (
        <View style={defaultStyles.container}>
            <View style={styles.container}>
                <View style={styles.listContainer}>
                    {isLoading || isRefetching ? (
                        <FlatList
                            data={[]}
                            renderItem={renderItems}
                            ListHeaderComponent={renderListHeader()}
                            contentContainerStyle={styles.listContent}
                        />
                    ) : (
                        <FlatList
                            data={filteredHistory}
                            keyExtractor={(item) => String(item.id)}
                            renderItem={renderItems}
                            keyboardShouldPersistTaps="handled"
                            contentContainerStyle={styles.listContent}
                            ListHeaderComponent={renderListHeader()}
                            ListEmptyComponent={
                                <Text style={styles.emptyText}>
                                    {searchText.trim()
                                        ? "Nenhum pedido encontrado para sua busca."
                                        : "Você ainda não possui pedidos."}
                                </Text>
                            }
                        />
                    )}
                </View>

                <EvaluateModal open={!!orderId} orderId={orderId} closeModal={onCloseModal} />
            </View>
        </View>
    )
}

const styles = StyleSheet.create({
    container: {
        flex: 1,
        flexDirection: "column",
        paddingTop: Platform.select({ ios: 50, default: 34 }),
    },
    title: {
        fontSize: fontSize.lg,
        textAlign: "center",
    },
    subTitleContainer: {
        flexDirection: "row",
        justifyContent: "flex-start",
        alignItems: "center",
        marginTop: 18,
        paddingHorizontal: screenPadding.horizontal,
    },
    subTitle: {
        fontSize: fontSize.sm,
        ...fontStyle.semiBold,
    },
    searchContainer: {
        paddingHorizontal: screenPadding.horizontal,
        marginTop: 10,
        height: 42,
    },
    searchInputText: {
        paddingVertical: 8,
        paddingHorizontal: 10,
    },
    icon: {
        padding: 4,
        backgroundColor: colors.primaryMuted,
        borderRadius: 6,
        marginRight: 4,
    },
    card: {
        marginHorizontal: screenPadding.horizontal,
        marginVertical: 6,
        padding: 14,
        borderRadius: 16,
        backgroundColor: colors.surface,
        boxShadow: "0px 6px 24px 0px rgba(27, 25, 31, 0.08)",
        elevation: 3,
    },
    cardHeader: {
        flexDirection: "row",
        alignItems: "flex-start",
        justifyContent: "space-between",
        gap: 8,
    },
    orderId: { fontSize: fontSize.sm, color: colors.text, ...fontStyle.bold },
    orderDate: { fontSize: 13, color: colors.textMuted, marginTop: 1, ...fontStyle.regular },
    badge: { paddingHorizontal: 10, paddingVertical: 5, borderRadius: 999 },
    badgeText: { fontSize: fontSize.xs, ...fontStyle.semiBold },
    cardBody: {
        flexDirection: "row",
        alignItems: "center",
        gap: 12,
        marginTop: 12,
    },
    thumb: {
        width: 64,
        height: 64,
        borderRadius: 12,
        backgroundColor: colors.background,
        borderWidth: 1,
        borderColor: colors.border,
        alignItems: "center",
        justifyContent: "center",
    },
    thumbImg: { width: 46, height: 46 },
    itemRow: { flexDirection: "row", alignItems: "center", gap: 8 },
    qtyBadge: {
        minWidth: 22,
        height: 22,
        paddingHorizontal: 5,
        borderRadius: 6,
        backgroundColor: colors.primaryMuted,
        alignItems: "center",
        justifyContent: "center",
    },
    qtyText: { fontSize: fontSize.xs, color: colors.primary, ...fontStyle.bold },
    itemName: { flex: 1, fontSize: fontSize.sm, color: colors.text, ...fontStyle.regular },
    cardFooter: {
        flexDirection: "row",
        alignItems: "center",
        justifyContent: "space-between",
        marginTop: 14,
        paddingTop: 12,
        borderTopWidth: 1,
        borderTopColor: colors.border,
    },
    totalLabel: { fontSize: fontSize.xs, color: colors.textMuted, ...fontStyle.regular },
    totalValue: { fontSize: fontSize.md, color: colors.text, ...fontStyle.bold },
    evalBtn: {
        paddingHorizontal: 18,
        paddingVertical: 9,
        borderRadius: 999,
        borderWidth: 1.5,
        borderColor: colors.primary,
    },
    evalBtnText: { fontSize: fontSize.sm, color: colors.primary, ...fontStyle.semiBold },
    emptyText: {
        color: colors.textMuted,
        fontSize: fontSize.sm,
        textAlign: "center",
        marginTop: 24,
        paddingHorizontal: screenPadding.horizontal,
        ...fontStyle.regular,
    },
    listContainer: {
        flex: 1,
    },
    listContent: {
        paddingTop: 14,
        paddingBottom: 110,
    },
    lastOrderBox: {
        flexDirection: "row",
        alignItems: "center",
        justifyContent: "space-between",
        gap: 10,
        padding: 10,
        marginTop: 16,
        marginHorizontal: screenPadding.horizontal,
        borderRadius: 14,
        boxShadow: "0px 4px 40px 0px #39253D29",
        backgroundColor: colors.white,
    },
    lastOrderInfo: {
        flex: 1,
        flexDirection: "row",
        alignItems: "center",
        gap: 8,
    },
    lastOrderText: {
        flex: 1,
    },
    lastOrderTitle: {
        fontSize: fontSize.sm,
        ...fontStyle.semiBold,
    },
    lastOrderSummary: {
        color: colors.textMuted,
        fontSize: fontSize.xs,
        marginTop: 2,
        ...fontStyle.regular,
    },
    lastOrderTotal: {
        color: colors.primary,
        fontSize: fontSize.sm,
        marginTop: 2,
        ...fontStyle.semiBold,
    },
    repeatButton: {
        width: 82,
    },
    repeatButtonInner: {
        paddingHorizontal: 10,
        paddingVertical: 8,
        borderRadius: 10,
    },
    repeatButtonText: {
        fontSize: fontSize.xs,
    },
    evalBanner: {
        flexDirection: "row",
        alignItems: "center",
        gap: 10,
        marginTop: 14,
        marginHorizontal: screenPadding.horizontal,
        paddingVertical: 12,
        paddingHorizontal: 12,
        borderRadius: 14,
        backgroundColor: colors.primaryMuted,
        borderWidth: 1,
        borderColor: "#FFD9C2",
    },
    evalIcon: {
        width: 34,
        height: 34,
        borderRadius: 10,
        backgroundColor: colors.white,
        alignItems: "center",
        justifyContent: "center",
    },
    evalTitle: {
        fontSize: fontSize.sm,
        color: colors.text,
        ...fontStyle.semiBold,
    },
    evalSub: {
        fontSize: fontSize.xs,
        color: colors.textMuted,
        marginTop: 1,
        ...fontStyle.regular,
    },
    evalCta: {
        backgroundColor: colors.primary,
        paddingHorizontal: 14,
        paddingVertical: 8,
        borderRadius: 999,
    },
    evalCtaText: {
        color: colors.white,
        fontSize: fontSize.xs,
        ...fontStyle.semiBold,
    },
})

export default PedidosScreen
