import React, { useState } from "react"
import OrderService from "@/services/order.service"
import Input from "../atoms/Input"
import Button from "../atoms/Button"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { Modal, Platform, Pressable, ScrollView, StyleSheet, Text, View } from "react-native"
import { colors, fontSize, fontStyle, screenPadding } from "@/styles/theme"
import { AirbnbRating } from "react-native-ratings"
import AntDesign from "@expo/vector-icons/AntDesign"

interface Props {
    orderId: number | null
    open: boolean
    closeModal: () => void
}

const EvaluateModal = ({ orderId, open, closeModal }: Props) => {
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

    // Avaliação NUNCA bloqueia: pular só fecha (registra "ignorado" para não insistir).
    // Enviar exige a nota, mas sem Alert nativo — o botão fica desabilitado até ter
    // estrela, e a mensagem é truncada em 140 em vez de barrar.
    const podeEnviar = rating > 0 && !isPending

    const pular = () => {
        if (!orderId) {
            closeModal()
            return
        }
        mutate({ ignorado: true })
    }

    const enviar = () => {
        if (!orderId || !rating) return
        mutate({ rating, mensagem: msg.slice(0, 140) })
    }

    return (
        <Modal visible={open} animationType="slide" transparent onRequestClose={pular}>
            {/* Apresentação em "bottom sheet": o fundo escurecido fecha (= pular). */}
            <Pressable style={styles.backdrop} onPress={pular} />
            <View style={styles.sheet}>
                <View style={styles.grabber} />

                <View style={styles.header}>
                    <Text style={styles.title}>Como foi sua entrega?</Text>
                    <Pressable onPress={pular} hitSlop={12} style={styles.closeBtn}>
                        <AntDesign name="close" size={22} color={colors.textMuted} />
                    </Pressable>
                </View>

                <Text style={styles.subtitle}>
                    Leva 5 segundos e ajuda a melhorar. Se preferir, é só pular.
                </Text>

                <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{ paddingBottom: 8 }}>
                    <View style={styles.ratingBox}>
                        <AirbnbRating
                            count={5}
                            reviews={["Ruim", "Regular", "Bom", "Muito bom", "Excelente"]}
                            size={42}
                            defaultRating={0}
                            selectedColor={colors.primary}
                            reviewColor={colors.primary}
                            onFinishRating={(rat: any) => setRating(rat)}
                        />
                    </View>

                    <Text style={styles.label}>Quer deixar um comentário? (opcional)</Text>
                    <Input
                        multiline
                        value={msg}
                        onChangeText={(text) => setMsg(text)}
                        textStyle={{ height: 64 }}
                        placeholder="Conte o que achou…"
                    />
                </ScrollView>

                <View style={styles.actions}>
                    <Button
                        uppercase={false}
                        title="Enviar avaliação"
                        onPress={enviar}
                        disabled={!podeEnviar}
                    />
                    <Button type="clear" uppercase={false} title="Agora não" onPress={pular} disabled={isPending} />
                </View>
            </View>
        </Modal>
    )
}

const styles = StyleSheet.create({
    backdrop: {
        ...StyleSheet.absoluteFillObject,
        backgroundColor: "rgba(0,0,0,0.45)",
    },
    sheet: {
        position: "absolute",
        left: 0,
        right: 0,
        bottom: 0,
        maxHeight: "88%",
        backgroundColor: colors.surface,
        borderTopLeftRadius: 24,
        borderTopRightRadius: 24,
        paddingHorizontal: screenPadding.horizontal,
        paddingTop: 10,
        paddingBottom: Platform.select({ ios: 34, default: 20 }),
    },
    grabber: {
        alignSelf: "center",
        width: 44,
        height: 5,
        borderRadius: 999,
        backgroundColor: colors.border,
        marginBottom: 12,
    },
    header: {
        flexDirection: "row",
        alignItems: "center",
        justifyContent: "space-between",
    },
    title: {
        fontSize: fontSize.lg,
        color: colors.text,
        ...fontStyle.bold,
    },
    closeBtn: {
        padding: 4,
    },
    subtitle: {
        fontSize: fontSize.sm,
        color: colors.textMuted,
        marginTop: 4,
        marginBottom: 8,
        ...fontStyle.regular,
    },
    ratingBox: {
        alignItems: "center",
        paddingVertical: 14,
    },
    label: {
        fontSize: fontSize.sm,
        color: colors.text,
        marginBottom: 8,
        ...fontStyle.medium,
    },
    actions: {
        paddingTop: 12,
        gap: 4,
    },
})

export default EvaluateModal
