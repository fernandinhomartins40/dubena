import React, { useEffect, useMemo, useRef } from "react"
import Button from "@/components/atoms/button"
import LoaderSimple from "@/components/atoms/LoaderSimple"
import Header from "@/components/molecules/header"
import OrderConfirm from "@/components/organism/OrderConfirm"
import PaymentMethod from "@/components/organism/PaymentMethod"
import PaymentMethodSheet from "@/components/organism/PaymentMethodSheet"
import ProductList from "@/components/organism/productlist"
import CartItems from "@/components/templates/CartItems"
import ErrorView from "@/components/templates/errorview"
import { BackgroundImgUri } from "@/constants/images"
import OrderService from "@/services/order.service"
import useFlashStore from "@/store/flashStore"
import { colors, defaultStyles, fontSize, fontStyle } from "@/styles/theme"
import { CondicaoPagamento } from "@/types/types"
import { BottomSheetModal } from "@gorhom/bottom-sheet"
import { useQuery } from "@tanstack/react-query"
import { ImageBackground, Platform, Pressable, ScrollView, StyleSheet, Text, View } from "react-native"
import { useSafeAreaInsets } from "react-native-safe-area-context"
import { StatusBar } from "expo-status-bar"
import * as NavigationBar from "expo-navigation-bar"
import { Fontisto } from "@expo/vector-icons"
import Toast from "react-native-toast-message"
import ImageBanner from "@/components/atoms/ImageBanner"
import { CartLines, HistoryItem } from "@/types/types"

