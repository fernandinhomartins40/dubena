import { useCallback, useEffect, useState } from "react"
import {
    ActivityIndicator,
    FlatList,
    StyleSheet,
    Text,
    TouchableOpacity,
    View,
} from "react-native"
import { useRouter } from "expo-router"
import * as Location from "expo-location"
import { useSafeAreaInsets } from "react-native-safe-area-context"
import { MapPin, ChevronRight, Store, Building2 } from "lucide-react-native"
import useAppStore from "@/store/appStore"
import MarketplaceService, {
    CidadePlataforma,
    EmpresaMarketplace,
} from "@/services/marketplace.service"
import Button from "@/components/atoms/button"
import { colors, fontSize } from "@/styles/theme"

/**
 * Seleção de revenda (F7 — marketplace). Descobre as empresas que atendem o ponto
 * do usuário e ele escolhe a "loja ativa" ANTES do login.
 *
 * NENHUMA cidade é assumida em código: com GPS, busca pela posição real; sem GPS
 * (negado/indisponível), o usuário ESCOLHE a cidade do catálogo da plataforma
 * (marketplace/cidades) — escala para qualquer praça sem rebuild. Sem cobertura,
 * o app diz isso honestamente. A escolha aqui é UX: o servidor revalida a
 * cobertura na criação do pedido, e trocar de revenda invalida a sessão local.
 */

type Origem =
    | { tipo: "gps"; latitude: number; longitude: number }
    | { tipo: "cidade"; cidade: CidadePlataforma }

const SelecionarRevenda = () => {
    const router = useRouter()
    const insets = useSafeAreaInsets()
    const { setEmpresaAtiva } = useAppStore()

    const [empresas, setEmpresas] = useState<EmpresaMarketplace[] | null>(null)
    const [cidades, setCidades] = useState<CidadePlataforma[] | null>(null)
    const [origem, setOrigem] = useState<Origem | null>(null)
    const [buscando, setBuscando] = useState(true)
    const [escolhendoCidade, setEscolhendoCidade] = useState(false)
    const [erro, setErro] = useState<string | null>(null)

    const buscarEmpresas = useCallback(async (ponto: Origem) => {
        setErro(null)
        setBuscando(true)
        setEmpresas(null)
        setOrigem(ponto)
        try {
            const [lat, lng] =
                ponto.tipo === "gps"
                    ? [ponto.latitude, ponto.longitude]
                    : [ponto.cidade.latitude!, ponto.cidade.longitude!]
            setEmpresas(await MarketplaceService.GetEmpresas(lat, lng))
        } catch (err: any) {
            setErro(err?.message ?? "Não foi possível buscar as revendas.")
        } finally {
            setBuscando(false)
        }
    }, [])

    /** Abre o seletor de cidade (fluxo sem GPS / troca manual). */
    const abrirCidades = useCallback(async () => {
        setErro(null)
        setEscolhendoCidade(true)
        setBuscando(true)
        try {
            if (!cidades) {
                const lista = await MarketplaceService.GetCidades()
                // Só cidades com centro cadastrado ancoram uma busca.
                setCidades(lista.filter((c) => c.latitude != null && c.longitude != null))
            }
        } catch (err: any) {
            setErro(err?.message ?? "Não foi possível carregar as cidades.")
        } finally {
            setBuscando(false)
        }
    }, [cidades])

    /** 1ª carga: tenta o GPS; sem permissão/posição, cai para a escolha de cidade. */
    const iniciar = useCallback(async () => {
        setErro(null)
        setBuscando(true)
        try {
            const perm = await Location.requestForegroundPermissionsAsync()
            if (perm.granted) {
                const pos = await Promise.race([
                    Location.getCurrentPositionAsync({}),
                    new Promise<null>((resolve) => setTimeout(() => resolve(null), 6000)),
                ])
                if (pos) {
                    await buscarEmpresas({
                        tipo: "gps",
                        latitude: pos.coords.latitude,
                        longitude: pos.coords.longitude,
                    })
                    return
                }
            }
            // Sem GPS: o usuário escolhe a cidade — nunca assumimos uma.
            await abrirCidades()
        } catch (err: any) {
            setErro(err?.message ?? "Não foi possível obter sua localização.")
            setBuscando(false)
        }
    }, [buscarEmpresas, abrirCidades])

    useEffect(() => {
        iniciar()
    }, [iniciar])

    const escolherEmpresa = (empresa: EmpresaMarketplace) => {
        setEmpresaAtiva({
            id: empresa.id,
            nome: empresa.nome,
            distancia_km: empresa.distancia_km,
            tempo_entrega_min: empresa.tempo_entrega_min,
        })
        router.replace("/login")
    }

    const escolherCidade = (cidade: CidadePlataforma) => {
        setEscolhendoCidade(false)
        buscarEmpresas({ tipo: "cidade", cidade })
    }

    const subtitulo = escolhendoCidade
        ? "Escolha a sua cidade para ver as revendas que atendem lá."
        : origem?.tipo === "cidade"
          ? `Revendas que atendem ${origem.cidade.nome}/${origem.cidade.uf}.`
          : "Encontramos as revendas que atendem a sua região."

    return (
        <View style={[styles.container, { paddingTop: insets.top + 16 }]}>
            <View style={styles.header}>
                <Text style={styles.title}>
                    {escolhendoCidade ? "Escolha sua cidade" : "Escolha sua revenda"}
                </Text>
                <Text style={styles.subtitle}>{subtitulo}</Text>
            </View>

            {buscando && (
                <View style={styles.centro}>
                    <ActivityIndicator size="large" color={colors.primary} />
                    <Text style={styles.aviso}>
                        {escolhendoCidade ? "Carregando cidades…" : "Buscando revendas próximas…"}
                    </Text>
                </View>
            )}

            {!buscando && erro && (
                <View style={styles.centro}>
                    <Text style={styles.aviso}>{erro}</Text>
                    <Button title="Tentar novamente" onPress={iniciar} />
                </View>
            )}

            {/* ── Seletor de CIDADE (sem GPS / troca manual) ── */}
            {!buscando && !erro && escolhendoCidade && (
                <FlatList
                    data={cidades ?? []}
                    keyExtractor={(c) => String(c.id)}
                    contentContainerStyle={{ paddingBottom: insets.bottom + 24 }}
                    ListEmptyComponent={
                        <View style={styles.centro}>
                            <Text style={styles.aviso}>
                                A plataforma ainda não atende nenhuma cidade cadastrada.
                            </Text>
                        </View>
                    }
                    renderItem={({ item }) => (
                        <TouchableOpacity style={styles.card} onPress={() => escolherCidade(item)}>
                            <View style={styles.cardIcone}>
                                <Building2 size={22} color={colors.primary} />
                            </View>
                            <View style={{ flex: 1 }}>
                                <Text style={styles.cardNome}>
                                    {item.nome}/{item.uf}
                                </Text>
                            </View>
                            <ChevronRight size={20} color={colors.textMuted} />
                        </TouchableOpacity>
                    )}
                />
            )}

            {/* ── Lista de REVENDAS ── */}
            {!buscando && !erro && !escolhendoCidade && (
                <>
                    {empresas !== null && empresas.length === 0 && (
                        <View style={styles.centro}>
                            <Store size={40} color={colors.textMuted} />
                            <Text style={styles.aviso}>
                                {origem?.tipo === "cidade"
                                    ? `Nenhuma revenda atende ${origem.cidade.nome}/${origem.cidade.uf} por enquanto.`
                                    : "Nenhuma revenda atende a sua região por enquanto."}
                            </Text>
                            <Button title="Buscar em outra cidade" onPress={abrirCidades} />
                        </View>
                    )}

                    {empresas !== null && empresas.length > 0 && (
                        <FlatList
                            data={empresas}
                            keyExtractor={(e) => String(e.id)}
                            contentContainerStyle={{ paddingBottom: insets.bottom + 24 }}
                            ListFooterComponent={
                                <TouchableOpacity onPress={abrirCidades}>
                                    <Text style={styles.trocarCidade}>Buscar em outra cidade</Text>
                                </TouchableOpacity>
                            }
                            renderItem={({ item }) => (
                                <TouchableOpacity
                                    style={styles.card}
                                    onPress={() => escolherEmpresa(item)}
                                >
                                    <View style={styles.cardIcone}>
                                        <Store size={22} color={colors.primary} />
                                    </View>
                                    <View style={{ flex: 1 }}>
                                        <Text style={styles.cardNome}>{item.nome}</Text>
                                        <View style={styles.cardLinha}>
                                            <MapPin size={13} color={colors.textMuted} />
                                            <Text style={styles.cardDetalhe}>
                                                {item.distancia_km != null
                                                    ? `${item.distancia_km.toFixed(1)} km`
                                                    : "distância indisponível"}
                                                {item.tempo_entrega_min != null
                                                    ? ` · entrega ~${item.tempo_entrega_min} min`
                                                    : ""}
                                            </Text>
                                        </View>
                                    </View>
                                    <ChevronRight size={20} color={colors.textMuted} />
                                </TouchableOpacity>
                            )}
                        />
                    )}
                </>
            )}
        </View>
    )
}

