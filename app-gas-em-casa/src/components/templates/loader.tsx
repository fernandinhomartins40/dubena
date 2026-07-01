import { colors } from "@/styles/theme"
import { ActivityIndicator, View } from "react-native"

/** Loader de tela cheia — fundo claro da marca nova (sem a imagem roxa antiga). */
const Loader = () => {
    return (
        <View style={{ flex: 1, backgroundColor: colors.background, justifyContent: "center", alignItems: "center" }}>
            <ActivityIndicator size="large" color={colors.primary} />
        </View>
    )
}

export default Loader
