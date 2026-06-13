import { useEffect } from "react"
import { ImageBackground, Platform, StyleSheet, Text, View } from "react-native"
import { SmartphoneImgUri } from "@/constants/images"
import { defaultStyles, fontSize, screenPadding, utilsStyles } from "@/styles/theme"
import { LinearGradient } from "expo-linear-gradient"
import { MaterialCommunityIcons } from "@expo/vector-icons"
import Button from "@/components/atoms/Button"
import * as Notifications from "expo-notifications"
import * as Device from "expo-device"
import messaging from "@react-native-firebase/messaging"
import { PermissionsAndroid } from "react-native"
import { useRouter } from "expo-router"
import useAppStore from "@/store/appStore"
import { useSafeAreaInsets } from "react-native-safe-area-context"

const Page3 = () => {
    const router = useRouter()
    const { setPermissions } = useAppStore()
    const insets = useSafeAreaInsets()

    const requestPermissionForNotifications = async () => {
        if (!Device.isDevice) return

        if (Platform.OS === "android") {
            await Notifications.setNotificationChannelAsync("default", {
                name: "default",
                importance: Notifications.AndroidImportance.MAX,
                vibrationPattern: [0, 250, 250, 250],
                lightColor: "#7a2c9e7C",
            })

            PermissionsAndroid.request(PermissionsAndroid.PERMISSIONS.POST_NOTIFICATIONS)
            await Notifications.requestPermissionsAsync()
        }

        if (Platform.OS === "ios") {
            await messaging().requestPermission()
        }
    }

    useEffect(() => {
        requestPermissionForNotifications()
    }, [])

    const handleOnPress = () => {
        setPermissions(true)

        router.replace("/login")
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
            <ImageBackground source={{ uri: SmartphoneImgUri }} style={defaultStyles.image}>
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
                                    name="bell-ring-outline"
                                    size={34}
                                    color="#FFF"
                                />
                            </View>
                            <Text style={{ fontSize: 32, color: "#FFF", fontWeight: 700 }}>
                                Fique informado em tempo real!
                            </Text>
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
                                Receba notificações sobre o status do seu pedido e saiba exatamente
                                quando o seu gás vai chegar.
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

export default Page3
