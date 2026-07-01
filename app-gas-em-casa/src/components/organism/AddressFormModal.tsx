import React, { useEffect, useRef, useState } from "react"
import {
    View,
    Text,
    Modal,
    ScrollView,
    StyleSheet,
    Pressable,
    KeyboardAvoidingView,
    Platform,
} from "react-native"
import { useSafeAreaInsets } from "react-native-safe-area-context"
import { Address, AddressType, GMapsAddress } from "@/types/types"
import { colors, fontSize, fontStyle, radius } from "@/styles/theme"
import { ChevronLeft } from "lucide-react-native"
import AddressTypeIcon from "../atoms/AddressTypeIcon"
import Input from "../atoms/input"
import Button from "../atoms/button"
import AddressService from "@/services/address.service"
import { useRouter } from "expo-router"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { TextInput } from "react-native-gesture-handler"
import Toast from "react-native-toast-message"

interface Props {
    address: Address | GMapsAddress | null
    open: boolean
    closeModal: () => void
}

/**
 * Formulário de endereço (etapa 2 do fluxo de endereço, após escolher o ponto no
 * mapa). Modal em tela cheia com header próprio, identidade nova e validação inline
 * (toast, sem Alert nativo). Espaçamento pelos tokens do tema.
 */
const AddressFormModal = ({ open, address, closeModal }: Props) => {
    const baseTypes = [AddressType.Home, AddressType.Workplace, AddressType.Default]
    const router = useRouter()
    const { top } = useSafeAreaInsets()
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

    const onSaved = () => {
        queryClient.invalidateQueries({ queryKey: ["addresses"] })
        Toast.show({ type: "success", text1: "Endereço salvo." })
        router.replace("/(auth)/(tabs)/home")
    }
    const { mutate: updateAddress, isPending: isUpdating } = useMutation({
        mutationFn: AddressService.Update,
        onSuccess: onSaved,
    })
    const { mutate: insertAddress, isPending: isInserting } = useMutation({
        mutationFn: AddressService.Store,
        onSuccess: onSaved,
    })

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
        const faltando =
            (!street && "a rua") ||
            (!district && "o bairro") ||
            (!state && "o estado") ||
            (!city && "a cidade") ||
            (!number && "o número") ||
            (!zip && "o CEP")
        if (faltando) {
            Toast.show({ type: "error", text1: `Informe ${faltando}.` })
            return false
        }
        return true
    }

    const storeAddress = async () => {
        if (!isValidated()) return
        const address_id = address && "id" in address ? (address as any).id : null
        const data = {
            titulo: type,
            uf: state,
            cidade: city,
            bairro: district,
            endereco: street,
            numero: number,
            cep: zip,
            ponto_referencia: reference,
            complemento: complement,
            latitude: address?.latitude,
            longitude: address?.longitude,
        }
        if (address_id) updateAddress({ id: address_id, data })
        else insertAddress({ data })
    }

    const renderTypesButton = (addressType: AddressType, idx: number) => {
        const isSelected = type === addressType
        return (
            <Pressable
                key={`basetype_${idx}`}
                onPress={() => setType(addressType)}
                style={[styles.typeButton, isSelected && styles.typeSelected]}
            >
                <AddressTypeIcon
                    type={addressType}
                    color={isSelected ? colors.primary : colors.textMuted}
                    size={22}
                />
                <Text style={[styles.typeLabel, isSelected && { color: colors.primary }]}>
                    {addressType}
                </Text>
            </Pressable>
        )
    }

    return (
        <Modal visible={open} animationType="slide" onRequestClose={closeModal}>
            <View style={[styles.screen, { paddingTop: top }]}>
                <View style={styles.header}>
                    <Pressable onPress={closeModal} hitSlop={12} style={styles.backBtn}>
                        <ChevronLeft size={24} color={colors.text} />
                    </Pressable>
                    <Text style={styles.headerTitle}>Confirmar endereço</Text>
                    <View style={{ width: 40 }} />
                </View>

                <KeyboardAvoidingView
                    style={{ flex: 1 }}
                    behavior={Platform.OS === "ios" ? "padding" : undefined}
                    keyboardVerticalOffset={Platform.OS === "ios" ? 60 : 0}
                >
                    <ScrollView
                        contentContainerStyle={styles.body}
                        showsVerticalScrollIndicator={false}
                        keyboardShouldPersistTaps="handled"
                    >
                        <Text style={styles.sectionLabel}>Tipo de endereço</Text>
                        <View style={styles.typeRow}>
                            {baseTypes.map((t, idx) => renderTypesButton(t, idx))}
                        </View>

                        <View style={styles.grid2}>
                            <View style={{ flex: 1 }}>
                                <Input disabled label="UF" value={state} onChangeText={() => {}} />
                            </View>
                            <View style={{ flex: 2 }}>
                                <Input disabled label="Cidade" value={city} onChangeText={() => {}} />
                            </View>
                        </View>

                        <Input
                            disabled={address?.bairro !== ""}
                            label="Bairro"
                            value={district}
                            onChangeText={setDistrict}
                            onSubmitEditing={() => streetRef.current?.focus()}
                            returnKeyType="next"
                            submitBehavior="submit"
                        />
                        <Input
                            ref={streetRef}
                            disabled={address?.rua !== ""}
                            label="Rua"
                            value={street}
                            onChangeText={setStreet}
                            onSubmitEditing={() => numberRef.current?.focus()}
                            returnKeyType="next"
                            submitBehavior="submit"
                        />
                        <View style={styles.grid2}>
                            <View style={{ flex: 1 }}>
                                <Input
                                    ref={numberRef}
                                    label="Número"
                                    value={number}
                                    onChangeText={setNumber}
                                    keyboardType="number-pad"
                                    onSubmitEditing={() => zipRef.current?.focus()}
                                    returnKeyType="next"
                                    submitBehavior="submit"
                                />
                            </View>
                            <View style={{ flex: 1 }}>
                                <Input
                                    ref={zipRef}
                                    label="CEP"
                                    value={zip}
                                    onChangeText={setZip}
                                    keyboardType="number-pad"
                                    onSubmitEditing={() => referenceRef.current?.focus()}
                                    returnKeyType="next"
                                    submitBehavior="submit"
                                />
                            </View>
                        </View>
                        <Input
                            ref={referenceRef}
                            label="Ponto de referência"
                            value={reference}
                            onChangeText={setReference}
                            onSubmitEditing={() => complementRef.current?.focus()}
                            returnKeyType="next"
                            submitBehavior="submit"
                        />
                        <Input
                            ref={complementRef}
                            label="Complemento"
                            value={complement}
                            onChangeText={setComplement}
                            onSubmitEditing={storeAddress}
                            returnKeyType="done"
                            submitBehavior="submit"
                        />
                    </ScrollView>
                </KeyboardAvoidingView>

                <View style={[styles.footer, { paddingBottom: 16 }]}>
                    <Button
                        disabled={isInserting || isUpdating}
                        title="Salvar endereço"
                        uppercase={false}
                        onPress={storeAddress}
                    />
                </View>
            </View>
        </Modal>
    )
}

