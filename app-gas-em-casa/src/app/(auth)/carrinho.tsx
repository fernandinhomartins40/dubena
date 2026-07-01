import { useEffect, useMemo, useRef, useState } from "react"
import {
    ActivityIndicator,
    Platform,
    Pressable,
    ScrollView,
    StyleSheet,
    Text,
    TextInput,
    View,
} from "react-native"
import { useSafeAreaInsets } from "react-native-safe-area-context"
import { useMutation, useQuery } from "@tanstack/react-query"
import { useRouter } from "expo-router"
import { BottomSheetModal } from "@gorhom/bottom-sheet"
import { ChevronLeft, Minus, Plus, Trash2, CreditCard, Tag, ShoppingCart } from "lucide-react-native"
import Toast from "react-native-toast-message"
import Button from "@/components/atoms/button"
import PaymentMethodSheet from "@/components/organism/PaymentMethodSheet"
import { GasImgUri } from "@/constants/images"
import OrderService from "@/services/order.service"
import useFlashStore from "@/store/flashStore"
import { colors, fontSize, fontStyle, radius, shadow } from "@/styles/theme"
import { CondicaoPagamento } from "@/types/types"
import FastImage from "react-native-fast-image"

const brl = new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" })

/**
 * Tela de CARRINHO (estilo marketplace). Substitui o bottom sheet de confirmação:
 * revisa itens (foto, preço, +/-, remover), aplica cupom, escolhe forma de
 * pagamento e finaliza. Total/desconto vêm SEMPRE da cotação do servidor.
 */
