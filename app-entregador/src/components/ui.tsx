import { COLORS } from "@/constants/app"
import { fontSize, radius, shadow, spacing } from "@/styles/theme"
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
    dica,
}: {
    titulo: string
    onPress: () => void
    carregando?: boolean
    variante?: "primario" | "secundario" | "perigo"
    desabilitado?: boolean
    /** Consequência da ação, lida pelo leitor de tela (ex.: "avisa todos os clientes"). */
    dica?: string
}) {
    const bg =
        variante === "primario"
            ? COLORS.primaryStrong
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
            // PIE P2-C-17: sem isto o leitor de tela anunciava "botão" sem nome
            // enquanto carrega (só o spinner) e não dizia que estava desativado.
            accessibilityRole="button"
            accessibilityLabel={titulo}
            accessibilityHint={dica}
            accessibilityState={{ disabled: !!inativo, busy: !!carregando }}
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
                accessibilityLabel={label}
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
        borderRadius: radius.md,
        alignItems: "center",
        justifyContent: "center",
        paddingHorizontal: 20,
    },
    botaoTexto: { fontSize: fontSize.base, fontWeight: "700" },
    label: { fontSize: fontSize.sm, fontWeight: "600", color: COLORS.muted, marginBottom: 6 },
    input: {
        height: 50,
        borderWidth: 1,
        borderColor: COLORS.border,
        borderRadius: radius.md,
        paddingHorizontal: 14,
        fontSize: fontSize.base,
        color: COLORS.text,
        backgroundColor: COLORS.card,
    },
    cartao: {
        backgroundColor: COLORS.card,
        borderRadius: radius.lg,
        padding: spacing.lg,
        borderWidth: 1,
        borderColor: COLORS.border,
        ...shadow.card,
    },
    etiqueta: {
        alignSelf: "flex-start",
        backgroundColor: COLORS.accent,
        paddingHorizontal: 10,
        paddingVertical: 4,
        borderRadius: radius.pill,
    },
    etiquetaTexto: { fontSize: fontSize.xs, fontWeight: "700", color: COLORS.graphite },
    aviso: {
        backgroundColor: "#FEF3C7",
        borderColor: "#F59E0B",
        borderWidth: 1,
        borderRadius: radius.md,
        padding: 10,
    },
    avisoTexto: { fontSize: fontSize.sm, color: "#78350F", fontWeight: "600" },
    falhaTitulo: { fontSize: fontSize.base, fontWeight: "800", color: COLORS.text, textAlign: "center" },
    falhaTexto: { fontSize: fontSize.sm, color: COLORS.muted, textAlign: "center" },
})

/**
 * Faixa "sem conexão — mostrando dados de HH:MM" (PIE P2-C-10/11). Fica no topo
 * da lista enquanto a consulta falha mas há dado guardado.
 */
export function AvisoOffline({ texto }: { texto: string | null }) {
    if (!texto) return null
    return (
        <View style={s.aviso} accessibilityRole="alert">
            <Text style={s.avisoTexto}>{texto}</Text>
        </View>
    )
}

/**
 * Estado de falha SEM dado guardado: diz que é falta de sinal (ou erro do
 * servidor) e oferece tentar de novo — nunca "nenhuma entrega".
 */
export function FalhaAoCarregar({ semConexao, onTentar }: { semConexao: boolean; onTentar: () => void }) {
    return (
        <Cartao style={{ alignItems: "center", gap: 10, paddingVertical: 24 }}>
            <Text style={s.falhaTitulo} accessibilityRole="alert">
                {semConexao ? "Sem conexão com a internet" : "Não foi possível carregar"}
            </Text>
            <Text style={s.falhaTexto}>
                {semConexao
                    ? "As entregas aparecem assim que o sinal voltar. Nada foi perdido."
                    : "O servidor não respondeu como esperado. Tente de novo em instantes."}
            </Text>
            <View style={{ alignSelf: "stretch" }}>
                <Botao titulo="Tentar de novo" variante="secundario" onPress={onTentar} />
            </View>
        </Cartao>
    )
}
