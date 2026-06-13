import React from "react"
import Button from "@/components/atoms/Button"
import LoaderSimple from "@/components/atoms/LoaderSimple"
import Header from "@/components/molecules/Header"
import OnlinePayment from "@/components/organism/OnlinePayment"
import OrderConfirm from "@/components/organism/OrderConfirm"
import PaymentMethod from "@/components/organism/PaymentMethod"
import PaymentMethodSheet from "@/components/organism/PaymentMethodSheet"
import ProductList from "@/components/organism/ProductList"
import CartItems from "@/components/templates/CartItems"
import ErrorView from "@/components/templates/ErrorView"
import { PaymentTypes } from "@/constants/app"
import { BackgroundImgUri } from "@/constants/images"
import OrderService from "@/services/order.service"
import useFlashStore from "@/store/flashStore"
import { colors, defaultStyles, fontSize, fontStyle } from "@/styles/theme"
import { Payment } from "@/types/types"
import { BottomSheetModal } from "@gorhom/bottom-sheet"
import { useQuery } from "@tanstack/react-query"
import { useEffect, useRef } from "react"
import { Alert, ImageBackground, Platform, ScrollView, StyleSheet, Text, View } from "react-native"
import { useSafeAreaInsets } from "react-native-safe-area-context"
import { StatusBar } from "expo-status-bar"
import * as NavigationBar from "expo-navigation-bar"
import useAppStore from "@/store/appStore"
import ImageBanner from "@/components/atoms/ImageBanner"
import TestNotificationButton from "@/components/atoms/TestNotification"
import { useRouter } from "expo-router"

const HomeScreen = () => {
    const { user } = useAppStore()
    const { store, cart, payment, rebuyOrder, setPayment, addToCart, setRebuyOrder, clearCart } =
        useFlashStore()
    const { top, bottom } = useSafeAreaInsets()
    const router = useRouter()
    const {
        data: root,
        isLoading,
        isError,
    } = useQuery({
        queryKey: ["root"],
        queryFn: () => OrderService.GetInitial(store?.revenda_id, user?.id),
        enabled: !!(store && store?.revenda_id),
    })
    const formatter = new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" })
    const paymentMethodRef = useRef<BottomSheetModal>(null)
    const onlinePaymentRef = useRef<BottomSheetModal>(null)
    const confirmRef = useRef<BottomSheetModal>(null)

    if (Platform.OS === "android") NavigationBar.setButtonStyleAsync("dark")

    useEffect(() => {
        if (!root) return
        let lastItem = root.product.find((item) => item.descricao.includes("13"))

        setPayment(root.payment[0])

        if (typeof lastItem !== "undefined" && !(lastItem.id in cart.products)) {
            addToCart(lastItem)
        }
    }, [root])

    useEffect(() => {
        if (!root || !rebuyOrder) return

        clearCart()

        let products = root.product
        for (const rebProd of rebuyOrder) {
            const prod = products.find((it) => it.id == rebProd.id)

            if (!rebProd.quantity || !prod) break

            for (let i = 0; i < rebProd.quantity; i++) {
                addToCart(prod)
            }
        }

        setRebuyOrder(null)

        paymentMethodRef.current?.present()
    }, [root, rebuyOrder])

    const handleOnPress = () => {
        paymentMethodRef?.current?.present()
    }

    const handleOnlineInfo = () => {
        onlinePaymentRef?.current?.present()
    }

    const handleOnSetPayment = (pay: Payment) => {
        setPayment(pay)

        if (pay.tipo === PaymentTypes.Online) {
            onlinePaymentRef?.current?.present()
        }
    }

    const handleConfirm = () => {
        if (user?.gasdopovo && !store?.gaspovoativado) {
            Alert.alert(
                "Atenção",
                'O Programa Gás do Povo não está disponível no momento. Por favor, desative a opção "Gás do Povo" para continuar comprando.',
            )
            router.navigate("/(auth)/(tabs)/perfil?disable=1")
            return
        }

        confirmRef.current?.present()
    }

    const loadingState = () => <LoaderSimple />

    const renderProductList = () => {
        return (
            <View style={{ maxHeight: 300 }}>
                <ProductList products={root?.product} />
            </View>
        )
    }

    const renderProducts = () => (
        <>
            <View style={{ paddingTop: 8 }}>
                <Text
                    style={[
                        {
                            textAlign: "center",
                            fontSize: fontSize.base,
                        },
                        fontStyle.semiBold,
                    ]}
                >
                    Novo Pedido
                </Text>
            </View>

            {renderProductList()}

            <View
                style={{
                    flexDirection: "column",
                    justifyContent: "space-evenly",
                    gap: 30,
                }}
            >
                <View
                    style={{
                        display: "flex",
                        flexDirection: "row",
                        justifyContent: "space-between",
                        paddingHorizontal: 25,
                    }}
                >
                    <View>
                        <Text style={[styles.textTitles, fontStyle.regular]}>
                            Total: {"\n"}
                            <Text
                                style={{
                                    fontSize: 26,
                                    color: colors.primary,
                                    ...fontStyle.semiBold,
                                }}
                            >
                                {formatter.format(cart.total)}
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

                {root?.payment && (
                    <View style={{ alignItems: "center" }}>
                        <PaymentMethod payment={payment} onPress={handleOnPress} />
                    </View>
                )}

                <View style={{ paddingHorizontal: 25 }}>
                    <Button title="Confirmar" disabled={cart.total <= 0} onPress={handleConfirm} />

                    {/* <TestNotificationButton /> */}
                </View>
            </View>
        </>
    )

    if (isError) {
        return <ErrorView message={"Houve um erro desconhecido, por favor contate a revenda."} />
    }

    return (
        <View style={defaultStyles.container}>
            <StatusBar style="inverted" />

            <ImageBackground
                source={{ uri: BackgroundImgUri }}
                style={[defaultStyles.image, { paddingTop: top }]}
            >
                <View
                    style={{
                        flex: 1,
                        display: "flex",
                        flexDirection: "column",
                    }}
                >
                    <Header />

                    <View style={[styles.container]}>
                        <ScrollView
                            contentContainerStyle={{ paddingBottom: 70 + bottom }}
                            showsVerticalScrollIndicator={false}
                        >
                            {!isLoading ? renderProducts() : loadingState()}
                        </ScrollView>
                    </View>
                </View>
            </ImageBackground>

            <PaymentMethodSheet
                ref={paymentMethodRef}
                methods={root?.payment}
                selectedId={payment?.id}
                setPayment={handleOnSetPayment}
            />

            <OnlinePayment ref={onlinePaymentRef} />

            <OrderConfirm
                ref={confirmRef}
                onPressPayment={handleOnPress}
                showOnlinePayment={handleOnlineInfo}
            />

            <ImageBanner />
        </View>
    )
}

const styles = StyleSheet.create({
    flexColumn: {
        display: "flex",
        flexDirection: "column",
        justifyContent: "space-evenly",
    },
    container: {
        flex: 1,
        backgroundColor: colors.white,
        height: "100%",
        marginTop: 20,
        borderRadius: 30,
        justifyContent: "flex-start",
        overflow: "hidden",
    },
    textTitles: {
        fontSize: 16,
        color: colors.textMuted,
    },
})

export default HomeScreen
