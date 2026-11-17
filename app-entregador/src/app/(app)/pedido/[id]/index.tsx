import { AvisoOffline, Botao, Cartao, FalhaAoCarregar } from "@/components/ui"
import { avisoOffline, comCache, eSemConexao, iniciarDoCache } from "@/helpers/cacheLocal"
import { acaoPendenteDoPedido } from "@/helpers/envioResiliente"
import { BRASIL_VIEW, COLORS } from "@/constants/app"
import { HttpError } from "@/helpers/http"
import EntregaService from "@/services/entrega.service"
import { PedidoEntrega } from "@/types/types"
import { useQuery, useQueryClient } from "@tanstack/react-query"
import { router, useLocalSearchParams } from "expo-router"
import { useState } from "react"
import { Alert, Linking, Platform, ScrollView, StyleSheet, Text, View } from "react-native"
import { etapaDoPedido } from "@/helpers/situacao"
import MapView, { Marker, PROVIDER_GOOGLE } from "react-native-maps"
import Toast from "react-native-toast-message"
import { formatarMoeda } from "@/helpers/moeda"

const moeda = formatarMoeda

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

    const consulta = useQuery({
        queryKey: ["entregador", "pedidos"],
        queryFn: comCache("pedidos", EntregaService.Pedidos),
        ...iniciarDoCache<PedidoEntrega[]>("pedidos"),
    })
    const { data } = consulta
    const pedido = (data ?? []).find((p) => p.id === pedidoId)
    // P2-C-05/P2-C-14: conclusão guardada sem sinal — não oferecer de novo.
    const conclusaoGuardada = acaoPendenteDoPedido(pedidoId, "concluir")

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
            const r = await EntregaService.Aceitar(pedidoId)
            // P2-C-14: "guardado" não é "feito" — o entregador precisa saber.
            Toast.show(
                r.enfileirado
                    ? { type: "info", text1: "Sem sinal: aceite guardado.", text2: "Vai quando a internet voltar." }
                    : { type: "success", text1: "Corrida aceita." },
            )
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
            const r = await EntregaService.Recusar(pedidoId)
            Toast.show({
                type: "info",
                text1: r.enfileirado ? "Sem sinal: recusa guardada." : "Corrida recusada.",
                ...(r.enfileirado ? { text2: "A central recebe quando a internet voltar." } : {}),
            })
            recarregar()
            router.back()
        } catch (e) {
            Toast.show({ type: "error", text1: (e as HttpError).message })
        } finally {
            setAcao(null)
        }
    }

    const confirmarRecusa = () =>
        Alert.alert("Recusar esta corrida?", "O pedido volta para a central redistribuir.", [
            { text: "Não", style: "cancel" },
            { text: "Recusar", style: "destructive", onPress: () => void recusar() },
        ])

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
        // P2-C-10: sem sinal e sem nada guardado, dizer isso — não "não encontrada".
        if (consulta.isError) {
            return (
                <View style={s.center}>
                    <FalhaAoCarregar semConexao={eSemConexao(consulta.error)} onTentar={() => void consulta.refetch()} />
                </View>
            )
        }
        return (
            <View style={s.center}>
                <Text style={{ color: COLORS.muted }}>
                    {consulta.isLoading ? "Carregando…" : "Esta entrega não está mais atribuída a você."}
                </Text>
            </View>
        )
    }

    return (
        <ScrollView contentContainerStyle={{ padding: 16, gap: 12 }}>
            <AvisoOffline texto={avisoOffline(consulta)} />
            <MapView
                // Mesma armadilha do minimapa da rota: o pedido chega depois da
                // montagem e initialRegion não reage. Remonta quando o destino aparece.
                key={temDestino ? `${pedido!.lat},${pedido!.lng}` : "brasil"}
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

            {/* PIE P2-C-01/P2-C-14: ações só para pedido em aberto. No Histórico,
                "Recusar" tirava a venda já concluída do malote do entregador. */}
            {conclusaoGuardada ? (
                <Cartao>
                    <Text style={{ color: COLORS.text, fontWeight: "700" }}>Conclusão guardada no aparelho.</Text>
                    <Text style={{ color: COLORS.muted, marginTop: 4 }}>
                        Ela é enviada sozinha quando a internet voltar. Não precisa concluir de novo.
                    </Text>
                </Cartao>
            ) : etapaDoPedido(pedido) !== "finalizado" ? (
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
                    <Botao titulo="Recusar corrida" variante="perigo" onPress={confirmarRecusa} carregando={acao === "recusar"} />
                </View>
            ) : (
                <Text style={{ color: COLORS.muted, textAlign: "center" }}>Pedido encerrado: {pedido.situacao ?? ""}</Text>
            )}
        </ScrollView>
    )
}

const s = StyleSheet.create({
    center: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
    mapa: { height: 220, borderRadius: 14, overflow: "hidden" },
    numero: { fontSize: 16, fontWeight: "800", color: COLORS.primaryStrong },
    cliente: { fontSize: 18, fontWeight: "700", color: COLORS.text, marginTop: 6 },
    endereco: { fontSize: 14, color: COLORS.muted, marginTop: 4 },
    valor: { fontSize: 16, fontWeight: "700", color: COLORS.graphite, marginTop: 8 },
})
