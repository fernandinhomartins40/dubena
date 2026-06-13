import React from "react"
import { colors, defaultStyles, fontSize, fontStyle } from "@/styles/theme"
import { Address } from "@/types/types"
import { BottomSheetModal, BottomSheetScrollView, useBottomSheetModal } from "@gorhom/bottom-sheet"
import { forwardRef, useMemo } from "react"
import { Alert, StyleSheet, Text, View } from "react-native"
import AddressTypeIcon from "../atoms/AddressTypeIcon"
import AnimatedPressable from "../atoms/AnimatedPressable"
import { ScrollView } from "react-native-gesture-handler"
import Button from "../atoms/Button"
import AntDesign from "@expo/vector-icons/AntDesign"
import { useRouter } from "expo-router"
import Toast from "react-native-toast-message"
import AddressService from "@/services/address.service"
import useAppStore from "@/store/appStore"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import LoaderOverlay from "../atoms/LoaderOverlay"
import useBottomSheetBackHandler from "@/hooks/useBottomSheetBackHandler"
import useFlashStore from "@/store/flashStore"

interface AddressSheetProps {
    addresses?: Address[]
    addressId?: number
}

type Ref = BottomSheetModal

const AddressSheet = forwardRef<Ref, AddressSheetProps>(({ addresses, addressId }, ref) => {
    const snapPoints = useMemo(() => ["50%", "90%"], [])
    const { handleSheetPositionChange } = useBottomSheetBackHandler(
        ref as React.RefObject<BottomSheetModal>,
    )
    const { user, setNewAddress } = useAppStore()
    const { setStore } = useFlashStore()
    const router = useRouter()
    const { dismiss } = useBottomSheetModal()
    const queryClient = useQueryClient()
    const { mutate: mutateFavourite, isPending } = useMutation({
        mutationFn: AddressService.MakeFavorite,
        onSuccess: (_dt, vars) => {
            setNewAddress(vars.address_id || 0)
            setStore(null)
            queryClient.invalidateQueries({ queryKey: ["store"] })
            dismiss()
        },
    })
    const {
        mutate: deleteAddress,
        isPending: isDeletionPending,
        variables: delVariables,
    } = useMutation({
        mutationFn: AddressService.Delete,
        onSettled: async () => {
            return await queryClient.invalidateQueries({ queryKey: ["addresses"] })
        },
    })

    const addNewAddress = () => {
        dismiss()
        router.push("/(auth)/address")
    }

    const handleDelete = async (address_id: number | undefined) => {
        if (address_id === addressId) {
            Toast.show({
                type: "error",
                text1: "Oops",
                text1Style: {
                    fontSize: 18,
                },
                text2: "O Endereço Favorito não pode ser apagado.",
                text2Style: {
                    fontSize: 16,
                },
            })

            return
        }

        deleteAddress({ address_id: address_id })
    }

    const handleOnLongPress = (address_id: number | undefined) =>
        Alert.alert("Pera aí..", "O que deseja fazer com este endereço?", [
            {
                text: "Não quero fazer nada",
                onPress: () => console.log("nothing Pressed"),
                style: "cancel",
            },
            {
                text: "Excluir",
                onPress: () => handleDelete(address_id),
                style: "destructive",
            },
            {
                text: "Editar",
                onPress: () => {
                    dismiss()
                    router.push(`/(auth)/address?address_id=${address_id}`)
                },
            },
        ])

    const handleMakeFavorite = async (address_id: number | undefined) => {
        if (address_id === addressId) return

        mutateFavourite({ address_id: address_id, client_id: user?.id })
    }

    const renderAddresses = (address: Address, idx: number, selected: boolean = false) => (
        <AnimatedPressable
            key={`address_${idx}`}
            onPress={() => handleMakeFavorite(address.id)}
            onLongPress={() => handleOnLongPress(address.id)}
            style={{
                opacity: isDeletionPending && address.id === delVariables.address_id ? 0.5 : 1,
            }}
        >
            <View style={[styles.addressItem, selected && styles.selected]}>
                <View>
                    <AddressTypeIcon type={address.titulo} color={colors.textMuted} size={24} />
                </View>
                <View style={{ flexDirection: "column", justifyContent: "flex-start" }}>
                    <Text style={{ fontSize: 14, ...fontStyle.regular }}>
                        {address.rua}, {address.numero}
                    </Text>
                    <Text style={[styles.listSubtitle, fontStyle.regular]}>{address.bairro}</Text>
                    <Text style={[styles.listSubtitle, fontStyle.regular]}>
                        {address.complemento}
                    </Text>
                </View>
            </View>
        </AnimatedPressable>
    )

    return (
        <>
            <BottomSheetModal
                ref={ref}
                snapPoints={snapPoints}
                index={2}
                onChange={handleSheetPositionChange}
            >
                <BottomSheetScrollView>
                    <View>
                        <Text style={[styles.title, fontStyle.semiBold]}>Endereços de Entrega</Text>
                    </View>
                    <View style={{ marginBottom: 10 }}>
                        <Text
                            style={[
                                styles.listSubtitle,
                                fontStyle.regular,
                                { textAlign: "center", fontStyle: "italic" },
                            ]}
                        >
                            Para editar/excluir o endereço, pressione e segure.
                        </Text>
                    </View>
                    <ScrollView style={[defaultStyles.container, { flexDirection: "column" }]}>
                        {addresses?.map((address, idx) =>
                            renderAddresses(address, idx, address.id == addressId),
                        )}

                        <View style={{ marginHorizontal: 14, marginTop: 10 }}>
                            <Button
                                title={
                                    <View
                                        style={{
                                            flexDirection: "row",
                                            justifyContent: "center",
                                            gap: 6,
                                        }}
                                    >
                                        <View>
                                            <Text
                                                style={{
                                                    color: colors.white,
                                                    fontSize: fontSize.sm,
                                                    ...fontStyle.semiBold,
                                                }}
                                            >
                                                Adicionar Endereço
                                            </Text>
                                        </View>
                                        <View>
                                            <AntDesign name="pluscircleo" size={24} color="white" />
                                        </View>
                                    </View>
                                }
                                onPress={addNewAddress}
                            />
                        </View>
                    </ScrollView>
                </BottomSheetScrollView>
            </BottomSheetModal>

            <LoaderOverlay isLoading={isPending || isDeletionPending} />
        </>
    )
})

const styles = StyleSheet.create({
    title: {
        textAlign: "center",
        fontSize: 20,
        paddingVertical: 5,
    },
    addressItem: {
        flexDirection: "row",
        alignItems: "center",
        gap: 6,
        marginHorizontal: 14,
        marginVertical: 4,
        borderWidth: StyleSheet.hairlineWidth,
        borderRadius: 6,
        borderColor: colors.textMuted,
        padding: 10,
    },
    listSubtitle: {
        fontSize: 11,
        color: colors.textMuted,
    },
    selected: {
        borderColor: colors.primary,
        backgroundColor: colors.primaryMuted,
    },
})

export default AddressSheet
