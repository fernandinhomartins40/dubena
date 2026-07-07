import { Botao } from "@/components/ui"
import { BRASIL_VIEW, COLORS } from "@/constants/app"
import { decodePolyline } from "@/helpers/polyline"
import JornadaService from "@/services/jornada.service"
import { fontSize, radius, shadow } from "@/styles/theme"
import { useQuery } from "@tanstack/react-query"
import * as Location from "expo-location"
import { router } from "expo-router"
import { Crosshair, MapPin, X } from "lucide-react-native"
import { useEffect, useMemo, useRef, useState } from "react"
import { StyleSheet, Text, TouchableOpacity, View } from "react-native"
import MapView, { Marker, Polyline, PROVIDER_GOOGLE } from "react-native-maps"
import { useSafeAreaInsets } from "react-native-safe-area-context"

/**
 * NAVEGAÇÃO INTERNA (L6) — estilo app de mobilidade: mapa em tela cheia com o
 * traçado da rota (polyline da Routes API quando disponível; reta entre paradas
 * sem ela), posição do entregador AO VIVO seguindo no mapa, banner da próxima
 * parada com ETA/distância e ação de concluir. Nada de sair para o Google Maps.
 */
export default function Navegacao() {
    const { top, bottom } = useSafeAreaInsets()
    const mapa = useRef<MapView>(null)
    const [posicao, setPosicao] = useState<Location.LocationObjectCoords | null>(null)
    const [seguir, setSeguir] = useState(true)

    const { data } = useQuery({
        queryKey: ["entregador", "rota"],
        queryFn: JornadaService.Rota,
        refetchInterval: 20000,
    })

    const paradas = (data?.paradas ?? []).filter((p) => p.lat != null && p.lng != null)
    const proxima = paradas[0]

    // Traçado: polylines reais por trecho quando o backend mandou; senão, liga
    // posição → paradas com retas (melhora sozinho quando a Routes API ligar).
    const trechos = useMemo(() => {
        const reais = paradas
            .filter((p) => p.polyline)
            .map((p) => decodePolyline(p.polyline!))
        if (reais.length > 0) return reais

        const pontos = [
            ...(posicao ? [{ latitude: posicao.latitude, longitude: posicao.longitude }] : []),
            ...paradas.map((p) => ({ latitude: p.lat!, longitude: p.lng! })),
        ]
        return pontos.length >= 2 ? [pontos] : []
    }, [paradas, posicao])

    // Posição ao vivo: watch + câmera seguindo (heading/velocidade do GPS).
    useEffect(() => {
        let sub: Location.LocationSubscription | null = null
        let ativo = true
        ;(async () => {
            const perm = await Location.requestForegroundPermissionsAsync()
            if (!perm.granted || !ativo) return
            sub = await Location.watchPositionAsync(
                { accuracy: Location.Accuracy.BestForNavigation, timeInterval: 3000, distanceInterval: 10 },
                (loc) => {
                    setPosicao(loc.coords)
                },
            )
        })()
        return () => {
            ativo = false
            sub?.remove()
        }
    }, [])

    // Câmera acompanha o entregador enquanto "seguir" estiver ligado.
    useEffect(() => {
        if (!posicao || !seguir) return
        mapa.current?.animateCamera(
            {
                center: { latitude: posicao.latitude, longitude: posicao.longitude },
                heading: posicao.heading ?? 0,
                zoom: 16.5,
                pitch: 45,
            },
            { duration: 800 },
        )
    }, [posicao, seguir])

    // Âncora: próxima parada → posição do PRÓPRIO GPS → Brasil (viewport neutro).
    const ancora =
        proxima?.lat != null && proxima?.lng != null
            ? { latitude: proxima.lat, longitude: proxima.lng }
            : posicao
              ? { latitude: posicao.latitude, longitude: posicao.longitude }
              : null
    const regiao = ancora
        ? { ...ancora, latitudeDelta: 0.02, longitudeDelta: 0.02 }
        : BRASIL_VIEW

    return (
        <View style={{ flex: 1 }}>
            <MapView
                ref={mapa}
                style={StyleSheet.absoluteFillObject}
                provider={PROVIDER_GOOGLE}
                initialRegion={regiao}
                showsUserLocation
                showsMyLocationButton={false}
                showsCompass
                onPanDrag={() => setSeguir(false)}
            >
                {trechos.map((coords, i) => (
                    <Polyline
                        key={i}
                        coordinates={coords}
                        strokeWidth={5}
                        strokeColor={COLORS.primary}
                    />
                ))}
                {paradas.map((p, i) => (
                    <Marker
                        key={p.pedido_id}
                        coordinate={{ latitude: p.lat!, longitude: p.lng! }}
                        title={`${p.sequencia}. ${p.cliente ?? "Cliente"}`}
                        description={p.endereco}
                    >
                        <View style={[s.pin, i === 0 && { backgroundColor: COLORS.graphite }]}>
                            <Text style={s.pinTexto}>{p.sequencia}</Text>
                        </View>
                    </Marker>
                ))}
            </MapView>

            {/* Fechar */}
            <TouchableOpacity style={[s.fechar, { top: top + 12 }]} onPress={() => router.back()} hitSlop={10}>
                <X size={22} color={COLORS.text} />
            </TouchableOpacity>

            {/* Recentrar */}
            <TouchableOpacity
                style={[s.recentrar, { bottom: bottom + 190 }]}
                onPress={() => setSeguir(true)}
                hitSlop={10}
            >
                <Crosshair size={22} color={seguir ? COLORS.primary : COLORS.muted} />
            </TouchableOpacity>

            {/* Banner da próxima parada */}
            <View style={[s.banner, { paddingBottom: bottom + 14 }]}>
                {proxima ? (
                    <>
                        <View style={{ flexDirection: "row", alignItems: "center", gap: 10 }}>
                            <View style={s.bannerSeq}>
                                <Text style={s.bannerSeqTexto}>{proxima.sequencia}</Text>
                            </View>
                            <View style={{ flex: 1 }}>
                                <Text style={s.bannerCliente} numberOfLines={1}>
                                    {proxima.cliente ?? `Pedido #${proxima.pedido_id}`}
                                </Text>
                                <View style={{ flexDirection: "row", alignItems: "center", gap: 4 }}>
                                    <MapPin size={12} color={COLORS.muted} />
                                    <Text style={s.bannerEndereco} numberOfLines={1}>{proxima.endereco}</Text>
                                </View>
                            </View>
                            <View style={{ alignItems: "flex-end" }}>
                                {proxima.eta_min != null && (
                                    <Text style={s.bannerEta}>~{Math.round(proxima.eta_min)} min</Text>
                                )}
                                {proxima.distancia_trecho_km != null && (
                                    <Text style={s.bannerKm}>{proxima.distancia_trecho_km} km</Text>
                                )}
                            </View>
                        </View>
                        <Botao
                            titulo="Cheguei — concluir entrega"
                            onPress={() => router.push(`/(app)/pedido/${proxima.pedido_id}/concluir`)}
                        />
                    </>
                ) : (
                    <Text style={s.bannerVazio}>Nenhuma parada na rota. Volte e inicie a rota.</Text>
                )}
            </View>
        </View>
    )
}

