import { Botao, Cartao, Etiqueta } from "@/components/ui"
import { COLORS } from "@/constants/app"
import { useTrilhaMissao } from "@/hooks/useTrilhaMissao"
import MissaoService from "@/services/missao.service"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { router } from "expo-router"
import * as Location from "expo-location"
import { Compass, Footprints, Home as HomeIcon, MapPin, ShoppingBag, Timer } from "lucide-react-native"
import { useState } from "react"
import {
    ActivityIndicator, Alert, Linking, Platform, RefreshControl,
    ScrollView, StyleSheet, Text, View,
} from "react-native"
import { useSafeAreaInsets } from "react-native-safe-area-context"
import Toast from "react-native-toast-message"

const TIPO_LABEL: Record<string, string> = {
    panfletagem: "Panfletagem",
    visita_comercial: "Visita comercial",
    divulgacao_valegas: "Divulgação Vale Gás",
    prospeccao: "Prospecção",
    acao_promocional: "Ação promocional",
    campanha_bairro: "Campanha de bairro",
}

/**
 * Missão de campo (L7/L8) — hub: missão ativa, métricas, próxima casa sugerida,
 * registrar visita, vender, adiar (com motivo) e concluir. A trilha GPS roda em
 * lote enquanto a missão está em andamento.
 */
export default function MissaoScreen() {
    const { top } = useSafeAreaInsets()
    const qc = useQueryClient()
    const [sugestao, setSugestao] = useState<Awaited<ReturnType<typeof MissaoService.ProximaCasaSugestao>>>(null)
    const [buscando, setBuscando] = useState(false)

    const { data: missao, isLoading, refetch, isRefetching } = useQuery({
        queryKey: ["entregador", "missao"],
        queryFn: MissaoService.Atual,
        refetchInterval: 30000,
    })

    const emAndamento = missao?.status === "em_andamento"
    useTrilhaMissao(!!emAndamento)

    const recarregar = () => qc.invalidateQueries({ queryKey: ["entregador", "missao"] })

    const iniciar = useMutation({
        mutationFn: MissaoService.Iniciar,
        onSuccess: () => { Toast.show({ type: "success", text1: "Missão iniciada." }); recarregar() },
        onError: (e: any) => Toast.show({ type: "error", text1: e?.message ?? "Erro ao iniciar." }),
    })

    const concluir = useMutation({
        mutationFn: MissaoService.Concluir,
        onSuccess: () => { Toast.show({ type: "success", text1: "Missão concluída. Aguarde a revisão." }); recarregar() },
        onError: (e: any) => Toast.show({ type: "error", text1: e?.message ?? "Erro ao concluir." }),
    })

    const adiar = () => {
        const motivos: { label: string; valor: Parameters<typeof MissaoService.Adiar>[0] }[] = [
            { label: "Recebi nova entrega", valor: "nova_entrega" },
            { label: "Emergência", valor: "emergencia" },
            { label: "Problema no veículo", valor: "veiculo" },
            { label: "Clima", valor: "clima" },
            { label: "Outro", valor: "outro" },
        ]
        Alert.alert("Adiar missão", "Informe o motivo:", [
            ...motivos.map((m) => ({
                text: m.label,
                onPress: async () => {
                    try {
                        await MissaoService.Adiar(m.valor)
                        Toast.show({ type: "info", text1: "Adiamento solicitado (aguarda aprovação)." })
                        recarregar()
                    } catch (e: any) {
                        Toast.show({ type: "error", text1: e?.message ?? "Erro ao adiar." })
                    }
                },
            })),
            { text: "Cancelar", style: "cancel" as const },
        ])
    }

    const buscarProximaCasa = async () => {
        setBuscando(true)
        try {
            const perm = await Location.requestForegroundPermissionsAsync()
            if (!perm.granted) return
            const pos = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced })
            const casa = await MissaoService.ProximaCasaSugestao(pos.coords.latitude, pos.coords.longitude)
            setSugestao(casa)
            if (!casa) Toast.show({ type: "info", text1: "Nenhuma casa próxima para sugerir." })
        } catch (e: any) {
            Toast.show({ type: "error", text1: e?.message ?? "Erro ao buscar sugestão." })
        } finally {
            setBuscando(false)
        }
    }

    const navegarAte = (lat: number, lng: number) => {
        const ll = `${lat},${lng}`
        const url = Platform.select({ ios: `http://maps.apple.com/?daddr=${ll}`, default: `google.navigation:q=${ll}` })
        Linking.openURL(url!).catch(() => Linking.openURL(`https://www.google.com/maps/dir/?api=1&destination=${ll}`))
    }

    if (isLoading) {
        return <View style={s.center}><ActivityIndicator color={COLORS.primary} /></View>
    }

    if (!missao) {
        return (
            <View style={s.center}>
                <Compass size={40} color={COLORS.muted} />
                <Text style={s.vazioTitulo}>Sem missão no momento</Text>
                <Text style={s.vazioSub}>
                    Quando você ficar sem entregas, a central pode te enviar uma missão de campo
                    (panfletagem, visitas, prospecção). Ela aparecerá aqui.
                </Text>
            </View>
        )
    }

    const m = missao.metricas

    return (
        <ScrollView
            style={{ backgroundColor: COLORS.bg }}
            contentContainerStyle={{ paddingTop: top + 16, paddingHorizontal: 16, paddingBottom: 28, gap: 12 }}
            refreshControl={<RefreshControl refreshing={isRefetching} onRefresh={refetch} />}
            showsVerticalScrollIndicator={false}
        >
            <Text style={s.tituloPagina}>Missão de campo</Text>
            <Cartao style={{ gap: 8 }}>
                <View style={{ flexDirection: "row", justifyContent: "space-between", alignItems: "center" }}>
                    <Etiqueta texto={TIPO_LABEL[missao.missao.tipo] ?? missao.missao.tipo} />
                    <Text style={s.status}>{missao.status === "em_andamento" ? "Em andamento" : "Aguardando início"}</Text>
                </View>
                <Text style={s.titulo}>{missao.missao.titulo}</Text>
                {missao.missao.descricao ? <Text style={s.descricao}>{missao.missao.descricao}</Text> : null}
                {missao.missao.meta_visitas ? (
                    <Text style={s.meta}>Meta: {m.visitas_total}/{missao.missao.meta_visitas} visitas</Text>
                ) : null}
            </Cartao>

            {emAndamento && (
                <View style={{ flexDirection: "row", gap: 10 }}>
                    <Stat icone={<HomeIcon size={16} color={COLORS.primary} />} valor={m.visitas_total} label="Visitas" />
                    <Stat icone={<ShoppingBag size={16} color={COLORS.success} />} valor={m.vendas} label="Vendas" />
                    <Stat icone={<Footprints size={16} color={COLORS.graphite} />} valor={`${m.distancia_km}km`} label="Percurso" />
                    <Stat icone={<Timer size={16} color={COLORS.muted} />} valor={m.duracao_min ?? 0} label="Min" />
                </View>
            )}

            {emAndamento && sugestao && (
                <Cartao style={{ gap: 6, borderColor: COLORS.primary, borderWidth: 1.5 }}>
                    <Text style={s.sugestaoTitulo}>Próxima casa sugerida</Text>
                    <Text style={s.sugestaoNome}>{sugestao.nome}</Text>
                    <Text style={s.sugestaoEndereco}>
                        <MapPin size={12} color={COLORS.muted} /> {sugestao.endereco} · {Math.round(sugestao.distancia_m)} m
                    </Text>
                    <Botao titulo="Navegar até lá" variante="secundario" onPress={() => navegarAte(sugestao.lat, sugestao.lng)} />
                </Cartao>
            )}

            <View style={{ gap: 10 }}>
                {missao.status === "atribuida" && (
                    <Botao titulo="Iniciar missão" onPress={() => iniciar.mutate()} carregando={iniciar.isPending} />
                )}
                {emAndamento && (
                    <>
                        <Botao titulo={buscando ? "Buscando..." : "Sugerir próxima casa"} variante="secundario" onPress={buscarProximaCasa} desabilitado={buscando} />
                        <Botao titulo="Registrar visita" onPress={() => router.push({ pathname: "/(app)/missao-visita", params: sugestao ? { cliente_id: String(sugestao.cliente_id), nome: sugestao.nome } : {} })} />
                        <Botao titulo="Vender agora" variante="secundario" onPress={() => router.push({ pathname: "/(app)/missao-venda", params: sugestao ? { cliente_id: String(sugestao.cliente_id), nome: sugestao.nome } : {} })} />
                        <Botao titulo="Concluir missão" variante="secundario" onPress={() => concluir.mutate()} carregando={concluir.isPending} />
                        <Botao titulo="Adiar missão" variante="perigo" onPress={adiar} />
                    </>
                )}
            </View>
        </ScrollView>
    )
}

