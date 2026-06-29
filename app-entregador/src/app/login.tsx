import { Botao, Campo } from "@/components/ui"
import { APP, COLORS } from "@/constants/app"
import { HttpError } from "@/helpers/http"
import AuthService from "@/services/auth.service"
import useAppStore from "@/store/appStore"
import { router } from "expo-router"
import { useState } from "react"
import {
    KeyboardAvoidingView,
    Platform,
    ScrollView,
    StyleSheet,
    Text,
    View,
} from "react-native"
import { useSafeAreaInsets } from "react-native-safe-area-context"
import Toast from "react-native-toast-message"

/**
 * Login do entregador (P7) — e-mail/senha do colaborador. Suporta 2FA (TOTP):
 * se o servidor responder 423 (two_factor_required), revela o campo de código.
 * O tenant é derivado do token no servidor; nada de empresa_id aqui.
 */
export default function Login() {
    const insets = useSafeAreaInsets()
    const setToken = useAppStore((s) => s.setToken)
    const setUser = useAppStore((s) => s.setUser)

    const [email, setEmail] = useState(APP.debug ? "" : "")
    const [senha, setSenha] = useState("")
    const [otp, setOtp] = useState("")
    const [precisa2fa, setPrecisa2fa] = useState(false)
    const [carregando, setCarregando] = useState(false)

    const entrar = async () => {
        if (!email.trim() || !senha) {
            Toast.show({ type: "error", text1: "Informe e-mail e senha." })
            return
        }
        setCarregando(true)
        try {
            const resp = await AuthService.Login({
                email: email.trim(),
                password: senha,
                otp: precisa2fa ? otp.trim() : undefined,
                plataforma: Platform.OS,
            })
            setToken(resp.token)
            setUser(resp.user)
            router.replace("/(app)/pedidos")
        } catch (e) {
            const err = e as HttpError
            // 423 = 2FA exigido (ver AppAuthController). Revela o campo de código.
            if (err.status === 423) {
                setPrecisa2fa(true)
                Toast.show({ type: "info", text1: "Digite o código de verificação." })
            } else {
                Toast.show({ type: "error", text1: err.message })
            }
        } finally {
            setCarregando(false)
        }
    }

    return (
        <KeyboardAvoidingView
            style={{ flex: 1, backgroundColor: COLORS.bg }}
            behavior={Platform.OS === "ios" ? "padding" : undefined}
        >
            <ScrollView
                contentContainerStyle={[s.container, { paddingTop: insets.top + 60 }]}
                keyboardShouldPersistTaps="handled"
            >
                <View style={s.logo}>
                    <Text style={s.logoTexto}>Entregador</Text>
                </View>
                <Text style={s.subtitulo}>Acesse com seu e-mail e senha de colaborador.</Text>

                <Campo
                    label="E-mail"
                    value={email}
                    onChangeText={setEmail}
                    autoCapitalize="none"
                    keyboardType="email-address"
                    placeholder="voce@empresa.com.br"
                    editable={!carregando}
                />
                <Campo
                    label="Senha"
                    value={senha}
                    onChangeText={setSenha}
                    secureTextEntry
                    placeholder="••••••••"
                    editable={!carregando}
                />
                {precisa2fa && (
                    <Campo
                        label="Código de verificação (2FA)"
                        value={otp}
                        onChangeText={setOtp}
                        keyboardType="number-pad"
                        placeholder="000000"
                        editable={!carregando}
                    />
                )}

                <Botao titulo="Entrar" onPress={entrar} carregando={carregando} />
            </ScrollView>
        </KeyboardAvoidingView>
    )
}

const s = StyleSheet.create({
    container: { paddingHorizontal: 24, paddingBottom: 40 },
    logo: { alignItems: "center", marginBottom: 8 },
    logoTexto: { fontSize: 30, fontWeight: "800", color: COLORS.primary },
    subtitulo: {
        textAlign: "center",
        color: COLORS.muted,
        marginBottom: 28,
        fontSize: 14,
    },
})
