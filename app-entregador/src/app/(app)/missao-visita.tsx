import { Botao, Campo, Cartao } from "@/components/ui"
import { COLORS } from "@/constants/app"
import { ArquivoUpload, tirarFoto } from "@/helpers/camera"
import MissaoService from "@/services/missao.service"
import { StatusVisita } from "@/types/types"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { router, useLocalSearchParams } from "expo-router"
import * as Location from "expo-location"
import { Camera, Check } from "lucide-react-native"
import { useState } from "react"
import { Image, ScrollView, StyleSheet, Text, TouchableOpacity, View } from "react-native"
import Toast from "react-native-toast-message"

const STATUS: { valor: StatusVisita; label: string }[] = [
    { valor: "visitada", label: "Visitada" },
    { valor: "ausente", label: "Ausente" },
    { valor: "interessado", label: "Interessado" },
    { valor: "frustrada", label: "Frustrada" },
]

/**
 * Registrar visita (L7) — tela dedicada (padrão do ecossistema: formulário nunca
 * em bottom-sheet). Status da residência + observação + foto de evidência.
 */
export default function MissaoVisita() {
    const { cliente_id, nome } = useLocalSearchParams<{ cliente_id?: string; nome?: string }>()
    const qc = useQueryClient()
    const [status, setStatus] = useState<StatusVisita>("visitada")
    const [observacao, setObservacao] = useState("")
    const [foto, setFoto] = useState<ArquivoUpload | null>(null)

    const registrar = useMutation({
        mutationFn: async () => {
            let coords: { latitude: number; longitude: number } | null = null
            try {
                const pos = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced })
                coords = pos.coords
            } catch {
                // sem GPS neste momento — registra sem coordenada
            }
            return MissaoService.RegistrarVisita({
                status,
                latitude: coords?.latitude ?? null,
                longitude: coords?.longitude ?? null,
                cliente_id: cliente_id ? Number(cliente_id) : null,
                observacao: observacao || undefined,
                tipo_foto: "visita",
            }, foto)
        },
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ["entregador", "missao"] })
            Toast.show({ type: "success", text1: "Visita registrada." })
            router.back()
        },
        onError: (e: any) => Toast.show({ type: "error", text1: e?.message ?? "Erro ao registrar." }),
    })

    const capturar = async () => {
        const f = await tirarFoto()
        if (f) setFoto(f)
    }

    return (
        <ScrollView contentContainerStyle={{ padding: 16, gap: 14 }}>
            {nome ? (
                <Cartao>
                    <Text style={s.clienteLabel}>Residência sugerida</Text>
                    <Text style={s.clienteNome}>{nome}</Text>
                </Cartao>
            ) : null}

            <Text style={s.secao}>Resultado da visita</Text>
            <View style={{ gap: 8 }}>
                {STATUS.map((op) => {
                    const sel = status === op.valor
                    return (
                        <TouchableOpacity key={op.valor} style={[s.opcao, sel && s.opcaoSel]} onPress={() => setStatus(op.valor)} activeOpacity={0.8}>
                            <Text style={[s.opcaoTexto, sel && { color: COLORS.primary }]}>{op.label}</Text>
                            {sel && <Check size={18} color={COLORS.primary} />}
                        </TouchableOpacity>
                    )
                })}
            </View>

            <Campo label="Observação (opcional)" value={observacao} onChangeText={setObservacao} placeholder="Ex.: pediu retorno semana que vem" />

            <Text style={s.secao}>Evidência</Text>
            <TouchableOpacity style={s.fotoBox} onPress={capturar} activeOpacity={0.8}>
                {foto ? (
                    <Image source={{ uri: foto.uri }} style={s.fotoPreview} />
                ) : (
                    <View style={{ alignItems: "center", gap: 6 }}>
                        <Camera size={28} color={COLORS.muted} />
                        <Text style={s.fotoTexto}>Tirar foto da visita</Text>
                    </View>
                )}
            </TouchableOpacity>

            <Botao titulo="Registrar visita" onPress={() => registrar.mutate()} carregando={registrar.isPending} />
        </ScrollView>
    )
}

const s = StyleSheet.create({
    clienteLabel: { fontSize: 12, fontWeight: "700", color: COLORS.primary },
    clienteNome: { fontSize: 16, fontWeight: "700", color: COLORS.text, marginTop: 2 },
    secao: { fontSize: 13, fontWeight: "700", color: COLORS.muted },
    opcao: {
        flexDirection: "row", justifyContent: "space-between", alignItems: "center",
        backgroundColor: COLORS.card, borderWidth: 1.5, borderColor: COLORS.border,
        borderRadius: 12, paddingHorizontal: 14, paddingVertical: 12,
    },
    opcaoSel: { borderColor: COLORS.primary, backgroundColor: "#FFF1E8" },
    opcaoTexto: { fontSize: 15, fontWeight: "600", color: COLORS.text },
    fotoBox: {
        height: 160, borderRadius: 14, borderWidth: 1.5, borderStyle: "dashed",
        borderColor: COLORS.border, backgroundColor: COLORS.card,
        alignItems: "center", justifyContent: "center", overflow: "hidden",
    },
    fotoPreview: { width: "100%", height: "100%" },
    fotoTexto: { fontSize: 13, color: COLORS.muted },
})
