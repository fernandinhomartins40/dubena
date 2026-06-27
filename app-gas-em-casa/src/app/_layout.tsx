import useNotificationClick from "@/hooks/useNotificationClick"
import useAppStore from "@/store/appStore"
import { initSecureStorage } from "@/store/storage"
import { rootStyle } from "@/styles/theme"
import { BottomSheetModalProvider } from "@gorhom/bottom-sheet"
import { QueryClient, QueryClientProvider, useQuery } from "@tanstack/react-query"
import { Href, SplashScreen, Stack, useRouter } from "expo-router"
import { useEffect, useState } from "react"
import { StyleProp, ViewStyle } from "react-native"
import { GestureHandlerRootView } from "react-native-gesture-handler"
import { SafeAreaProvider } from "react-native-safe-area-context"
import Toast from "react-native-toast-message"

const queryClient = new QueryClient()

SplashScreen.preventAutoHideAsync()

export default function App() {
    // Boot de segurança (F0): monta o MMKV cifrado e só então hidrata as stores.
    const [storageReady, setStorageReady] = useState(false)

    useEffect(() => {
        let mounted = true
        ;(async () => {
            await initSecureStorage()
            await useAppStore.persist.rehydrate()
            if (mounted) setStorageReady(true)
        })()
        return () => {
            mounted = false
        }
    }, [])

    useNotificationClick()

    // Mantém a splash até o storage cifrado estar pronto (evita "flash" de tela de login).
    if (!storageReady) return null

    // useEffect(() => {
    //     const timer = setTimeout(() => {
    //         if (user) {
    //             router.replace("/(auth)/(tabs)/home")
    //         }

    //         if (!config.permissions && !user) {
    //             router.replace("/(tutorial)/page1" as Href)
    //         }

    //         if (config.permissions && !user) {
    //             router.replace("/login")
    //         }

    //         SplashScreen.hideAsync()
    //     }, 250)

    //     return () => clearTimeout(timer)
    // }, [])

    return (
        <GestureHandlerRootView>
            <BottomSheetModalProvider>
                <QueryClientProvider client={queryClient}>
                    <SafeAreaProvider style={rootStyle.root as StyleProp<ViewStyle>}>
                        <RootNavigation />
                    </SafeAreaProvider>
                </QueryClientProvider>
            </BottomSheetModalProvider>

            <Toast />
        </GestureHandlerRootView>
    )
}

const RootNavigation = () => (
    <Stack initialRouteName="index">
        <Stack.Screen name="(auth)" options={{ headerShown: false }} />

        <Stack.Screen name="index" options={{ headerShown: false }} />

        <Stack.Screen name="startupvideo" options={{ headerShown: false }} />

        <Stack.Screen name="login" options={{ headerShown: false }} />

        <Stack.Screen name="(tutorial)" options={{ presentation: "modal", headerShown: false }} />

        <Stack.Screen name="policies" options={{ presentation: "modal", headerShown: false }} />

        <Stack.Screen name="sms" options={{ presentation: "modal", headerShown: false }} />

        <Stack.Screen name="newuser" options={{ presentation: "modal", headerShown: false }} />
    </Stack>
)
