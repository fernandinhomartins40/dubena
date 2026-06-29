import { Botao, Campo } from "@/components/ui"
import { COLORS } from "@/constants/app"
import { ArquivoUpload, tirarFoto } from "@/helpers/camera"
import { HttpError } from "@/helpers/http"
import EntregaService from "@/services/entrega.service"
import { useQueryClient } from "@tanstack/react-query"
import * as FileSystem from "expo-file-system"
import { Image } from "expo-image"
import { router, useLocalSearchParams } from "expo-router"
import * as Location from "expo-location"
import { useRef, useState } from "react"
import { ScrollView, StyleSheet, Text, View } from "react-native"
import SignatureScreen, { SignatureViewRef } from "react-native-signature-canvas"
import Toast from "react-native-toast-message"

/**
 * Comprovação + conclusão da entrega (P7). Foto E/OU assinatura é obrigatória
 * (mesma regra do EntregaService no backend). A assinatura é capturada num canvas
 * e salva como PNG temporário antes do upload multipart.
 */
export default function Concluir() {
    const { id } = useLocalSearchParams<{ id: string }>()
    const pedidoId = Number(id)
    const qc = useQueryClient()
    const sigRef = useRef<SignatureViewRef>(null)

    const [recebidoPor, setRecebidoPor] = useState("")
    const [foto, setFoto] = useState<ArquivoUpload | null>(null)
    const [assinatura, setAssinatura] = useState<ArquivoUpload | null>(null)
    const [salvando, setSalvando] = useState(false)

    const anexarFoto = async () => {
        const f = await tirarFoto()
        if (f) setFoto(f)
    }

    /** Recebe o base64 da assinatura do canvas e grava como PNG temporário. */
    const onAssinatura = async (base64: string) => {
        try {
            const data = base64.replace("data:image/png;base64,", "")
            const path = `${FileSystem.cacheDirectory}assinatura-${pedidoId}-${Date.now()}.png`
            await FileSystem.writeAsStringAsync(path, data, {
                encoding: FileSystem.EncodingType.Base64,
            })
            setAssinatura({ uri: path, name: "assinatura.png", type: "image/png" })
            Toast.show({ type: "success", text1: "Assinatura capturada." })
        } catch {
            Toast.show({ type: "error", text1: "Não foi possível salvar a assinatura." })
        }
    }

    const concluir = async () => {
        if (!foto && !assinatura) {
            Toast.show({ type: "error", text1: "Anexe foto ou colha a assinatura." })
            return
        }
        setSalvando(true)
        try {
            const pos = await Location.getCurrentPositionAsync({
                accuracy: Location.Accuracy.Balanced,
            }).catch(() => null)

            await EntregaService.Concluir(
                pedidoId,
                {
                    recebido_por: recebidoPor.trim() || undefined,
                    latitude: pos?.coords.latitude ?? null,
                    longitude: pos?.coords.longitude ?? null,
                },
                { foto, assinatura },
            )
            Toast.show({ type: "success", text1: "Entrega concluída!" })
            qc.invalidateQueries({ queryKey: ["entregador", "pedidos"] })
            router.replace("/(app)/pedidos")
        } catch (e) {
            Toast.show({ type: "error", text1: (e as HttpError).message })
        } finally {
            setSalvando(false)
        }
    }

    return (
        <ScrollView contentContainerStyle={{ padding: 16 }} keyboardShouldPersistTaps="handled">
            <Campo
                label="Recebido por (opcional)"
                value={recebidoPor}
                onChangeText={setRecebidoPor}
                placeholder="Nome de quem recebeu"
            />

            <Text style={s.titulo}>Foto da entrega</Text>
            {foto ? <Image source={{ uri: foto.uri }} style={s.preview} contentFit="cover" /> : null}
            <Botao
                titulo={foto ? "Trocar foto" : "Tirar foto"}
                variante="secundario"
                onPress={anexarFoto}
            />

            <Text style={s.titulo}>Assinatura do cliente</Text>
            <View style={s.canvas}>
                <SignatureScreen
                    ref={sigRef}
                    onOK={onAssinatura}
                    webStyle={SIGN_STYLE}
                    descriptionText=""
                    clearText="Limpar"
                    confirmText="Salvar assinatura"
                />
            </View>
            {assinatura ? <Text style={s.ok}>✓ Assinatura capturada</Text> : null}

            <View style={{ marginTop: 16 }}>
                <Botao titulo="Concluir entrega" onPress={concluir} carregando={salvando} />
            </View>
        </ScrollView>
    )
}

const SIGN_STYLE = `
  .m-signature-pad { box-shadow: none; border: none; }
  .m-signature-pad--body { border: none; }
  .m-signature-pad--footer { margin: 4px; }
  body, html { height: 220px; }
`

const s = StyleSheet.create({
    titulo: { fontSize: 14, fontWeight: "700", color: COLORS.text, marginTop: 18, marginBottom: 8 },
    preview: { height: 200, borderRadius: 12, marginBottom: 10 },
    canvas: {
        height: 240,
        borderWidth: 1,
        borderColor: COLORS.border,
        borderRadius: 12,
        overflow: "hidden",
        backgroundColor: COLORS.card,
    },
    ok: { color: COLORS.success, fontWeight: "700", marginTop: 8 },
})
