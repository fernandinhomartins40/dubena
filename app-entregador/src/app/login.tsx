import { Botao, Campo } from "@/components/ui"
import { APP, COLORS } from "@/constants/app"
import { HttpError } from "@/helpers/http"
import AuthService from "@/services/auth.service"
import useAppStore from "@/store/appStore"
import { router } from "expo-router"
import { Bike } from "lucide-react-native"
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
 * Credencial do atalho de debug — vem do ambiente (LOGIN_TESTE_EMAIL/_SENHA via
 * app.config.ts), nunca do código. Sem ambas, o atalho não é renderizado.
 */
const LOGIN_TESTE = APP.login_teste
const MOSTRAR_TESTE = APP.debug && !!LOGIN_TESTE.email && !!LOGIN_TESTE.senha

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

    /**
     * Entra no app. Aceita credenciais explícitas (atalho de teste); por padrão
     * usa o que está nos campos. Mantém o e-mail/senha visíveis nos campos quando
     * vier do atalho, para o tester ver o que foi usado.
     */
    const entrar = async (cred?: { email: string; senha: string }) => {
        const mail = (cred?.email ?? email).trim()
        const pass = cred?.senha ?? senha
        if (cred) {
            setEmail(cred.email)
            setSenha(cred.senha)
        }
        if (!mail || !pass) {
            Toast.show({ type: "error", text1: "Informe e-mail e senha." })
            return
        }
        setCarregando(true)
        try {
            const resp = await AuthService.Login({
                email: mail,
                password: pass,
                otp: precisa2fa ? otp.trim() : undefined,
                plataforma: Platform.OS,
            })
            setToken(resp.token)
            setUser(resp.user)
            router.replace("/(app)/(tabs)/inicio")
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
                    <View style={s.logoIcone}>
                        <Bike size={34} color={COLORS.white} strokeWidth={2.2} />
                    </View>
                    <Text style={s.logoTexto}>Gás em Casa</Text>
                    <Text style={s.logoSub}>Entregador</Text>
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

                <Botao titulo="Entrar" onPress={() => entrar()} carregando={carregando} />

                {MOSTRAR_TESTE && (
                    <View style={s.testeBox}>
                        <Text style={s.testeLabel}>Modo teste</Text>
                        <Botao
                            titulo="Preencher login de teste"
                            variante="secundario"
                            onPress={() => entrar(LOGIN_TESTE)}
                            carregando={carregando}
                        />
                        <Text style={s.testeHint}>{LOGIN_TESTE.email}</Text>
                    </View>
                )}
            </ScrollView>
        </KeyboardAvoidingView>
    )
}

const s = StyleSheet.create({
    container: { paddingHorizontal: 24, paddingBottom: 40 },
    logo: { alignItems: "center", marginBottom: 8, gap: 6 },
    logoIcone: {
        width: 72,
        height: 72,
        borderRadius: 999,
        backgroundColor: COLORS.primary,
        alignItems: "center",
        justifyContent: "center",
        marginBottom: 4,
    },
    logoTexto: { fontSize: 30, fontWeight: "800", color: COLORS.graphite },
    logoSub: {
        fontSize: 13,
        fontWeight: "700",
        color: COLORS.primary,
        textTransform: "uppercase",
        letterSpacing: 2,
        marginTop: -4,
    },
    subtitulo: {
        textAlign: "center",
        color: COLORS.muted,
        marginBottom: 28,
        fontSize: 14,
    },
    testeBox: {
        marginTop: 24,
        paddingTop: 16,
        borderTopWidth: 1,
        borderTopColor: COLORS.border,
        gap: 8,
    },
    testeLabel: {
        fontSize: 12,
        fontWeight: "700",
        color: COLORS.muted,
        textTransform: "uppercase",
        letterSpacing: 0.5,
    },
    testeHint: {
        fontSize: 12,
        color: COLORS.muted,
        textAlign: "center",
    },
})
