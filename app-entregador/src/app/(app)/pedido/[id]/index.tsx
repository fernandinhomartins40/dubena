import { Botao, Cartao } from "@/components/ui"
import { BRASIL_VIEW, COLORS } from "@/constants/app"
import { HttpError } from "@/helpers/http"
import EntregaService from "@/services/entrega.service"
import { PedidoEntrega } from "@/types/types"
import { useQuery, useQueryClient } from "@tanstack/react-query"
import { router, useLocalSearchParams } from "expo-router"
import { useState } from "react"
import { Linking, Platform, ScrollView, StyleSheet, Text, View } from "react-native"
import MapView, { Marker, PROVIDER_GOOGLE } from "react-native-maps"
import Toast from "react-native-toast-message"

const moeda = (v: number) => `R$ ${v.toFixed(2).replace(".", ",")}`

/**
 * Detalhe da entrega (P7) — mapa com o destino, dados do cliente e as ações do
 * ciclo: aceitar, recusar, registrar ocorrência e concluir (comprovação).
 *
 * O pedido vem do cache da lista (mesma queryKey); o app não tem um GET unitário
 * dedicado, então reaproveitamos o item já carregado em `entregador/pedidos`.
 */
export default function PedidoDetalhe() {
    const { id } = useLocalSearchParams<{ id: string }>()
    const pedidoId = Number(id)
    const qc = useQueryClient()
    const [acao, setAcao] = useState<"aceitar" | "recusar" | null>(null)

    const { data } = useQuery({
        queryKey: ["entregador", "pedidos"],
        queryFn: EntregaService.Pedidos,
    })
    const pedido = (data ?? []).find((p) => p.id === pedidoId)

    const temDestino = pedido?.lat != null && pedido?.lng != null
    // Sem destino geolocalizado, viewport neutro (Brasil) — nada de cidade fixa.
    const regiao = temDestino
        ? {
              latitude: pedido!.lat!,
              longitude: pedido!.lng!,
              latitudeDelta: 0.01,
              longitudeDelta: 0.01,
          }
        : BRASIL_VIEW

    const recarregar = () => qc.invalidateQueries({ queryKey: ["entregador", "pedidos"] })

    const aceitar = async () => {
        setAcao("aceitar")
        try {
            await EntregaService.Aceitar(pedidoId)
            Toast.show({ type: "success", text1: "Corrida aceita." })
            recarregar()
        } catch (e) {
            Toast.show({ type: "error", text1: (e as HttpError).message })
        } finally {
            setAcao(null)
        }
    }

    const recusar = async () => {
        setAcao("recusar")
        try {
            await EntregaService.Recusar(pedidoId)
            Toast.show({ type: "info", text1: "Corrida recusada." })
            recarregar()
            router.back()
        } catch (e) {
            Toast.show({ type: "error", text1: (e as HttpError).message })
        } finally {
            setAcao(null)
        }
    }

    const abrirNoMapa = () => {
        if (!temDestino) return
        const ll = `${pedido!.lat},${pedido!.lng}`
        const url = Platform.select({
            ios: `http://maps.apple.com/?daddr=${ll}`,
            default: `google.navigation:q=${ll}`,
        })
        Linking.openURL(url!).catch(() =>
            Linking.openURL(`https://www.google.com/maps/dir/?api=1&destination=${ll}`),
        )
    }

    if (!pedido) {
        return (
            <View style={s.center}>
                <Text style={{ color: COLORS.muted }}>Entrega não encontrada na lista.</Text>
            </View>
        )
    }

    return (
        <ScrollView contentContainerStyle={{ padding: 16, gap: 12 }}>
            <MapView
                style={s.mapa}
                provider={PROVIDER_GOOGLE}
                initialRegion={regiao}
                pointerEvents="none"
            >
                {temDestino && (
                    <Marker
                        coordinate={{ latitude: pedido.lat!, longitude: pedido.lng! }}
                        title={pedido.cliente ?? "Cliente"}
                        description={pedido.endereco}
                    />
                )}
            </MapView>

            <Cartao>
                <Text style={s.numero}>#{pedido.id}</Text>
                <Text style={s.cliente}>{pedido.cliente ?? "Cliente"}</Text>
                <Text style={s.endereco}>{pedido.endereco || "Endereço não informado"}</Text>
                <Text style={s.valor}>{moeda(pedido.valor_venda)}</Text>
                {temDestino && (
                    <View style={{ marginTop: 12 }}>
                        <Botao titulo="Navegar até o cliente" variante="secundario" onPress={abrirNoMapa} />
                    </View>
                )}
            </Cartao>

            <View style={{ gap: 10, marginTop: 4 }}>
                <Botao titulo="Aceitar corrida" onPress={aceitar} carregando={acao === "aceitar"} />
                <Botao
                    titulo="Concluir entrega"
                    variante="secundario"
                    onPress={() => router.push(`/(app)/pedido/${pedidoId}/concluir`)}
                />
                <Botao
                    titulo="Registrar ocorrência"
                    variante="secundario"
                    onPress={() => router.push(`/(app)/pedido/${pedidoId}/ocorrencia`)}
                />
                <Botao titulo="Recusar corrida" variante="perigo" onPress={recusar} carregando={acao === "recusar"} />
            </View>
        </ScrollView>
    )
}

const s = StyleSheet.create({
    center: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
    mapa: { height: 220, borderRadius: 14, overflow: "hidden" },
    numero: { fontSize: 16, fontWeight: "800", color: COLORS.primary },
    cliente: { fontSize: 18, fontWeight: "700", color: COLORS.text, marginTop: 6 },
    endereco: { fontSize: 14, color: COLORS.muted, marginTop: 4 },
    valor: { fontSize: 16, fontWeight: "700", color: COLORS.graphite, marginTop: 8 },
})
