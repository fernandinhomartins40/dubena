import { Platform, Pressable, StyleSheet, View } from "react-native"
import MaterialIcons from "@expo/vector-icons/MaterialIcons"
import { useRouter } from "expo-router"

type Props = {
    onPress?: () => void | undefined
}

const IosBackButton = ({ onPress }: Props) => {
    if (Platform.OS !== "ios") return null

    const router = useRouter()

    const handleBack = () => {
        if (onPress) {
            onPress()
            return
        }

        router.back()
    }

    return (
        <Pressable onPress={handleBack}>
            <View style={styles.container}>
                <MaterialIcons name="arrow-back-ios-new" size={24} color="black" />
            </View>
        </Pressable>
    )
}

const styles = StyleSheet.create({
    container: {
        display: "flex",
        flexDirection: "row",
        justifyContent: "flex-start",
        alignItems: "center",
        paddingHorizontal: 14,
        paddingTop: 8,
    },
})

export default IosBackButton