export default function Carrinho() {
    const { top, bottom } = useSafeAreaInsets()
    const router = useRouter()
    const paymentRef = useRef<BottomSheetModal>(null)
    const {
        cart,
        catalog,
        condicoes,
        condicao,
        cotacao,
        cupom,
        gasdopovo,
        addToCart,
        removeFromCart,
        setCondicao,
        setCondicoes,
        setCupom,
        cartItensPayload,
        qtyTotal,
        setPendingOrder,
        setPixOrder,
    } = useFlashStore()

    const [cupomInput, setCupomInput] = useState(cupom ?? "")

    // Robustez: se o usuário chegar ao carrinho sem a Home ter carregado as formas
    // de pagamento (ex.: recompra direta), buscamos o init aqui e populamos o store.
    const { data: init } = useQuery({
        queryKey: ["init", gasdopovo],
        queryFn: () => OrderService.GetInit(gasdopovo),
        enabled: condicoes.length === 0,
    })
    useEffect(() => {
        if (!init) return
        if (init.condicoes?.length) {
            setCondicoes(init.condicoes)
            if (!condicao) setCondicao(init.condicoes[0])
        }
    }, [init])

    const produtoDe = (id: number) => catalog.find((p) => p.id === id)
    const linhas = useMemo(() => Object.entries(cart).map(([id, qty]) => ({ id: Number(id), qty })), [cart])

    // Cupom (validação server-side).
    const validarCupom = useMutation({
        mutationFn: (codigo: string) => OrderService.VerifyCoupon(codigo),
        onSuccess: (c) => {
            setCupom(c.codigo)
            Toast.show({ type: "success", text1: `Cupom ${c.codigo} aplicado!` })
        },
        onError: (e: any) => {
            setCupom(null)
            Toast.show({ type: "error", text1: e?.message ?? "Cupom inválido." })
        },
    })

    // Criar pedido → PIX ou acompanhamento.
    const criar = useMutation({
        mutationFn: OrderService.CreateOrder,
        onSuccess: async (pedido) => {
            try {
                const pix = await OrderService.GerarPix(pedido.id)
                setPixOrder({ id: pedido.id, pix } as any)
                router.replace("/(auth)/pix")
            } catch {
                setPendingOrder({ id: pedido.id } as any)
                router.replace("/(auth)/track")
            }
        },
        onError: (e: any) => Toast.show({ type: "error", text1: e?.message ?? "Não foi possível gerar seu pedido." }),
    })

    const finalizar = () => {
        if (qtyTotal() === 0) {
            Toast.show({ type: "info", text1: "Seu carrinho está vazio." })
            return
        }
        if (!condicao) {
            Toast.show({ type: "info", text1: "Escolha a forma de pagamento." })
            paymentRef.current?.present()
            return
        }
        criar.mutate({
            condicaopagamento_id: condicao.id,
            codigo_cupom: cupom,
            gasdopovo,
            itens: cartItensPayload(),
        })
    }

    const subtotal = cotacao?.subtotal ?? 0
    const desconto = cotacao?.desconto ?? 0
    const total = cotacao?.total ?? 0
    const vazio = qtyTotal() === 0

    return (
        <View style={styles.screen}>
            {/* Header */}
            <View style={[styles.header, { paddingTop: top + 6 }]}>
                <Pressable onPress={() => router.back()} hitSlop={12} style={styles.backBtn}>
                    <ChevronLeft size={24} color={colors.text} />
                </Pressable>
                <Text style={styles.headerTitle}>Seu carrinho</Text>
                <View style={{ width: 40 }} />
            </View>

            {vazio ? (
                <View style={styles.empty}>
                    <View style={styles.emptyIcon}>
                        <ShoppingCart size={34} color={colors.primary} strokeWidth={1.8} />
                    </View>
                    <Text style={styles.emptyTitle}>Carrinho vazio</Text>
                    <Text style={styles.emptySub}>Adicione um produto para começar seu pedido.</Text>
                    <View style={{ width: 200, marginTop: 16 }}>
                        <Button title="Ver produtos" uppercase={false} onPress={() => router.back()} />
                    </View>
                </View>
            ) : (
                <>
                    <ScrollView contentContainerStyle={{ paddingBottom: 220 + bottom }} showsVerticalScrollIndicator={false}>
                        {/* Itens */}
                        <View style={styles.card}>
                            {linhas.map(({ id, qty }, idx) => {
                                const p = produtoDe(id)
                                const preco = gasdopovo && p?.preco_gasdopovo != null ? p.preco_gasdopovo : (p?.preco ?? 0)
                                return (
                                    <View key={id} style={[styles.item, idx > 0 && styles.itemBorder]}>
                                        <FastImage source={{ uri: GasImgUri }} style={styles.itemImg} />
                                        <View style={{ flex: 1 }}>
                                            <Text style={styles.itemName} numberOfLines={2}>
                                                {p?.descricao ?? `Produto #${id}`}
                                            </Text>
                                            <Text style={styles.itemPrice}>{brl.format(preco)}</Text>
                                        </View>
                                        <View style={styles.stepper}>
                                            <Pressable onPress={() => removeFromCart(id)} hitSlop={6} style={styles.stepBtn}>
                                                {qty <= 1 ? (
                                                    <Trash2 size={16} color={colors.errorColor} />
                                                ) : (
                                                    <Minus size={16} color={colors.graphite} />
                                                )}
                                            </Pressable>
                                            <Text style={styles.stepQty}>{qty}</Text>
                                            <Pressable onPress={() => addToCart(id)} hitSlop={6} style={styles.stepBtn}>
                                                <Plus size={16} color={colors.graphite} />
                                            </Pressable>
                                        </View>
                                    </View>
                                )
                            })}
                        </View>

                        {/* Cupom */}
                        <View style={styles.card}>
                            <View style={styles.rowLabel}>
                                <Tag size={18} color={colors.primary} strokeWidth={2} />
                                <Text style={styles.rowLabelText}>Cupom de desconto</Text>
                            </View>
                            <View style={styles.cupomRow}>
                                <TextInput
                                    value={cupomInput}
                                    onChangeText={setCupomInput}
                                    autoCapitalize="characters"
                                    placeholder="Digite o código"
                                    placeholderTextColor={colors.textMuted}
                                    style={styles.cupomInput}
                                />
                                <Pressable
                                    onPress={() => cupomInput.trim() && validarCupom.mutate(cupomInput.trim())}
                                    style={styles.cupomBtn}
                                >
                                    {validarCupom.isPending ? (
                                        <ActivityIndicator size="small" color={colors.white} />
                                    ) : (
                                        <Text style={styles.cupomBtnText}>Aplicar</Text>
                                    )}
                                </Pressable>
                            </View>
                            {cotacao?.cupom ? (
                                <Text style={styles.cupomOk}>✓ Cupom {cotacao.cupom.codigo} aplicado</Text>
                            ) : null}
                        </View>

                        {/* Forma de pagamento */}
                        <Pressable style={styles.card} onPress={() => paymentRef.current?.present()}>
                            <View style={styles.payRow}>
                                <View style={styles.payIcon}>
                                    <CreditCard size={20} color={colors.primary} strokeWidth={2} />
                                </View>
                                <View style={{ flex: 1 }}>
                                    <Text style={styles.rowLabelText}>Forma de pagamento</Text>
                                    <Text style={styles.payValue}>
                                        {condicao ? condicao.descricao : "Escolher forma de pagamento"}
                                    </Text>
                                </View>
                                <ChevronLeft size={20} color={colors.textMuted} style={{ transform: [{ rotate: "180deg" }] }} />
                            </View>
                        </Pressable>

                        {/* Resumo */}
                        <View style={styles.card}>
                            <View style={styles.sumRow}>
                                <Text style={styles.sumLabel}>Subtotal</Text>
                                <Text style={styles.sumValue}>{brl.format(subtotal)}</Text>
                            </View>
                            {desconto > 0 && (
                                <View style={styles.sumRow}>
                                    <Text style={styles.sumLabel}>Desconto</Text>
                                    <Text style={[styles.sumValue, { color: colors.success }]}>- {brl.format(desconto)}</Text>
                                </View>
                            )}
                            <View style={[styles.sumRow, styles.sumTotalRow]}>
                                <Text style={styles.sumTotalLabel}>Total</Text>
                                <Text style={styles.sumTotal}>{brl.format(total)}</Text>
                            </View>
                        </View>
                    </ScrollView>

                    {/* Barra fixa de finalizar */}
                    <View style={[styles.footer, { paddingBottom: 12 + bottom }]}>
                        <View>
                            <Text style={styles.footerLabel}>{qtyTotal()} item(ns)</Text>
                            <Text style={styles.footerTotal}>{brl.format(total)}</Text>
                        </View>
                        <View style={{ flex: 1, maxWidth: 210 }}>
                            <Button
                                title="Finalizar pedido"
                                uppercase={false}
                                disabled={criar.isPending}
                                onPress={finalizar}
                            />
                        </View>
                    </View>
                </>
            )}

            <PaymentMethodSheet
                ref={paymentRef}
                condicoes={condicoes}
                selectedId={condicao?.id}
                setCondicao={(c: CondicaoPagamento) => setCondicao(c)}
            />
        </View>
    )
}

