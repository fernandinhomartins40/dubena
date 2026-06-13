import { useCallback, useRef, useState } from "react"
import { BackgroundImgUri, LogoWhiteImgUri } from "@/constants/images"
import { removeAlphaNumericCharacter } from "@/helpers/utils"
import { defaultStyles, screenPadding } from "@/styles/theme"
import { useRouter } from "expo-router"
import {
    Alert,
    ImageBackground,
    KeyboardAvoidingView,
    Platform,
    ScrollView,
    StyleSheet,
    TextInput,
    View,
} from "react-native"
import useAppStore from "@/store/appStore"
import FastImage from "react-native-fast-image"
import Input from "@/components/atoms/Input"
import Button from "@/components/atoms/Button"
import * as NavigationBar from "expo-navigation-bar"

const Login = () => {
    const { config, setLoginData, loginData } = useAppStore()
    const [name, setName] = useState(loginData.name ?? "")
    const phoneRef = useRef(loginData.phone ?? "")
    const phoneInputRef = useRef<TextInput>(null)
    const router = useRouter()

    if (Platform.OS === "android") NavigationBar.setButtonStyleAsync("dark")

    const onChangePhone = useCallback((text: string, rawText?: string | null) => {
        phoneRef.current = text
    }, [])

    const validated = () => {
        if (!name || name.length < 3) {
            Alert.alert("Ops...", "O nome deve conter mais que 2 letras!")
            return false
        }

        if (name.trim().split(" ").length <= 1) {
            Alert.alert("Ops...", "Por favor, informe o seu nome completo")
            return false
        }

        const pattern = /^[A-zÀ-ú\s]+$/
        if (!pattern.test(name)) {
            Alert.alert(
                "Ops...",
                "Por favor, não utilize números ou caracteres especiais no seu nome",
            )
            return false
        }

        let rplName = removeAlphaNumericCharacter(name)
        if (rplName !== name) {
            Alert.alert("Ops...", "O nome não pode conter caracteres especiais!")
            return
        }

        if (!phoneRef.current || phoneRef.current.length !== 15) {
            Alert.alert("Ops...", "Parece que esse número de telefone tá errado, tenta novamente?")
            return false
        }

        return true
    }

    const loginUser = () => {
        if (!validated()) return

        setLoginData({ name, phone: phoneRef.current })

        if (config.termsAccepted) router.navigate("/sms")
        else router.navigate("/policies")
    }

    return (
        <View style={defaultStyles.container}>
            <ImageBackground source={{ uri: BackgroundImgUri }} style={defaultStyles.image}>
                <KeyboardAvoidingView
                    style={{ flex: 1 }}
                    behavior={Platform.OS === "ios" ? "padding" : "height"}
                    keyboardVerticalOffset={Platform.OS === "ios" ? 100 : 20}
                >
                    <ScrollView
                        contentContainerStyle={styles.container}
                        bounces={false}
                        showsVerticalScrollIndicator={false}
                    >
                        <FastImage
                            source={{ uri: LogoWhiteImgUri, priority: FastImage.priority.normal }}
                            style={defaultStyles.logo}
                            resizeMode="contain"
                        />

                        <View style={[defaultStyles.panel, styles.extraPanel]}>
                            <Input
                                label="Nome Completo"
                                placeholder="Digite seu nome completo"
                                value={name}
                                onChangeText={setName}
                                onSubmitEditing={() => phoneInputRef.current?.focus()}
                                returnKeyType="next"
                                submitBehavior="submit"
                            />

                            <Input
                                ref={phoneInputRef}
                                mask="([00]) [00000]-[0000]"
                                component="masked"
                                label="Número de Telefone"
                                placeholder="N° do telefone com DDD"
                                keyboardType="number-pad"
                                defaultValue={phoneRef.current}
                                onChangeText={onChangePhone}
                                returnKeyType="done"
                                submitBehavior="submit"
                                onSubmitEditing={loginUser}
                            />

                            <Button title="entrar" onPress={loginUser} />
                        </View>
                    </ScrollView>
                </KeyboardAvoidingView>
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
    extraPanel: {
        height: 290,
        gap: 8,
    },
})

export default Login
