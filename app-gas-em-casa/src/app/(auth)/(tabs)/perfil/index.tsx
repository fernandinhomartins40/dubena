import { colors, fontSize, fontStyle, radius, shadow } from "@/styles/theme"
import { Alert, ScrollView, StyleSheet, Text, View } from "react-native"
import { MapPin, User, LogOut, Trash2 } from "lucide-react-native"
import { useEffect, useRef } from "react"
import { BottomSheetModal } from "@gorhom/bottom-sheet"
import { useMutation, useQuery } from "@tanstack/react-query"
import { useSafeAreaInsets } from "react-native-safe-area-context"
import UserService from "@/services/user.service"
import AddressService from "@/services/address.service"
import useAppStore from "@/store/appStore"
import AddressSheet from "@/components/organism/AddressSheet"
import SettingRow from "@/components/molecules/SettingRow"
import { useLocalSearchParams, useRouter } from "expo-router"
import LoaderOverlay from "@/components/atoms/LoaderOverlay"
import resetStorage from "@/helpers/utils"
import { capitalizeFirstLetter } from "@/helpers/utils"

const PerfilScreen = () => {
    const addressesSheetRef = useRef<BottomSheetModal>(null)
    const { user } = useAppStore()
    const router = useRouter()
    const param = useLocalSearchParams()
    const { top } = useSafeAreaInsets()

    const { data: perfil } = useQuery({
        queryKey: ["perfil"],
        queryFn: () => UserService.GetPerfil(),
        enabled: !!user,
    })
    const { data: addresses } = useQuery({
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
        if (param?.disable == "1") router.push("/(auth)/perfil-dados")
        setTimeout(() => router.setParams({ disable: "0" }), 200)
    }, [param])

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
                { text: "Sim, excluir", style: "destructive", onPress: () => mutate() },
            ],
            { cancelable: true },
        )
    }

    const nome = perfil?.nome ?? user?.name ?? "Você"
    const inicial = (nome?.trim?.()?.[0] ?? "U").toUpperCase()

    return (
        <View style={styles.screen}>
            <ScrollView contentContainerStyle={{ paddingTop: top + 16, paddingBottom: 100 }}>
                <Text style={styles.pageTitle}>Perfil</Text>

                {/* Card do usuário */}
                <View style={styles.userCard}>
                    <View style={styles.avatar}>
                        <Text style={styles.avatarText}>{inicial}</Text>
                    </View>
                    <View style={{ flex: 1 }}>
                        <Text style={styles.userName} numberOfLines={1}>
                            {capitalizeFirstLetter(nome)}
                        </Text>
                        {perfil?.telefone ? (
                            <Text style={styles.userPhone}>{perfil.telefone}</Text>
                        ) : null}
                    </View>
                </View>

                {/* Ações */}
                <View style={styles.group}>
                    <SettingRow
                        icon={MapPin}
                        title="Endereços"
                        subtitle="Gerencie seus endereços de entrega"
                        onPress={() => addressesSheetRef.current?.present()}
                    />
                    <SettingRow
                        icon={User}
                        title="Dados pessoais"
                        subtitle="Informações da sua conta"
                        onPress={() => router.push("/(auth)/perfil-dados")}
                    />
                    <SettingRow
                        icon={LogOut}
                        title="Sair"
                        subtitle="Encerrar a sessão neste aparelho"
                        onPress={handleLogout}
                    />
                    <SettingRow
                        icon={Trash2}
                        title="Excluir conta"
                        subtitle="Remover permanentemente sua conta"
                        onPress={deleteUser}
                        danger
                    />
                </View>
            </ScrollView>

            <AddressSheet ref={addressesSheetRef} addresses={addresses} />
            <LoaderOverlay isLoading={isPending} />
        </View>
    )
}

const styles = StyleSheet.create({
    screen: { flex: 1, backgroundColor: colors.background },
    pageTitle: {
        fontSize: fontSize.xl,
        color: colors.text,
        ...fontStyle.bold,
        paddingHorizontal: 16,
    },
    userCard: {
        flexDirection: "row",
        alignItems: "center",
        gap: 14,
        marginHorizontal: 16,
        marginTop: 16,
        padding: 16,
        borderRadius: radius.lg,
        backgroundColor: colors.surface,
        ...shadow.card,
    },
    avatar: {
        width: 56,
        height: 56,
        borderRadius: radius.pill,
        backgroundColor: colors.primary,
        alignItems: "center",
        justifyContent: "center",
    },
    avatarText: { fontSize: fontSize.lg, color: colors.white, ...fontStyle.bold },
    userName: { fontSize: fontSize.md, color: colors.text, ...fontStyle.bold },
    userPhone: { fontSize: fontSize.sm, color: colors.textMuted, marginTop: 2, ...fontStyle.regular },
    group: {
        marginTop: 20,
        marginHorizontal: 16,
        gap: 10,
    },
})

export default PerfilScreen
