import { Botao, Cartao, Etiqueta } from "@/components/ui"
import { COLORS, PEDIDOS_POLL_MS } from "@/constants/app"
import { useJornada } from "@/hooks/useJornada"
import AuthService from "@/services/auth.service"
import EntregaService from "@/services/entrega.service"
import JornadaService from "@/services/jornada.service"
import useAppStore from "@/store/appStore"
import { PedidoEntrega } from "@/types/types"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { router, useNavigation } from "expo-router"
import { Bike, MapPin, Package } from "lucide-react-native"
import { useLayoutEffect, useMemo, useState } from "react"
import {
    Alert,
    FlatList,
    RefreshControl,
    StyleSheet,
    Text,
    TouchableOpacity,
    View,
} from "react-native"

const moeda = (v: number) => `R$ ${v.toFixed(2).replace(".", ",")}`

type Aba = "pendentes" | "andamento" | "historico"

/** Painel do entregador: jornada + resumo do dia + entregas por aba. */
export default function Pedidos() {
    const navigation = useNavigation()
    const logout = useAppStore((s) => s.logout)
    const user = useAppStore((s) => s.user)
    const qc = useQueryClient()
    const { jornada, dashboard, ativa } = useJornada()
    const [aba, setAba] = useState<Aba>("pendentes")

    const { data, isLoading, refetch, isRefetching } = useQuery({
        queryKey: ["entregador", "pedidos"],
        queryFn: EntregaService.Pedidos,
        refetchInterval: PEDIDOS_POLL_MS,
    })

    const encerrar = useMutation({
        mutationFn: () => JornadaService.Encerrar(null),
        onSuccess: () => qc.invalidateQueries({ queryKey: ["entregador"] }),
    })

    const sair = async () => {
        try {
            await AuthService.Logout()
        } catch {
            // mesmo se falhar no servidor, limpamos local
        }
        logout()
        router.replace("/login")
    }

    useLayoutEffect(() => {
        navigation.setOptions({
            headerRight: () => (
                <TouchableOpacity onPress={sair} hitSlop={10}>
                    <Text style={{ color: COLORS.white, fontWeight: "700" }}>Sair</Text>
                </TouchableOpacity>
            ),
        })
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [navigation])

    const pedidos = data ?? []
    const filtrados = useMemo(() => filtrarPorAba(pedidos, aba), [pedidos, aba])

    const confirmarEncerrar = () =>
        Alert.alert("Encerrar jornada?", "Você deixará de receber novas entregas.", [
            { text: "Não", style: "cancel" },
            { text: "Encerrar", style: "destructive", onPress: () => encerrar.mutate() },
        ])

    return (
        <FlatList
            data={filtrados}
            keyExtractor={(p) => String(p.id)}
            contentContainerStyle={{ padding: 16, gap: 12 }}
            refreshControl={<RefreshControl refreshing={isRefetching} onRefresh={refetch} />}
            ListHeaderComponent={
                <View style={{ gap: 12, marginBottom: 4 }}>
                    <JornadaCard
                        nome={user?.name ?? "Entregador"}
                        ativa={ativa}
                        placa={jornada.data?.veiculo?.placa ?? null}
                        onIniciar={() => router.push("/(app)/inicio")}
                        onEncerrar={confirmarEncerrar}
                        encerrando={encerrar.isPending}
                    />
                    <ResumoDia
                        pendentes={dashboard.data?.pendentes ?? 0}
                        concluidos={dashboard.data?.concluidos_hoje ?? 0}
                    />
                    <Abas atual={aba} onChange={setAba} />
                </View>
            }
            ListEmptyComponent={
                !isLoading ? (
                    <Text style={s.vazio}>
                        {ativa ? "Nenhuma entrega nesta aba." : "Inicie a jornada para receber entregas."}
                    </Text>
                ) : null
            }
            renderItem={({ item }) => <ItemPedido pedido={item} />}
        />
    )
}

function JornadaCard({
    nome, ativa, placa, onIniciar, onEncerrar, encerrando,
}: {
    nome: string; ativa: boolean; placa: string | null
    onIniciar: () => void; onEncerrar: () => void; encerrando: boolean
}) {
    return (
        <Cartao style={{ gap: 10 }}>
            <View style={{ flexDirection: "row", alignItems: "center", gap: 10 }}>
                <View style={[s.statusDot, { backgroundColor: ativa ? COLORS.success : COLORS.muted }]} />
                <View style={{ flex: 1 }}>
                    <Text style={s.jornadaNome}>{nome}</Text>
                    <Text style={s.jornadaSub}>
                        {ativa ? `Em serviço${placa ? ` · ${placa}` : ""}` : "Fora de serviço"}
                    </Text>
                </View>
            </View>
            {ativa ? (
                <Botao titulo="Encerrar jornada" variante="perigo" onPress={onEncerrar} carregando={encerrando} />
            ) : (
                <Botao titulo="Iniciar jornada" onPress={onIniciar} />
            )}
        </Cartao>
    )
}

function ResumoDia({ pendentes, concluidos }: { pendentes: number; concluidos: number }) {
    return (
        <View style={{ flexDirection: "row", gap: 12 }}>
            <Stat icon={<Package size={18} color={COLORS.primary} />} valor={pendentes} label="Pendentes" />
            <Stat icon={<Bike size={18} color={COLORS.success} />} valor={concluidos} label="Entregues hoje" />
        </View>
    )
}

function Stat({ icon, valor, label }: { icon: React.ReactNode; valor: number; label: string }) {
    return (
        <Cartao style={{ flex: 1, gap: 4 }}>
            {icon}
            <Text style={s.statValor}>{valor}</Text>
            <Text style={s.statLabel}>{label}</Text>
        </Cartao>
    )
}

function Abas({ atual, onChange }: { atual: Aba; onChange: (a: Aba) => void }) {
    const abas: { chave: Aba; label: string }[] = [
        { chave: "pendentes", label: "Pendentes" },
        { chave: "andamento", label: "Em andamento" },
        { chave: "historico", label: "Histórico" },
    ]
    return (
        <View style={s.abas}>
            {abas.map((a) => {
                const on = atual === a.chave
                return (
                    <TouchableOpacity key={a.chave} style={[s.aba, on && s.abaOn]} onPress={() => onChange(a.chave)}>
                        <Text style={[s.abaTexto, on && s.abaTextoOn]}>{a.label}</Text>
                    </TouchableOpacity>
                )
            })}
        </View>
    )
}

function ItemPedido({ pedido }: { pedido: PedidoEntrega }) {
    return (
        <TouchableOpacity activeOpacity={0.85} onPress={() => router.push(`/(app)/pedido/${pedido.id}`)}>
            <Cartao>
                <View style={s.linhaTopo}>
                    <Text style={s.numero}>#{pedido.id}</Text>
                    {pedido.situacao ? <Etiqueta texto={pedido.situacao} /> : null}
                </View>
                <Text style={s.cliente}>{pedido.cliente ?? "Cliente"}</Text>
                <Text style={s.endereco}>
                    <MapPin size={12} color={COLORS.muted} /> {pedido.endereco || "Endereço não informado"}
                </Text>
                <Text style={s.valor}>{moeda(pedido.valor_venda)}</Text>
            </Cartao>
        </TouchableOpacity>
    )
}

/**
 * Filtra por aba usando o texto da situação (o backend expõe `situacao`; o efeito
 * não vem na lista). Heurística: "entregue/conclu" = histórico; "cancel" = histórico;
 * situações de deslocamento = andamento; o resto = pendentes.
 */
function filtrarPorAba(pedidos: PedidoEntrega[], aba: Aba): PedidoEntrega[] {
    const norm = (s: string | null) => (s ?? "").toLowerCase()
    return pedidos.filter((p) => {
        const sit = norm(p.situacao)
        const finalizado = sit.includes("entreg") || sit.includes("conclu") || sit.includes("cancel")
        const andamento = sit.includes("caminho") || sit.includes("rota") || sit.includes("saiu") || sit.includes("atend")
        if (aba === "historico") return finalizado
        if (aba === "andamento") return andamento && !finalizado
        return !finalizado && !andamento
    })
}

const s = StyleSheet.create({
    statusDot: { width: 12, height: 12, borderRadius: 999 },
    jornadaNome: { fontSize: 17, fontWeight: "800", color: COLORS.text },
    jornadaSub: { fontSize: 13, color: COLORS.muted, marginTop: 2 },
    statValor: { fontSize: 24, fontWeight: "800", color: COLORS.text },
    statLabel: { fontSize: 12, color: COLORS.muted },
    abas: { flexDirection: "row", backgroundColor: COLORS.card, borderRadius: 12, padding: 4, borderWidth: 1, borderColor: COLORS.border },
    aba: { flex: 1, paddingVertical: 8, borderRadius: 9, alignItems: "center" },
    abaOn: { backgroundColor: COLORS.primary },
    abaTexto: { fontSize: 13, fontWeight: "700", color: COLORS.muted },
    abaTextoOn: { color: COLORS.white },
    linhaTopo: { flexDirection: "row", justifyContent: "space-between", alignItems: "center" },
    numero: { fontSize: 16, fontWeight: "800", color: COLORS.primary },
    cliente: { fontSize: 16, fontWeight: "600", color: COLORS.text, marginTop: 8 },
    endereco: { fontSize: 14, color: COLORS.muted, marginTop: 2 },
    valor: { fontSize: 15, fontWeight: "700", color: COLORS.graphite, marginTop: 8 },
    vazio: { textAlign: "center", color: COLORS.muted, marginTop: 40, fontSize: 15 },
})
