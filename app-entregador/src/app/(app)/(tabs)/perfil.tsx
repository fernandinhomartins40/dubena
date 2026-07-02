import { Cartao } from "@/components/ui"
import { COLORS } from "@/constants/app"
import { useJornada } from "@/hooks/useJornada"
import AuthService from "@/services/auth.service"
import JornadaService from "@/services/jornada.service"
import useAppStore from "@/store/appStore"
import { fontSize, radius } from "@/styles/theme"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { router } from "expo-router"
import { ChevronRight, LogOut, PlayCircle, PowerOff, Truck } from "lucide-react-native"
import { Alert, ScrollView, StyleSheet, Text, TouchableOpacity, View } from "react-native"
import { useSafeAreaInsets } from "react-native-safe-area-context"

/**
 * Perfil (F10) — padrão visual do perfil do app do consumidor: card do usuário
 * com avatar (inicial) e linhas de ação com tile de ícone + chevron.
 */
export default function Perfil() {
    const { top } = useSafeAreaInsets()
    const user = useAppStore((s) => s.user)
    const logout = useAppStore((s) => s.logout)
    const qc = useQueryClient()
    const { jornada, ativa } = useJornada()

    const encerrar = useMutation({
        mutationFn: () => JornadaService.Encerrar(null),
        onSuccess: () => qc.invalidateQueries({ queryKey: ["entregador"] }),
    })

    const confirmarEncerrar = () =>
        Alert.alert("Encerrar jornada?", "Você deixará de receber novas entregas.", [
            { text: "Não", style: "cancel" },
            { text: "Encerrar", style: "destructive", onPress: () => encerrar.mutate() },
        ])

    const sair = async () => {
        try {
            await AuthService.Logout()
        } catch {
            // mesmo se falhar no servidor, limpamos local
        }
        logout()
        router.replace("/login")
    }

    const nome = user?.name ?? "Entregador"
    const inicial = (nome.trim()[0] ?? "E").toUpperCase()
    const placa = jornada.data?.veiculo?.placa

    return (
        <ScrollView
            style={{ backgroundColor: COLORS.bg }}
            contentContainerStyle={{ paddingTop: top + 16, paddingHorizontal: 16, paddingBottom: 28, gap: 12 }}
            showsVerticalScrollIndicator={false}
        >
            <Text style={s.titulo}>Perfil</Text>

            {/* Card do colaborador (avatar com inicial, como no consumidor) */}
            <Cartao style={{ flexDirection: "row", alignItems: "center", gap: 14 }}>
                <View style={s.avatar}>
                    <Text style={s.avatarTexto}>{inicial}</Text>
                </View>
                <View style={{ flex: 1 }}>
                    <Text style={s.nome} numberOfLines={1}>{nome}</Text>
                    <View style={{ flexDirection: "row", alignItems: "center", gap: 6, marginTop: 2 }}>
                        <View style={[s.dot, { backgroundColor: ativa ? COLORS.success : COLORS.muted }]} />
                        <Text style={s.statusTexto}>
                            {ativa ? `Em serviço${placa ? ` · ${placa}` : ""}` : "Fora de serviço"}
                        </Text>
                    </View>
                </View>
            </Cartao>

            {/* Ações */}
            <View style={{ gap: 10, marginTop: 6 }}>
                {ativa ? (
                    <Linha
                        icone={<PowerOff size={20} color={COLORS.danger} />}
                        tileBg="#FDECEC"
                        titulo="Encerrar jornada"
                        sub={placa ? `Veículo ${placa} em uso` : "Finalizar o expediente"}
                        onPress={confirmarEncerrar}
                    />
                ) : (
                    <Linha
                        icone={<PlayCircle size={20} color={COLORS.primary} />}
                        titulo="Iniciar jornada"
                        sub="Escolher veículo e conferir o checklist"
                        onPress={() => router.push("/(app)/iniciar-jornada")}
                    />
                )}
                <Linha
                    icone={<Truck size={20} color={COLORS.primary} />}
                    titulo="Veículo da jornada"
                    sub={placa ? `${placa}${jornada.data?.veiculo?.descricao ? ` · ${jornada.data.veiculo.descricao}` : ""}` : "Nenhum veículo em uso"}
                    onPress={() => (ativa ? undefined : router.push("/(app)/iniciar-jornada"))}
                />
                <Linha
                    icone={<LogOut size={20} color={COLORS.danger} />}
                    tileBg="#FDECEC"
                    titulo="Sair"
                    sub="Encerrar a sessão neste aparelho"
                    onPress={sair}
                    danger
                />
            </View>
        </ScrollView>
    )
}

function Linha({ icone, titulo, sub, onPress, danger, tileBg }: {
    icone: React.ReactNode; titulo: string; sub: string
    onPress?: () => void; danger?: boolean; tileBg?: string
}) {
    return (
        <TouchableOpacity activeOpacity={0.85} onPress={onPress} disabled={!onPress}>
            <Cartao style={{ flexDirection: "row", alignItems: "center", gap: 12 }}>
                <View style={[s.tile, tileBg ? { backgroundColor: tileBg } : null]}>{icone}</View>
                <View style={{ flex: 1 }}>
                    <Text style={[s.linhaTitulo, danger && { color: COLORS.danger }]}>{titulo}</Text>
                    <Text style={s.linhaSub}>{sub}</Text>
                </View>
                {onPress ? <ChevronRight size={20} color={COLORS.muted} /> : null}
            </Cartao>
        </TouchableOpacity>
    )
}

const s = StyleSheet.create({
    titulo: { fontSize: fontSize.xl, fontWeight: "800", color: COLORS.text },
    avatar: {
        width: 56, height: 56, borderRadius: 999,
        backgroundColor: COLORS.primary, alignItems: "center", justifyContent: "center",
    },
    avatarTexto: { fontSize: fontSize.lg, color: COLORS.white, fontWeight: "800" },
    nome: { fontSize: fontSize.md, fontWeight: "800", color: COLORS.text },
    dot: { width: 8, height: 8, borderRadius: 999 },
    statusTexto: { fontSize: fontSize.sm, color: COLORS.muted, fontWeight: "600" },
    tile: {
        width: 40, height: 40, borderRadius: radius.md,
        backgroundColor: "#FFF1E8", alignItems: "center", justifyContent: "center",
    },
    linhaTitulo: { fontSize: fontSize.md, fontWeight: "700", color: COLORS.text },
    linhaSub: { fontSize: fontSize.sm, color: COLORS.muted, marginTop: 1 },
})
