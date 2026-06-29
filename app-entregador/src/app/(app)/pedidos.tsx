import { Cartao, Etiqueta } from "@/components/ui"
import { COLORS, PEDIDOS_POLL_MS } from "@/constants/app"
import AuthService from "@/services/auth.service"
import EntregaService from "@/services/entrega.service"
import useAppStore from "@/store/appStore"
import { PedidoEntrega } from "@/types/types"
import { useQuery } from "@tanstack/react-query"
import { router, useNavigation } from "expo-router"
import { useLayoutEffect } from "react"
import {
    FlatList,
    RefreshControl,
    StyleSheet,
    Switch,
    Text,
    TouchableOpacity,
    View,
} from "react-native"

const moeda = (v: number) => `R$ ${v.toFixed(2).replace(".", ",")}`

/** Lista das entregas do entregador + toggle "em serviço" (liga o GPS). */
export default function Pedidos() {
    const navigation = useNavigation()
    const emServico = useAppStore((s) => s.emServico)
    const setEmServico = useAppStore((s) => s.setEmServico)
    const logout = useAppStore((s) => s.logout)
    const user = useAppStore((s) => s.user)

    const { data, isLoading, refetch, isRefetching } = useQuery({
        queryKey: ["entregador", "pedidos"],
        queryFn: EntregaService.Pedidos,
        refetchInterval: PEDIDOS_POLL_MS,
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

    return (
        <View style={{ flex: 1 }}>
            <View style={s.servicoBar}>
                <View>
                    <Text style={s.servicoTitulo}>{user?.name ?? "Entregador"}</Text>
                    <Text style={s.servicoSub}>
                        {emServico ? "Em serviço · enviando posição" : "Fora de serviço"}
                    </Text>
                </View>
                <Switch
                    value={emServico}
                    onValueChange={setEmServico}
                    trackColor={{ true: COLORS.accent, false: COLORS.border }}
                    thumbColor={emServico ? COLORS.primary : "#FFF"}
                />
            </View>

            <FlatList
                data={data ?? []}
                keyExtractor={(p) => String(p.id)}
                contentContainerStyle={{ padding: 16, gap: 12 }}
                refreshControl={
                    <RefreshControl refreshing={isRefetching} onRefresh={refetch} />
                }
                ListEmptyComponent={
                    !isLoading ? (
                        <Text style={s.vazio}>Nenhuma entrega atribuída no momento.</Text>
                    ) : null
                }
                renderItem={({ item }) => <ItemPedido pedido={item} />}
            />
        </View>
    )
}

function ItemPedido({ pedido }: { pedido: PedidoEntrega }) {
    return (
        <TouchableOpacity
            activeOpacity={0.85}
            onPress={() => router.push(`/(app)/pedido/${pedido.id}`)}
        >
            <Cartao>
                <View style={s.linhaTopo}>
                    <Text style={s.numero}>#{pedido.id}</Text>
                    {pedido.situacao ? <Etiqueta texto={pedido.situacao} /> : null}
                </View>
                <Text style={s.cliente}>{pedido.cliente ?? "Cliente"}</Text>
                <Text style={s.endereco}>{pedido.endereco || "Endereço não informado"}</Text>
                <Text style={s.valor}>{moeda(pedido.valor_venda)}</Text>
            </Cartao>
        </TouchableOpacity>
    )
}

const s = StyleSheet.create({
    servicoBar: {
        flexDirection: "row",
        alignItems: "center",
        justifyContent: "space-between",
        backgroundColor: COLORS.card,
        paddingHorizontal: 16,
        paddingVertical: 14,
        borderBottomWidth: 1,
        borderBottomColor: COLORS.border,
    },
    servicoTitulo: { fontSize: 16, fontWeight: "700", color: COLORS.text },
    servicoSub: { fontSize: 13, color: COLORS.muted, marginTop: 2 },
    linhaTopo: { flexDirection: "row", justifyContent: "space-between", alignItems: "center" },
    numero: { fontSize: 16, fontWeight: "800", color: COLORS.primary },
    cliente: { fontSize: 16, fontWeight: "600", color: COLORS.text, marginTop: 8 },
    endereco: { fontSize: 14, color: COLORS.muted, marginTop: 2 },
    valor: { fontSize: 15, fontWeight: "700", color: COLORS.graphite, marginTop: 8 },
    vazio: { textAlign: "center", color: COLORS.muted, marginTop: 60, fontSize: 15 },
})