function Stat({ icone, valor, label }: { icone: React.ReactNode; valor: number | string; label: string }) {
    return (
        <Cartao style={{ flex: 1, alignItems: "center", gap: 2, paddingVertical: 10, paddingHorizontal: 4 }}>
            {icone}
            <Text style={s.statValor}>{valor}</Text>
            <Text style={s.statLabel}>{label}</Text>
        </Cartao>
    )
}

const s = StyleSheet.create({
    center: { flex: 1, alignItems: "center", justifyContent: "center", padding: 32, gap: 8, backgroundColor: COLORS.bg },
    tituloPagina: { fontSize: 22, fontWeight: "800", color: COLORS.text },
    vazioTitulo: { fontSize: 17, fontWeight: "700", color: COLORS.text },
    vazioSub: { fontSize: 14, color: COLORS.muted, textAlign: "center", lineHeight: 20 },
    status: { fontSize: 12, fontWeight: "700", color: COLORS.primary },
    titulo: { fontSize: 20, fontWeight: "800", color: COLORS.text },
    descricao: { fontSize: 14, color: COLORS.muted, lineHeight: 20 },
    meta: { fontSize: 13, fontWeight: "700", color: COLORS.graphite },
    statValor: { fontSize: 16, fontWeight: "800", color: COLORS.text },
    statLabel: { fontSize: 10, color: COLORS.muted },
    sugestaoTitulo: { fontSize: 12, fontWeight: "700", color: COLORS.primary },
    sugestaoNome: { fontSize: 16, fontWeight: "700", color: COLORS.text },
    sugestaoEndereco: { fontSize: 13, color: COLORS.muted },
})
