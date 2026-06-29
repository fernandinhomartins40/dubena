import { COLORS } from "@/constants/app"
import useAppStore from "@/store/appStore"
import { initSecureStorage } from "@/store/storage"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"
import { Stack } from "expo-router"
import * as SplashScreen from "expo-splash-screen"
import { StatusBar } from "expo-status-bar"
import { useEffect, useState } from "react"
import { GestureHandlerRootView } from "react-native-gesture-handler"
import { SafeAreaProvider } from "react-native-safe-area-context"
import Toast from "react-native-toast-message"

SplashScreen.preventAutoHideAsync().catch(() => {})

const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: 1, staleTime: 10000 } },
})

/**
 * Layout raiz (P7). Ordem do boot:
 *  1. inicializa o storage cifrado (MMKV + chave no keychain);
 *  2. hidrata a store (token/usuário) só DEPOIS — evita ler antes da chave pronta;
 *  3. libera a navegação e esconde o splash.
 */
export default function RootLayout() {
    const [pronto, setPronto] = useState(false)

    useEffect(() => {
        ;(async () => {
            await initSecureStorage()
            await useAppStore.persist.rehydrate()
            setPronto(true)
            await SplashScreen.hideAsync().catch(() => {})
        })()
    }, [])

    if (!pronto) return null

    return (
        <GestureHandlerRootView style={{ flex: 1 }}>
            <SafeAreaProvider>
                <QueryClientProvider client={queryClient}>
                    <StatusBar style="dark" />
                    <Stack
                        screenOptions={{
                            headerStyle: { backgroundColor: COLORS.primary },
                            headerTintColor: COLORS.white,
                            headerTitleStyle: { fontWeight: "700" },
                            contentStyle: { backgroundColor: COLORS.bg },
                        }}
                    >
                        <Stack.Screen name="index" options={{ headerShown: false }} />
                        <Stack.Screen name="login" options={{ headerShown: false }} />
                        <Stack.Screen name="(app)" options={{ headerShown: false }} />
                    </Stack>
                    <Toast />
                </QueryClientProvider>
            </SafeAreaProvider>
        </GestureHandlerRootView>
    )
}
