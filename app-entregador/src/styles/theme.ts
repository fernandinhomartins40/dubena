import { COLORS } from "@/constants/app"

/**
 * Design system do App do Entregador (F10) — ESPELHA o do App Gás em Casa
 * (mesma plataforma, mesma sensação): paleta Supergasbras (laranja #FF6200 /
 * lime #DBFB3B / grafite), tipografia, raios, espaçamento e sombra de card.
 *
 * `colors` reexporta a paleta do constants (fonte única) + apelidos usados
 * pelos componentes (primaryMuted/surface/background/textMuted/border).
 */
export const colors = {
    ...COLORS,
    primaryMuted: "#FFF1E8",
    surface: COLORS.card,
    background: COLORS.bg,
    textMuted: COLORS.muted,
    errorColor: COLORS.danger,
}

export const fontSize = {
    xs: 11,
    sm: 13,
    md: 15,
    base: 16,
    lg: 18,
    xl: 22,
    xxl: 28,
}

export const radius = {
    sm: 8,
    md: 12,
    lg: 14,
    xl: 20,
    pill: 999,
}

export const spacing = {
    xs: 4,
    sm: 8,
    md: 12,
    lg: 16,
    xl: 24,
}

export const shadow = {
    card: {
        shadowColor: "#000",
        shadowOpacity: 0.06,
        shadowRadius: 8,
        shadowOffset: { width: 0, height: 2 },
        elevation: 2,
    },
}
