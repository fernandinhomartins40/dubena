import { capitalizeFirstLetter, truncateText } from "@/helpers/utils"
import UserService from "@/services/user.service"
import AddressService from "@/services/address.service"
import useAppStore from "@/store/appStore"
import { colors, fontSize, fontStyle, radius } from "@/styles/theme"
import { BottomSheetModal } from "@gorhom/bottom-sheet"
import { useQuery } from "@tanstack/react-query"
import { useRef } from "react"
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from "react-native"
import { ChevronDown, MapPin } from "lucide-react-native"
import AddressSheet from "../organism/AddressSheet"

/**
 * Header estilo marketplace (iFood): barra branca com "Entregar em [endereço] ▾"
 * clicável e a saudação ao lado. Fundo claro (sem a imagem roxa antiga).
 */
const HomeHeader = () => {
    const { user } = useAppStore()
    const addressesSheetRef = useRef<BottomSheetModal>(null)

    const { data: perfil } = useQuery({
        queryKey: ["perfil"],
        queryFn: () => UserService.GetPerfil(),
        enabled: !!user,
    })
    const { data: addresses, isLoading } = useQuery({
        queryKey: ["addresses"],
        queryFn: () => AddressService.GetAll(),
        enabled: !!user,
    })

    const favorito = addresses?.find((a) => a.favorito) ?? addresses?.[0]
    const primeiroNome = capitalizeFirstLetter(
        truncateText(perfil?.nome?.split(" ")?.[0] ?? user?.name ?? "", 16),
    )

    if (!user) return null

    return (
        <View style={styles.wrapper}>
            <Pressable style={styles.addressRow} onPress={() => addressesSheetRef.current?.present()}>
                <View style={styles.pin}>
                    <MapPin size={18} color={colors.primary} strokeWidth={2.2} />
                </View>
                <View style={{ flex: 1 }}>
                    <Text style={styles.label}>ENTREGAR EM</Text>
                    <View style={styles.addressLine}>
                        <Text style={styles.address} numberOfLines={1}>
                            {isLoading
                                ? "Carregando…"
                                : favorito
                                  ? `${truncateText(favorito.endereco ?? "", 26, "..")}${favorito.numero ? `, ${favorito.numero}` : ""}`
                                  : "Adicionar endereço"}
                        </Text>
                        <ChevronDown size={16} color={colors.text} />
                    </View>
                </View>
                {isLoading && <ActivityIndicator size="small" color={colors.primary} />}
            </Pressable>

            <Text style={styles.hello}>
                Olá, <Text style={fontStyle.bold}>{primeiroNome}</Text> 👋
            </Text>

            <AddressSheet ref={addressesSheetRef} addresses={addresses} />
        </View>
    )
}

const styles = StyleSheet.create({
    wrapper: {
        paddingHorizontal: 16,
        paddingBottom: 6,
    },
    addressRow: {
        flexDirection: "row",
        alignItems: "center",
        gap: 10,
    },
    pin: {
        width: 36,
        height: 36,
        borderRadius: radius.md,
        backgroundColor: colors.primaryMuted,
        alignItems: "center",
        justifyContent: "center",
    },
    label: {
        fontSize: 11,
        letterSpacing: 0.5,
        color: colors.textMuted,
        ...fontStyle.semiBold,
    },
    addressLine: {
        flexDirection: "row",
        alignItems: "center",
        gap: 4,
    },
    address: {
        fontSize: fontSize.sm,
        color: colors.text,
        ...fontStyle.semiBold,
    },
    hello: {
        marginTop: 12,
        fontSize: fontSize.lg,
        color: colors.text,
        ...fontStyle.regular,
    },
})

export default HomeHeader
