import { Stack } from "expo-router"

const Layout = () => {
    return (
        <Stack>
            <Stack.Screen name="page1" options={{ headerShown: false }} />
            <Stack.Screen name="page2" options={{ headerShown: false }} />
            <Stack.Screen name="page3" options={{ headerShown: false }} />
        </Stack>
    )
}

export default Layout
