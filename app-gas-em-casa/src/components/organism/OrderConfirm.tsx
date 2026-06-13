import React from "react"
import { BottomSheetModal, BottomSheetScrollView } from "@gorhom/bottom-sheet"
import { forwardRef, useMemo } from "react"
import { Alert, StyleSheet, Text, View } from "react-native"
import CartItems from "../templates/CartItems"
import useFlashStore from "@/store/flashStore"
import Fontisto from "@expo/vector-icons/Fontisto"
import { colors, fontSize, fontStyle, screenPadding } from "@/styles/theme"
import FastImage from "react-native-fast-image"
import PaymentMethod from "./PaymentMethod"
import Button from "../atoms/Button"
import { CouponType, PaymentTypes } from "@/types/types"
import MaterialIcons from "@expo/vector-icons/MaterialIcons"
import useAppStore from "@/store/appStore"
import { useMutation, useQuery } from "@tanstack/react-query"
import OrderService from "@/services/order.service"
import { useRouter } from "expo-router"
import { APP } from "@/constants/app"
import useBottomSheetBackHandler from "@/hooks/useBottomSheetBackHandler"

type Ref = BottomSheetModal

interface Props {
    onPressPayment: () => void
    showOnlinePayment: () => void
}

const OrderConfirm = forwardRef<Ref, Props>(({ onPressPayment, showOnlinePayment }, ref) => {
    const snapPoints = useMemo(() => ["50%", "85%"], [])
    const { handleSheetPositionChange } = useBottomSheetBackHandler(
        ref as React.RefObject<BottomSheetModal>,
    )
    const isDebug = APP.debug
    const router = useRouter()
    const { user, onlineTries, tryPayOnline, voidOnlineTries } = useAppStore()
    const {
        store,
        cart: { products, total, discountTotal, coupon: cartCoupon },
        payment,
        cardInfo,
        applyCoupon,
        setPendingOrder,
        setPixOrder,
    } = useFlashStore()
    const firstKey: any = Object.keys(products)[0]
    const first = products[firstKey]
    const formatter = new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" })
    const { data: coupon } = useQuery({
        queryKey: ["coupon"],
        queryFn: () => OrderService.GetCoupon(user?.id),
        enabled: !!user?.id,
    })
    const {
        data: mutatedData,
        mutate,
        isPending,
    } = useMutation({
        mutationFn: OrderService.VerifyCoupon,
        onSuccess: (data) => {
            applyCoupon(data)
        },
    })
    const { mutate: createOrder, isPending: isSubmitting } = useMutation({
        mutationFn: OrderService.CreateOrder,
        onSuccess: (data) => {
            if (payment?.tipo === PaymentTypes.Online) voidOnlineTries()

            if ("rejection" in data && data.rejection == "REPORTABLE" && "msg" in data) {
                Alert.alert("Oops..", String(data.msg))

                return
            }

            if ("status" in data && (data.status === "NOK" || data.status === "OPS")) {
                Alert.alert("Oops..", "Algo deu errado com a criação do pedido.")

                return
            }

            let orderRequest = "original" in data ? data.original : data

            if (
                payment?.tipo !== PaymentTypes.PIX &&
                "status" in orderRequest &&
                orderRequest.status === "OK"
            ) {
                setPendingOrder(orderRequest.data)

                router.replace("/(auth)/track")

                return
            }

            if (payment?.tipo === PaymentTypes.PIX && "pix" in orderRequest) {
                setPixOrder(orderRequest as any)

                router.replace("/(auth)/pix")

                return
            }
        },
        onError: (error) => {
            console.error(error)
            let rejection = "rejection" in error ? error.rejection : ""

            if (payment?.tipo === PaymentTypes.Online && rejection === "ERRO_PAGAMENTO") {
                tryPayOnline()
            }

            const isReportable =
                rejection == "REPORTABLE" ||
                rejection == "ERRO_PAGAMENTO" ||
                error.message.indexOf("fechada") > -1 ||
                error.message.indexOf("pedido pendente") > -1

            if (isReportable && error.message != undefined) {
                Alert.alert(error.message)
            } else {
                Alert.alert("Ocorreu um erro desconhecido ao tentar gerar seu pedido (1)..")
            }
        },
    })

    if (!first) return null

    const verifyCoupon = () => {
        mutate({ codigo_cupom: coupon?.codigo, cliente_id: user?.id })
    }

    const validated = () => {
        if (payment && payment.tipo != PaymentTypes.Online) return true

        for (const key in cardInfo) {
            if (Object.prototype.hasOwnProperty.call(cardInfo, key)) {
                const info = (cardInfo as any)[key]

                if (info === "") {
                    Alert.alert("Por favor, preencha todas as informações do cartão.")
                    return false
                }
            }
        }

        return true
    }

    const processProducts = () => {
        let prods = []
        for (const key in products) {
            if (!Object.prototype.hasOwnProperty.call(products, key)) continue

            const prod = products[key]

            prods.push({
                quantidade: prod.quantity,
                precovendaunitario: prod.unitPrice,
                precovendatotal: prod.total,
                produto_id: prod.id,
            })
        }

        return prods
    }

    const confirm = () => {
        if (!validated()) {
            showOnlinePayment()
            return
        }

        if (payment?.tipo === PaymentTypes.Online && onlineTries) {
            const anHourAgo = Date.now() - 3600000

            if (onlineTries.tries > 10 && onlineTries.date > anHourAgo && !isDebug) {
                Alert.alert(
                    "Você ultrapassou o número máximo de tentativas para realizar o pagamento online, tente novamente mais tarde",
                )
                return
            }

            if (onlineTries.date < anHourAgo) {
                voidOnlineTries()
            }
        }
        let data = new Date()
        const date =
            data.getFullYear().toString().padStart(2, "0") +
            "-" +
            data.getMonth().toString().padStart(2, "0") +
            "-" +
            data.getDay().toString().padStart(2, "0") +
            " " +
            data.getHours().toString().padStart(2, "0") +
            ":" +
            data.getMinutes().toString().padStart(2, "0") +
            ":" +
            data.getSeconds().toString().padStart(2, "0")

        const payloadProducts = processProducts()

        let discountPrice = 0

        if (discountTotal) {
            discountPrice = total - (discountTotal ?? 0)
        }

        const payload = {
            condicaopagamento_id: payment?.id,
            cliente_id: user?.id,
            endereco_id: user?.enderecopadrao_id,
            produtosJson: JSON.stringify(payloadProducts),
            datahoraprevisao: date,
            revenda_id: store?.revenda_id,
            codigo_cupom: cartCoupon?.code,
            desconto_cupons: discountPrice,
            pagamento: payment?.tipo == PaymentTypes.Online ? cardInfo : null,
        }

        createOrder(payload)
    }

    const getTotal = () => {
        let extra: number = 0

        if (user?.gasdopovo && store?.valorfretegp) {
            extra = store.valorfretegp
        }

        if (discountTotal) {
            return (
                <>
                    <View
                        style={{
                            flexDirection: "row",
                            justifyContent: "space-between",
                            alignItems: "center",
                        }}
                    >
                        <Text style={fontStyle.regular}>Total:</Text>
                        <Text
                            style={{
                                fontSize: 16,
                                color: colors.textMuted,
                                ...fontStyle.regular,
                            }}
                        >
                            {formatter.format(total)}
                        </Text>
                    </View>
                    <View
                        style={{
                            flexDirection: "row",
                            justifyContent: "space-between",
                            alignItems: "center",
                        }}
                    >
                        <Text style={fontStyle.regular}>Total com Cupom:</Text>
                        <Text
                            style={{
                                fontSize: 18,
                                color: colors.primary,
                                ...fontStyle.semiBold,
                            }}
                        >
                            {formatter.format(discountTotal)}
                        </Text>
                    </View>
                </>
            )
        }

        return (
            <View
                style={{
                    flexDirection: "row",
                    justifyContent: "space-between",
                    alignItems: "center",
                }}
            >
                <Text style={fontStyle.regular}>Total:</Text>
                <Text style={{ fontSize: 18, color: colors.primary, ...fontStyle.semiBold }}>
                    {formatter.format(total + extra)}
                </Text>
            </View>
        )
    }

    const renderCoupon = () => {
        if (!coupon || mutatedData || cartCoupon != null || user?.gasdopovo) return null

        let desc = ""

        if (coupon.tipo === CouponType.Percentage) {
            desc = formatter.format(Number(coupon.valor))
        } else {
            let newFormatter = new Intl.NumberFormat("pt-br", { minimumFractionDigits: 2 })
            desc = newFormatter.format(Number(coupon.valor)) + " %"
        }

        desc += " de desconto!!"

        return (
            <View style={{ padding: screenPadding.horizontal, flexDirection: "column" }}>
                <View style={{ flexDirection: "row", alignItems: "center" }}>
                    <View
                        style={{
                            padding: 4,
                            backgroundColor: colors.primaryMuted,
                            borderRadius: 6,
                        }}
                    >
                        <MaterialIcons name="discount" size={18} color={colors.primary} />
                    </View>
                    <Text style={{ fontSize: fontSize.sm, ...fontStyle.semiBold }}>
                        Cupom disponível
                    </Text>
                </View>

                <View style={{ flexDirection: "row", alignItems: "center", paddingTop: 8 }}>
                    <View
                        style={{
                            padding: 8,
                            backgroundColor: colors.primaryMuted,
                            borderRadius: 6,
                        }}
                    >
                        <Text style={{ fontSize: fontSize.base, ...fontStyle.semiBold }}>
                            {coupon.codigo}
                        </Text>
                    </View>

                    <View>
                        <Button
                            disabled={isPending}
                            type="clear"
                            textStyle={{ fontSize: fontSize.sm, color: colors.primary }}
                            title="Aplicar"
                            onPress={verifyCoupon}
                        />
                    </View>
                </View>

                <View>
                    <Text
                        style={{
                            fontStyle: "italic",
                            fontSize: fontSize.sm,
                            color: colors.textMuted,
                            ...fontStyle.semiBold,
                        }}
                    >
                        {desc}
                    </Text>
                </View>
            </View>
        )
    }

    return (
        <BottomSheetModal
            index={2}
            ref={ref}
            snapPoints={snapPoints}
            onChange={handleSheetPositionChange}
        >
            <BottomSheetScrollView style={{ flex: 1, flexDirection: "column" }}>
                <View>
                    <Text style={[styles.title, fontStyle.semiBold]}>Finalizar Pedido</Text>
                </View>

                <View
                    style={{ flexDirection: "column", padding: screenPadding.horizontal, gap: 4 }}
                >
                    <View style={{ flexDirection: "row", alignItems: "center", gap: 6 }}>
                        <View
                            style={{
                                padding: 4,
                                backgroundColor: colors.primaryMuted,
                                borderRadius: 6,
                            }}
                        >
                            <Fontisto name="flash" size={24} color={colors.primary} />
                        </View>

                        <Text style={{ fontSize: 18, ...fontStyle.semiBold }}>Revisar Itens</Text>
                    </View>

                    <View style={{ flexDirection: "row", gap: 4 }}>
                        <View
                            style={{
                                padding: screenPadding.horizontal,
                                borderColor: colors.primary,
                                borderWidth: StyleSheet.hairlineWidth,
                                borderRadius: 14,
                            }}
                        >
                            <FastImage
                                source={{ uri: first.base64Img }}
                                style={{ width: 80, height: 80 }}
                            />
                        </View>
                        <View
                            style={{
                                flexDirection: "column",
                                gap: 4,
                                justifyContent: "space-between",
                            }}
                        >
                            <View>
                                <CartItems
                                    isGasPovo={user?.gasdopovo}
                                    deliveryTax={store?.valorfretegp}
                                />
                            </View>

                            <View
                                style={{
                                    flexDirection: "column",
                                    paddingBottom: 10,
                                    paddingLeft: 8,
                                }}
                            >
                                {getTotal()}
                            </View>
                        </View>
                    </View>
                </View>

                <View style={{ alignItems: "center" }}>
                    <PaymentMethod payment={payment} onPress={onPressPayment} />
                </View>

                {renderCoupon()}

                <View style={{ paddingHorizontal: screenPadding.horizontal, marginTop: 18 }}>
                    <Button
                        disabled={isPending || isSubmitting}
                        title="Finalizar Pedido"
                        onPress={() => confirm()}
                    />
                </View>
            </BottomSheetScrollView>
        </BottomSheetModal>
    )
})

const styles = StyleSheet.create({
    title: { textAlign: "center", fontSize: 20, fontWeight: "bold" },
})

export default OrderConfirm
