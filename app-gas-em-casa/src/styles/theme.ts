import { Platform, StatusBar, StyleSheet } from "react-native"

export const colors = {
    errorColor: "red",
    primary: "#9747FF",
    secondary: "#e7eb13",
    background: "#FFF",
    text: "#000",
    textMuted: "#9ca3af",
    icon: "#000",
    maximumTrackTintColor: "rgba(255,255,255,0.4)",
    minimumTrackTintColor: "rgba(255,255,255,0.6)",
    softGrey: "#919191",
    white: "#FFF",
    primaryMuted: "#F7ECFA",
    disabled: "#f1f1f1",
}

export const fontSize = {
    xs: 12,
    sm: 16,
    base: 20,
    lg: 24,
}

export const screenPadding = {
    horizontal: 14,
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
