import { AvisoOffline, Botao, Cartao, FalhaAoCarregar } from "@/components/ui"
import { avisoOffline, comCache, eSemConexao, iniciarDoCache } from "@/helpers/cacheLocal"
import type { Rota } from "@/types/types"
import { BRASIL_VIEW, COLORS, TRACADO_ESTIMADO, TRACEJADO } from "@/constants/app"
import { decodePolyline } from "@/helpers/polyline"
import JornadaService from "@/services/jornada.service"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { router } from "expo-router"
import { Clock, MapPin, Navigation } from "lucide-react-native"
import { useMemo } from "react"
import { ActivityIndicator, Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from "react-native"
import MapView, { Marker, Polyline, PROVIDER_GOOGLE } from "react-native-maps"
import { useSafeAreaInsets } from "react-native-safe-area-context"
import Toast from "react-native-toast-message"

/**
 * Rota do dia (L6/F10) — a SEQUÊNCIA otimizada que o ERP calcula (o entregador não
 * escolhe aleatoriamente). "INICIAR ROTA" move as entregas para "Saiu para
 * entrega" (o cliente é avisado e passa a acompanhar). Mapa com o TRAÇADO da rota
 * + paradas numeradas + lista com ETA. "Navegar" abre a NAVEGAÇÃO INTERNA
 * (estilo app de mobilidade) — nunca sai para o Google Maps.
 * Vive nas TABS: título de página próprio, sem header do Stack.
 */
export default function RotaScreen() {
    const { top } = useSafeAreaInsets()
    const qc = useQueryClient()
    const consulta = useQuery({
        queryKey: ["entregador", "rota"],
        queryFn: comCache("rota", JornadaService.Rota),
        ...iniciarDoCache<Rota>("rota"),
        refetchInterval: 30000,
    })
    const { data, isLoading, refetch, isRefetching } = consulta

    const iniciar = useMutation({
        mutationFn: JornadaService.IniciarRota,
        onSuccess: (r) => {
            qc.invalidateQueries({ queryKey: ["entregador"] })
            Toast.show({
                type: r.iniciados > 0 ? "success" : "info",
                text1: r.iniciados > 0
                    ? `Rota iniciada — ${r.iniciados} entrega(s) a caminho.`
                    : "A rota já estava iniciada.",
            })
        },
        onError: (e: any) => Toast.show({ type: "error", text1: e?.message ?? "Erro ao iniciar a rota." }),
    })

    const paradas = data?.paradas ?? []
    const comGeo = paradas.filter((p) => p.lat != null && p.lng != null)
    const primeira = comGeo[0]

    // Sem parada geolocalizada ainda, o mapa mostra o Brasil (viewport neutro) —
    // nada de cidade fixa; assim que houver parada, enquadra nela.
    const regiao = primeira
        ? {
              latitude: primeira.lat!,
              longitude: primeira.lng!,
              latitudeDelta: 0.04,
              longitudeDelta: 0.04,
          }
        : BRASIL_VIEW

    // Traçado: polylines reais (Routes API) quando o backend mandou. Sem elas, a
    // reta entre os pontos é só uma ESTIMATIVA de direção — sai tracejada e cinza
    // para nunca ser confundida com o caminho pelas ruas (sólida, ela parecia rota
    // e cortava quarteirões).
    const trechos = useMemo(() => {
        const reais = comGeo.filter((p) => p.polyline).map((p) => decodePolyline(p.polyline!))
        if (reais.length > 0) return reais.map((coords) => ({ coords, real: true }))
        const pontos = comGeo.map((p) => ({ latitude: p.lat!, longitude: p.lng! }))
        return pontos.length >= 2 ? [{ coords: pontos, real: false }] : []
    }, [comGeo])

    /** Navegação INTERNA (tela cheia, posição ao vivo) — não sai do app. */
    const navegar = () => router.push("/(app)/navegacao")

    if (isLoading) {
        return <View style={s.center}><ActivityIndicator color={COLORS.primary} /></View>
    }

    if (paradas.length === 0 && consulta.isError) {
        return (
            <View style={[s.center, { padding: 16 }]}>
                <FalhaAoCarregar semConexao={eSemConexao(consulta.error)} onTentar={() => void refetch()} />
            </View>
        )
    }

    if (paradas.length === 0) {
        return (
            <View style={s.center}>
                <Navigation size={32} color={COLORS.muted} />
                <Text style={{ color: COLORS.muted, marginTop: 8 }}>Nenhuma entrega na rota agora.</Text>
            </View>
        )
    }

    return (
        <ScrollView
            style={{ backgroundColor: COLORS.bg }}
            contentContainerStyle={{ paddingTop: top + 16, paddingHorizontal: 16, paddingBottom: 28, gap: 12 }}
            refreshControl={<RefreshControl refreshing={isRefetching} onRefresh={refetch} />}
            showsVerticalScrollIndicator={false}
        >
            <Text style={s.tituloPagina}>Rota do dia</Text>
            <AvisoOffline texto={avisoOffline(consulta)} />

            <Botao
                titulo="Iniciar rota"
                // P2-C-14: dispara aviso a TODOS os clientes da rota — não pode
                // sair por um toque acidental.
                dica="Avisa todos os clientes desta rota que a entrega saiu"
                onPress={() =>
                    Alert.alert(
                        "Iniciar a rota agora?",
                        `Os ${paradas.length} cliente(s) da rota serão avisados de que a entrega saiu.`,
                        [
                            { text: "Ainda não", style: "cancel" },
                            { text: "Iniciar rota", onPress: () => iniciar.mutate() },
                        ],
                    )
                }
                carregando={iniciar.isPending}
            />
            {/* initialRegion só vale na montagem e as paradas chegam depois: sem a key,
                o minimapa ficava para sempre na vista do Brasil. Remonta quando a 1ª
                parada geolocalizada aparece (ou muda), não a cada atualização. */}
            <MapView
                key={primeira ? `${primeira.lat},${primeira.lng}` : "brasil"}
                style={s.mapa}
                provider={PROVIDER_GOOGLE}
                initialRegion={regiao}
                pointerEvents="none"
            >
                {trechos.map((t, i) => (
                    <Polyline
                        key={i}
                        coordinates={t.coords}
                        strokeWidth={t.real ? 4 : 3}
                        strokeColor={t.real ? COLORS.primary : TRACADO_ESTIMADO}
                        lineDashPattern={t.real ? undefined : TRACEJADO}
                    />
                ))}
                {comGeo.map((p) => (
                    <Marker key={p.pedido_id} coordinate={{ latitude: p.lat!, longitude: p.lng! }} title={`${p.sequencia}. ${p.cliente ?? "Cliente"}`} description={p.endereco}>
                        <View style={s.pin}><Text style={s.pinTexto}>{p.sequencia}</Text></View>
                    </Marker>
                ))}
            </MapView>

            <Cartao style={{ flexDirection: "row", justifyContent: "space-around" }}>
                <Resumo icone={<MapPin size={16} color={COLORS.primary} />} valor={`${data!.distancia_total_km} km`} label="Distância" />
                <Resumo icone={<Clock size={16} color={COLORS.primary} />} valor={`${Math.round(data!.duracao_total_min)} min`} label="Tempo estimado" />
                <Resumo icone={<Navigation size={16} color={COLORS.primary} />} valor={String(paradas.length)} label="Paradas" />
            </Cartao>

            {paradas.map((p, i) => (
                <Cartao key={p.pedido_id} style={i === 0 ? s.proxima : undefined}>
                    <View style={s.linha}>
                        <View style={[s.seq, i === 0 && { backgroundColor: COLORS.primary }]}>
                            <Text style={[s.seqTexto, i === 0 && { color: COLORS.white }]}>{p.sequencia}</Text>
                        </View>
                        <View style={{ flex: 1 }}>
                            <Text style={s.cliente}>{p.cliente ?? `Pedido #${p.pedido_id}`}</Text>
                            <Text style={s.endereco}>{p.endereco || "Endereço não informado"}</Text>
                            <Text style={s.meta}>
                                {p.eta_min != null ? `Chega em ~${Math.round(p.eta_min)} min` : "Sem estimativa"}
                                {p.distancia_trecho_km != null ? ` · ${p.distancia_trecho_km} km` : ""}
                            </Text>
                        </View>
                    </View>
                    {i === 0 && (
                        <View style={{ marginTop: 10, gap: 8 }}>
                            {p.lat != null && p.lng != null && (
                                <Botao titulo="Navegar (no app)" onPress={navegar} />
                            )}
                            <Botao
                                titulo="Detalhes / concluir entrega"
                                variante="secundario"
                                onPress={() => router.push(`/(app)/pedido/${p.pedido_id}`)}
                            />
                        </View>
                    )}
                </Cartao>
            ))}
        </ScrollView>
    )
}

function Resumo({ icone, valor, label }: { icone: React.ReactNode; valor: string; label: string }) {
    return (
        <View style={{ alignItems: "center", gap: 2 }}>
            {icone}
            <Text style={s.resumoValor}>{valor}</Text>
            <Text style={s.resumoLabel}>{label}</Text>
        </View>
    )
}

const s = StyleSheet.create({
    center: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24, backgroundColor: COLORS.bg },
    tituloPagina: { fontSize: 22, fontWeight: "800", color: COLORS.text },
    mapa: { height: 240, borderRadius: 14, overflow: "hidden" },
    pin: { backgroundColor: COLORS.primary, width: 26, height: 26, borderRadius: 999, alignItems: "center", justifyContent: "center", borderWidth: 2, borderColor: COLORS.white },
    pinTexto: { color: COLORS.white, fontWeight: "800", fontSize: 12 },
    resumoValor: { fontSize: 16, fontWeight: "800", color: COLORS.text },
    resumoLabel: { fontSize: 11, color: COLORS.muted },
    proxima: { borderColor: COLORS.primary, borderWidth: 1.5 },
    linha: { flexDirection: "row", gap: 12, alignItems: "flex-start" },
    seq: { width: 30, height: 30, borderRadius: 999, backgroundColor: "#FFF1E8", alignItems: "center", justifyContent: "center" },
    seqTexto: { fontWeight: "800", color: COLORS.primaryStrong },
    cliente: { fontSize: 16, fontWeight: "700", color: COLORS.text },
    endereco: { fontSize: 13, color: COLORS.muted, marginTop: 2 },
    meta: { fontSize: 12, color: COLORS.graphite, marginTop: 4, fontWeight: "600" },
})
