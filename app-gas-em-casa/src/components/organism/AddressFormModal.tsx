import React, { useEffect, useRef, useState } from "react"
import {
    View,
    Text,
    Modal,
    ScrollView,
    StyleSheet,
    TouchableOpacity,
    Alert,
    KeyboardAvoidingView,
    Platform,
    Dimensions,
} from "react-native"
import { Address, AddressType, GMapsAddress } from "@/types/types"
import { colors, fontSize } from "@/styles/theme"
import IosBackButton from "../atoms/IosBackButton"
import AddressTypeIcon from "../atoms/AddressTypeIcon"
import Input from "../atoms/Input"
import Button from "../atoms/Button"
import useAppStore from "@/store/appStore"
import AddressService from "@/services/address.service"
import { useRouter } from "expo-router"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { TextInput } from "react-native-gesture-handler"
import useFlashStore from "@/store/flashStore"

const { width } = Dimensions.get("window")
interface Props {
    address: Address | GMapsAddress | null
    open: boolean
    closeModal: () => void
}

const AddressFormModal = ({ open, address, closeModal }: Props) => {
    const baseTypes = [AddressType.Home, AddressType.Workplace, AddressType.Default]
    const { user, setNewAddress } = useAppStore()
    const { setStore } = useFlashStore()
    const router = useRouter()
    const [type, setType] = useState(AddressType.Default)
    const [state, setState] = useState("")
    const [city, setCity] = useState("")
    const [district, setDistrict] = useState("")
    const [street, setStreet] = useState("")
    const [number, setNumber] = useState("")
    const [zip, setZip] = useState("")
    const [reference, setReference] = useState("")
    const [complement, setComplement] = useState("")
    const streetRef = useRef<TextInput>(null)
    const numberRef = useRef<TextInput>(null)
    const zipRef = useRef<TextInput>(null)
    const referenceRef = useRef<TextInput>(null)
    const complementRef = useRef<TextInput>(null)
    const queryClient = useQueryClient()
    const { mutate: updateAddress, isPending: isUpdating } = useMutation({
        mutationFn: AddressService.Update,
        onSuccess: (data) => {
            setNewAddress(data.id)
            setStore(null)
            queryClient.removeQueries({ queryKey: ["store"] })
            queryClient.invalidateQueries({ queryKey: ["store"] })
            router.replace("/(auth)/(tabs)/home")
        },
    })
    const { mutate: insertAddress, isPending: isInserting } = useMutation({
        mutationFn: AddressService.Store,
        onSuccess: (data) => {
            setNewAddress(data.id)
            setStore(null)
            queryClient.removeQueries({ queryKey: ["store"] })
            queryClient.invalidateQueries({ queryKey: ["store"] })
            router.replace("/(auth)/(tabs)/home")
        },
    })
    const isSmaller = Platform.select({ ios: width <= 375, default: width <= 360 })

    useEffect(() => {
        if (address) {
            setState(address?.uf)
            setCity(address?.cidade)
            setDistrict(address?.bairro)
            setStreet(address?.rua)
            setNumber(String(address?.numero))
            setZip(address?.cep)

            if ("complemento" in address) setComplement(address.complemento)

            if ("pontoreferencia" in address) setReference(address.pontoreferencia)
        }
    }, [address])

    const isValidated = () => {
        if (!street) {
            Alert.alert("Oops..", "Informe a rua.")
            return false
        }

        if (!district) {
            Alert.alert("Oops..", "Informe o Bairro.")
            return false
        }

        if (!state) {
            Alert.alert("Oops..", "Informe o Estado.")
            return false
        }

        if (!city) {
            Alert.alert("Oops..", "Informe a Cidade.")
            return false
        }

        if (!number) {
            Alert.alert("Oops..", "Informe o número.")
            return false
        }

        if (!zip) {
            Alert.alert("Oops..", "Informe o CEP.")
            return false
        }

        return true
    }

    const storeAddress = async () => {
        if (!isValidated()) return

        let address_id = address && "id" in address ? address.id : null
        let payload = {
            id: address_id,
            cliente_id: user?.id,
            titulo: type,
            uf: state,
            cidade: city,
            bairro: district,
            rua: street,
            numero: number,
            cep: zip,
            pontoreferencia: reference,
            complemento: complement,
            latitude: address?.latitude,
            longitude: address?.longitude,
        }

        if (address_id) {
            updateAddress({ data: payload })
        } else {
            insertAddress({ data: payload })
        }
    }

    const renderTypesButton = (addressType: AddressType, idx: number) => {
        let isSelected = type == addressType
        let selectedStyle = isSelected ? styles.selected : {}

        return (
            <TouchableOpacity
                key={`basetype_${idx}`}
                onPress={() => setType(addressType)}
                activeOpacity={0.9}
            >
                <View style={[styles.typeButton, selectedStyle]}>
                    <AddressTypeIcon
                        type={addressType}
                        color={isSelected ? colors.primary : colors.textMuted}
                        size={20}
                    />
                    <Text style={{ fontSize: 14, textAlign: "center" }}>{addressType}</Text>
                </View>
            </TouchableOpacity>
        )
    }

    return (
        <Modal visible={open} animationType="fade" onRequestClose={closeModal}>
            <View
                style={[styles.flexColumn, { marginTop: Platform.select({ ios: 35, default: 0 }) }]}
            >
                <IosBackButton />

                <View style={[styles.flexColumn, { marginBottom: 20 }]}>
                    <View style={{ paddingTop: 8 }}>
                        <Text style={styles.title}>Novo Endereço</Text>
                    </View>

                    <KeyboardAvoidingView
                        behavior={Platform.OS === "ios" ? "padding" : "height"}
                        keyboardVerticalOffset={Platform.OS === "ios" ? 90 : 20}
                    >
                        <ScrollView
                            contentContainerStyle={[
                                styles.flexColumn,
                                { paddingHorizontal: 14, marginBottom: 30 },
                                { paddingBottom: isSmaller ? 165 : 16 },
                            ]}
                            showsVerticalScrollIndicator={false}
                        >
                            <View
                                style={{
                                    flexDirection: "row",
                                    alignItems: "center",
                                    justifyContent: "space-between",
                                    gap: 6,
                                }}
                            >
                                {baseTypes.map((type, idx) => renderTypesButton(type, idx))}
                            </View>

                            <Input
                                disabled
                                label="UF"
                                value={state}
                                onChangeText={(text: string) => console.log(text)}
                            />

                            <Input
                                disabled
                                label="Cidade"
                                value={city}
                                onChangeText={(text: string) => console.log(text)}
                            />

                            <Input
                                disabled={address?.bairro !== ""}
                                label="Bairro"
                                value={district}
                                onChangeText={(text: string) => setDistrict(text)}
                                onSubmitEditing={() => streetRef.current?.focus()}
                                returnKeyType="next"
                                submitBehavior="submit"
                            />

                            <Input
                                ref={streetRef}
                                disabled={address?.rua !== ""}
                                label="Rua"
                                value={street}
                                onChangeText={(text: string) => setStreet(text)}
                                onSubmitEditing={() => numberRef.current?.focus()}
                                returnKeyType="next"
                                submitBehavior="submit"
                            />

                            <Input
                                ref={numberRef}
                                label="Número"
                                value={number}
                                onChangeText={(text: string) => setNumber(text)}
                                onSubmitEditing={() => zipRef.current?.focus()}
                                returnKeyType="next"
                                submitBehavior="submit"
                            />

                            <Input
                                ref={zipRef}
                                label="CEP"
                                value={zip}
                                onChangeText={(text: string) => setZip(text)}
                                onSubmitEditing={() => referenceRef.current?.focus()}
                                returnKeyType="next"
                                submitBehavior="submit"
                            />

                            <Input
                                ref={referenceRef}
                                label="Ponto de Referência"
                                value={reference}
                                onChangeText={(text: string) => setReference(text)}
                                onSubmitEditing={() => complementRef.current?.focus()}
                                returnKeyType="next"
                                submitBehavior="submit"
                            />

                            <Input
                                ref={complementRef}
                                label="Complemento"
                                value={complement}
                                onChangeText={(text: string) => setComplement(text)}
                                onSubmitEditing={storeAddress}
                                returnKeyType="done"
                                submitBehavior="submit"
                            />

                            <View style={{ paddingTop: 10 }}>
                                <Button
                                    disabled={isInserting || isUpdating}
                                    title="Salvar Endereço"
                                    onPress={storeAddress}
                                    textStyle={{ fontSize: 18 }}
                                />
                            </View>
                        </ScrollView>
                    </KeyboardAvoidingView>
                </View>
            </View>
        </Modal>
    )
}

const styles = StyleSheet.create({
    title: {
        textAlign: "center",
        fontSize: fontSize.base,
        fontWeight: "600",
    },
    flexColumn: {
        display: "flex",
        flexDirection: "column",
    },
    typeButton: {
        height: 80,
        width: 100,
        flexDirection: "column",
        alignItems: "center",
        justifyContent: "center",
        borderColor: colors.textMuted,
        borderWidth: StyleSheet.hairlineWidth,
        borderRadius: 14,
        paddingVertical: 10,
    },
    selected: {
        borderColor: colors.primary,
    },
})

export default AddressFormModal
