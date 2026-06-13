import useFlashStore from "@/store/flashStore"
import { colors, defaultStyles, fontSize, fontStyle, screenPadding } from "@/styles/theme"
import { Alert, Linking, Platform, ScrollView, StyleSheet, Text, View } from "react-native"
import FontAwesome6 from "@expo/vector-icons/FontAwesome6"
import MapView, { Marker, PROVIDER_DEFAULT, PROVIDER_GOOGLE } from "react-native-maps"
import IconButton from "@/components/atoms/IconButton"
import { useSafeAreaInsets } from "react-native-safe-area-context"

const InfoScreen = () => {
    const { store } = useFlashStore()
    const { bottom } = useSafeAreaInsets()

    const handleOnMapPress = () => {
        if (!store) return null

        if (!store.latitude || !store.longitude) return null

        // const url = `https://www.google.com/maps?q=${store.latitude},${store.longitude}`
        const scheme = Platform.select({
            ios: `maps://0,0?ll=${store.latitude},${store.longitude}`,
            android: `geo:${store.latitude},${store.longitude}?q=${store.latitude},${store.longitude}`,
        })

        if (scheme) {
            Linking.openURL(scheme).catch((_err) =>
                Alert.alert("Oops..", "Não foi possível abrir o mapa."),
            )
        }
    }

    const goToWhatsApp = () => {
        if (!store?.whatsapp) return

        Linking.openURL(`whatsapp://send?phone=${store.whatsapp}`)
    }

    const renderStore = () => {
        if (!store) {
            return (
                <View
                    style={{
                        flexDirection: "column",
                        paddingTop: 30,
                        paddingHorizontal: screenPadding.horizontal,
                    }}
                >
                    <Text style={{ fontSize: fontSize.base, ...fontStyle.semiBold }}>
                        Revenda encontra-se fechada no momento.
                    </Text>
                </View>
            )
        }

        return (
            <View
                style={{
                    flexDirection: "column",
                    paddingTop: 30,
                    paddingHorizontal: screenPadding.horizontal,
                }}
            >
                <View>
                    <Text style={{ fontSize: fontSize.base, ...fontStyle.semiBold }}>
                        {store.revenda_nome}
                    </Text>
                </View>

                <View style={styles.infoRow}>
                    <View style={styles.iconRow}>
                        <View style={styles.iconContainer}>
                            <FontAwesome6 name="phone" size={18} color={colors.primary} />
                        </View>

                        <Text style={{ fontSize: fontSize.sm, ...fontStyle.regular }}>
                            {store.telefone}
                        </Text>
                    </View>

                    <View style={styles.iconRow}>
                        <View style={styles.iconContainer}>
                            <FontAwesome6 name="clock" size={18} color={colors.primary} />
                        </View>

                        <Text style={{ fontSize: fontSize.sm, ...fontStyle.regular }}>
                            {store.delivery_res}
                        </Text>
                    </View>
                </View>

                <View style={styles.infoRow}>
                    <View style={styles.iconContainer}>
                        <FontAwesome6 name="calendar" size={18} color={colors.primary} />
                    </View>

                    <Text style={{ fontSize: fontSize.sm, ...fontStyle.regular }}>
                        Segunda à Sábado | {store.horariofuncionamento}
                    </Text>
                </View>

                <View style={styles.infoRow}>
                    <View style={styles.iconContainer}>
                        <FontAwesome6 name="calendar" size={18} color={colors.primary} />
                    </View>

                    <Text style={{ fontSize: fontSize.sm, ...fontStyle.regular }}>
                        Domingos e Feriados | {store.horariodom}
                    </Text>
                </View>

                <View style={styles.infoRow}>
                    <View style={styles.iconContainer}>
                        <FontAwesome6 name="location-dot" size={18} color={colors.primary} />
                    </View>

                    <Text selectable style={{ fontSize: fontSize.sm, ...fontStyle.regular }}>
                        {store.enderecocompleto}
                    </Text>
                </View>
            </View>
        )
    }

    const renderMap = () => {
        if (!store) return null

        if (!store.latitude || !store.longitude) return null

        return (
            <View
                style={{ height: 250, width: "100%", paddingHorizontal: screenPadding.horizontal }}
            >
                <MapView
                    style={{ flex: 1 }}
                    initialRegion={{
                        latitude: store.latitude,
                        longitude: store.longitude,
                        latitudeDelta: 0.0222,
                        longitudeDelta: 0.0221,
                    }}
                    provider={Platform.OS === "ios" ? PROVIDER_DEFAULT : PROVIDER_GOOGLE}
                    scrollEnabled={false}
                    zoomEnabled={false}
                >
                    <Marker
                        coordinate={{
                            latitude: store.latitude,
                            longitude: store.longitude,
                        }}
                        title="Aqui está a revenda!"
                        description="Clique para abrir no mapa"
                        onPress={handleOnMapPress}
                    />
                </MapView>
            </View>
        )
    }

    return (
        <View style={defaultStyles.container}>
            <ScrollView
                contentContainerStyle={[styles.container, { paddingBottom: 70 + bottom }]}
                showsVerticalScrollIndicator={false}
            >
                <View>
                    <Text style={[styles.title, fontStyle.semiBold]}>Sobre a Distribuidora</Text>
                </View>

                {renderStore()}

                {renderMap()}

                {store?.whatsapp ? (
                    <View style={{ marginTop: 15 }}>
                        <IconButton width={70} height={70} onPress={goToWhatsApp}>
                            <FontAwesome6 name="whatsapp" size={40} color="green" />
                        </IconButton>
                    </View>
                ) : (
                    ""
                )}
            </ScrollView>
        </View>
    )
}

const styles = StyleSheet.create({
    container: {
        flexDirection: "column",
        paddingTop: Platform.select({ ios: 50, default: 34 }),
        paddingHorizontal: screenPadding.horizontal,
    },
    title: {
        fontSize: fontSize.lg,
        textAlign: "center",
    },
    iconContainer: {
        height: 28,
        padding: 4,
        backgroundColor: colors.primaryMuted,
        borderRadius: 6,
    },
    iconRow: {
        flexDirection: "row",
        alignItems: "center",
        gap: 2,
    },
    infoRow: {
        paddingVertical: 10,
        flexDirection: "row",
        gap: 10,
    },
})

export default InfoScreen
