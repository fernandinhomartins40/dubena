import { colors, fontSize, fontStyle } from "@/styles/theme"
import { Tabs } from "expo-router"
import { Home, ClipboardList, Info, User } from "lucide-react-native"
import { useSafeAreaInsets } from "react-native-safe-area-context"
import { Pressable } from "react-native"

/**
 * Tab bar moderna (marketplace) — ícones Lucide (linha fina, consistentes),
 * cor da marca no ativo, sem sombra pesada. Substitui os ícones de baixa
 * qualidade de várias famílias (@expo/vector-icons).
 */
const AuthLayout = () => {
    const insets = useSafeAreaInsets()

    return (
        <Tabs
            initialRouteName="home"
            screenOptions={{
                tabBarActiveTintColor: colors.primary,
                tabBarInactiveTintColor: colors.textMuted,
                tabBarLabelStyle: { fontSize: fontSize.xs, ...fontStyle.medium },
                headerShown: false,
                tabBarStyle: {
                    backgroundColor: colors.surface,
                    borderTopWidth: 1,
                    borderTopColor: colors.border,
                    paddingTop: 8,
                    paddingBottom: 10 + insets.bottom,
                    height: 62 + insets.bottom,
                },
                tabBarButton: (props) => <Pressable {...(props as any)} android_ripple={{ borderless: true }} />,
            }}
        >
            <Tabs.Screen
                name="home"
                options={{
                    title: "Início",
                    tabBarIcon: ({ color, focused }) => (
                        <Home size={24} color={color} strokeWidth={focused ? 2.4 : 1.8} />
                    ),
                }}
            />
            <Tabs.Screen
                name="pedidos"
                options={{
                    title: "Pedidos",
                    tabBarIcon: ({ color, focused }) => (
                        <ClipboardList size={24} color={color} strokeWidth={focused ? 2.4 : 1.8} />
                    ),
                }}
            />
            <Tabs.Screen
                name="info"
                options={{
                    title: "Informações",
                    tabBarIcon: ({ color, focused }) => (
                        <Info size={24} color={color} strokeWidth={focused ? 2.4 : 1.8} />
                    ),
                }}
            />
            <Tabs.Screen
                name="perfil"
                options={{
                    title: "Perfil",
                    tabBarIcon: ({ color, focused }) => (
                        <User size={24} color={color} strokeWidth={focused ? 2.4 : 1.8} />
                    ),
                }}
            />
        </Tabs>
    )
}

export default AuthLayout
