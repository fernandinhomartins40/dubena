import { COLORS } from "@/constants/app"
import React from "react"
import {
    ActivityIndicator,
    StyleSheet,
    Text,
    TextInput,
    TextInputProps,
    TouchableOpacity,
    View,
    ViewStyle,
} from "react-native"

/** Primitivas de UI do app do entregador — paleta Supergasbras, sem dependências. */

export function Botao({
    titulo,
    onPress,
    carregando,
    variante = "primario",
    desabilitado,
}: {
    titulo: string
    onPress: () => void
    carregando?: boolean
    variante?: "primario" | "secundario" | "perigo"
    desabilitado?: boolean
}) {
    const bg =
        variante === "primario"
            ? COLORS.primary
            : variante === "perigo"
              ? COLORS.danger
              : COLORS.card
    const fg = variante === "secundario" ? COLORS.text : COLORS.white
    const inativo = carregando || desabilitado

    return (
        <TouchableOpacity
            style={[
                s.botao,
                { backgroundColor: bg, opacity: inativo ? 0.6 : 1 },
                variante === "secundario" && { borderWidth: 1, borderColor: COLORS.border },
            ]}
            onPress={onPress}
            disabled={inativo}
            activeOpacity={0.8}
        >
            {carregando ? (
                <ActivityIndicator color={fg} />
            ) : (
                <Text style={[s.botaoTexto, { color: fg }]}>{titulo}</Text>
            )}
        </TouchableOpacity>
    )
}

export function Campo({ label, ...props }: { label: string } & TextInputProps) {
    return (
        <View style={{ marginBottom: 14 }}>
            <Text style={s.label}>{label}</Text>
            <TextInput
                style={s.input}
                placeholderTextColor={COLORS.muted}
                {...props}
            />
        </View>
    )
}

export function Cartao({ children, style }: { children: React.ReactNode; style?: ViewStyle }) {
    return <View style={[s.cartao, style]}>{children}</View>
}

export function Etiqueta({ texto }: { texto: string }) {
    return (
        <View style={s.etiqueta}>
            <Text style={s.etiquetaTexto}>{texto}</Text>
        </View>
    )
}

const s = StyleSheet.create({
    botao: {
        height: 52,
        borderRadius: 12,
        alignItems: "center",
        justifyContent: "center",
        paddingHorizontal: 20,
    },
    botaoTexto: { fontSize: 16, fontWeight: "700" },
    label: { fontSize: 13, fontWeight: "600", color: COLORS.muted, marginBottom: 6 },
    input: {
        height: 50,
        borderWidth: 1,
        borderColor: COLORS.border,
        borderRadius: 12,
        paddingHorizontal: 14,
        fontSize: 16,
        color: COLORS.text,
        backgroundColor: COLORS.card,
    },
    cartao: {
        backgroundColor: COLORS.card,
        borderRadius: 14,
        padding: 16,
        borderWidth: 1,
        borderColor: COLORS.border,
    },
    etiqueta: {
        alignSelf: "flex-start",
        backgroundColor: COLORS.accent,
        paddingHorizontal: 10,
        paddingVertical: 4,
        borderRadius: 999,
    },
    etiquetaTexto: { fontSize: 12, fontWeight: "700", color: COLORS.graphite },
})
