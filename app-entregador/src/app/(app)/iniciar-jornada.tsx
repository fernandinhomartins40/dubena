import { Botao, Campo, Cartao } from "@/components/ui"
import { COLORS } from "@/constants/app"
import JornadaService from "@/services/jornada.service"
import { VeiculoOpcao } from "@/types/types"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { router } from "expo-router"
import { Check, Truck } from "lucide-react-native"
import { useState } from "react"
import { ActivityIndicator, ScrollView, StyleSheet, Text, TouchableOpacity, View } from "react-native"

/** Itens do checklist do veículo (início da jornada). */
const CHECKLIST = [
    { chave: "pneus", label: "Pneus calibrados" },
    { chave: "documentos", label: "Documentos em dia" },
    { chave: "gas", label: "Botijões/carga conferidos" },
    { chave: "combustivel", label: "Combustível suficiente" },
    { chave: "avarias", label: "Sem avarias novas" },
]

/**
 * Início de jornada (L4) — o entregador escolhe o veículo, confirma o checklist e
 * o km inicial. A partir daqui o ERP passa a considerá-lo em campo (distribuição +
 * GPS). Substitui o antigo toggle volátil "em serviço".
 */
export default function InicioJornada() {
    const qc = useQueryClient()
    const [veiculoId, setVeiculoId] = useState<number | null>(null)
    const [km, setKm] = useState("")
    const [checks, setChecks] = useState<Record<string, boolean>>({})

    const veiculos = useQuery({ queryKey: ["entregador", "veiculos"], queryFn: JornadaService.Veiculos })

    const iniciar = useMutation({
        mutationFn: () =>
            JornadaService.Iniciar({
                veiculo_id: veiculoId,
                km_inicial: km ? Number(km) : null,
                checklist: CHECKLIST.reduce((acc, i) => ({ ...acc, [i.chave]: !!checks[i.chave] }), {}),
            }),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ["entregador"] })
            router.replace("/(app)/(tabs)/inicio")
        },
    })

    const todosMarcados = CHECKLIST.every((i) => checks[i.chave])

    return (
        <ScrollView contentContainerStyle={{ padding: 16, gap: 14 }}>
            <Text style={s.titulo}>Iniciar jornada</Text>
            <Text style={s.sub}>Escolha o veículo e confira o checklist antes de começar.</Text>

            <Text style={s.secao}>Veículo</Text>
            {veiculos.isLoading ? (
                <ActivityIndicator color={COLORS.primary} />
            ) : (veiculos.data ?? []).length === 0 ? (
                <Text style={s.vazio}>Nenhum veículo cadastrado. Você pode iniciar sem veículo.</Text>
            ) : (
                <View style={{ gap: 8 }}>
                    {(veiculos.data ?? []).map((v: VeiculoOpcao) => {
                        const sel = veiculoId === v.id
                        return (
                            <TouchableOpacity
                                key={v.id}
                                activeOpacity={0.8}
                                onPress={() => setVeiculoId(sel ? null : v.id)}
                                style={[s.veiculo, sel && s.veiculoSel]}
                            >
                                <View style={[s.iconeVeiculo, sel && { backgroundColor: COLORS.primary }]}>
                                    <Truck size={18} color={sel ? COLORS.white : COLORS.primary} />
                                </View>
                                <View style={{ flex: 1 }}>
                                    <Text style={s.veiculoPlaca}>{v.placa}</Text>
                                    {v.descricao ? <Text style={s.veiculoDesc}>{v.descricao}</Text> : null}
                                </View>
                                {sel && <Check size={20} color={COLORS.primary} />}
                            </TouchableOpacity>
                        )
                    })}
                </View>
            )}

            <Campo
                label="Km inicial (opcional)"
                value={km}
                onChangeText={setKm}
                keyboardType="number-pad"
                placeholder="Ex.: 45210"
            />

            <Text style={s.secao}>Checklist do veículo</Text>
            <Cartao style={{ gap: 4 }}>
                {CHECKLIST.map((i) => {
                    const on = !!checks[i.chave]
                    return (
                        <TouchableOpacity
                            key={i.chave}
                            activeOpacity={0.7}
                            onPress={() => setChecks((c) => ({ ...c, [i.chave]: !on }))}
                            style={s.checkRow}
                        >
                            <View style={[s.checkbox, on && { backgroundColor: COLORS.success, borderColor: COLORS.success }]}>
                                {on && <Check size={14} color={COLORS.white} strokeWidth={3} />}
                            </View>
                            <Text style={s.checkLabel}>{i.label}</Text>
                        </TouchableOpacity>
                    )
                })}
            </Cartao>

            {iniciar.isError ? (
                <Text style={s.erro}>{(iniciar.error as any)?.message ?? "Não foi possível iniciar."}</Text>
            ) : null}

            <Botao
                titulo={todosMarcados ? "Iniciar jornada" : "Confirme o checklist"}
                onPress={() => iniciar.mutate()}
                carregando={iniciar.isPending}
                desabilitado={!todosMarcados}
            />
        </ScrollView>
    )
}

const s = StyleSheet.create({
    titulo: { fontSize: 22, fontWeight: "800", color: COLORS.text },
    sub: { fontSize: 14, color: COLORS.muted, marginTop: -6 },
    secao: { fontSize: 13, fontWeight: "700", color: COLORS.muted, marginTop: 4 },
    vazio: { fontSize: 14, color: COLORS.muted },
    veiculo: {
        flexDirection: "row",
        alignItems: "center",
        gap: 12,
        backgroundColor: COLORS.card,
        borderWidth: 1.5,
        borderColor: COLORS.border,
        borderRadius: 14,
        padding: 12,
    },
    veiculoSel: { borderColor: COLORS.primary, backgroundColor: "#FFF1E8" },
    iconeVeiculo: {
        width: 38, height: 38, borderRadius: 999, backgroundColor: "#FFF1E8",
        alignItems: "center", justifyContent: "center",
    },
    veiculoPlaca: { fontSize: 16, fontWeight: "700", color: COLORS.text },
    veiculoDesc: { fontSize: 13, color: COLORS.muted },
    checkRow: { flexDirection: "row", alignItems: "center", gap: 10, paddingVertical: 8 },
    checkbox: {
        width: 22, height: 22, borderRadius: 6, borderWidth: 2, borderColor: COLORS.border,
        alignItems: "center", justifyContent: "center",
    },
    checkLabel: { fontSize: 15, color: COLORS.text },
    erro: { color: COLORS.danger, fontSize: 14 },
})
