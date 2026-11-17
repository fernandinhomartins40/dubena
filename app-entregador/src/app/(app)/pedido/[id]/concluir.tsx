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
 * e salva como PNG antes do upload multipart.
 *
 * PIE P2-C-16: o desenho só valia depois de tocar "Salvar assinatura" DENTRO do
 * quadro; o cliente assinava, o entregador tocava "Concluir" e recebia "Anexe
 * foto ou colha a assinatura". Agora cada traço é lido ao terminar, e o
 * "Concluir" ainda pede a leitura se o último traço não tiver chegado. A rolagem
 * da tela é travada durante o traço (no Android o ScrollView roubava o gesto).
 *
 * PIE P2-C-05: sem sinal, a conclusão fica guardada na fila (com a foto copiada
 * para fora do cache) e vai sozinha quando a rede voltar, com a mesma chave.
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
    const [rolagem, setRolagem] = useState(true)
    // Refs: o "Concluir" pode rodar antes de o estado do último traço assentar.
    const assinaturaRef = useRef<ArquivoUpload | null>(null)
    const desenhou = useRef(false)
    const aguardando = useRef<((a: ArquivoUpload | null) => void) | null>(null)
    // Um arquivo por tela: cada traço sobrescreve o mesmo PNG.
    const caminho = useRef(`${FileSystem.cacheDirectory}assinatura-${pedidoId}-${Date.now()}.png`)

    const anexarFoto = async () => {
        const f = await tirarFoto()
        if (f) setFoto(f)
    }

    const entregar = (a: ArquivoUpload | null) => {
        const r = aguardando.current
        aguardando.current = null
        r?.(a)
    }

    /** Recebe o base64 do canvas e grava como PNG. Chamado a cada traço. */
    const onAssinatura = async (base64: string) => {
        try {
            const data = base64.replace("data:image/png;base64,", "")
            await FileSystem.writeAsStringAsync(caminho.current, data, {
                encoding: FileSystem.EncodingType.Base64,
            })
            const arq = { uri: caminho.current, name: "assinatura.png", type: "image/png" }
            assinaturaRef.current = arq
            setAssinatura(arq)
            entregar(arq)
        } catch {
            entregar(null)
            Toast.show({ type: "error", text1: "Não foi possível guardar a assinatura." })
        }
    }

    const limparAssinatura = () => {
        desenhou.current = false
        assinaturaRef.current = null
        setAssinatura(null)
    }

    /** Lê o canvas agora (o último traço pode ainda não ter virado arquivo). */
    const lerAssinatura = (): Promise<ArquivoUpload | null> =>
        new Promise((resolve) => {
            aguardando.current = resolve
            sigRef.current?.readSignature()
            // O canvas vazio chama onEmpty; se nada responder, não trava a tela.
            setTimeout(() => {
                if (aguardando.current === resolve) entregar(assinaturaRef.current)
            }, 2500)
        })

    const concluir = async () => {
        setSalvando(true)
        try {
            let ass = assinaturaRef.current
            if (desenhou.current) ass = (await lerAssinatura()) ?? ass

            if (!foto && !ass) {
                Toast.show({ type: "error", text1: "Anexe uma foto ou peça a assinatura do cliente." })
                return
            }

            const pos = await Location.getCurrentPositionAsync({
                accuracy: Location.Accuracy.Balanced,
            }).catch(() => null)

            const r = await EntregaService.Concluir(
                pedidoId,
                {
                    recebido_por: recebidoPor.trim() || undefined,
                    latitude: pos?.coords.latitude ?? null,
                    longitude: pos?.coords.longitude ?? null,
                },
                { foto, assinatura: ass },
            )
            if (r.enfileirado) {
                Toast.show({
                    type: "info",
                    text1: "Sem sinal: entrega guardada.",
                    text2: "Ela é enviada sozinha quando a internet voltar.",
                })
            } else {
                Toast.show({ type: "success", text1: "Entrega concluída!" })
            }
            qc.invalidateQueries({ queryKey: ["entregador", "pedidos"] })
            router.replace("/(app)/(tabs)/entregas")
        } catch (e) {
            Toast.show({ type: "error", text1: (e as HttpError).message })
        } finally {
            setSalvando(false)
        }
    }

    return (
        <ScrollView contentContainerStyle={{ padding: 16 }} keyboardShouldPersistTaps="handled" scrollEnabled={rolagem}>
            <Campo
                label="Recebido por (opcional)"
                value={recebidoPor}
                onChangeText={setRecebidoPor}
                placeholder="Nome de quem recebeu"
            />

            <Text style={s.titulo} accessibilityRole="header">Foto da entrega</Text>
            {foto ? (
                <Image
                    source={{ uri: foto.uri }}
                    style={s.preview}
                    contentFit="cover"
                    accessibilityLabel="Foto da entrega anexada"
                />
            ) : null}
            <Botao
                titulo={foto ? "Trocar foto" : "Tirar foto"}
                variante="secundario"
                onPress={anexarFoto}
            />

            <Text style={s.titulo} accessibilityRole="header">Assinatura do cliente</Text>
            <View style={s.canvas} accessibilityLabel="Quadro para o cliente assinar com o dedo">
                <SignatureScreen
                    ref={sigRef}
                    onOK={onAssinatura}
                    onEmpty={() => entregar(null)}
                    onBegin={() => {
                        desenhou.current = true
                        setRolagem(false)
                    }}
                    onEnd={() => {
                        setRolagem(true)
                        sigRef.current?.readSignature()
                    }}
                    onClear={limparAssinatura}
                    webStyle={SIGN_STYLE}
                    descriptionText=""
                    clearText="Limpar"
                    confirmText="Salvar assinatura"
                />
            </View>
            {assinatura ? <Text style={s.ok}>✓ Assinatura registrada</Text> : null}

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
