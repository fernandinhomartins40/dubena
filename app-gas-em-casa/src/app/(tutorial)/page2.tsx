import { useEffect } from "react"
import { ImageBackground, Platform, StyleSheet, Text, View } from "react-native"
import { defaultStyles, fontSize, screenPadding, utilsStyles } from "@/styles/theme"
import { SenhoraUri } from "@/constants/images"
import { LinearGradient } from "expo-linear-gradient"
import { MaterialCommunityIcons } from "@expo/vector-icons"
import Button from "@/components/atoms/button"
import { Href, useRouter } from "expo-router"

import * as Location from "expo-location"
import { useSafeAreaInsets } from "react-native-safe-area-context"

const Page2 = () => {
    const router = useRouter()
    const insets = useSafeAreaInsets()

    const requestPermissiontForLocation = async () => {
        await Location.requestForegroundPermissionsAsync()
    }

    useEffect(() => {
        requestPermissiontForLocation()
    }, [])

    const handleOnPress = () => {
        router.navigate("/(tutorial)/page3" as Href)
    }

    const renderButton = () => {
        if (Platform.OS === "ios") {
            return (
                <View style={utilsStyles.fullWidth}>
                    <Button title="continuar" onPress={handleOnPress} />
                </View>
            )
        }

        return <Button title="continuar" onPress={handleOnPress} />
    }

    return (
        <View style={defaultStyles.container}>
            <ImageBackground source={{ uri: SenhoraUri }} style={defaultStyles.image}>
                <View style={styles.container}>
                    <LinearGradient
                        colors={["#6E248040", "transparent"]}
                        style={styles.topGradient}
                        start={{ x: 0.5, y: 0.2 }}
                        end={{ x: 0.5, y: 0.5 }}
                    >
                        <View style={styles.header}>
                            <View>
                                <MaterialCommunityIcons
                                    name="map-marker-radius-outline"
                                    size={34}
                                    color="#FFF"
                                />
                            </View>
                            <View>
                                <Text style={{ fontSize: 30, color: "#FFF", fontWeight: 700 }}>
                                    Permitir Localização Automática
                                </Text>
                            </View>
                        </View>
                    </LinearGradient>

                    <LinearGradient
                        colors={["#00000070", "transparent"]}
                        style={[styles.bottomGradient, { paddingBottom: insets.bottom }]}
                        start={{ x: 0.5, y: 1 }}
                        end={{ x: 0.5, y: 0.5 }}
                    >
                        <View style={styles.footer}>
                            <Text
                                style={{
                                    fontSize: fontSize.base,
                                    color: "#FFF",
                                    fontWeight: 600,
                                }}
                            >
                                A localização automática garante que o gás chegue o mais rápido
                                possível.
                            </Text>

                            {renderButton()}
                        </View>
                    </LinearGradient>
                </View>
            </ImageBackground>
        </View>
    )
}

const styles = StyleSheet.create({
    container: {
        flex: 1,
        justifyContent: "center",
        alignItems: "center",
    },
    topGradient: {
        position: "absolute",
        left: 0,
        right: 0,
        top: 0,
        height: "50%",
        borderColor: "#000",
    },
    bottomGradient: {
        position: "absolute",
        left: 0,
        right: 0,
        bottom: 0,
        height: "50%",
    },
    header: {
        display: "flex",
        flexDirection: "column",
        justifyContent: "flex-start",
        alignItems: "flex-start",
        marginTop: 50,
        paddingHorizontal: screenPadding.horizontal,
    },
    footer: {
        flex: 1,
        display: "flex",
        justifyContent: "flex-end",
        alignItems: "flex-end",
        paddingHorizontal: screenPadding.horizontal,
        paddingBottom: Platform.select({
            ios: 35,
            default: 20,
        }),
        gap: 10,
    },
})

export default Page2
