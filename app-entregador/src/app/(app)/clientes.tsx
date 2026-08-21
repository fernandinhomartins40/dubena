import { Botao, Campo, Cartao } from "@/components/ui"
import { COLORS } from "@/constants/app"
import VendaService, { type ClienteCampo } from "@/services/venda.service"
import { colors, fontSize, radius, spacing } from "@/styles/theme"
import { useQuery } from "@tanstack/react-query"
import { router, useLocalSearchParams } from "expo-router"
import { MapPin, UserPlus, Users } from "lucide-react-native"
import { useState } from "react"
import { FlatList, RefreshControl, StyleSheet, Text, TouchableOpacity, View } from "react-native"

/**
 * Buscar cliente — a porta de entrada da venda em campo.
 *
 * Sem esta tela a solicitação era inalcançável: ela exige `cliente_id` por
 * parâmetro e nada navegava até lá. É o equivalente do `ClienteBusca` do NFWEB.
 *
 * O parâmetro `destino` decide para onde vai depois de escolher — assim a mesma
 * tela serve a "vender", "solicitar" e a qualquer fluxo futuro que precise de um
 * cliente, em vez de duplicar a busca em cada um.
 */
export default function Clientes() {
    const params = useLocalSearchParams<{ destino?: string }>()
    const destino = params.destino ?? "/(app)/solicitar-venda"

    const [termo, setTermo] = useState("")
    const [busca, setBusca] = useState("")

    const { data, isLoading, isRefetching, refetch } = useQuery({
        queryKey: ["entregador", "clientes", busca],
        queryFn: () => VendaService.BuscarClientes(busca || undefined),
    })

    const clientes = data?.data ?? []

    const escolher = (c: ClienteCampo) =>
        router.push({ pathname: destino as never, params: { cliente_id: String(c.id), nome: c.nome } })

    return (
        <View style={{ flex: 1, backgroundColor: COLORS.bg }}>
            <FlatList
                data={clientes}
                keyExtractor={(c) => String(c.id)}
                contentContainerStyle={{ padding: 16, paddingBottom: 28, gap: 12 }}
                refreshControl={<RefreshControl refreshing={isRefetching} onRefresh={refetch} />}
                keyboardShouldPersistTaps="handled"
                ListHeaderComponent={
                    <View style={{ gap: 8, marginBottom: 4 }}>
                        <Campo
                            label="Buscar por nome ou documento"
                            value={termo}
                            onChangeText={setTermo}
                            onSubmitEditing={() => setBusca(termo)}
                            returnKeyType="search"
                            placeholder="Ex.: Padaria Central"
                            autoCorrect={false}
                        />
                        <Botao titulo="Buscar" onPress={() => setBusca(termo)} carregando={isLoading} />
                        <Botao
                            titulo="Cadastrar novo cliente"
                            variante="secundario"
                            onPress={() => router.push("/(app)/cliente-novo")}
                        />
                    </View>
                }
                ListEmptyComponent={
                    !isLoading ? (
                        <Cartao style={{ alignItems: "center", gap: 8, paddingVertical: 28 }}>
                            <Users size={32} color={COLORS.muted} />
                            <Text style={s.vazio}>
                                {busca ? "Nenhum cliente encontrado." : "Busque por nome ou documento."}
                            </Text>
                        </Cartao>
                    ) : null
                }
                renderItem={({ item }) => (
                    <TouchableOpacity activeOpacity={0.85} onPress={() => escolher(item)}>
                        <Cartao style={{ flexDirection: "row", alignItems: "center", gap: 12 }}>
                            <View style={s.tile}>
                                <UserPlus size={20} color={COLORS.primary} />
                            </View>
                            <View style={{ flex: 1 }}>
                                <Text style={s.nome} numberOfLines={1}>{item.nome}</Text>
                                {item.endereco !== "" && (
                                    <View style={s.linhaEndereco}>
                                        <MapPin size={12} color={COLORS.muted} />
                                        <Text style={s.sub} numberOfLines={1}>{item.endereco}</Text>
                                    </View>
                                )}
                                {item.documento !== "" && <Text style={s.sub}>{item.documento}</Text>}
                            </View>
                        </Cartao>
                    </TouchableOpacity>
                )}
            />
        </View>
    )
}

const s = StyleSheet.create({
    tile: {
        width: 40, height: 40, borderRadius: radius.md,
        backgroundColor: colors.primaryMuted,
        alignItems: "center", justifyContent: "center",
    },
    nome: { fontSize: fontSize.md, fontWeight: "700", color: COLORS.text },
    sub: { fontSize: fontSize.sm, color: COLORS.muted },
    linhaEndereco: { flexDirection: "row", alignItems: "center", gap: 4, marginTop: spacing.xs / 2 },
    vazio: { fontSize: fontSize.sm, color: COLORS.muted, textAlign: "center" },
})
