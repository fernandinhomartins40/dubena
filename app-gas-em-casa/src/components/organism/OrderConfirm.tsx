import React, { forwardRef, useMemo } from "react"
import { BottomSheetModal, BottomSheetScrollView } from "@gorhom/bottom-sheet"
import { Alert, StyleSheet, Text, View } from "react-native"
import CartItems from "../templates/CartItems"
import useFlashStore from "@/store/flashStore"
import Fontisto from "@expo/vector-icons/Fontisto"
import { colors, fontStyle, screenPadding } from "@/styles/theme"
import PaymentMethod from "./PaymentMethod"
import Button from "../atoms/Button"
import { useMutation } from "@tanstack/react-query"
import OrderService from "@/services/order.service"
import { useRouter } from "expo-router"
import useBottomSheetBackHandler from "@/hooks/useBottomSheetBackHandler"

type Ref = BottomSheetModal

interface Props {
    onPressPayment: () => void
}

/**
 * OrderConfirm (F3) — revisão e finalização. Total/desconto vêm da COTAÇÃO do servidor
 * (nunca calculados aqui). Cria o pedido com condicaopagamento_id + cupom; em seguida
 * gera o PIX (F4) e leva para a tela de pagamento. (Cartão online = F5.)
 */
const OrderConfirm = forwardRef<Ref, Props>(({ onPressPayment }, ref) => {
    const snapPoints = useMemo(() => ["50%", "85%"], [])
    const { handleSheetPositionChange } = useBottomSheetBackHandler(
        ref as React.RefObject<BottomSheetModal>,
    )
    const router = useRouter()
    const {
        condicao,
        cotacao,
        cupom,
        gasdopovo,
        appConfig,
        cartItensPayload,
        setPendingOrder,
        setPixOrder,
    } = useFlashStore()
    const formatter = new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" })

    const { mutate: createOrder, isPending } = useMutation({
        mutationFn: OrderService.CreateOrder,
        onSuccess: async (pedido) => {
            try {
                // Gera a cobrança PIX do pedido e vai para a tela de pagamento (F4).
                const pix = await OrderService.GerarPix(pedido.id)
                setPixOrder({ id: pedido.id, pix } as any)
                router.replace("/(auth)/pix")
            } catch {
                // Sem PIX (ex.: pagamento na entrega): acompanha o pedido.
                setPendingOrder({ id: pedido.id } as any)
                router.replace("/(auth)/track")
            }
        },
        onError: (error: any) => {
            Alert.alert("Oops..", error?.message ?? "Não foi possível gerar seu pedido.")
        },
    })

    const confirm = () => {
        if (!condicao) {
            Alert.alert("Atenção", "Selecione uma forma de pagamento.")
            return
        }

        createOrder({
            // pedidosituacao_id é resolvido pelo servidor (1ª PENDENTE do grupo).
            condicaopagamento_id: condicao.id,
            codigo_cupom: cupom,
            gasdopovo,
            itens: cartItensPayload(),
        })
    }

    const total = cotacao?.total ?? 0
    const subtotal = cotacao?.subtotal ?? 0
    const desconto = cotacao?.desconto ?? 0

    return (
        <BottomSheetModal
            index={2}
            ref={ref}
            snapPoints={snapPoints}
            onChange={handleSheetPositionChange}
        >
            <BottomSheetScrollView style={{ flex: 1, flexDirection: "column" }}>
                <Text style={[styles.title, fontStyle.semiBold]}>Finalizar Pedido</Text>

                <View style={{ flexDirection: "column", padding: screenPadding.horizontal, gap: 4 }}>
                    <View style={{ flexDirection: "row", alignItems: "center", gap: 6 }}>
                        <View style={styles.iconBox}>
                            <Fontisto name="flash" size={24} color={colors.primary} />
                        </View>
                        <Text style={{ fontSize: 18, ...fontStyle.semiBold }}>Revisar Itens</Text>
                    </View>

                    <View style={{ paddingTop: 8 }}>
                        <CartItems
                            isGasPovo={gasdopovo}
                            deliveryTax={appConfig?.frete_gaspovo ?? null}
                        />
                    </View>

                    <View style={{ flexDirection: "column", paddingTop: 10, gap: 4 }}>
                        {desconto > 0 && (
                            <>
                                <View style={styles.row}>
                                    <Text style={fontStyle.regular}>Subtotal:</Text>
                                    <Text style={{ color: colors.textMuted, ...fontStyle.regular }}>
                                        {formatter.format(subtotal)}
                                    </Text>
                                </View>
                                <View style={styles.row}>
                                    <Text style={fontStyle.regular}>Desconto:</Text>
                                    <Text style={{ color: colors.primary, ...fontStyle.regular }}>
                                        - {formatter.format(desconto)}
                                    </Text>
                                </View>
                            </>
                        )}
                        <View style={styles.row}>
                            <Text style={fontStyle.regular}>Total:</Text>
                            <Text
                                style={{ fontSize: 18, color: colors.primary, ...fontStyle.semiBold }}
                            >
                                {formatter.format(total)}
                            </Text>
                        </View>
                    </View>
                </View>

                <View style={{ alignItems: "center" }}>
                    <PaymentMethod condicao={condicao} onPress={onPressPayment} />
                </View>

                <View style={{ paddingHorizontal: screenPadding.horizontal, marginTop: 18 }}>
                    <Button disabled={isPending} title="Finalizar Pedido" onPress={confirm} />
                </View>
            </BottomSheetScrollView>
        </BottomSheetModal>
    )
})

const styles = StyleSheet.create({
    title: { textAlign: "center", fontSize: 20, fontWeight: "bold" },
    iconBox: { padding: 4, backgroundColor: colors.primaryMuted, borderRadius: 6 },
    row: { flexDirection: "row", justifyContent: "space-between", alignItems: "center" },
})

export default OrderConfirm
