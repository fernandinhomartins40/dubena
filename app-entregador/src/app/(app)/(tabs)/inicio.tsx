import { Botao, Cartao } from "@/components/ui"
import { COLORS } from "@/constants/app"
import { useJornada } from "@/hooks/useJornada"
import EntregaService from "@/services/entrega.service"
import JornadaService from "@/services/jornada.service"
import useAppStore from "@/store/appStore"
import { fontSize, radius } from "@/styles/theme"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { router } from "expo-router"
import {
    CheckCircle2, ChevronRight, Compass, MapPin, Map as MapIcon,
    Package, PlayCircle, Receipt, Ticket, Truck, Users,
} from "lucide-react-native"
import {
    Alert, RefreshControl, ScrollView, StyleSheet, Text, TouchableOpacity, View,
} from "react-native"
import { useSafeAreaInsets } from "react-native-safe-area-context"

const moeda = (v: number) => `R$ ${v.toFixed(2).replace(".", ",")}`
const primeiroNome = (nome?: string | null) => (nome ?? "Entregador").trim().split(" ")[0]

/**
 * Início (F10) — dashboard do entregador no padrão visual do app do consumidor:
 * saudação, cartão de jornada (CTA grande fora de serviço / status + encerrar em
 * serviço), estatísticas do dia, atalhos para Rota/Missão e as próximas entregas.
 */
export default function Inicio() {
    const { top } = useSafeAreaInsets()
    const user = useAppStore((s) => s.user)
    const qc = useQueryClient()
    const { jornada, dashboard, ativa } = useJornada()

    const { data: pedidos, refetch, isRefetching } = useQuery({
        queryKey: ["entregador", "pedidos"],
        queryFn: EntregaService.Pedidos,
        refetchInterval: 30000,
    })

    const encerrar = useMutation({
        mutationFn: () => JornadaService.Encerrar(null),
        onSuccess: () => qc.invalidateQueries({ queryKey: ["entregador"] }),
    })

    const confirmarEncerrar = () =>
        Alert.alert("Encerrar jornada?", "Você deixará de receber novas entregas.", [
            { text: "Não", style: "cancel" },
            { text: "Encerrar", style: "destructive", onPress: () => encerrar.mutate() },
        ])

    const placa = jornada.data?.veiculo?.placa
    const pendentes = dashboard.data?.pendentes ?? 0
    const concluidos = dashboard.data?.concluidos_hoje ?? 0
    const proximas = (pedidos ?? [])
        .filter((p) => {
            const s = (p.situacao ?? "").toLowerCase()
            return !s.includes("entreg") && !s.includes("conclu") && !s.includes("cancel")
        })
        .slice(0, 3)

    return (
        <ScrollView
            style={{ backgroundColor: COLORS.bg }}
            contentContainerStyle={{ paddingTop: top + 16, paddingHorizontal: 16, paddingBottom: 28, gap: 14 }}
            refreshControl={<RefreshControl refreshing={isRefetching} onRefresh={refetch} />}
            showsVerticalScrollIndicator={false}
        >
            {/* Saudação + status (padrão HomeHeader do consumidor) */}
            <View>
                <Text style={s.hello}>
                    Olá, <Text style={{ fontWeight: "800" }}>{primeiroNome(user?.name)}</Text> 👋
                </Text>
                <View style={s.statusRow}>
                    <View style={[s.dot, { backgroundColor: ativa ? COLORS.success : COLORS.muted }]} />
                    <Text style={s.statusTexto}>
                        {ativa ? `Em serviço${placa ? ` · ${placa}` : ""}` : "Fora de serviço"}
                    </Text>
                </View>
            </View>

            {/* Cartão de jornada */}
            {ativa ? (
                <Cartao style={{ gap: 12 }}>
                    <View style={s.linha}>
                        <View style={s.tile}>
                            <Truck size={20} color={COLORS.primary} />
                        </View>
                        <View style={{ flex: 1 }}>
                            <Text style={s.cardTitulo}>Jornada em andamento</Text>
                            <Text style={s.cardSub}>
                                {placa ? `Veículo ${placa}` : "Sem veículo vinculado"}
                            </Text>
                        </View>
                        <TouchableOpacity onPress={confirmarEncerrar} hitSlop={10}>
                            <Text style={s.encerrar}>Encerrar</Text>
                        </TouchableOpacity>
                    </View>
                </Cartao>
            ) : (
                <Cartao style={{ gap: 12, borderColor: COLORS.primary, borderWidth: 1.5 }}>
                    <View style={s.linha}>
                        <View style={s.tile}>
                            <PlayCircle size={20} color={COLORS.primary} />
                        </View>
                        <View style={{ flex: 1 }}>
                            <Text style={s.cardTitulo}>Comece o seu dia</Text>
                            <Text style={s.cardSub}>Escolha o veículo e confira o checklist</Text>
                        </View>
                    </View>
                    <Botao titulo="Iniciar jornada" onPress={() => router.push("/(app)/iniciar-jornada")} />
                </Cartao>
            )}

            {/* Estatísticas do dia */}
            <View style={{ flexDirection: "row", gap: 12 }}>
                <Cartao style={s.stat}>
                    <View style={s.tile}>
                        <Package size={18} color={COLORS.primary} />
                    </View>
                    <Text style={s.statValor}>{pendentes}</Text>
                    <Text style={s.statLabel}>Pendentes</Text>
                </Cartao>
                <Cartao style={s.stat}>
                    <View style={[s.tile, { backgroundColor: "#E8F8EE" }]}>
                        <CheckCircle2 size={18} color={COLORS.success} />
                    </View>
                    <Text style={s.statValor}>{concluidos}</Text>
                    <Text style={s.statLabel}>Entregues hoje</Text>
                </Cartao>
            </View>

            {/* Atalhos */}
            <Text style={s.secao}>Atalhos</Text>
            <Atalho
                icone={<MapIcon size={20} color={COLORS.primary} />}
                titulo="Rota do dia"
                sub="Sequência otimizada das suas entregas"
                onPress={() => router.push("/(app)/(tabs)/rota")}
            />
            <Atalho
                icone={<Compass size={20} color={COLORS.primary} />}
                titulo="Missão de campo"
                sub="Panfletagem, prospecção e vendas"
                onPress={() => router.push("/(app)/(tabs)/missao")}
            />
            {/* Funções portadas dos apps legados (NFWEB e MovelApp). Ficam aqui
                nos Atalhos e não na tab bar: ela já tem cinco áreas, e o
                dashboard é onde o entregador começa o dia. */}
            <Atalho
                icone={<Users size={20} color={COLORS.primary} />}
                titulo="Vender para um cliente"
                sub="Buscar ou cadastrar e enviar à Central"
                onPress={() => router.push("/(app)/clientes")}
            />
            <Atalho
                icone={<Ticket size={20} color={COLORS.primary} />}
                titulo="Verificar Vale Gás"
                sub="Conferir o código antes de aceitar"
                onPress={() => router.push("/(app)/vale-gas")}
            />
            <Atalho
                icone={<Receipt size={20} color={COLORS.primary} />}
                titulo="Minhas vendas"
                sub="O que fechei no período"
                onPress={() => router.push("/(app)/relatorio-vendas")}
            />

            {/* Próximas entregas */}
            <View style={s.secaoRow}>
                <Text style={s.secao}>Próximas entregas</Text>
                <TouchableOpacity onPress={() => router.push("/(app)/(tabs)/entregas")} hitSlop={8}>
                    <Text style={s.verTodas}>Ver todas</Text>
                </TouchableOpacity>
            </View>
            {proximas.length === 0 ? (
                <Cartao>
                    <Text style={s.vazio}>
                        {ativa ? "Nenhuma entrega pendente no momento." : "Inicie a jornada para receber entregas."}
                    </Text>
                </Cartao>
            ) : (
                proximas.map((p) => (
                    <TouchableOpacity
                        key={p.id}
                        activeOpacity={0.85}
                        onPress={() => router.push(`/(app)/pedido/${p.id}`)}
                    >
                        <Cartao style={{ gap: 6 }}>
                            <View style={s.linha}>
                                <View style={s.tile}>
                                    <Package size={18} color={COLORS.primary} />
                                </View>
                                <View style={{ flex: 1 }}>
                                    <Text style={s.cardTitulo}>#{p.id} · {p.cliente ?? "Cliente"}</Text>
                                    <View style={{ flexDirection: "row", alignItems: "center", gap: 4 }}>
                                        <MapPin size={12} color={COLORS.muted} />
                                        <Text style={s.cardSub} numberOfLines={1}>
                                            {p.endereco || "Endereço não informado"}
                                        </Text>
                                    </View>
                                </View>
                                <Text style={s.valor}>{moeda(p.valor_venda)}</Text>
                            </View>
                        </Cartao>
                    </TouchableOpacity>
                ))
            )}
        </ScrollView>
    )
}

