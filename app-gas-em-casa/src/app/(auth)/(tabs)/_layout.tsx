import { colors, fontSize, fontStyle } from "@/styles/theme"
import { Tabs } from "expo-router"
import Octicons from "@expo/vector-icons/Octicons"
import Foundation from "@expo/vector-icons/Foundation"
import Ionicons from "@expo/vector-icons/Ionicons"
import FontAwesome5 from "@expo/vector-icons/FontAwesome5"
import { useSafeAreaInsets } from "react-native-safe-area-context"
import { Dimensions, Pressable } from "react-native"

const { width } = Dimensions.get("window")

const AuthLayout = () => {
    const insets = useSafeAreaInsets()
    const isSmaller = width <= 360
    const iconSize = isSmaller ? 20 : 26

    return (
        <>
            <Tabs
                initialRouteName="home"
                screenOptions={{
                    tabBarActiveTintColor: colors.primary,
                    tabBarLabelStyle: { fontSize: fontSize.xs, fontWeight: 500 },
                    headerShown: false,
                    tabBarStyle: {
                        position: "absolute",
                        borderTopLeftRadius: 20,
                        borderTopRightRadius: 20,
                        borderTopWidth: 0,
                        paddingTop: 8,
                        paddingBottom: 14 + insets.bottom,
                        height: 66 + insets.bottom,
                    },
                }}
            >
                <Tabs.Screen
                    name="home"
                    options={{
                        title: "Home",
                        tabBarIcon: ({ color }) => (
                            <Octicons name="home" size={iconSize} color={color} />
                        ),
                        tabBarButton: ({ onPress, style, children }) => (
                            <Pressable onPress={onPress} style={style}>
                                {children}
                            </Pressable>
                        ),
                        tabBarLabelStyle: {
                            ...fontStyle.semiBold,
                        },
                    }}
                />
                <Tabs.Screen
                    name="pedidos"
                    options={{
                        title: "Pedidos",
                        tabBarIcon: ({ color }) => (
                            <Foundation name="clipboard-notes" size={iconSize} color={color} />
                        ),

                        tabBarButton: ({ onPress, style, children }) => (
                            <Pressable onPress={onPress} style={style}>
                                {children}
                            </Pressable>
                        ),
                        tabBarLabelStyle: {
                            ...fontStyle.semiBold,
                        },
                    }}
                />
                <Tabs.Screen
                    name="info"
                    options={{
                        title: "Informações",
                        tabBarIcon: ({ color }) => (
                            <Ionicons
                                name="information-circle-outline"
                                size={iconSize}
                                color={color}
                            />
                        ),
                        tabBarButton: ({ onPress, style, children }) => (
                            <Pressable onPress={onPress} style={style}>
                                {children}
                            </Pressable>
                        ),
                        tabBarLabelStyle: {
                            ...fontStyle.semiBold,
                        },
                    }}
                />
                <Tabs.Screen
                    name="perfil"
                    options={{
                        title: "Perfil",
                        tabBarIcon: ({ color }) => (
                            <FontAwesome5 name="user-circle" size={iconSize} color={color} />
                        ),
                        tabBarButton: ({ onPress, style, children }) => (
                            <Pressable onPress={onPress} style={style}>
                                {children}
                            </Pressable>
                        ),
                        tabBarLabelStyle: {
                            ...fontStyle.semiBold,
                        },
                    }}
                />
            </Tabs>
        </>
    )
}

export default AuthLayout