const s = StyleSheet.create({
    pin: {
        backgroundColor: COLORS.primary, width: 28, height: 28, borderRadius: 999,
        alignItems: "center", justifyContent: "center", borderWidth: 2, borderColor: COLORS.white,
    },
    pinTexto: { color: COLORS.white, fontWeight: "800", fontSize: 12 },
    fechar: {
        position: "absolute", left: 16, width: 42, height: 42, borderRadius: 999,
        backgroundColor: COLORS.card, alignItems: "center", justifyContent: "center", ...shadow.card,
    },
    recentrar: {
        position: "absolute", right: 16, width: 46, height: 46, borderRadius: 999,
        backgroundColor: COLORS.card, alignItems: "center", justifyContent: "center", ...shadow.card,
    },
    banner: {
        position: "absolute", left: 0, right: 0, bottom: 0,
        backgroundColor: COLORS.card, borderTopLeftRadius: radius.xl, borderTopRightRadius: radius.xl,
        padding: 16, gap: 12, ...shadow.card,
    },
    bannerSeq: {
        width: 34, height: 34, borderRadius: 999, backgroundColor: "#FFF1E8",
        alignItems: "center", justifyContent: "center",
    },
    bannerSeqTexto: { color: COLORS.primary, fontWeight: "800" },
    bannerCliente: { fontSize: fontSize.md, fontWeight: "800", color: COLORS.text },
    bannerEndereco: { flex: 1, fontSize: fontSize.sm, color: COLORS.muted },
    bannerEta: { fontSize: fontSize.md, fontWeight: "800", color: COLORS.primary },
    bannerKm: { fontSize: fontSize.xs, color: COLORS.muted },
    bannerVazio: { textAlign: "center", color: COLORS.muted, fontSize: fontSize.sm, paddingVertical: 8 },
})
