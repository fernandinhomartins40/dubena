import { useEffect, useState } from "react"
import Button from "@/components/atoms/Button"
import { BackgroundImgUri, LogoWhiteImgUri } from "@/constants/images"
import useAppStore from "@/store/appStore"
import { colors, defaultStyles, fontSize, screenPadding } from "@/styles/theme"
import {
    ImageBackground,
    KeyboardAvoidingView,
    Platform,
    StyleSheet,
    Text,
    View,
} from "react-native"
import FastImage from "react-native-fast-image"
import useTimer from "@/hooks/useTimer"
import UserService from "@/services/user.service"
import { useRouter } from "expo-router"
import SmsInput from "@/components/atoms/SmsInput"
import { useMutation } from "@tanstack/react-query"
import ErrorView from "@/components/templates/ErrorView"
import { APP } from "@/constants/app"
import { getApp } from "@react-native-firebase/app"
import { getMessaging, getToken, requestPermission } from "@react-native-firebase/messaging"
import {
    getAuth,
    onAuthStateChanged,
    signInWithPhoneNumber,
    signOut,
} from "@react-native-firebase/auth"
import * as NavigationBar from "expo-navigation-bar"
import Toast from "react-native-toast-message"

const app = getApp()

const Sms = () => {
    const [timer, setTimer] = useTimer(1)
    const [code, setCode] = useState("")
    const [confirm, setConfirm] = useState<any>(null)
    const [isSubmitting, setIsSubmitting] = useState(false)
    const router = useRouter()
    const { loginData, apiToken, setToken, setUser } = useAppStore()
    const resendButtonDisabled = timer > 0
    const isDebug = APP.debug
    const {
        mutate: getApiToken,
        isPending: isTokenPending,
        error: tokenErr,
    } = useMutation({
        mutationFn: UserService.GetToken,
        onSuccess: (data) => {
            setToken(data.access_token)

            getClient({ fullName: loginData.name, phone: loginData.phone })
        },
        onError: (error) => {
            console.error("cc", error)
        },
    })
    const {
        mutate: setFcmPushToken,
        isPending: isFcmPending,
        error: fcmErr,
    } = useMutation({
        mutationFn: UserService.StoreFcmToken,
        onSuccess: () => {
            if (router.canDismiss()) router.dismissAll()

            router.replace("/(auth)/(tabs)/home")
        },
        onError: (error) => {
            console.error("bb", error)
        },
    })
    const {
        mutate: getClient,
        isPending: isClientPending,
        error: clientErr,
    } = useMutation({
        mutationFn: UserService.GetClient,
        onSuccess: (data) => {
            if ("status" in data) {
                if (router.canDismiss()) router.dismissAll()

                router.replace("/newuser")

                return
            }

            setUser(data)

            setFcmToken(data.id)
        },
        onError: (error) => {
            console.error("aa", error)
        },
    })

    useEffect(() => {
        if (!isDebug) {
            signOut(getAuth(app))
        }
    }, [])

    const isLoading = isTokenPending || isClientPending || isFcmPending

    if (Platform.OS === "android") NavigationBar.setButtonStyleAsync("dark")

    const setFcmToken = async (id: number) => {
        try {
            const messaging = getMessaging(app)

            await requestPermission(messaging)

            const fcmtoken = await getToken(messaging)

            setFcmPushToken({ client_id: id, token: fcmtoken })
        } catch (err) {
            console.error(err)
        }
    }

    const handleAuthStateChange = (firebaseUser: any) => {
        if (!firebaseUser && !isDebug) return

        if (!apiToken) {
            getApiToken()
        } else {
            getClient({ fullName: loginData.name, phone: loginData.phone })
        }

        setIsSubmitting(false)
    }

    const signWithPhone = async () => {
        try {
            const confirmation = await signInWithPhoneNumber(getAuth(app), "+55 " + loginData.phone)
            setConfirm(confirmation)
        } catch (error) {
            console.error(error)
        }
    }

    const confirmCode = async () => {
        if (isDebug) {
            setIsSubmitting(true)
            handleAuthStateChange(null)
            return
        }

        if (!code || code.length < 6 || isSubmitting) return

        setIsSubmitting(true)

        try {
            const result = await confirm.confirm(code)

            if (result?.user) {
                handleAuthStateChange(result.user)
            }
        } catch (error) {
            Toast.show({
                text1: "Erro!",
                text1Style: {
                    fontSize: 15,
                },
                text2: "Código de SMS Inválido.",
                text2Style: {
                    fontSize: 13,
                },
                type: "error",
            })
            setIsSubmitting(false)
        }
    }

    const resend = () => {
        setTimer(30)

        if (isDebug) return

        signWithPhone()
    }

    useEffect(() => {
        if (isDebug) return

        let unsubscribe: any

        if (confirm) unsubscribe = onAuthStateChanged(getAuth(app), handleAuthStateChange)

        return () => unsubscribe && unsubscribe()
    }, [confirm])

    useEffect(() => {
        setTimer(30)

        if (isDebug) return

        signWithPhone()
    }, [])

    if (fcmErr || tokenErr || clientErr) {
        return <ErrorView message="Algo deu errado.." />
    }

    return (
        <View style={defaultStyles.container}>
            <ImageBackground source={{ uri: BackgroundImgUri }} style={defaultStyles.image}>
                <View style={styles.container}>
                    <FastImage
                        source={{ uri: LogoWhiteImgUri, priority: FastImage.priority.normal }}
                        style={defaultStyles.logo}
                        resizeMode="contain"
                    />

                    <KeyboardAvoidingView
                        style={[defaultStyles.panel, { height: 320, alignItems: "center", gap: 4 }]}
                    >
                        <Text style={[{ fontSize: 20, paddingBottom: 8 }]}>Verificação de SMS</Text>
                        <Text style={styles.txtAlignCenter}>
                            Digite o código de 6 dígitos enviado para{" "}
                        </Text>
                        <Text style={[styles.txtAlignCenter, { fontSize: 16 }]}>
                            {loginData.phone}
                        </Text>

                        <View style={{ padding: 10 }}>
                            <SmsInput code={code} setCode={setCode} onSubmitEditing={confirmCode} />
                        </View>

                        <Button
                            disabled={isSubmitting || isLoading}
                            title="confirmar"
                            onPress={confirmCode}
                        />

                        <Text>Não recebeu o código?</Text>
                        <View
                            style={{
                                display: "flex",
                                flexDirection: "row",
                                alignItems: "center",
                                justifyContent: "center",
                            }}
                        >
                            <View style={{ width: 140 }}>
                                <Button
                                    uppercase={false}
                                    type="clear"
                                    textStyle={{ fontSize: fontSize.sm }}
                                    disabled={resendButtonDisabled}
                                    onPress={resend}
                                >
                                    <Text
                                        style={{
                                            color: resendButtonDisabled
                                                ? colors.textMuted
                                                : colors.primary,
                                        }}
                                    >
                                        Reenviar
                                        <Text style={{ color: "#000" }}> ({timer}s)</Text>
                                    </Text>
                                </Button>
                            </View>
                        </View>
                    </KeyboardAvoidingView>
                </View>
            </ImageBackground>
        </View>
    )
}

const styles = StyleSheet.create({
    container: {
        flex: 1,
        display: "flex",
        justifyContent: "center",
        alignItems: "center",
        paddingHorizontal: screenPadding.horizontal,
    },
    txtAlignCenter: { textAlign: "center" },
})

export default Sms
