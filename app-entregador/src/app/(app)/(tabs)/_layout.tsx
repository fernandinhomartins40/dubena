import { COLORS } from "@/constants/app"
import { fontSize } from "@/styles/theme"
import { Tabs } from "expo-router"
import { LayoutDashboard, Package, Map as MapIcon, Compass, User } from "lucide-react-native"
import { Pressable } from "react-native"
import { useSafeAreaInsets } from "react-native-safe-area-context"

/**
 * Tab bar do entregador (F10) — ESPELHA a do app do consumidor (mesma plataforma):
 * ícones Lucide de linha fina, cor da marca no ativo, fundo claro com borda sutil.
 * Cinco áreas: Início (dashboard), Entregas, Rota, Missão e Perfil.
 */
export default function TabsLayout() {
    const insets = useSafeAreaInsets()

    const icone = (Icone: typeof LayoutDashboard) =>
        ({ color, focused }: { color: string; focused: boolean }) => (
            <Icone size={24} color={color} strokeWidth={focused ? 2.4 : 1.8} />
        )

    return (
        <Tabs
            initialRouteName="inicio"
            screenOptions={{
                tabBarActiveTintColor: COLORS.primary,
                tabBarInactiveTintColor: COLORS.muted,
                tabBarLabelStyle: { fontSize: fontSize.xs, fontWeight: "500" },
                headerShown: false,
                tabBarStyle: {
                    backgroundColor: COLORS.card,
                    borderTopWidth: 1,
                    borderTopColor: COLORS.border,
                    paddingTop: 8,
                    paddingBottom: 10 + insets.bottom,
                    height: 62 + insets.bottom,
                },
                tabBarButton: (props) => (
                    <Pressable {...(props as any)} android_ripple={{ borderless: true }} />
                ),
            }}
        >
            <Tabs.Screen name="inicio" options={{ title: "Início", tabBarIcon: icone(LayoutDashboard) }} />
            <Tabs.Screen name="entregas" options={{ title: "Entregas", tabBarIcon: icone(Package) }} />
            <Tabs.Screen name="rota" options={{ title: "Rota", tabBarIcon: icone(MapIcon) }} />
            <Tabs.Screen name="missao" options={{ title: "Missão", tabBarIcon: icone(Compass) }} />
            <Tabs.Screen name="perfil" options={{ title: "Perfil", tabBarIcon: icone(User) }} />
        </Tabs>
    )
}
