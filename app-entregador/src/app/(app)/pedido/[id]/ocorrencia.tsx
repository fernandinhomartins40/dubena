import { Botao, Campo } from "@/components/ui"
import { COLORS } from "@/constants/app"
import { ArquivoUpload, tirarFoto } from "@/helpers/camera"
import { HttpError } from "@/helpers/http"
import EntregaService from "@/services/entrega.service"
import { TipoOcorrencia } from "@/types/types"
import { useQueryClient } from "@tanstack/react-query"
import { Image } from "expo-image"
import { router, useLocalSearchParams } from "expo-router"
import * as Location from "expo-location"
import { useState } from "react"
import { ScrollView, StyleSheet, Text, TouchableOpacity, View } from "react-native"
import Toast from "react-native-toast-message"

const TIPOS: { valor: TipoOcorrencia; label: string }[] = [
    { valor: "ausente", label: "Cliente ausente" },
    { valor: "endereco_errado", label: "Endereço errado" },
    { valor: "avaria", label: "Avaria no produto" },
    { valor: "outro", label: "Outro" },
]

/** Registra uma ocorrência de campo (com foto opcional). */
export default function Ocorrencia() {
    const { id } = useLocalSearchParams<{ id: string }>()
    const pedidoId = Number(id)
    const qc = useQueryClient()

    const [tipo, setTipo] = useState<TipoOcorrencia>("ausente")
    const [descricao, setDescricao] = useState("")
    const [foto, setFoto] = useState<ArquivoUpload | null>(null)
    const [salvando, setSalvando] = useState(false)

    const anexarFoto = async () => {
        const f = await tirarFoto()
        if (f) setFoto(f)
    }

    const enviar = async () => {
        setSalvando(true)
        try {
            const pos = await Location.getCurrentPositionAsync({
                accuracy: Location.Accuracy.Balanced,
            }).catch(() => null)

            await EntregaService.Ocorrencia(
                pedidoId,
                {
                    tipo,
                    descricao: descricao.trim() || undefined,
                    latitude: pos?.coords.latitude ?? null,
                    longitude: pos?.coords.longitude ?? null,
                },
                foto,
            )
            Toast.show({ type: "success", text1: "Ocorrência registrada." })
            qc.invalidateQueries({ queryKey: ["entregador", "pedidos"] })
            router.back()
        } catch (e) {
            Toast.show({ type: "error", text1: (e as HttpError).message })
        } finally {
            setSalvando(false)
        }
    }

    return (
        <ScrollView contentContainerStyle={{ padding: 16 }}>
            <Text style={s.label}>Tipo de ocorrência</Text>
            <View style={s.chips}>
                {TIPOS.map((t) => {
                    const ativo = tipo === t.valor
                    return (
                        <TouchableOpacity
                            key={t.valor}
                            style={[s.chip, ativo && s.chipAtivo]}
                            onPress={() => setTipo(t.valor)}
                        >
                            <Text style={[s.chipTexto, ativo && s.chipTextoAtivo]}>{t.label}</Text>
                        </TouchableOpacity>
                    )
                })}
            </View>

            <View style={{ marginTop: 16 }}>
                <Campo
                    label="Descrição (opcional)"
                    value={descricao}
                    onChangeText={setDescricao}
                    placeholder="O que aconteceu?"
                    multiline
                />
            </View>

            {foto ? (
                <Image source={{ uri: foto.uri }} style={s.preview} contentFit="cover" />
            ) : null}

            <View style={{ gap: 10, marginTop: 8 }}>
                <Botao
                    titulo={foto ? "Trocar foto" : "Anexar foto"}
                    variante="secundario"
                    onPress={anexarFoto}
                />
                <Botao titulo="Registrar ocorrência" onPress={enviar} carregando={salvando} />
            </View>
        </ScrollView>
    )
}

const s = StyleSheet.create({
    label: { fontSize: 13, fontWeight: "600", color: COLORS.muted, marginBottom: 8 },
    chips: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
    chip: {
        paddingHorizontal: 14,
        paddingVertical: 10,
        borderRadius: 999,
        borderWidth: 1,
        borderColor: COLORS.border,
        backgroundColor: COLORS.card,
    },
    chipAtivo: { backgroundColor: COLORS.primary, borderColor: COLORS.primary },
    chipTexto: { color: COLORS.text, fontWeight: "600" },
    chipTextoAtivo: { color: COLORS.white },
    preview: { height: 200, borderRadius: 12, marginTop: 16 },
})
