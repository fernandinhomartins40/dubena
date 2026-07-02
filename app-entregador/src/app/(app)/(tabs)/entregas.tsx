import { Cartao, Etiqueta } from "@/components/ui"
import { COLORS, PEDIDOS_POLL_MS } from "@/constants/app"
import EntregaService from "@/services/entrega.service"
import { fontSize, radius } from "@/styles/theme"
import { PedidoEntrega } from "@/types/types"
import { useQuery } from "@tanstack/react-query"
import { router } from "expo-router"
import { ChevronRight, MapPin, Package, PackageOpen } from "lucide-react-native"
import { useMemo, useState } from "react"
import {
    FlatList, RefreshControl, StyleSheet, Text, TouchableOpacity, View,
} from "react-native"
import { useSafeAreaInsets } from "react-native-safe-area-context"

const moeda = (v: number) => `R$ ${v.toFixed(2).replace(".", ",")}`

type Aba = "pendentes" | "andamento" | "historico"

/**
 * Entregas (F10) — lista das entregas do entregador no padrão visual do app do
 * consumidor: título de página, segmento de abas, cards com tile de ícone,
 * endereço com pin e valor em destaque.
 */
export default function Entregas() {
    const { top } = useSafeAreaInsets()
    const [aba, setAba] = useState<Aba>("pendentes")

    const { data, isLoading, refetch, isRefetching } = useQuery({
        queryKey: ["entregador", "pedidos"],
        queryFn: EntregaService.Pedidos,
        refetchInterval: PEDIDOS_POLL_MS,
    })

    const filtrados = useMemo(() => filtrarPorAba(data ?? [], aba), [data, aba])

    return (
        <View style={{ flex: 1, backgroundColor: COLORS.bg }}>
            <FlatList
                data={filtrados}
                keyExtractor={(p) => String(p.id)}
                contentContainerStyle={{ paddingTop: top + 16, paddingHorizontal: 16, paddingBottom: 28, gap: 12 }}
                refreshControl={<RefreshControl refreshing={isRefetching} onRefresh={refetch} />}
                ListHeaderComponent={
                    <View style={{ gap: 12, marginBottom: 4 }}>
                        <Text style={s.titulo}>Entregas</Text>
                        <Abas atual={aba} onChange={setAba} />
                    </View>
                }
                ListEmptyComponent={
                    !isLoading ? (
                        <Cartao style={{ alignItems: "center", gap: 8, paddingVertical: 28 }}>
                            <PackageOpen size={32} color={COLORS.muted} />
                            <Text style={s.vazio}>Nenhuma entrega nesta aba.</Text>
                        </Cartao>
                    ) : null
                }
                renderItem={({ item }) => <ItemPedido pedido={item} />}
            />
        </View>
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
            <Cartao style={{ gap: 10 }}>
                <View style={s.linhaTopo}>
                    <View style={{ flexDirection: "row", alignItems: "center", gap: 10 }}>
                        <View style={s.tile}>
                            <Package size={18} color={COLORS.primary} />
                        </View>
                        <Text style={s.numero}>#{pedido.id}</Text>
                    </View>
                    {pedido.situacao ? <Etiqueta texto={pedido.situacao} /> : null}
                </View>
                <Text style={s.cliente}>{pedido.cliente ?? "Cliente"}</Text>
                <View style={{ flexDirection: "row", alignItems: "center", gap: 4 }}>
                    <MapPin size={13} color={COLORS.muted} />
                    <Text style={s.endereco} numberOfLines={1}>
                        {pedido.endereco || "Endereço não informado"}
                    </Text>
                </View>
                <View style={s.rodape}>
                    <Text style={s.valor}>{moeda(pedido.valor_venda)}</Text>
                    <View style={{ flexDirection: "row", alignItems: "center", gap: 2 }}>
                        <Text style={s.detalhe}>Detalhes</Text>
                        <ChevronRight size={16} color={COLORS.primary} />
                    </View>
                </View>
            </Cartao>
        </TouchableOpacity>
    )
}

/** Filtra por aba usando o texto da situação (o backend expõe `situacao`). */
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
    titulo: { fontSize: fontSize.xl, fontWeight: "800", color: COLORS.text },
    abas: {
        flexDirection: "row", backgroundColor: COLORS.card, borderRadius: radius.md,
        padding: 4, borderWidth: 1, borderColor: COLORS.border,
    },
    aba: { flex: 1, paddingVertical: 8, borderRadius: 9, alignItems: "center" },
    abaOn: { backgroundColor: COLORS.primary },
    abaTexto: { fontSize: fontSize.sm, fontWeight: "700", color: COLORS.muted },
    abaTextoOn: { color: COLORS.white },
    tile: {
        width: 36, height: 36, borderRadius: radius.md,
        backgroundColor: "#FFF1E8", alignItems: "center", justifyContent: "center",
    },
    linhaTopo: { flexDirection: "row", justifyContent: "space-between", alignItems: "center" },
    numero: { fontSize: fontSize.base, fontWeight: "800", color: COLORS.primary },
    cliente: { fontSize: fontSize.base, fontWeight: "700", color: COLORS.text },
    endereco: { flex: 1, fontSize: fontSize.sm, color: COLORS.muted },
    rodape: {
        flexDirection: "row", justifyContent: "space-between", alignItems: "center",
        borderTopWidth: 1, borderTopColor: COLORS.border, paddingTop: 10, marginTop: 2,
    },
    valor: { fontSize: fontSize.md, fontWeight: "800", color: COLORS.graphite },
    detalhe: { fontSize: fontSize.sm, color: COLORS.primary, fontWeight: "700" },
    vazio: { fontSize: fontSize.sm, color: COLORS.muted },
})
