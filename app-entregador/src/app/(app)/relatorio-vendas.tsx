import { Cartao } from "@/components/ui"
import { COLORS } from "@/constants/app"
import VendaService from "@/services/venda.service"
import { fontSize } from "@/styles/theme"
import { useQuery } from "@tanstack/react-query"
import { Receipt } from "lucide-react-native"
import { FlatList, RefreshControl, StyleSheet, Text, View } from "react-native"

const brl = (v: number) => `R$ ${Number(v).toFixed(2).replace(".", ",")}`

/**
 * Relatório de vendas — porta o `ReportPedidos` do NFWEB e o `getPedidosReport`
 * do MovelApp (os dois apps têm a mesma tela).
 *
 * O vendedor confere o próprio dia antes de encerrar. O total no topo é o que
 * ele checa primeiro; a lista existe para achar o pedido que não bate.
 */
export default function RelatorioVendas() {
    const { data, isLoading, isRefetching, refetch } = useQuery({
        queryKey: ["entregador", "relatorio-vendas"],
        queryFn: () => VendaService.RelatorioDeVendas(),
    })

    const r = data?.data

    return (
        <View style={{ flex: 1, backgroundColor: COLORS.bg }}>
            <FlatList
                data={r?.pedidos ?? []}
                keyExtractor={(p) => String(p.id)}
                contentContainerStyle={{ padding: 16, paddingBottom: 28, gap: 12 }}
                refreshControl={<RefreshControl refreshing={isRefetching} onRefresh={refetch} />}
                ListHeaderComponent={
                    <Cartao style={{ gap: 4, marginBottom: 4 }}>
                        <Text style={s.rotulo}>Total no período</Text>
                        <Text style={s.total}>{brl(r?.total ?? 0)}</Text>
                        <Text style={s.sub}>
                            {r?.quantidade ?? 0} {(r?.quantidade ?? 0) === 1 ? "venda" : "vendas"}
                            {r?.periodo && ` · ${r.periodo.inicio} a ${r.periodo.fim}`}
                        </Text>
                    </Cartao>
                }
                ListEmptyComponent={
                    !isLoading ? (
                        <Cartao style={{ alignItems: "center", gap: 8, paddingVertical: 28 }}>
                            <Receipt size={32} color={COLORS.muted} />
                            <Text style={s.sub}>Nenhuma venda concluída no período.</Text>
                        </Cartao>
                    ) : null
                }
                renderItem={({ item }) => (
                    <Cartao style={{ flexDirection: "row", alignItems: "center", gap: 12 }}>
                        <View style={{ flex: 1 }}>
                            <Text style={s.cliente} numberOfLines={1}>{item.cliente || "Cliente"}</Text>
                            <Text style={s.sub}>#{item.id} · {item.datahora}</Text>
                        </View>
                        <Text style={s.valor}>{brl(item.valor)}</Text>
                    </Cartao>
                )}
            />
        </View>
    )
}

const s = StyleSheet.create({
    rotulo: { fontSize: fontSize.sm, color: COLORS.muted, fontWeight: "600" },
    total: { fontSize: fontSize.xxl, fontWeight: "800", color: COLORS.text },
    cliente: { fontSize: fontSize.md, fontWeight: "700", color: COLORS.text },
    valor: { fontSize: fontSize.md, fontWeight: "800", color: COLORS.success },
    sub: { fontSize: fontSize.sm, color: COLORS.muted },
})
