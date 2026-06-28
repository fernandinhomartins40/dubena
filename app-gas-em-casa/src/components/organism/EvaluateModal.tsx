import React, { useState } from "react"
import OrderService from "@/services/order.service"
import OrderItems from "../molecules/OrderItems"
import Input from "../atoms/Input"
import Button from "../atoms/Button"
import IosBackButton from "../atoms/IosBackButton"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { Alert, Modal, Platform, ScrollView, StyleSheet, Text, View } from "react-native"
import { colors, fontSize, fontStyle, screenPadding } from "@/styles/theme"
import { AirbnbRating } from "react-native-ratings"
import AntDesign from "@expo/vector-icons/AntDesign"
import IconButton from "../atoms/IconButton"

interface Props {
    orderId: number | null
    open: boolean
    closeModal: () => void
}

const EvaluateModal = ({ orderId, open, closeModal }: Props) => {
    const { data: order } = useQuery({
        queryKey: ["order-eval", orderId],
        queryFn: () => OrderService.Track(orderId!),
        enabled: !!orderId,
    })
    const queryClient = useQueryClient()
    const { mutate, isPending } = useMutation({
        mutationFn: (payload: { rating?: number; mensagem?: string; ignorado?: boolean }) =>
            OrderService.Evaluate(orderId!, payload),
        onSuccess: () => {
            closeModal()
        },
        onError: (err) => {
            console.error(err)
        },
        onSettled: async () => {
            await queryClient.invalidateQueries({ queryKey: ["order-history"] })
        },
    })
    const [rating, setRating] = useState<number>(0)
    const [msg, setMsg] = useState("")

    const evaluateOrder = (ignore: boolean = false) => {
        if (!orderId) return

        if (!ignore) {
            if (msg.length > 140) {
                Alert.alert("Seu comentário é muito grande (máx. 140), tente novamente.")
                return
            }
            if (!rating) {
                Alert.alert("Informe sua avaliação.")
                return
            }
            mutate({ rating, mensagem: msg })
            return
        }

        mutate({ ignorado: true })
    }

    const renderProducts = () => {
        if (!order?.itens) return null
        return <OrderItems products={order.itens} totalPrice={String(order.valor_venda ?? "")} />
    }

    return (
        <Modal visible={open} animationType="fade" onRequestClose={() => evaluateOrder(true)}>
            <ScrollView
                style={{ flex: 1, marginTop: Platform.select({ ios: 55, default: 10 }) }}
                contentContainerStyle={{ paddingBottom: 25 }}
            >
                <View
                    style={{
                        flexDirection: "row",
                        marginBottom: 50,
                        alignItems: "center",
                        paddingTop: 8,
                    }}
                >
                    <View style={{ flexDirection: "column", flex: 1, justifyContent: "center" }}>
                        <View style={{ flexDirection: "row", alignItems: "center" }}>
                            <View style={{ width: "30%" }}>
                                <IosBackButton onPress={() => evaluateOrder(true)} />
                            </View>
                            <View style={{ flex: 1 }}>
                                <Text style={styles.title}>Avaliar Pedido</Text>
                            </View>
                        </View>
                    </View>

                    {Platform.OS === "android" ? (
                        <View style={{ paddingRight: 8 }}>
                            <IconButton
                                noBackground
                                disabled={isPending}
                                height={50}
                                width={50}
                                onPress={() => evaluateOrder(true)}
                            >
                                <AntDesign name="close" size={24} color="black" />
                            </IconButton>
                        </View>
                    ) : (
                        ""
                    )}
                </View>

                <View
                    style={{ flexDirection: "column", paddingHorizontal: screenPadding.horizontal }}
                >
                    <View style={[styles.box, { flexDirection: "column", padding: 12 }]}>
                        <View>
                            <Text
                                style={{
                                    color: colors.textMuted,
                                    fontSize: fontSize.sm,
                                    paddingBottom: 8,
                                    ...fontStyle.regular,
                                }}
                            >
                                Pedido a ser Avaliado
                            </Text>
                        </View>
                        <View>{renderProducts()}</View>
                    </View>

                    <View style={{ flexDirection: "column", paddingTop: 20 }}>
                        <View>
                            <Text
                                style={{
                                    fontSize: fontSize.base,
                                    textAlign: "center",
                                    ...fontStyle.semiBold,
                                }}
                            >
                                O que achou da sua experiência?
                            </Text>
                        </View>

                        <View style={{ paddingVertical: 10 }}>
                            <Text
                                style={{
                                    fontSize: fontSize.sm,
                                    textAlign: "center",
                                    color: colors.textMuted,
                                    ...fontStyle.regular,
                                }}
                            >
                                Sua opinião é muito importante! Avalie o pedido e ajude-nos a
                                entregar o melhor para você.
                            </Text>
                        </View>

                        <View style={[styles.box, { flexDirection: "column" }]}>
                            <View>
                                <AirbnbRating
                                    count={5}
                                    reviews={[
                                        "Nada satisfeito",
                                        "Pouco Satisfeito",
                                        "Bom",
                                        "Muito Bom",
                                        "Excelente",
                                    ]}
                                    size={45}
                                    defaultRating={0}
                                    selectedColor={colors.primary}
                                    reviewColor={colors.primary}
                                    onFinishRating={(rat: any) => {
                                        setRating(rat)
                                    }}
                                />
                            </View>

                            <View style={{ paddingTop: 15 }}>
                                <Text style={{ fontSize: fontSize.sm, ...fontStyle.regular }}>
                                    Deixe sua sugestão
                                </Text>
                            </View>

                            <View style={{ paddingTop: 8 }}>
                                <Input
                                    multiline
                                    value={msg}
                                    onChangeText={(text) => setMsg(text)}
                                    textStyle={{ height: 60 }}
                                    placeholder="Quero sugerir.."
                                />
                            </View>

                            <View style={{ paddingTop: 12 }}>
                                <Button
                                    uppercase={false}
                                    title="Enviar Avaliação"
                                    onPress={() => evaluateOrder()}
                                    disabled={isPending}
                                />
                            </View>
                        </View>
                    </View>
                </View>
            </ScrollView>
        </Modal>
    )
}

const styles = StyleSheet.create({
    title: {
        textAlign: "left",
        fontSize: fontSize.lg,
        ...fontStyle.semiBold,
    },
    box: {
        flexDirection: "column",
        padding: 12,
        marginVertical: 5,
        borderRadius: 16,
        boxShadow: "0px 4px 40px 0px #39253D29",
        marginHorizontal: 8,
    },
})

export default EvaluateModal
