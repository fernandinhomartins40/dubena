import { INTERNAL_BUILD_NUMBER } from "@/constants/app"
import UserService from "@/services/user.service"
import useAppStore from "@/store/appStore"
import { defaultStyles, fontSize, screenPadding } from "@/styles/theme"
import { UserFormSchema } from "@/types/types"
import { useMutation, useQuery } from "@tanstack/react-query"
import React from "react"
import { Alert, Platform, StyleSheet, Text, View } from "react-native"
import { getMessaging, getToken, requestPermission } from "@react-native-firebase/messaging"
import Toast from "react-native-toast-message"
import { useRouter } from "expo-router"
import UserForm from "@/components/templates/UserForm"
import { getApp } from "@react-native-firebase/app"
import StoreService from "@/services/store.service"
import LoaderSimple from "@/components/atoms/LoaderSimple"

const app = getApp()

const NewUser = () => {
    const { loginData, setUser } = useAppStore()
    const router = useRouter()
    const { data: gasDoPovo, isLoading } = useQuery({
        queryKey: ["get-isgpenabled"],
        queryFn: StoreService.GetIsGasPovoAllowed,
    })
    const { mutate, isPending } = useMutation({
        mutationFn: UserService.Store,
        onSuccess: (data) => {
            if ("status" in data && data.status == "NOK") {
                let msg: any =
                    "msg" in data ? data.msg : "Erro desconhecido por favor, contate a revenda."

                Alert.alert("Oops..", msg)

                return
            }

            if (data.id) {
                let name = data.primeironome.toLowerCase()
                name = name.charAt(0).toUpperCase() + name.slice(1)

                Toast.show({
                    type: "success",
                    text1: "Sucesso",
                    text1Style: {
                        fontSize: 18,
                    },
                    text2: `Seja bem-vindo ${name}`,
                    text2Style: {
                        fontSize: 16,
                    },
                })

                setUser(data)

                router.replace("/(auth)/(tabs)/home")
            }
        },
        onError: (err) => {
            console.error(err)
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
            const messaging = getMessaging(app)

            await requestPermission(messaging)

            const fcmtoken = await getToken(messaging)

            payload.pushregistration_id = fcmtoken

            mutate({ data: payload })
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

                {isLoading || !gasDoPovo ? (
                    <LoaderSimple />
                ) : (
                    <UserForm
                        user={form}
                        onSave={handleOnSave}
                        isSubmitting={isPending}
                        isGpAllowed={gasDoPovo.isAllowed}
                    />
                )}
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
