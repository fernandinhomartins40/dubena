import { colors, fontSize, fontStyle, radius, shadow } from "@/styles/theme"
import { StyleSheet, Text, View } from "react-native"
import { AlertTriangle } from "lucide-react-native"
import { useSafeAreaInsets } from "react-native-safe-area-context"

interface ErrorViewProps {
    message: string | string[]
}

/** Tela de erro — fundo claro da marca nova, card centralizado (sem imagem roxa). */
const ErrorView = ({ message }: ErrorViewProps) => {
    const { top } = useSafeAreaInsets()

    return (
        <View style={[styles.screen, { paddingTop: top }]}>
            <View style={styles.card}>
                <View style={styles.icon}>
                    <AlertTriangle size={28} color={colors.warning} strokeWidth={2} />
                </View>
                <Text style={styles.title}>Ops!</Text>
                <Text style={styles.message}>{message}</Text>
            </View>
        </View>
    )
}

const styles = StyleSheet.create({
    screen: {
        flex: 1,
        backgroundColor: colors.background,
        justifyContent: "center",
        alignItems: "center",
        paddingHorizontal: 24,
    },
    card: {
        width: "100%",
        alignItems: "center",
        backgroundColor: colors.surface,
        borderRadius: radius.xl,
        padding: 28,
        gap: 8,
        ...shadow.card,
    },
    icon: {
        width: 56,
        height: 56,
        borderRadius: radius.lg,
        backgroundColor: "#FEF3E2",
        alignItems: "center",
        justifyContent: "center",
        marginBottom: 6,
    },
    title: { fontSize: fontSize.lg, color: colors.text, ...fontStyle.bold },
    message: { fontSize: fontSize.sm, color: colors.textMuted, textAlign: "center", ...fontStyle.regular },
})

export default ErrorView
