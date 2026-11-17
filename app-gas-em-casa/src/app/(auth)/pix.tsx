import Button from "@/components/atoms/button"
import Input from "@/components/atoms/input"
import useFlashStore from "@/store/flashStore"
import { colors, defaultStyles, fontSize, screenPadding } from "@/styles/theme"
import Clipboard from "@react-native-clipboard/clipboard"
import React, { useEffect, useState } from "react"
import { Alert, Platform, StyleSheet, Text, View } from "react-native"
import FastImage from "react-native-fast-image"
import Toast from "react-native-toast-message"
import { Copy } from "lucide-react-native"
import IconButton from "@/components/atoms/iconbutton"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import OrderService from "@/services/order.service"
import { useRouter } from "expo-router"
import useRefetchOnAppFocus from "@/hooks/useRefetchOnAppFocus"
import { validadePix } from "@/helpers/pedidoApp"

/**
 * Tela do PIX (P2-B-04).
 *
 * Antes: sem voltar, sem gerar outro código, sem cancelar, e o texto "o pedido
 * será cancelado em 5 minutos" — falso: nenhum código cancela pedido por PIX
 * vencido (`PixService::expirarVencidas` só marca a cobrança). Quem fechava a
 * tela ficava preso num pedido PENDENTE que bloqueava qualquer pedido novo.
 *
 * Agora: a validade real do código, "Gerar novo código" quando expira (o
 * servidor emite outra cobrança — `criarCobrancaPedido` só reaproveita a ATIVA
 * e não vencida), "Acompanhar pedido" e "Cancelar pedido" (regra do servidor).
 */
const Pix = () => {
    const { pixOrder, setPixOrder, setPendingOrder } = useFlashStore()
    const router = useRouter()
    const queryClient = useQueryClient()
    const [agora, setAgora] = useState(Date.now())

    useEffect(() => {
        const t = setInterval(() => setAgora(Date.now()), 15_000)
        return () => clearInterval(t)
    }, [])

    const { data: status } = useQuery({
        queryKey: ["pix-status", pixOrder?.id],
        queryFn: () => OrderService.PixStatus(pixOrder!.id),
        enabled: !!pixOrder,
        refetchInterval: 15 * 1_000,
    })
    useRefetchOnAppFocus("pix-status")

    const irParaAcompanhamento = () => {
        if (!pixOrder) return
        setPendingOrder({ id: pixOrder.id } as any)
        setPixOrder(null)
        router.replace("/(auth)/track")
    }

    useEffect(() => {
        if (status?.pago) {
            queryClient.invalidateQueries({ queryKey: ["order-history"], refetchType: "all" })
            irParaAcompanhamento()
        }
    }, [status])

    const novoCodigo = useMutation({
        mutationFn: () => OrderService.GerarPix(pixOrder!.id),
        onSuccess: (pix) => {
            setPixOrder({ id: pixOrder!.id, pix } as any)
            setAgora(Date.now())
        },
        onError: (e: any) => Toast.show({ type: "error", text1: e?.message ?? "Não foi possível gerar um novo código." }),
    })

    const cancelar = useMutation({
        mutationFn: () => OrderService.Cancel(pixOrder!.id),
        onSuccess: () => {
            setPixOrder(null)
            queryClient.invalidateQueries({ queryKey: ["order-history"], refetchType: "all" })
            Toast.show({ type: "success", text1: "Pedido cancelado." })
            router.replace("/(auth)/(tabs)/home")
        },
        onError: (e: any) => Toast.show({ type: "error", text1: e?.message ?? "Não foi possível cancelar o pedido." }),
    })

    const confirmarCancelamento = () =>
        Alert.alert("Cancelar pedido", "Tem certeza que deseja cancelar este pedido?", [
            { text: "Não", style: "cancel" },
            { text: "Sim, cancelar", style: "destructive", onPress: () => cancelar.mutate() },
        ])

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

    const validade = validadePix(pixOrder.pix.expira_em, agora)
    const expirado = validade.expirado || status?.situacao === "EXPIRADA"

    return (
        <View style={defaultStyles.container}>
            <View style={styles.container}>
                <View>
                    <Text style={[styles.title, { fontWeight: 600 }]} accessibilityRole="header">
                        Pagamento
                    </Text>
                </View>

                <View>
                    <Text style={{ textAlign: "center", fontSize: fontSize.base, paddingTop: 35 }}>
                        {expirado ? "Código PIX expirado" : "Aguardando pagamento PIX"}
                    </Text>
                </View>

                {!expirado && (
                    <>
                        <View style={{ justifyContent: "center", alignItems: "center", marginVertical: 15 }}>
                            {pixOrder.pix.qrcode ? (
                                <FastImage
                                    source={{ uri: `data:image/png;base64,${pixOrder.pix.qrcode}` }}
                                    style={{ width: 250, height: 250 }}
                                    accessibilityLabel="QR Code do PIX"
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
                                        accessibilityRole="button"
                                        accessibilityLabel="Copiar código PIX"
                                    >
                                        <Copy size={16} color={colors.primary} strokeWidth={2} />
                                    </IconButton>
                                }
                            />
                        </View>
                    </>
                )}

                {validade.texto ? (
                    <View style={{ marginVertical: 15 }}>
                        <Text style={{ fontSize: fontSize.xs, color: colors.textMuted, textAlign: "center" }}>
                            {validade.texto}
                        </Text>
                    </View>
                ) : null}

                <View style={{ gap: 10 }}>
                    {expirado ? (
                        <Button
                            title="Gerar novo código"
                            disabled={novoCodigo.isPending}
                            onPress={() => novoCodigo.mutate()}
                        />
                    ) : (
                        <Button title="Copiar" onPress={handleClick} />
                    )}
                    <Button type="outline" uppercase={false} title="Acompanhar pedido" onPress={irParaAcompanhamento} textStyle={{ color: colors.primaryDark }} />
                    <Button
                        type="clear"
                        uppercase={false}
                        title="Cancelar pedido"
                        disabled={cancelar.isPending}
                        onPress={confirmarCancelamento}
                        textStyle={{ color: colors.errorColor }}
                    />
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
