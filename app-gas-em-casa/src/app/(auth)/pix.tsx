import Button from "@/components/atoms/Button"
import Input from "@/components/atoms/Input"
import useFlashStore from "@/store/flashStore"
import { colors, defaultStyles, fontSize, screenPadding } from "@/styles/theme"
import Clipboard from "@react-native-clipboard/clipboard"
import React, { useEffect } from "react"
import { Platform, StyleSheet, Text, View } from "react-native"
import FastImage from "react-native-fast-image"
import Toast from "react-native-toast-message"
import Feather from "@expo/vector-icons/Feather"
import IconButton from "@/components/atoms/IconButton"
import { useQuery, useQueryClient } from "@tanstack/react-query"
import OrderService from "@/services/order.service"
import { useRouter } from "expo-router"
import useRefetchOnAppFocus from "@/hooks/useRefetchOnAppFocus"

const Pix = () => {
    const { pixOrder, setPixOrder, clearCart } = useFlashStore()
    const router = useRouter()
    const queryClient = useQueryClient()
    const { data: status } = useQuery({
        queryKey: ["pix-status", pixOrder?.id],
        queryFn: () => OrderService.PixStatus(pixOrder!.id),
        enabled: !!pixOrder,
        refetchInterval: 15 * 1_000,
    })
    useRefetchOnAppFocus("pix-status")

    useEffect(() => {
        if (status?.pago) {
            setPixOrder(null)
            clearCart()
            queryClient.invalidateQueries({ queryKey: ["order-history"], refetchType: "all" })
            router.replace("/(auth)/track")
        }
    }, [status])

    const handleClick = () => {
        if (pixOrder === null) return

        Clipboard.setString(pixOrder.pix.copia_e_cola)

        Toast.show({
            type: "success",
            text1: "Sucesso",
            text1Style: {
                fontSize: 18,
            },
            text2: "PIX copiado",
            text2Style: {
                fontSize: 16,
            },
        })
    }

    if (pixOrder === null) return null

    return (
        <View style={defaultStyles.container}>
            <View style={styles.container}>
                <View>
                    <Text style={[styles.title, { fontWeight: 600 }]}>Pagamento</Text>
                </View>

                <View>
                    <Text style={{ textAlign: "center", fontSize: fontSize.base, paddingTop: 35 }}>
                        Aguardando pagamento PIX
                    </Text>
                </View>

                <View
                    style={{ justifyContent: "center", alignItems: "center", marginVertical: 15 }}
                >
                    {pixOrder.pix.qrcode ? (
                        <FastImage
                            source={{ uri: `data:image/png;base64,${pixOrder.pix.qrcode}` }}
                            style={{ width: 250, height: 250 }}
                        />
                    ) : null}
                </View>

                <View style={{ marginVertical: 15 }}>
                    <Text style={{ fontSize: fontSize.sm, textAlign: "center" }}>
                        Escaneie o QR Code ou pague copiando e colando o seguinte código:
                    </Text>
                </View>

                <View style={{ marginVertical: 15 }}>
                    <Input
                        disabled
                        noDisabledStyle
                        value={pixOrder.pix.copia_e_cola}
                        inputSufix={
                            <IconButton
                                width={45}
                                height={45}
                                style={{ padding: 10 }}
                                onPress={handleClick}
                            >
                                <Feather name="copy" size={16} color={colors.primary} />
                            </IconButton>
                        }
                    />
                </View>

                <View style={{ marginVertical: 15 }}>
                    <Text
                        style={{
                            fontSize: fontSize.xs,
                            color: colors.textMuted,
                            textAlign: "center",
                        }}
                    >
                        Você tem até 5 minutos para realizar o pagamento. Caso o prazo seja
                        excedido, o pedido será cancelado.
                    </Text>
                </View>

                <View>
                    <Button title="Copiar" onPress={handleClick} />
                </View>
            </View>
        </View>
    )
}

const styles = StyleSheet.create({
    container: {
        flexDirection: "column",
        paddingTop: Platform.select({ ios: 50, default: 34 }),
        paddingHorizontal: screenPadding.horizontal,
    },
    title: {
        fontSize: fontSize.lg,
        textAlign: "center",
    },
})

export default Pix