const styles = StyleSheet.create({
    screen: { flex: 1, backgroundColor: colors.background },
    header: {
        flexDirection: "row",
        alignItems: "center",
        justifyContent: "space-between",
        paddingHorizontal: 12,
        paddingBottom: 8,
    },
    backBtn: { width: 40, height: 40, borderRadius: 999, alignItems: "center", justifyContent: "center" },
    headerTitle: { fontSize: fontSize.md, color: colors.text, ...fontStyle.bold },

    empty: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
    emptyIcon: {
        width: 76, height: 76, borderRadius: radius.xl, backgroundColor: colors.primaryMuted,
        alignItems: "center", justifyContent: "center", marginBottom: 14,
    },
    emptyTitle: { fontSize: fontSize.lg, color: colors.text, ...fontStyle.bold },
    emptySub: { fontSize: fontSize.sm, color: colors.textMuted, textAlign: "center", marginTop: 4, ...fontStyle.regular },

    card: {
        marginHorizontal: 16, marginTop: 14, padding: 14, borderRadius: radius.lg,
        backgroundColor: colors.surface, ...shadow.card,
    },
    item: { flexDirection: "row", alignItems: "center", gap: 12, paddingVertical: 10 },
    itemBorder: { borderTopWidth: 1, borderTopColor: colors.border },
    itemImg: { width: 48, height: 58 },
    itemName: { fontSize: fontSize.sm, color: colors.text, ...fontStyle.semiBold },
    itemPrice: { fontSize: fontSize.sm, color: colors.primary, marginTop: 2, ...fontStyle.bold },
    stepper: {
        flexDirection: "row", alignItems: "center", gap: 10, borderWidth: 1, borderColor: colors.border,
        borderRadius: radius.pill, paddingHorizontal: 6, paddingVertical: 4,
    },
    stepBtn: { width: 30, height: 30, borderRadius: 999, alignItems: "center", justifyContent: "center" },
    stepQty: { fontSize: fontSize.sm, minWidth: 18, textAlign: "center", color: colors.text, ...fontStyle.semiBold },

    rowLabel: { flexDirection: "row", alignItems: "center", gap: 8, marginBottom: 10 },
    rowLabelText: { fontSize: fontSize.sm, color: colors.text, ...fontStyle.semiBold },
    cupomRow: { flexDirection: "row", gap: 8 },
    cupomInput: {
        flex: 1, height: 46, borderWidth: 1, borderColor: colors.border, borderRadius: radius.md,
        paddingHorizontal: 12, fontSize: fontSize.sm, color: colors.text,
    },
    cupomBtn: {
        paddingHorizontal: 18, borderRadius: radius.md, backgroundColor: colors.primary,
        alignItems: "center", justifyContent: "center", minWidth: 90,
    },
    cupomBtnText: { color: colors.white, fontSize: fontSize.sm, ...fontStyle.semiBold },
    cupomOk: { fontSize: 13, color: colors.success, marginTop: 8, ...fontStyle.medium },

    payRow: { flexDirection: "row", alignItems: "center", gap: 12 },
    payIcon: {
        width: 40, height: 40, borderRadius: radius.md, backgroundColor: colors.primaryMuted,
        alignItems: "center", justifyContent: "center",
    },
    payValue: { fontSize: 13, color: colors.textMuted, marginTop: 2, ...fontStyle.regular },

    sumRow: { flexDirection: "row", justifyContent: "space-between", alignItems: "center", paddingVertical: 4 },
    sumLabel: { fontSize: fontSize.sm, color: colors.textMuted, ...fontStyle.regular },
    sumValue: { fontSize: fontSize.sm, color: colors.text, ...fontStyle.medium },
    sumTotalRow: { borderTopWidth: 1, borderTopColor: colors.border, marginTop: 6, paddingTop: 10 },
    sumTotalLabel: { fontSize: fontSize.md, color: colors.text, ...fontStyle.bold },
    sumTotal: { fontSize: fontSize.lg, color: colors.primary, ...fontStyle.bold },

    footer: {
        position: "absolute", left: 0, right: 0, bottom: 0,
        flexDirection: "row", alignItems: "center", justifyContent: "space-between", gap: 12,
        paddingHorizontal: 16, paddingTop: 12,
        backgroundColor: colors.surface, borderTopWidth: 1, borderTopColor: colors.border,
    },
    footerLabel: { fontSize: fontSize.xs, color: colors.textMuted, ...fontStyle.regular },
    footerTotal: { fontSize: fontSize.md, color: colors.text, ...fontStyle.bold },
})