function Atalho({ icone, titulo, sub, onPress }: {
    icone: React.ReactNode; titulo: string; sub: string; onPress: () => void
}) {
    return (
        <TouchableOpacity activeOpacity={0.85} onPress={onPress}>
            <Cartao style={{ flexDirection: "row", alignItems: "center", gap: 12 }}>
                <View style={s.tile}>{icone}</View>
                <View style={{ flex: 1 }}>
                    <Text style={s.cardTitulo}>{titulo}</Text>
                    <Text style={s.cardSub}>{sub}</Text>
                </View>
                <ChevronRight size={20} color={COLORS.muted} />
            </Cartao>
        </TouchableOpacity>
    )
}

const s = StyleSheet.create({
    hello: { fontSize: fontSize.xl, color: COLORS.text, fontWeight: "400" },
    statusRow: { flexDirection: "row", alignItems: "center", gap: 6, marginTop: 4 },
    dot: { width: 9, height: 9, borderRadius: 999 },
    statusTexto: { fontSize: fontSize.sm, color: COLORS.muted, fontWeight: "600" },
    linha: { flexDirection: "row", alignItems: "center", gap: 12 },
    tile: {
        width: 40, height: 40, borderRadius: radius.md,
        backgroundColor: "#FFF1E8", alignItems: "center", justifyContent: "center",
    },
    cardTitulo: { fontSize: fontSize.md, color: COLORS.text, fontWeight: "700" },
    cardSub: { fontSize: fontSize.sm, color: COLORS.muted, marginTop: 1 },
    encerrar: { color: COLORS.danger, fontWeight: "700", fontSize: fontSize.sm },
    stat: { flex: 1, gap: 6, alignItems: "flex-start" },
    statValor: { fontSize: 26, fontWeight: "800", color: COLORS.text },
    statLabel: { fontSize: fontSize.xs, color: COLORS.muted },
    secao: { fontSize: fontSize.md, fontWeight: "800", color: COLORS.text, marginTop: 4 },
    secaoRow: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", marginTop: 4 },
    verTodas: { fontSize: fontSize.sm, color: COLORS.primary, fontWeight: "700" },
    valor: { fontSize: fontSize.sm, fontWeight: "800", color: COLORS.graphite },
    vazio: { fontSize: fontSize.sm, color: COLORS.muted, textAlign: "center", paddingVertical: 8 },
})
