import { capitalizeFirstLetter, truncateText } from "@/helpers/utils"
import UserService from "@/services/user.service"
import AddressService from "@/services/address.service"
import useAppStore from "@/store/appStore"
import { colors, fontSize, fontStyle } from "@/styles/theme"
import { BottomSheetModal } from "@gorhom/bottom-sheet"
import { useQuery } from "@tanstack/react-query"
import { useRef } from "react"
import { ActivityIndicator, StyleSheet, Text, View } from "react-native"
import AddressSheet from "../organism/AddressSheet"
import { Pressable } from "react-native-gesture-handler"

const Header = () => {
    const { user } = useAppStore()
    const addressesSheetRef = useRef<BottomSheetModal>(null)
    const { data: perfil } = useQuery({
        queryKey: ["perfil"],
        queryFn: () => UserService.GetPerfil(),
        enabled: !!user,
    })
    const {
        data: addresses,
        isLoading,
        isRefetching,
    } = useQuery({
        queryKey: ["addresses"],
        queryFn: () => AddressService.GetAll(),
        enabled: !!user,
    })

    const favorito = addresses?.find((a) => a.favorito) ?? addresses?.[0]

    const handleAddressClick = () => addressesSheetRef.current?.present()

    const renderName = () => {
        const primeiro = perfil?.nome?.split(" ")?.[0] ?? user?.name ?? ""
        return capitalizeFirstLetter(truncateText(primeiro, 14))
    }

    if (!user) return null

    return (
        <View style={styles.container}>
            <View>
                <Text style={[styles.baseText, fontStyle.regular]}>
                    Olá, {"\n"}
                    <Text
                        style={[fontStyle.bold, { width: 50, overflow: "hidden" }]}
                        ellipsizeMode="tail"
                        numberOfLines={1}
                    >
                        {renderName()}
                    </Text>
                </Text>
            </View>
            <View>
                {!isLoading && !isRefetching ? (
                    <Pressable onPress={handleAddressClick}>
                        <Text
                            style={[styles.baseText, fontStyle.regular, { fontSize: fontSize.sm }]}
                        >
                            <Text style={fontStyle.bold}>ENTREGAR EM</Text> {"\n"}
                            <Text>
                                {truncateText(favorito?.endereco ?? "Adicionar endereço", 20, "..")}
                                {favorito?.numero ? `, ${favorito.numero}` : ""}
                            </Text>
                        </Text>
                    </Pressable>
                ) : (
                    <ActivityIndicator size="large" color={colors.primary} />
                )}
            </View>

            <AddressSheet ref={addressesSheetRef} addresses={addresses} />
        </View>
    )
}

const styles = StyleSheet.create({
    container: {
        display: "flex",
        flexDirection: "row",
        justifyContent: "space-around",
        alignItems: "flex-end",
        paddingTop: 10,
    },
    baseText: {
        fontSize: fontSize.base,
        color: colors.white,
    },
})

export default Header
