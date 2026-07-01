import { colors, fontSize, fontStyle, radius, shadow } from "@/styles/theme"
import { Alert, Linking, Platform, Pressable, ScrollView, StyleSheet, Text, View } from "react-native"
import MapView, { Marker, PROVIDER_DEFAULT, PROVIDER_GOOGLE } from "react-native-maps"
import { Phone, Clock, MapPin, MessageCircle, Store } from "lucide-react-native"
import { useSafeAreaInsets } from "react-native-safe-area-context"
import { useQuery } from "@tanstack/react-query"
import StoreService from "@/services/store.service"

const InfoScreen = () => {
    const { top, bottom } = useSafeAreaInsets()
    const { data: store } = useQuery({
        queryKey: ["reseller"],
        queryFn: () => StoreService.GetReseller(),
    })

    const abrirMapa = () => {
        if (!store?.latitude || !store?.longitude) return
        const scheme = Platform.select({
            ios: `maps://0,0?ll=${store.latitude},${store.longitude}`,
            android: `geo:${store.latitude},${store.longitude}?q=${store.latitude},${store.longitude}`,
        })
        if (scheme) Linking.openURL(scheme).catch(() => Alert.alert("Ops..", "Não foi possível abrir o mapa."))
    }

    const abrirWhatsApp = () => {
        if (!store?.whatsapp) return
        Linking.openURL(`whatsapp://send?phone=${store.whatsapp}`).catch(() =>
            Alert.alert("Ops..", "Instale o WhatsApp para falar com a revenda."),
        )
    }

    const ligar = () => {
        if (!store?.telefone) return
        Linking.openURL(`tel:${store.telefone}`)
    }

    return (
        <View style={styles.screen}>
            <ScrollView
                contentContainerStyle={{ paddingTop: top + 16, paddingBottom: 90 + bottom }}
                showsVerticalScrollIndicator={false}
            >
                <Text style={styles.pageTitle}>Ajuda & Revenda</Text>

                {/* Card da revenda */}
                <View style={styles.card}>
                    <View style={styles.storeHeader}>
                        <View style={styles.storeIcon}>
                            <Store size={22} color={colors.primary} strokeWidth={2} />
                        </View>
                        <Text style={styles.storeName} numberOfLines={2}>
                            {store?.nome ?? "Carregando revenda…"}
                        </Text>
                    </View>

                    {store?.telefone ? (
                        <Pressable style={styles.infoRow} onPress={ligar}>
                            <Phone size={18} color={colors.primary} strokeWidth={2} />
                            <Text style={styles.infoText}>{store.telefone}</Text>
                        </Pressable>
                    ) : null}

                    {store?.tempo_entrega_min ? (
                        <View style={styles.infoRow}>
                            <Clock size={18} color={colors.primary} strokeWidth={2} />
                            <Text style={styles.infoText}>
                                Entrega em ~{store.tempo_entrega_min} min
                            </Text>
                        </View>
                    ) : null}
                </View>

                {/* Mapa */}
                {store?.latitude && store?.longitude ? (
                    <Pressable style={styles.mapCard} onPress={abrirMapa}>
                        <MapView
                            style={styles.map}
                            initialRegion={{
                                latitude: store.latitude,
                                longitude: store.longitude,
                                latitudeDelta: 0.0222,
                                longitudeDelta: 0.0221,
                            }}
                            provider={Platform.OS === "ios" ? PROVIDER_DEFAULT : PROVIDER_GOOGLE}
                            scrollEnabled={false}
                            zoomEnabled={false}
                            pointerEvents="none"
                        >
                            <Marker coordinate={{ latitude: store.latitude, longitude: store.longitude }} />
                        </MapView>
                        <View style={styles.mapHint}>
                            <MapPin size={16} color={colors.primary} strokeWidth={2} />
                            <Text style={styles.mapHintText}>Toque para abrir a rota</Text>
                        </View>
                    </Pressable>
                ) : null}

                {/* WhatsApp — CTA de ajuda */}
                {store?.whatsapp ? (
                    <Pressable style={styles.whatsBtn} onPress={abrirWhatsApp}>
                        <MessageCircle size={22} color={colors.white} strokeWidth={2.2} />
                        <Text style={styles.whatsText}>Falar no WhatsApp</Text>
                    </Pressable>
                ) : null}
            </ScrollView>
        </View>
    )
}

const styles = StyleSheet.create({
    screen: { flex: 1, backgroundColor: colors.background },
    pageTitle: { fontSize: fontSize.xl, color: colors.text, ...fontStyle.bold, paddingHorizontal: 16 },
    card: {
        marginHorizontal: 16,
        marginTop: 16,
        padding: 16,
        borderRadius: radius.lg,
        backgroundColor: colors.surface,
        ...shadow.card,
    },
    storeHeader: { flexDirection: "row", alignItems: "center", gap: 12, marginBottom: 6 },
    storeIcon: {
        width: 42,
        height: 42,
        borderRadius: radius.md,
        backgroundColor: colors.primaryMuted,
        alignItems: "center",
        justifyContent: "center",
    },
    storeName: { flex: 1, fontSize: fontSize.md, color: colors.text, ...fontStyle.bold },
    infoRow: { flexDirection: "row", alignItems: "center", gap: 10, paddingVertical: 8 },
    infoText: { fontSize: fontSize.sm, color: colors.text, ...fontStyle.regular },
    mapCard: {
        marginHorizontal: 16,
        marginTop: 16,
        borderRadius: radius.lg,
        overflow: "hidden",
        backgroundColor: colors.surface,
        ...shadow.card,
    },
    map: { height: 200, width: "100%" },
    mapHint: {
        flexDirection: "row",
        alignItems: "center",
        gap: 6,
        padding: 12,
    },
    mapHintText: { fontSize: 13, color: colors.textMuted, ...fontStyle.medium },
    whatsBtn: {
        flexDirection: "row",
        alignItems: "center",
        justifyContent: "center",
        gap: 10,
        marginHorizontal: 16,
        marginTop: 18,
        paddingVertical: 15,
        borderRadius: radius.lg,
        backgroundColor: "#25D366",
    },
    whatsText: { fontSize: fontSize.sm, color: colors.white, ...fontStyle.bold },
})

export default InfoScreen
