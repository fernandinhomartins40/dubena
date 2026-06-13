import Button from "@/components/atoms/Button"
import { CozinhaImgUri } from "@/constants/images"
import { colors, defaultStyles, fontSize, screenPadding, utilsStyles } from "@/styles/theme"
import { Href, useRouter } from "expo-router"
import { ImageBackground, Platform, StyleSheet, Text, View } from "react-native"
import { useSafeAreaInsets } from "react-native-safe-area-context"

const Page1 = () => {
    const router = useRouter()
    const insets = useSafeAreaInsets()

    const handleOnPress = () => {
        router.navigate("/(tutorial)/page2" as Href)
    }

    const renderButton = () => {
        if (Platform.OS === "ios") {
            return (
                <View style={utilsStyles.fullWidth}>
                    <Button title="entrar" onPress={handleOnPress} />
                </View>
            )
        }

        return <Button title="entrar" onPress={handleOnPress} />
    }

    return (
        <View style={[defaultStyles.container]}>
            <ImageBackground source={{ uri: CozinhaImgUri }} style={defaultStyles.image}>
                <View style={[styles.content]}>
                    <Text
                        style={{
                            fontSize: 34,
                            color: "#FFF",
                            fontWeight: 800,
                        }}
                    >
                        A maneira mais fácil de pedir Gás em Casa
                    </Text>

                    <Text
                        style={{
                            fontSize: fontSize.base,
                            color: "#FFF",
                        }}
                    >
                        Peça gás de cozinha rapidamente, sem complicações, e receba em casa!
                    </Text>

                    {/* <View style={{ display: "flex", flexDirection: "row", gap: 2 }}>
                        <Dot active />
                        <Dot />
                        <Dot />
                    </View> */}

                    <View style={{ paddingBottom: insets.bottom, width: "100%" }}>{renderButton()}</View>
                </View>
            </ImageBackground>
        </View>
    )
}

const Dot = ({ active = false }) => (
    <View
        style={{
            height: 10,
            width: active ? 30 : 10,
            borderRadius: 50,
            backgroundColor: colors.softGrey,
        }}
    ></View>
)

const styles = StyleSheet.create({
    content: {
        flex: 1,
        justifyContent: "flex-end",
        alignItems: "flex-start",
        paddingHorizontal: screenPadding.horizontal,
        paddingBottom: Platform.select({
            ios: 35,
            default: 20,
        }),
        gap: 10,
    },
})

export default Page1
