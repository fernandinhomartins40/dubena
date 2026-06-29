import Button from "@/components/atoms/button"
import Input from "@/components/atoms/input"
import { validateBirthDate, validateCpf } from "@/helpers/utils"
import { colors, fontSize } from "@/styles/theme"
import { UserFormSchema } from "@/types/types"
import React, { useCallback, useState } from "react"
import {
    Alert,
    KeyboardAvoidingView,
    Modal,
    Platform,
    Pressable,
    StyleSheet,
    Text,
    View,
} from "react-native"
import BouncyCheckbox from "react-native-bouncy-checkbox"
import { ScrollView } from "react-native-gesture-handler"

type Props = {
    user: UserFormSchema
    isSubmitting: boolean
    onSave: (payload: any) => void
    isGpAllowed?: boolean
}

const UserForm = ({ user, isSubmitting, onSave, isGpAllowed = false }: Props) => {
    const [birthDt, setBirthDt] = useState(user.datanascimento)
    const [sex, setSex] = useState(user.sexo)
    const [cpf, setCpf] = useState(user.cpf)
    const [isPtnr, setIsPtnr] = useState(user.conveniado)
    const [isProgGV, setIsProgGV] = useState(user.gasdopovo)
    const [openModal, setOpenModal] = useState(false)
    const sexOptions = ["Masculino", "Feminino", "Outro"]

    const handleCpfChange = useCallback((_text: string, rawText?: string | null | undefined) => {
        setCpf(String(rawText))
    }, [])

    const handleBirthDateChange = useCallback((text: string) => {
        setBirthDt(text)
    }, [])

    const handleOnSave = () => {
        let vlBirth = validateBirthDate(birthDt)

        if (!vlBirth.isValid) {
            Alert.alert("Oops..", vlBirth.message)

            return
        }

        if (cpf && !validateCpf(cpf)) {
            Alert.alert("Oops..", "CPF inválido")

            return
        }

        let payload = {
            ...user,
            conveniado: isPtnr,
            gasdopovo: isProgGV,
            cpf: cpf,
            datanascimento: birthDt,
            sexo: sex,
        }

        onSave(payload)
    }

    const handleSexChange = (sx: string) => {
        setSex(sx)

        setOpenModal(false)
    }

    const renderModal = () => (
        <Modal
            transparent
            visible={openModal}
            animationType="fade"
            onRequestClose={() => setOpenModal(false)}
        >
            <View style={{ flex: 1 }}>
                <View
                    style={{
                        margin: 20,
                        backgroundColor: "white",
                        borderRadius: 20,
                        padding: 35,
                        alignItems: "flex-start",
                        justifyContent: "center",
                        shadowColor: "#000",
                        shadowOffset: {
                            width: 0,
                            height: 2,
                        },
                        shadowOpacity: 0.25,
                        shadowRadius: 4,
                        elevation: 5,
                        gap: 6,
                    }}
                >
                    <View style={{ width: "100%", paddingBottom: 10 }}>
                        <Text style={styles.title}>Escolha uma das Opções</Text>
                    </View>

                    <View style={{ flexDirection: "column" }}>
                        {sexOptions.map((sx, idx) => (
                            <Pressable key={`sex_${idx}`} onPress={() => handleSexChange(sx)}>
                                <View
                                    style={{
                                        width: "100%",
                                        margin: 5,
                                        padding: 10,
                                        borderWidth: StyleSheet.hairlineWidth,
                                        borderColor: colors.primaryMuted,
                                        borderRadius: 14,
                                    }}
                                >
                                    <Text style={{ fontSize: fontSize.base }}>{sx}</Text>
                                </View>
                            </Pressable>
                        ))}
                    </View>

                    <View>
                        <Button
                            type="clear"
                            uppercase={false}
                            title={"Cancelar"}
                            textStyle={{ color: colors.primary }}
                            onPress={() => handleSexChange("")}
                        />
                    </View>
                </View>
            </View>
        </Modal>
    )

    return (
        <KeyboardAvoidingView
            behavior={Platform.OS === "ios" ? "padding" : "height"}
            keyboardVerticalOffset={Platform.OS === "ios" ? 0 : 0}
        >
            <ScrollView
                keyboardShouldPersistTaps="handled"
                contentContainerStyle={{
                    flexGrow: 1,
                    flexDirection: "column",
                    gap: 15,
                    paddingBottom: 160,
                }}
            >
                <View>
                    <Input
                        disabled
                        label="Nome Completo"
                        onChangeText={(text) => console.log(text)}
                        value={user.nome}
                    />
                </View>

                <View>
                    <Input
                        disabled
                        label="Número Deste Telefone"
                        onChangeText={() => console.log()}
                        value={user.telefone}
                    />
                </View>

                <View>
                    <Input
                        label="Data de Nascimento"
                        component="masked"
                        mask="[00]/[00]/[0000]"
                        // 99/99/9999
                        placeholder="DD/MM/AAAA"
                        keyboardType="number-pad"
                        value={birthDt}
                        onChangeText={handleBirthDateChange}
                    />
                </View>

                <View>
                    <Input
                        disabled
                        noDisabledStyle
                        label="Sexo"
                        placeholder="Sexo"
                        value={sex}
                        onChangeText={() => console.log()}
                        onPress={() => setOpenModal(true)}
                    />
                </View>

                {isPtnr || isProgGV ? (
                    <View>
                        <Input
                            label="CPF"
                            component="masked"
                            mask="[000].[000].[000]-[00]"
                            // 999.999.999-99
                            placeholder="999.999.999-99"
                            value={cpf}
                            onChangeText={handleCpfChange}
                            keyboardType="number-pad"
                        />
                    </View>
                ) : null}

                <View style={{ paddingTop: 10 }}>
                    <BouncyCheckbox
                        isChecked={isPtnr}
                        size={25}
                        fillColor={isProgGV ? "#ccc" : colors.primary}
                        text="Possuo Convênio"
                        textStyle={{
                            fontSize: fontSize.sm,
                            color: isProgGV ? "#ccc" : "#000",
                            textDecorationLine: "none",
                        }}
                        iconStyle={{ borderRadius: 8 }}
                        innerIconStyle={{ borderWidth: 2, borderRadius: 8 }}
                        onPress={(isChecked: boolean) => {
                            setIsPtnr(isChecked)
                            setIsProgGV(false)
                        }}
                        disabled={isProgGV}
                    />
                </View>

                {user?.gasdopovo || isGpAllowed ? (
                    <View style={{ paddingTop: 10 }}>
                        <BouncyCheckbox
                            isChecked={isProgGV}
                            size={25}
                            fillColor={isPtnr ? "#ccc" : colors.primary}
                            text="Programa Gás do Povo"
                            textStyle={{
                                fontSize: fontSize.sm,
                                color: isPtnr ? "#ccc" : "#000",
                                textDecorationLine: "none",
                            }}
                            iconStyle={{ borderRadius: 8 }}
                            innerIconStyle={{ borderWidth: 2, borderRadius: 8 }}
                            onPress={(isChecked: boolean) => {
                                setIsProgGV(isChecked)
                                setIsPtnr(false)
                            }}
                            disabled={isPtnr}
                        />
                    </View>
                ) : (
                    ""
                )}

                <View>
                    <Button title="Salvar" disabled={isSubmitting} onPress={handleOnSave} />
                </View>

                {renderModal()}
            </ScrollView>
        </KeyboardAvoidingView>
    )
}

const styles = StyleSheet.create({
    label: {
        color: "#000",
        fontSize: fontSize.sm,
        marginBottom: 5,
    },
    title: {
        textAlign: "center",
        fontSize: 20,
        fontWeight: "bold",
    },
})

export default UserForm
