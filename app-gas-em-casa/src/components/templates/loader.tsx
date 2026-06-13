import { BackgroundImgUri } from "@/constants/images"
import { colors, defaultStyles } from "@/styles/theme"
import { ActivityIndicator, ImageBackground, View } from "react-native"

const Loader = () => {
    return (
        <View style={defaultStyles.container}>
            <ImageBackground
                source={{ uri: BackgroundImgUri }}
                style={[
                    defaultStyles.image,
                    { display: "flex", justifyContent: "center", alignItems: "center" },
                ]}
            >
                <ActivityIndicator size="large" color={colors.primary} />
            </ImageBackground>
        </View>
    )
}

export default Loader
