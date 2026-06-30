import { BackgroundImgUri } from "@/constants/images"
import { colors, defaultStyles, screenPadding } from "@/styles/theme"
import { ImageBackground, StyleSheet, Text, View } from "react-native"
import Header from "../molecules/header"
import { useSafeAreaInsets } from "react-native-safe-area-context"

interface ErrorViewProps {
    message: string | string[]
}

const ErrorView = ({ message }: ErrorViewProps) => {
    const { top } = useSafeAreaInsets()

    return (
        <View style={defaultStyles.container}>
            <ImageBackground
                source={{ uri: BackgroundImgUri }}
                style={[defaultStyles.image, { paddingTop: top }]}
            >
                <View style={{ display: "flex", flexDirection: "column" }}>
                    <Header />

                    <View style={[styles.flexColumn, styles.container]}>
                        <View style={defaultStyles.panel}>
                            <Text style={{ fontSize: 22 }}>Oops!</Text>
                            <Text style={{ fontSize: 16 }}>{message}</Text>
                        </View>
                    </View>
                </View>
            </ImageBackground>
        </View>
    )
}

const styles = StyleSheet.create({
    flexColumn: {
        display: "flex",
        flexDirection: "column",
    },
    container: {
        backgroundColor: colors.white,
        height: "100%",
        marginTop: 30,
        borderRadius: 30,
        justifyContent: "flex-start",
    },
})

export default ErrorView