const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: colors.background,
        paddingHorizontal: 20,
    },
    header: {
        marginBottom: 20,
    },
    title: {
        fontSize: fontSize.lg,
        fontWeight: "700",
        color: colors.text,
    },
    subtitle: {
        marginTop: 4,
        fontSize: fontSize.sm,
        color: colors.textMuted,
    },
    centro: {
        flex: 1,
        alignItems: "center",
        justifyContent: "center",
        gap: 14,
        paddingBottom: 60,
    },
    aviso: {
        fontSize: fontSize.base,
        color: colors.textMuted,
        textAlign: "center",
    },
    card: {
        flexDirection: "row",
        alignItems: "center",
        gap: 12,
        backgroundColor: colors.surface,
        borderRadius: 14,
        padding: 16,
        marginBottom: 10,
        borderWidth: StyleSheet.hairlineWidth,
        borderColor: colors.border,
    },
    cardIcone: {
        width: 40,
        height: 40,
        borderRadius: 12,
        alignItems: "center",
        justifyContent: "center",
        backgroundColor: colors.primaryMuted,
    },
    cardNome: {
        fontSize: fontSize.base,
        fontWeight: "600",
        color: colors.text,
    },
    cardLinha: {
        flexDirection: "row",
        alignItems: "center",
        gap: 4,
        marginTop: 3,
    },
    cardDetalhe: {
        fontSize: fontSize.sm,
        color: colors.textMuted,
    },
    trocarCidade: {
        textAlign: "center",
        paddingVertical: 14,
        color: colors.primary,
        fontWeight: "600",
        fontSize: fontSize.sm,
    },
})

export default SelecionarRevenda
