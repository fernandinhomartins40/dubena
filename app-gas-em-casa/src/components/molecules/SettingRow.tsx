import { colors, fontSize, fontStyle, radius } from "@/styles/theme"
import { Pressable, StyleSheet, Text, View } from "react-native"
import { ChevronRight, type LucideIcon } from "lucide-react-native"

interface Props {
    icon: LucideIcon
    title: string
    subtitle?: string
    onPress?: () => void
    danger?: boolean
    disabled?: boolean
}

/** Linha de configuração/menu padrão (Lucide + título/subtítulo + chevron). */
const SettingRow = ({ icon: Icon, title, subtitle, onPress, danger, disabled }: Props) => {
    const cor = danger ? colors.errorColor : colors.primary
    const tint = danger ? "#FDECEC" : colors.primaryMuted

    return (
        <Pressable
            onPress={onPress}
            disabled={disabled}
            style={({ pressed }) => [styles.row, pressed && { opacity: 0.7 }]}
        >
            <View style={[styles.icon, { backgroundColor: tint }]}>
                <Icon size={22} color={cor} strokeWidth={2} />
            </View>
            <View style={{ flex: 1 }}>
                <Text style={[styles.title, danger && { color: colors.errorColor }]}>{title}</Text>
                {subtitle ? <Text style={styles.subtitle}>{subtitle}</Text> : null}
            </View>
            <ChevronRight size={20} color={colors.textMuted} />
        </Pressable>
    )
}

const styles = StyleSheet.create({
    row: {
        flexDirection: "row",
        alignItems: "center",
        gap: 12,
        paddingVertical: 14,
        paddingHorizontal: 14,
        backgroundColor: colors.surface,
        borderRadius: radius.lg,
        borderWidth: 1,
        borderColor: colors.border,
    },
    icon: {
        width: 42,
        height: 42,
        borderRadius: radius.md,
        alignItems: "center",
        justifyContent: "center",
    },
    title: { fontSize: fontSize.sm, color: colors.text, ...fontStyle.semiBold },
    subtitle: { fontSize: 13, color: colors.textMuted, marginTop: 2, ...fontStyle.regular },
})

export default SettingRow
