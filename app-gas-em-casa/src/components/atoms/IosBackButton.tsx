import { Platform, Pressable, StyleSheet, View } from "react-native"
import { ChevronLeft } from "lucide-react-native"
import { colors } from "@/styles/theme"
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
                <ChevronLeft size={24} color={colors.text} />
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