const HomeScreen = () => {
    const {
        catalog,
        condicoes,
        condicao,
        cart,
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
    const formatter = new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" })
    const paymentMethodRef = useRef<BottomSheetModal>(null)
    const confirmRef = useRef<BottomSheetModal>(null)

    if (Platform.OS === "android") NavigationBar.setButtonStyleAsync("dark")

    // Abertura do app: catálogo + condições de pagamento (preço só p/ exibição).
    const {
        data: init,
        isLoading,
        isError,
    } = useQuery({
        queryKey: ["init", gasdopovo],
        queryFn: () => OrderService.GetInit(gasdopovo),
    })

    useEffect(() => {
        if (!init) return
        setCatalog(init.produtos)
        setCondicoes(init.condicoes)
        if (!condicao && init.condicoes.length > 0) setCondicao(init.condicoes[0])
    }, [init])

    // Recompra: repopula o carrinho a partir de um pedido anterior.
    useEffect(() => {
        if (!init || !rebuyOrder) return
        clearCart()
        for (const [id, qty] of Object.entries(rebuyOrder)) {
            for (let i = 0; i < qty; i++) addToCart(Number(id))
        }
        setRebuyOrder(null)
        paymentMethodRef.current?.present()
    }, [init, rebuyOrder])

    // COTAÇÃO server-side: total/desconto vêm sempre do servidor (F3).
    const itens = cartItensPayload()
    const { data: cotacaoData } = useQuery({
        queryKey: ["cotacao", cart, condicao?.id, cupom, gasdopovo],
        queryFn: () =>
            OrderService.Cotar({
                itens,
                condicao_id: condicao?.id ?? null,
                codigo_cupom: cupom,
                gasdopovo,
            }),
        enabled: itens.length > 0,
    })

    useEffect(() => {
        setCotacao(cotacaoData ?? null)
    }, [cotacaoData])

    // Histórico p/ a recompra 1-toque (o caso de uso dominante: pedir o mesmo de novo).
    const { data: history } = useQuery<HistoryItem[]>({
        queryKey: ["order-history"],
        queryFn: () => OrderService.GetHistory(),
    })
    const ultimoConcluido = useMemo(
        () => (history ?? []).find((o) => o.efeito === "CONCLUIDO") ?? null,
        [history],
    )

    /** Recompra em 1 toque: repopula o carrinho com o último pedido e abre o pagamento. */
    const recomprar = () => {
        if (!ultimoConcluido) return
        const lines: CartLines = {}
        for (const item of ultimoConcluido.itens) lines[item.produto_id] = item.quantidade
        setRebuyOrder(lines)
    }

    const total = cotacao?.total ?? 0
    const hasItems = qtyTotal() > 0

    const handleConfirm = () => {
        if (!condicao) {
            Toast.show({ type: "info", text1: "Selecione uma forma de pagamento." })
            paymentMethodRef.current?.present()
            return
        }
        confirmRef.current?.present()
    }

    const handleSetCondicao = (c: CondicaoPagamento) => setCondicao(c)

    if (isError) {
        return <ErrorView message={"Houve um erro desconhecido, por favor contate a revenda."} />
    }

    const renderRecompra = () => {
        if (!ultimoConcluido || !ultimoConcluido.itens?.length) return null
        const first = ultimoConcluido.itens[0]
        const extra = ultimoConcluido.itens.length - 1
        return (
            <Pressable style={styles.rebuyCard} onPress={recomprar}>
                <View style={styles.rebuyIcon}>
                    <Fontisto name="flash" size={18} color={colors.primary} />
                </View>
                <View style={{ flex: 1 }}>
                    <Text style={styles.rebuyTitle}>Pedir de novo</Text>
                    <Text style={styles.rebuySub} numberOfLines={1}>
                        {first.quantidade}x {first.descricao}
                        {extra > 0 ? ` +${extra}` : ""} · {formatter.format(ultimoConcluido.valor_venda)}
                    </Text>
                </View>
                <View style={styles.rebuyCta}>
                    <Text style={styles.rebuyCtaText}>Repetir</Text>
                </View>
            </Pressable>
        )
    }

    const renderProducts = () => (
        <>
            {renderRecompra()}

            <View style={{ paddingTop: 8 }}>
                <Text style={[{ textAlign: "center", fontSize: fontSize.base }, fontStyle.semiBold]}>
                    Novo Pedido
                </Text>
            </View>

            <View style={{ maxHeight: 340 }}>
                <ProductList products={catalog} />
            </View>

            <View style={{ flexDirection: "column", justifyContent: "space-evenly", gap: 30 }}>
                <View style={styles.totalRow}>
                    <View>
                        <Text style={[styles.textTitles, fontStyle.regular]}>
                            Total: {"\n"}
                            <Text
                                style={{ fontSize: 26, color: colors.primary, ...fontStyle.semiBold }}
                            >
                                {formatter.format(total)}
                            </Text>
                        </Text>
                    </View>
                    <View style={{ width: "50%", ...fontStyle.regular }}>
                        <Text style={styles.textTitles}>Itens:</Text>
                        <View style={[styles.flexColumn, { gap: 2 }]}>
                            <CartItems />
                        </View>
                    </View>
                </View>

                {condicoes.length > 0 && (
                    <View style={{ alignItems: "center" }}>
                        <PaymentMethod
                            condicao={condicao}
                            onPress={() => paymentMethodRef.current?.present()}
                        />
                    </View>
                )}

                <View style={{ paddingHorizontal: 25 }}>
                    <Button title="Confirmar" disabled={!hasItems} onPress={handleConfirm} />
                </View>
            </View>
        </>
    )

    return (
        <View style={defaultStyles.container}>
            <StatusBar style="inverted" />

            <ImageBackground
                source={{ uri: BackgroundImgUri }}
                style={[defaultStyles.image, { paddingTop: top }]}
            >
                <View style={{ flex: 1, flexDirection: "column" }}>
                    <Header />
                    <View style={styles.container}>
                        <ScrollView
                            contentContainerStyle={{ paddingBottom: 70 + bottom }}
                            showsVerticalScrollIndicator={false}
                        >
                            {!isLoading ? renderProducts() : <LoaderSimple />}
                        </ScrollView>
                    </View>
                </View>
            </ImageBackground>

            <PaymentMethodSheet
                ref={paymentMethodRef}
                condicoes={condicoes}
                selectedId={condicao?.id}
                setCondicao={handleSetCondicao}
            />

            <OrderConfirm ref={confirmRef} onPressPayment={() => paymentMethodRef.current?.present()} />

            <ImageBanner />
        </View>
    )
}

const styles = StyleSheet.create({
    flexColumn: { display: "flex", flexDirection: "column", justifyContent: "space-evenly" },
    container: {
        flex: 1,
        backgroundColor: colors.white,
        height: "100%",
        marginTop: 20,
        borderRadius: 30,
        justifyContent: "flex-start",
        overflow: "hidden",
    },
    totalRow: {
        display: "flex",
        flexDirection: "row",
        justifyContent: "space-between",
        paddingHorizontal: 25,
    },
    textTitles: { fontSize: 16, color: colors.textMuted },
    rebuyCard: {
        flexDirection: "row",
        alignItems: "center",
        gap: 10,
        marginHorizontal: 16,
        marginTop: 14,
        padding: 12,
        borderRadius: 14,
        backgroundColor: colors.primaryMuted,
        borderWidth: 1,
        borderColor: "#FFD9C2",
    },
    rebuyIcon: {
        width: 36,
        height: 36,
        borderRadius: 10,
        backgroundColor: colors.white,
        alignItems: "center",
        justifyContent: "center",
    },
    rebuyTitle: { fontSize: fontSize.sm, color: colors.text, ...fontStyle.semiBold },
    rebuySub: { fontSize: fontSize.xs, color: colors.textMuted, marginTop: 1, ...fontStyle.regular },
    rebuyCta: {
        backgroundColor: colors.primary,
        paddingHorizontal: 16,
        paddingVertical: 9,
        borderRadius: 999,
    },
    rebuyCtaText: { color: colors.white, fontSize: fontSize.xs, ...fontStyle.semiBold },
})

export default HomeScreen
