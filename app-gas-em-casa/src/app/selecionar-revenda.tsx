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
import { MapPin, ChevronRight, Store } from "lucide-react-native"
import useAppStore from "@/store/appStore"
import MarketplaceService, { EmpresaMarketplace } from "@/services/marketplace.service"
import Button from "@/components/atoms/button"
import { colors, fontSize } from "@/styles/theme"
import { DEFAULT_LOCATION } from "@/constants/app"

/**
 * Seleção de revenda (F7 — marketplace). Descobre por GPS as empresas que atendem
 * o ponto e o usuário escolhe a "loja ativa" ANTES do login — o app da plataforma
 * não nasce mais amarrado a uma empresa de build. A escolha aqui é UX: o servidor
 * revalida a cobertura na criação do pedido, e trocar de revenda invalida a
 * sessão local (token é por empresa).
 */
const SelecionarRevenda = () => {
    const router = useRouter()
    const insets = useSafeAreaInsets()
    const { setEmpresaAtiva } = useAppStore()
    const [empresas, setEmpresas] = useState<EmpresaMarketplace[] | null>(null)
    const [erro, setErro] = useState<string | null>(null)

    const carregar = useCallback(async () => {
        setErro(null)
        setEmpresas(null)
        try {
            let coords = DEFAULT_LOCATION
            const perm = await Location.requestForegroundPermissionsAsync()
            if (perm.granted) {
                const pos = await Promise.race([
                    Location.getCurrentPositionAsync({}),
                    new Promise<null>((resolve) => setTimeout(() => resolve(null), 6000)),
                ])
                if (pos) coords = { latitude: pos.coords.latitude, longitude: pos.coords.longitude }
            }

            const lista = await MarketplaceService.GetEmpresas(coords.latitude, coords.longitude)
            setEmpresas(lista)
        } catch (err: any) {
            setErro(err?.message ?? "Não foi possível buscar as revendas.")
        }
    }, [])

    useEffect(() => {
        carregar()
    }, [carregar])

    const escolher = (empresa: EmpresaMarketplace) => {
        setEmpresaAtiva({
            id: empresa.id,
            nome: empresa.nome,
            distancia_km: empresa.distancia_km,
            tempo_entrega_min: empresa.tempo_entrega_min,
        })
        router.replace("/login")
    }

    return (
        <View style={[styles.container, { paddingTop: insets.top + 16 }]}>
            <View style={styles.header}>
                <Text style={styles.title}>Escolha sua revenda</Text>
                <Text style={styles.subtitle}>
                    Encontramos as revendas que atendem a sua região.
                </Text>
            </View>

            {empresas === null && !erro && (
                <View style={styles.centro}>
                    <ActivityIndicator size="large" color={colors.primary} />
                    <Text style={styles.aviso}>Buscando revendas próximas…</Text>
                </View>
            )}

            {erro && (
                <View style={styles.centro}>
                    <Text style={styles.aviso}>{erro}</Text>
                    <Button title="Tentar novamente" onPress={carregar} />
                </View>
            )}

            {empresas !== null && empresas.length === 0 && (
                <View style={styles.centro}>
                    <Store size={40} color={colors.textMuted} />
                    <Text style={styles.aviso}>
                        Nenhuma revenda atende a sua região por enquanto.
                    </Text>
                    <Button title="Buscar novamente" onPress={carregar} />
                </View>
            )}

            {empresas !== null && empresas.length > 0 && (
                <FlatList
                    data={empresas}
                    keyExtractor={(e) => String(e.id)}
                    contentContainerStyle={{ paddingBottom: insets.bottom + 24 }}
                    renderItem={({ item }) => (
                        <TouchableOpacity style={styles.card} onPress={() => escolher(item)}>
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
})

export default SelecionarRevenda
