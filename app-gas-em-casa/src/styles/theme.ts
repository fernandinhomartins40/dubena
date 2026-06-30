import { Platform, StatusBar, StyleSheet } from "react-native"

/**
 * Identidade visual — Supergasbras (laranja + lime sobre grafite).
 * As CHAVES são preservadas (primary/secondary/primaryMuted/…) para que todas as
 * telas existentes adotem a nova marca só trocando os valores. `primary` agora é
 * o laranja da marca; `secondary` é o lime de destaque.
 */
export const colors = {
    errorColor: "#DC2626",
    primary: "#FF6200", // laranja Supergasbras
    primaryDark: "#E04E00",
    secondary: "#DBFB3B", // lime de destaque
    background: "#F6F7F9",
    surface: "#FFFFFF",
    text: "#1A1A1A",
    graphite: "#2B2B2B",
    textMuted: "#6B7280",
    border: "#E6E8EB",
    icon: "#2B2B2B",
    maximumTrackTintColor: "rgba(255,255,255,0.4)",
    minimumTrackTintColor: "rgba(255,255,255,0.6)",
    softGrey: "#919191",
    white: "#FFF",
    primaryMuted: "#FFF1E8", // tint do laranja (era roxo claro)
    success: "#16A34A",
    successMuted: "#E7F6EC",
    warning: "#D97706",
    disabled: "#F1F1F1",
}

export const fontSize = {
    xs: 12,
    sm: 16,
    md: 18,
    base: 20,
    lg: 24,
    xl: 30,
    xxl: 36,
}

export const radius = {
    sm: 8,
    md: 12,
    lg: 16,
    xl: 24,
    pill: 999,
}

export const spacing = {
    xs: 4,
    sm: 8,
    md: 12,
    lg: 16,
    xl: 24,
    xxl: 32,
}

/** Sombra suave padrão dos cards (usar via {...shadow.card}). */
export const shadow = {
    card: {
        boxShadow: "0px 6px 24px 0px rgba(27, 25, 31, 0.08)",
        elevation: 3,
    },
}

export const screenPadding = {
    horizontal: 16,
}

export const settingPadding = {
    horizontal: 0,
}

export const fontStyle = StyleSheet.create({
    thin: {
        fontFamily: Platform.select({
            android: "Sora_100Thin",
            ios: "Sora",
        }),
        fontWeight: 100,
    },
    extraLight: {
        fontFamily: Platform.select({
            android: "Sora_200ExtraLight",
            ios: "Sora",
        }),
        fontWeight: 200,
    },
    light: {
        fontFamily: Platform.select({
            android: "Sora_300Light",
            ios: "Sora",
        }),
        fontWeight: 300,
    },
    regular: {
        fontFamily: Platform.select({
            android: "Sora_400Regular",
            ios: "Sora",
        }),
        fontWeight: 400,
    },
    medium: {
        fontFamily: Platform.select({
            android: "Sora_500Medium",
            ios: "Sora",
        }),
        fontWeight: 500,
    },
    semiBold: {
        fontFamily: Platform.select({
            android: "Sora_600SemiBold",
            ios: "Sora",
        }),
        fontWeight: 600,
    },
    bold: {
        fontFamily: Platform.select({
            android: "Sora_700Bold",
            ios: "Sora",
        }),
        fontWeight: 700,
    },
    extraBold: {
        fontFamily: Platform.select({
            android: "Sora_800ExtraBold",
            ios: "Sora",
        }),
        fontWeight: 800,
    },
})

export const rootStyle = StyleSheet.create({
    root: {
        fontFamily: Platform.select({
            android: "Sora_500Regular",
            ios: "Sora",
        }),
    },
})

export const defaultStyles = StyleSheet.create({
    androidPadding: {
        paddingTop: Platform.OS === "ios" ? 0 : StatusBar.currentHeight,
    },
    stackScreenHeaderPadding: {
        paddingTop: Platform.select({ android: 15, default: 0 }),
    },
    container: {
        flex: 1,
        backgroundColor: colors.background,
    },
    text: {
        fontSize: fontSize.base,
        color: colors.text,
    },
    justifyCenter: {
        justifyContent: "center",
    },
    alignItemsCenter: {
        alignItems: "center",
    },
    image: {
        flex: 1,
        resizeMode: "cover",
    },
    logo: {
        width: 200,
        height: 80,
        marginBottom: 6,
    },
    panel: {
        display: "flex",
        flexDirection: "column",
        width: "100%",
        backgroundColor: "#FFF",
        padding: 20,
        borderRadius: 20,
    },
})

export const utilsStyles = StyleSheet.create({
    itemSeparator: {
        borderColor: colors.textMuted,
        borderWidth: StyleSheet.hairlineWidth,
        opacity: 0.3,
    },
    emptyContentText: {
        ...defaultStyles.text,
        textAlign: "center",
        color: colors.textMuted,
        marginTop: 20,
    },
    emptyContentImage: {
        width: 200,
        height: 200,
        alignSelf: "center",
        marginTop: 40,
        opacity: 0.3,
    },
    fullWidth: {
        width: "100%",
    },
})
