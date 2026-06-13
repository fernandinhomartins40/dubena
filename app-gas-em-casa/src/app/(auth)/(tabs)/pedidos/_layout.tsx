import { defaultStyles } from "@/styles/theme"
import { Stack } from "expo-router"
import { View } from "react-native"

const PedidosScreenLayout = () => {
    return (
        <View style={defaultStyles.container}>
            <Stack>
                <Stack.Screen name="index" options={{ headerShown: false }} />
            </Stack>
        </View>
    )
}

export default PedidosScreenLayout
