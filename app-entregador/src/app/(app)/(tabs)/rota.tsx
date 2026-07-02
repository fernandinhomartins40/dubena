import { Botao, Cartao } from "@/components/ui"
import { COLORS, DEFAULT_LOCATION } from "@/constants/app"
import JornadaService from "@/services/jornada.service"
import { Parada } from "@/types/types"
import { useQuery } from "@tanstack/react-query"
import { Clock, MapPin, Navigation } from "lucide-react-native"
import { ActivityIndicator, Linking, Platform, RefreshControl, ScrollView, StyleSheet, Text, View } from "react-native"
import MapView, { Marker, PROVIDER_GOOGLE } from "react-native-maps"
import { useSafeAreaInsets } from "react-native-safe-area-context"

/**
 * Rota do dia (L6/F10) — a SEQUÊNCIA otimizada que o ERP calcula (o entregador não
 * escolhe aleatoriamente). Mapa com paradas numeradas + lista ordenada com ETA +
 * "Navegar" (deep link para o Google Maps/Apple Maps) na próxima parada.
 * Vive nas TABS: título de página próprio, sem header do Stack.
 */
export default function RotaScreen() {
    const { top } = useSafeAreaInsets()
    const { data, isLoading, refetch, isRefetching } = useQuery({
        queryKey: ["entregador", "rota"],
        queryFn: JornadaService.Rota,
        refetchInterval: 30000,
    })

    const paradas = data?.paradas ?? []
    const comGeo = paradas.filter((p) => p.lat != null && p.lng != null)
    const primeira = comGeo[0]

    const regiao = {
        latitude: primeira?.lat ?? DEFAULT_LOCATION.latitude,
        longitude: primeira?.lng ?? DEFAULT_LOCATION.longitude,
        latitudeDelta: 0.04,
        longitudeDelta: 0.04,
    }

    const navegar = (p: Parada) => {
        if (p.lat == null || p.lng == null) return
        const ll = `${p.lat},${p.lng}`
        const url = Platform.select({ ios: `http://maps.apple.com/?daddr=${ll}`, default: `google.navigation:q=${ll}` })
        Linking.openURL(url!).catch(() =>
            Linking.openURL(`https://www.google.com/maps/dir/?api=1&destination=${ll}`),
        )
    }

    if (isLoading) {
        return <View style={s.center}><ActivityIndicator color={COLORS.primary} /></View>
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
            <MapView style={s.mapa} provider={PROVIDER_GOOGLE} initialRegion={regiao} pointerEvents="none">
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
                    {p.lat != null && p.lng != null && (
                        <View style={{ marginTop: 10 }}>
                            <Botao titulo={i === 0 ? "Navegar até a próxima" : "Navegar"} variante={i === 0 ? "primario" : "secundario"} onPress={() => navegar(p)} />
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
    seqTexto: { fontWeight: "800", color: COLORS.primary },
    cliente: { fontSize: 16, fontWeight: "700", color: COLORS.text },
    endereco: { fontSize: 13, color: COLORS.muted, marginTop: 2 },
    meta: { fontSize: 12, color: COLORS.graphite, marginTop: 4, fontWeight: "600" },
})
