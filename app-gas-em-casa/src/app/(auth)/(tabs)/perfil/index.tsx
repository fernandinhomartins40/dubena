import { colors, defaultStyles, fontSize, fontStyle, screenPadding } from "@/styles/theme"
import { Alert, Platform, StyleSheet, Text, View } from "react-native"
import { Pressable } from "react-native-gesture-handler"
import MaterialCommunityIcons from "@expo/vector-icons/MaterialCommunityIcons"
import AntDesign from "@expo/vector-icons/AntDesign"
import { useEffect, useRef } from "react"
import { BottomSheetModal } from "@gorhom/bottom-sheet"
import { useMutation, useQuery } from "@tanstack/react-query"
import UserService from "@/services/user.service"
import AddressService from "@/services/address.service"
import useAppStore from "@/store/appStore"
import AddressSheet from "@/components/organism/AddressSheet"
import UserSheet from "@/components/organism/UserSheet"
import { useLocalSearchParams, useRouter } from "expo-router"
import LoaderOverlay from "@/components/atoms/LoaderOverlay"
import resetStorage from "@/helpers/utils"

const PerfilScreen = () => {
    const addressesSheetRef = useRef<BottomSheetModal>(null)
    const formRef = useRef<BottomSheetModal>(null)
    const { user } = useAppStore()
    const router = useRouter()
    const param = useLocalSearchParams()
    const { data: addresses, isLoading } = useQuery({
        queryKey: ["addresses"],
        queryFn: () => AddressService.GetAll(),
        enabled: !!user,
    })
    const { mutate, isPending } = useMutation({
        mutationFn: () => UserService.DeleteAccount(),
        onSuccess: () => {
            resetStorage()

            router.replace("/login")
        },
    })

    useEffect(() => {
        if (param?.disable == "1") {
            handleFormClick()
        }

        setTimeout(() => {
            router.setParams({ disable: "0" })
        }, 200)
    }, [param])

    const handleAddressClick = () => addressesSheetRef.current?.present()

    const handleFormClick = () => formRef.current?.present()

    const handleLogout = () => {
        resetStorage()

        router.replace("/login")
    }

    const deleteUser = () => {
        Alert.alert(
            "Tem certeza?",
            "Deseja realmente excluir sua conta? Esta ação não poderá ser desfeita.",
            [
                { text: "Não", style: "cancel" },
                {
                    text: "Sim",
                    onPress: () => {
                        mutate()
                    },
                },
            ],
            { cancelable: false },
        )
    }

    return (
        <View style={defaultStyles.container}>
            <View style={styles.container}>
                <View>
                    <Text style={[styles.title, { fontWeight: 600 }]}>Seu Perfil</Text>
                </View>

                <View style={{ marginTop: 30 }}>
                    <Pressable
                        disabled={isLoading}
                        onPress={handleAddressClick}
                        style={({ pressed }) => [
                            {
                                opacity: pressed ? 0.8 : 1.0,
                            },
                        ]}
                    >
                        <View
                            style={{
                                flexDirection: "row",
                                alignItems: "center",
                                flexWrap: "wrap",
                                padding: 10,
                                borderColor: colors.primaryMuted,
                                borderWidth: StyleSheet.hairlineWidth,
                                borderRadius: 10,
                            }}
                        >
                            <View style={{ width: "10%" }}>
                                <MaterialCommunityIcons
                                    name="map-marker-radius-outline"
                                    size={28}
                                    color={colors.primary}
                                />
                            </View>

                            <View
                                style={{
                                    width: "75%",
                                    flexDirection: "column",
                                    alignItems: "flex-start",
                                    justifyContent: "flex-start",
                                }}
                            >
                                <Text style={{ fontSize: fontSize.sm, ...fontStyle.regular }}>
                                    Endereços
                                </Text>
                                <Text
                                    style={{
                                        fontSize: 13,
                                        color: colors.textMuted,
                                        ...fontStyle.regular,
                                    }}
                                >
                                    Gerencie seus endereços de entrega!
                                </Text>
                            </View>

                            <View style={{ width: "10%" }}>
                                <AntDesign name="right" size={24} color={colors.primary} />
                            </View>
                        </View>
                    </Pressable>
                </View>

                <View style={{ marginTop: 30 }}>
                    <Pressable
                        onPress={handleFormClick}
                        style={({ pressed }) => [
                            {
                                opacity: pressed ? 0.8 : 1.0,
                            },
                        ]}
                    >
                        <View
                            style={{
                                flexDirection: "row",
                                alignItems: "center",
                                flexWrap: "wrap",
                                padding: 10,
                                borderColor: colors.primaryMuted,
                                borderWidth: StyleSheet.hairlineWidth,
                                borderRadius: 10,
                            }}
                        >
                            <View style={{ width: "10%" }}>
                                <AntDesign name="user" size={28} color={colors.primary} />
                            </View>

                            <View
                                style={{
                                    width: "75%",
                                    flexDirection: "column",
                                    alignItems: "flex-start",
                                    justifyContent: "flex-start",
                                }}
                            >
                                <Text style={{ fontSize: fontSize.sm, ...fontStyle.regular }}>
                                    Dados Pessoais
                                </Text>
                                <Text
                                    style={{
                                        fontSize: 13,
                                        color: colors.textMuted,
                                        ...fontStyle.regular,
                                    }}
                                >
                                    Informações da Conta
                                </Text>
                            </View>

                            <View style={{ width: "10%" }}>
                                <AntDesign name="right" size={24} color={colors.primary} />
                            </View>
                        </View>
                    </Pressable>
                </View>

                <View style={{ marginTop: 30 }}>
                    <Pressable
                        onPress={handleLogout}
                        style={({ pressed }) => [
                            {
                                opacity: pressed ? 0.8 : 1.0,
                            },
                        ]}
                    >
                        <View
                            style={{
                                flexDirection: "row",
                                alignItems: "center",
                                flexWrap: "wrap",
                                padding: 10,
                                borderColor: colors.primaryMuted,
                                borderWidth: StyleSheet.hairlineWidth,
                                borderRadius: 10,
                            }}
                        >
                            <View style={{ width: "10%" }}>
                                <MaterialCommunityIcons
                                    name="logout"
                                    size={28}
                                    color={colors.primary}
                                />
                            </View>

                            <View
                                style={{
                                    width: "75%",
                                    flexDirection: "column",
                                    alignItems: "flex-start",
                                    justifyContent: "flex-start",
                                }}
                            >
                                <Text style={{ fontSize: fontSize.sm, ...fontStyle.regular }}>
                                    Logout
                                </Text>
                                <Text
                                    style={{
                                        fontSize: 13,
                                        color: colors.textMuted,
                                        ...fontStyle.regular,
                                    }}
                                >
                                    Sair da conta atual
                                </Text>
                            </View>

                            <View style={{ width: "10%" }}>
                                <AntDesign name="right" size={24} color={colors.primary} />
                            </View>
                        </View>
                    </Pressable>
                </View>

                <View style={{ marginTop: 30 }}>
                    <Pressable
                        onPress={deleteUser}
                        style={({ pressed }) => [
                            {
                                opacity: pressed ? 0.8 : 1.0,
                            },
                        ]}
                    >
                        <View
                            style={{
                                flexDirection: "row",
                                alignItems: "center",
                                flexWrap: "wrap",
                                padding: 10,
                                borderColor: colors.primaryMuted,
                                borderWidth: StyleSheet.hairlineWidth,
                                borderRadius: 10,
                            }}
                        >
                            <View style={{ width: "10%" }}>
                                <AntDesign name="deleteuser" size={28} color="red" />
                            </View>

                            <View
                                style={{
                                    width: "75%",
                                    flexDirection: "column",
                                    alignItems: "flex-start",
                                    justifyContent: "flex-start",
                                }}
                            >
                                <Text style={{ fontSize: fontSize.sm, ...fontStyle.regular }}>
                                    Excluir Conta
                                </Text>
                                <Text
                                    style={{
                                        fontSize: 13,
                                        color: colors.textMuted,
                                        ...fontStyle.regular,
                                    }}
                                >
                                    Excluir minha conta
                                </Text>
                            </View>

                            <View style={{ width: "10%" }}>
                                <AntDesign name="right" size={24} color={colors.primary} />
                            </View>
                        </View>
                    </Pressable>
                </View>
            </View>

            <AddressSheet ref={addressesSheetRef} addresses={addresses} />

            {user ? <UserSheet ref={formRef} /> : ""}

            <LoaderOverlay isLoading={isPending} />
        </View>
    )
}

const styles = StyleSheet.create({
    container: {
        flexDirection: "column",
        paddingTop: Platform.select({ ios: 50, default: 34 }),
        paddingHorizontal: screenPadding.horizontal,
    },
    title: {
        fontSize: fontSize.lg,
        textAlign: "center",
    },
})

export default PerfilScreen
