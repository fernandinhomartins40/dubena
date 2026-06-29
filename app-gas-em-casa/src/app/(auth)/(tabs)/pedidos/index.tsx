import { colors, defaultStyles, fontSize, fontStyle, screenPadding } from "@/styles/theme"
import { Platform, Pressable, StyleSheet, Text, View } from "react-native"
import Entypo from "@expo/vector-icons/Entypo"
import { useQuery } from "@tanstack/react-query"
import OrderService from "@/services/order.service"
import LoaderSimple from "@/components/atoms/LoaderSimple"
import { CartLines, HistoryItem } from "@/types/types"
import OrderItems from "@/components/molecules/OrderItems"
import { FlatList } from "react-native-gesture-handler"
import Button from "@/components/atoms/button"
import { useCallback, useEffect, useMemo, useState } from "react"
import useFlashStore from "@/store/flashStore"
import EvaluateModal from "@/components/organism/EvaluateModal"
import Feather from "@expo/vector-icons/Feather"
import { Fontisto } from "@expo/vector-icons"
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

    useEffect(() => {
        if (evaluateOrderId) {
            setOrderId(evaluateOrderId)
        }
    }, [evaluateOrderId])

    const onCloseModal = useCallback(() => {
        setOrderId(null)
        setEvaluateOrderId(null)
    }, [])

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

    const renderProducts = (hist: HistoryItem) => (
        <View style={{ flexDirection: "row", alignItems: "flex-start", gap: 2 }}>
            <OrderItems products={hist.itens} totalPrice={String(hist.valor_venda)} />
        </View>
    )

    const renderItems = ({ item: hist }: { item: HistoryItem }) => {
        const cancelado = hist.efeito === "CANCELADO"
        const concluido = hist.efeito === "CONCLUIDO"

        return (
            <View style={styles.box}>
                <View style={styles.orderHeader}>
                    <Text style={styles.orderMeta}>
                        Pedido #{hist.id} - {hist.situacao ?? ""} - {formatDate(hist.datahora)}
                    </Text>
                </View>

                <View style={{ flex: 1, justifyContent: "flex-end" }}>{renderProducts(hist)}</View>

                {concluido && (
                    <View style={styles.evaluateContainer}>
                        <View style={{ width: 130 }}>
                            <Button
                                type="clear"
                                title="avaliar"
                                onPress={() => setOrderId(hist.id)}
                                textStyle={{ color: colors.primary, fontSize: fontSize.sm }}
                            />
                        </View>
                    </View>
                )}
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
                        <Fontisto name="flash" size={18} color={colors.primary} />
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

    const renderListHeader = () => (
        <>
            <View>
                <Text style={[styles.title, fontStyle.semiBold]}>Seus Pedidos</Text>
            </View>

            <View>{isLoading || isRefetching ? <LoaderSimple /> : renderLastOrder()}</View>

            <View style={styles.subTitleContainer}>
                <View style={styles.icon}>
                    <Entypo name="back-in-time" size={20} color={colors.primary} />
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
                                <Feather name="x" size={20} color={colors.textMuted} />
                            </Pressable>
                        ) : (
                            <Feather name="search" size={20} color={colors.textMuted} />
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
    box: {
        flexDirection: "column",
        padding: 10,
        marginVertical: 5,
        borderRadius: 16,
        boxShadow: "0px 4px 40px 0px #39253D29",
        marginHorizontal: screenPadding.horizontal,
        backgroundColor: colors.white,
    },
    orderHeader: {
        gap: 8,
        marginBottom: 10,
    },
    drawNumberBadge: {
        alignSelf: "flex-start",
        maxWidth: "100%",
        paddingVertical: 8,
        paddingHorizontal: 10,
        backgroundColor: colors.primaryMuted,
        borderRadius: 10,
    },
    drawNumberLabel: {
        color: colors.primary,
        fontSize: fontSize.xs,
        ...fontStyle.semiBold,
    },
    drawNumberValue: {
        color: colors.text,
        fontSize: fontSize.base,
        ...fontStyle.bold,
        flexWrap: "wrap",
    },
    orderMeta: {
        color: colors.textMuted,
        fontSize: fontSize.sm,
        ...fontStyle.regular,
        flexWrap: "wrap",
    },
    evaluateContainer: {
        justifyContent: "center",
        alignItems: "center",
        paddingTop: 8,
    },
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
})

export default PedidosScreen
