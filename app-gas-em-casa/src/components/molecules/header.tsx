import { capitalizeFirstLetter, truncateText } from "@/helpers/utils"
import UserService from "@/services/user.service"
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
    const {
        data: address,
        isLoading,
        isRefetching,
    } = useQuery({
        queryKey: ["address"],
        queryFn: () => UserService.GetAddress(user?.id || 0),
        enabled: !!user,
    })
    const { data: addresses } = useQuery({
        queryKey: ["addresses"],
        queryFn: () => UserService.GetAllAddress(user?.id || 0),
        enabled: !!user,
    })

    const handleAddressClick = () => addressesSheetRef.current?.present()

    const renderName = () => {
        let name = truncateText(user?.primeironome || "", 14)

        return capitalizeFirstLetter(name)
    }

    if (!user) return null

    return (
        <View style={styles.container}>
            <View>
                <Text style={[styles.baseText, fontStyle.regular]}>
                    Olá, {"\n"}
                    <Text style={[fontStyle.bold, {width: 50, overflow: "hidden"}]} ellipsizeMode="tail" numberOfLines={1}>
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
                                {truncateText(address?.rua ?? "", 20, "..")}, {address?.numero}
                            </Text>
                        </Text>
                    </Pressable>
                ) : (
                    <ActivityIndicator size="large" color={colors.primary} />
                )}
            </View>

            <AddressSheet
                ref={addressesSheetRef}
                addressId={user?.enderecopadrao_id}
                addresses={addresses}
            />
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
