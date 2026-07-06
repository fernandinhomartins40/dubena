import { APP, INTERNAL_BUILD_NUMBER } from "@/constants/app"
import UserService from "@/services/user.service"
import useAppStore from "@/store/appStore"
import { defaultStyles, fontSize, screenPadding } from "@/styles/theme"
import { UserFormSchema } from "@/types/types"
import { useMutation } from "@tanstack/react-query"
import React from "react"
import { Alert, Platform, StyleSheet, Text, View } from "react-native"
import { getMessaging, getToken, requestPermission } from "@react-native-firebase/messaging"
import { getAuth } from "@react-native-firebase/auth"
import Toast from "react-native-toast-message"
import { useRouter } from "expo-router"
import UserForm from "@/components/templates/UserForm"
import { getApp } from "@react-native-firebase/app"

const app = getApp()

/**
 * Cadastro de novo cliente (F3b). Reusa o login Firebase já feito (SMS): pega um ID
 * token fresco do usuário atual e chama POST app/v1/cliente/cadastro, que cria o
 * cliente + vincula o usuário e devolve o token Sanctum.
 */
const NewUser = () => {
    const { loginData, setToken, setUser, empresaAtiva } = useAppStore()
    const router = useRouter()
    const isDebug = APP.debug
    // F7: empresa da revenda escolhida no marketplace; build white-label é o default.
    const empresaId = empresaAtiva?.id ?? APP.empresa_id

    const { mutate, isPending } = useMutation({
        mutationFn: UserService.Cadastro,
        onSuccess: (data) => {
            setToken(data.token)
            setUser(data.user as any)

            Toast.show({
                type: "success",
                text1: "Sucesso",
                text1Style: { fontSize: 18 },
                text2: `Seja bem-vindo!`,
                text2Style: { fontSize: 16 },
            })

            router.replace("/(auth)/(tabs)/home")
        },
        onError: (err: any) => {
            Alert.alert("Oops..", err?.message ?? "Não foi possível concluir o cadastro.")
        },
    })

    const form: UserFormSchema = {
        nome: loginData.name,
        telefone: loginData.phone,
        conveniado: false,
        cpf: "",
        datanascimento: "",
        internal_build_number: INTERNAL_BUILD_NUMBER,
        pushregistration_id: "",
        sexo: "",
        gasdopovo: false,
    }

    const handleOnSave = async (payload: any) => {
        try {
            if (!empresaId) {
                // Sem revenda escolhida e sem empresa de build → volta à seleção.
                router.replace("/selecionar-revenda")
                return
            }

            const idToken: string = isDebug
                ? `fake:+55${loginData.phone.replace(/\D/g, "")}`
                : ((await getAuth(app).currentUser?.getIdToken()) ?? "")

            let pushToken: string | undefined
            try {
                const messaging = getMessaging(app)
                await requestPermission(messaging)
                pushToken = await getToken(messaging)
            } catch {
                pushToken = undefined
            }

            mutate({
                firebase_id_token: idToken,
                empresa_id: empresaId,
                nome: payload.nome,
                cpf: payload.cpf || null,
                datanascimento: payload.datanascimento || null,
                push_token: pushToken,
                device_id: pushToken?.slice(0, 64),
                plataforma: Platform.OS === "ios" ? "ios" : "android",
            })
        } catch (err) {
            console.error(err)
        }
    }

    return (
        <View style={defaultStyles.container}>
            <View style={styles.container}>
                <View style={{ marginBottom: 20 }}>
                    <Text style={{ fontSize: fontSize.lg, fontWeight: 600, textAlign: "center" }}>
                        Novo Cadastro
                    </Text>
                </View>

                <UserForm
                    user={form}
                    onSave={handleOnSave}
                    isSubmitting={isPending}
                    isGpAllowed={false}
                />
            </View>
        </View>
    )
}

const styles = StyleSheet.create({
    container: {
        flexDirection: "column",
        paddingTop: Platform.select({ ios: 55, default: 34 }),
        paddingHorizontal: screenPadding.horizontal,
    },
})

export default NewUser