const styles = StyleSheet.create({
    screen: { flex: 1, backgroundColor: colors.background },
    header: {
        flexDirection: "row",
        alignItems: "center",
        justifyContent: "space-between",
        paddingHorizontal: 12,
        paddingVertical: 8,
    },
    backBtn: { width: 40, height: 40, borderRadius: 999, alignItems: "center", justifyContent: "center" },
    headerTitle: { fontSize: fontSize.md, color: colors.text, ...fontStyle.bold },
    body: { paddingHorizontal: 16, paddingBottom: 24, gap: 14 },
    sectionLabel: { fontSize: fontSize.sm, color: colors.text, ...fontStyle.semiBold, marginTop: 4 },
    typeRow: { flexDirection: "row", gap: 10 },
    typeButton: {
        flex: 1,
        height: 78,
        alignItems: "center",
        justifyContent: "center",
        gap: 6,
        borderWidth: 1.5,
        borderColor: colors.border,
        borderRadius: radius.lg,
        backgroundColor: colors.surface,
    },
    typeSelected: { borderColor: colors.primary, backgroundColor: colors.primaryMuted },
    typeLabel: { fontSize: 13, color: colors.text, ...fontStyle.medium },
    grid2: { flexDirection: "row", gap: 10 },
    footer: {
        paddingHorizontal: 16,
        paddingTop: 12,
        borderTopWidth: 1,
        borderTopColor: colors.border,
        backgroundColor: colors.surface,
    },
})

export default AddressFormModal
