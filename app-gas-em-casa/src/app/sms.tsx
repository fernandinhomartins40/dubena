import { useEffect, useState } from "react"
import Button from "@/components/atoms/button"
import { LogoWhiteImgUri } from "@/constants/images"
import useAppStore from "@/store/appStore"
import { colors, defaultStyles, fontSize, screenPadding } from "@/styles/theme"
import {
    KeyboardAvoidingView,
    Platform,
    StyleSheet,
    Text,
    View,
} from "react-native"
import BrandGradient from "@/components/atoms/BrandGradient"
import FastImage from "react-native-fast-image"
import useTimer from "@/hooks/useTimer"
import UserService from "@/services/user.service"
import { useRouter } from "expo-router"
import SmsInput from "@/components/atoms/smsInput"
import { useMutation } from "@tanstack/react-query"
import ErrorView from "@/components/templates/errorview"
import { APP } from "@/constants/app"
import { getApp } from "@react-native-firebase/app"
import { getMessaging, getToken, requestPermission } from "@react-native-firebase/messaging"
import { getAuth, signInWithPhoneNumber, signOut } from "@react-native-firebase/auth"
import * as NavigationBar from "expo-navigation-bar"
import Toast from "react-native-toast-message"

const app = getApp()

const Sms = () => {
    const [timer, setTimer] = useTimer(1)
    const [code, setCode] = useState("")
    const [confirm, setConfirm] = useState<any>(null)
    const [isSubmitting, setIsSubmitting] = useState(false)
    const router = useRouter()
    const { loginData, setToken, setUser } = useAppStore()
    const resendButtonDisabled = timer > 0
    const isDebug = APP.debug

    /**
     * F1: login real do cliente. Recebe o ID token do Firebase (telefone já verificado
     * por SMS), coleta o device/push token e autentica no ERP-NOVO, que devolve o token
     * Sanctum do usuário. Sem cliente cadastrado → vai para o cadastro (newuser).
     */
    const {
        mutate: doLogin,
        isPending: isLoggingIn,
        error: loginErr,
    } = useMutation({
        mutationFn: UserService.Login,
        onSuccess: (data) => {
            setToken(data.token)
            setUser(data.user as any)

            if (router.canDismiss()) router.dismissAll()
            router.replace("/(auth)/(tabs)/home")
        },
        onError: (error: any) => {
            // 422 = telefone não encontrado nesta empresa → fluxo de cadastro.
            if (error?.status === 422) {
                if (router.canDismiss()) router.dismissAll()
                router.replace("/newuser")
                return
            }
            console.error("login", error)
        },
    })

    useEffect(() => {
        if (!isDebug) {
            signOut(getAuth(app))
        }
    }, [])

    const isLoading = isLoggingIn

    if (Platform.OS === "android") NavigationBar.setButtonStyleAsync("dark")

    /** Coleta o push token do FCM (best-effort; não bloqueia o login se falhar). */
    const collectPushToken = async (): Promise<string | undefined> => {
        try {
            const messaging = getMessaging(app)
            await requestPermission(messaging)
            return await getToken(messaging)
        } catch (err) {
            console.error("fcm", err)
            return undefined
        }
    }

    /** Após verificar o SMS, pega o ID token do Firebase e faz o login no ERP-NOVO. */
    const finishLogin = async (firebaseUser: any) => {
        try {
            if (!APP.empresa_id) {
                throw new Error("EMPRESA_ID não configurada para este build.")
            }

            const idToken: string = isDebug
                ? `fake:+55${loginData.phone.replace(/\D/g, "")}`
                : await firebaseUser.getIdToken()

            const pushToken = await collectPushToken()

            doLogin({
                firebase_id_token: idToken,
                empresa_id: APP.empresa_id,
                push_token: pushToken,
                device_id: pushToken?.slice(0, 64),
                plataforma: Platform.OS === "ios" ? "ios" : "android",
            })
        } catch (err) {
            console.error("finishLogin", err)
        } finally {
            setIsSubmitting(false)
        }
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
            finishLogin(null)
            return
        }

        if (!code || code.length < 6 || isSubmitting) return

        setIsSubmitting(true)

        try {
            const result = await confirm.confirm(code)

            if (result?.user) {
                finishLogin(result.user)
            } else {
                setIsSubmitting(false)
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
        setTimer(30)

        if (isDebug) return

        signWithPhone()
    }, [])

    if (loginErr && (loginErr as any)?.status !== 422) {
        return <ErrorView message="Algo deu errado.." />
    }

    return (
        <View style={defaultStyles.container}>
            <BrandGradient>
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
            </BrandGradient>
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
