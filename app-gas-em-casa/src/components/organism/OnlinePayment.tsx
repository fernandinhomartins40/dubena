import { OnlinePaymentTypes, SupportedBrands } from "@/constants/app"
import { colors, fontSize, fontStyle, screenPadding } from "@/styles/theme"
import { BottomSheetModal, BottomSheetScrollView, useBottomSheetModal } from "@gorhom/bottom-sheet"
import { forwardRef, useCallback, useEffect, useMemo, useRef, useState } from "react"
import {
    Alert,
    KeyboardAvoidingView,
    Modal,
    Platform,
    Pressable,
    StyleSheet,
    Text,
    TouchableWithoutFeedback,
    View,
    ViewStyle,
} from "react-native"
import { ScrollView, TextInput } from "react-native-gesture-handler"
import { CardBrandSvgIcon } from "../atoms/PaymentIcon"
import Input from "../atoms/Input"
import Button from "../atoms/Button"
import Toast from "react-native-toast-message"
import { getBrandByCardNumber } from "@/helpers/utils"
import useBottomSheetBackHandler from "@/hooks/useBottomSheetBackHandler"
import useDebounce from "@/hooks/useDebounce"
import { CardInfoPayload } from "@/types/types"

type Ref = BottomSheetModal

interface OnlinePaymentProps {}

/**
 * Captura de cartão (LEGADO/F5). Componente preservado para a F5 (cartão tokenizado).
 * Atualmente ÓRFÃO — não está plugado em nenhum fluxo (a captura passará a usar o SDK
 * de tokenização do adquirente). Mantém estado LOCAL (não no flashStore) até a F5
 * reescrever a UX. Não trafegar PAN/CVV: o SDK devolve só o token (ver PLANO F5).
 */
const OnlinePayment = forwardRef<Ref, OnlinePaymentProps>((props, ref) => {
    const defaultBrand = SupportedBrands[0]
    const snapPoints = useMemo(() => ["50%", "90%"], [])
    const { handleSheetPositionChange } = useBottomSheetBackHandler(
        ref as React.RefObject<BottomSheetModal>,
    )
    // Estado local (F5 reescreve para tokenização). `payment` legado não existe mais.
    const payment = null as { descricao: string } | null
    const [cardInfo, setCardInfo] = useState<CardInfoPayload>({
        brand: "mastercard",
        type: OnlinePaymentTypes.Credit,
        holder_name: "",
        expiration_month: "",
        expiration_year: "",
        card_number: "",
        card_cvv: "",
    })
    const [type, setType] = useState<OnlinePaymentTypes>()
    const [open, setOpen] = useState(false)
    const [brand, setBrand] = useState(defaultBrand)
    const [name, setName] = useState("")
    const [cardNumber, setCardNumber] = useState("")
    const [cardCvv, setCardCvv] = useState("")
    const [expMonth, setExpMonth] = useState("")
    const [expYear, setExpYear] = useState("")
    const { dismiss } = useBottomSheetModal()
    const debNumber = useDebounce(cardNumber, 100)
    const cardNumberRef = useRef<TextInput>(null)
    const cvvRef = useRef<TextInput>(null)
    const monthRef = useRef<TextInput>(null)
    const yearRef = useRef<TextInput>(null)

    useEffect(() => {
        const idxOf = payment?.descricao.toLowerCase()?.indexOf("crédito") ?? -1
        const creditOrDebit = idxOf > -1 ? OnlinePaymentTypes.Credit : OnlinePaymentTypes.Debit

        setType(creditOrDebit)
    }, [payment])

    useEffect(() => {
        if (name == "" && cardInfo.holder_name != "") {
            let card = SupportedBrands.filter((value) => value.type == cardInfo.brand)

            setBrand(card[0])
            setName(cardInfo.holder_name)
            setCardNumber(cardInfo.card_number)
            setCardCvv(cardInfo.card_cvv)
            setExpMonth(cardInfo.expiration_month)
            setExpYear(cardInfo.expiration_year)
        }
    }, [cardInfo])

    useEffect(() => {
        const result = getBrandByCardNumber(debNumber || "")

        if (result) {
            let brands = SupportedBrands.filter((it) => it.type == result)
            let brand = brands[0]

            setBrand(brand)

            if (type && !brand.types.includes(type)) {
                let desc = type === OnlinePaymentTypes.Debit ? "Débito" : "Crédito"
                Alert.alert(
                    "Oops...",
                    `A Bandeira do seu cartão não permite pagamento do tipo: ${desc}`,
                )
            }
        }

        if (debNumber == "") {
            setBrand(defaultBrand)
        }
    }, [debNumber])

    const handleBrandChange = (card: (typeof SupportedBrands)[0]) => {
        setBrand(card)
        setOpen(false)
    }

    const handleNameChange = (text: string) => setName(text)

    const handleNumberChange = useCallback((text: string, rawText?: string | null | undefined) => {
        setCardNumber(String(rawText))
    }, [])

    const handleCvvChange = useCallback(
        (_masked: string, text?: string | null) => setCardCvv(String(text)),
        [],
    )

    const handleExpMonthChange = useCallback(
        (_masked: string, text?: string | null) => setExpMonth(String(text)),
        [],
    )

    const handleExpYearChange = useCallback(
        (_masked: string, text?: string | null) => setExpYear(String(text)),
        [],
    )

    const submitFromKeyPress = () => {
        if (!brand) {
            setOpen(true)
            return
        }

        addCard()
    }

    const addCard = () => {
        let options = {
            type: "error",
            text1: "Oops",
            text1Style: { fontSize: 18 },
            text2: "Informe um mês válido",
            text2Style: { fontSize: 16 },
        }

        if (!name) {
            options.text2 = "Informe o Nome Impresso no Cartão."
            Toast.show(options)
            return
        }

        if (!cardNumber) {
            options.text2 = "Informe o Número do Cartão"
            Toast.show(options)
            return
        }

        if (!cardCvv) {
            options.text2 = "Informe o Código de Segurança (CVV)"
            Toast.show(options)
            return
        }

        if (!expMonth || Number(expMonth) <= 0 || Number(expMonth) > 12) {
            options.text2 = "Informe um mês válido"
            Toast.show(options)
            return
        }

        let year = new Date().getFullYear()
        if (!expYear || Number(expYear) < year || Number(expYear) > 2100) {
            options.text2 = "Informe um ano válido"
            Toast.show(options)
            return
        }

        if (brand.id == 0) {
            options.text2 = "Escolha a bandeira"
            Toast.show(options)
            return
        }

        if (type && !brand.types.includes(type)) {
            let desc = type === OnlinePaymentTypes.Debit ? "Débito" : "Crédito"
            Alert.alert(
                "Oops...",
                `A Bandeira do seu cartão não permite pagamento do tipo: ${desc}`,
            )
            return
        }

        setCardInfo({
            brand: brand.type,
            card_cvv: cardCvv,
            card_number: cardNumber,
            expiration_month: expMonth,
            expiration_year: expYear,
            holder_name: name,
            type: !type ? OnlinePaymentTypes.Credit : type,
        })

        dismiss()
    }

    return (
        <>
            <BottomSheetModal
                index={2}
                ref={ref}
                snapPoints={snapPoints}
                onChange={handleSheetPositionChange}
            >
                <BottomSheetScrollView style={{ flex: 1, flexDirection: "column" }}>
                    <View style={{ paddingBottom: 15 }}>
                        <Text style={[styles.title, fontStyle.semiBold]}>Pagamento Online</Text>
                    </View>

                    <KeyboardAvoidingView
                        style={{ flex: 1,flexGrow: 1, }}
                        behavior={"height"}
                        keyboardVerticalOffset={130}
                    >
                        <ScrollView
                            keyboardShouldPersistTaps="handled"
                            contentContainerStyle={{
                                
                                paddingHorizontal: screenPadding.horizontal,
                                gap: 6,
                                paddingBottom: 120,
                            }}
                        >
                            <View>
                                <Input
                                    label="Nome Impresso no Cartão"
                                    placeholder="Nome Impresso no Cartão"
                                    value={name}
                                    onChangeText={handleNameChange}
                                    autoComplete="cc-name"
                                    onSubmitEditing={() => cardNumberRef.current?.focus()}
                                    returnKeyType="next"
                                    submitBehavior="submit"
                                />
                            </View>

                            <View>
                                <Input
                                    ref={cardNumberRef}
                                    component="masked"
                                    label="Número do Cartão"
                                    placeholder={brand.placeholder}
                                    mask={brand.cardMask}
                                    keyboardType="numeric"
                                    // value={cardNumber}
                                    onChangeText={handleNumberChange}
                                    autoComplete="cc-number"
                                    onSubmitEditing={() => cvvRef.current?.focus()}
                                    returnKeyType="next"
                                    submitBehavior="submit"
                                />
                            </View>

                            <View>
                                <Input
                                    ref={cvvRef}
                                    component="masked"
                                    label="CVV (Código de Segurança)"
                                    placeholder={brand.cvvPlaceholder}
                                    mask={brand.cvvMask}
                                    keyboardType="number-pad"
                                    value={cardCvv}
                                    onChangeText={handleCvvChange}
                                    autoComplete="cc-csc"
                                    onSubmitEditing={() => monthRef.current?.focus()}
                                    returnKeyType="next"
                                    submitBehavior="submit"
                                />
                            </View>

                            <View style={{ paddingHorizontal: 2 }}>
                                <Text style={styles.label}>Data de Expiração</Text>
                            </View>
                            <View style={{ flexDirection: "row", gap: 6 }}>
                                <View style={{ flexGrow: 1 }}>
                                    <Input
                                        ref={monthRef}
                                        component="masked"
                                        placeholder="Mês"
                                        mask="[00]"
                                        keyboardType="number-pad"
                                        value={expMonth}
                                        onChangeText={handleExpMonthChange}
                                        autoComplete="cc-exp-month"
                                        onSubmitEditing={() => yearRef.current?.focus()}
                                        returnKeyType="next"
                                        submitBehavior="submit"
                                    />
                                </View>

                                <View style={{ flexGrow: 1 }}>
                                    <Input
                                        ref={yearRef}
                                        component="masked"
                                        placeholder="Ano"
                                        mask="[0000]"
                                        keyboardType="number-pad"
                                        value={expYear}
                                        onChangeText={handleExpYearChange}
                                        autoComplete="cc-exp-year"
                                        onSubmitEditing={submitFromKeyPress}
                                        returnKeyType="done"
                                        submitBehavior="submit"
                                    />
                                </View>
                            </View>

                            <View style={{ paddingHorizontal: 2 }}>
                                <Text style={styles.label}>Bandeira</Text>
                            </View>
                            <Pressable
                                onPress={() => setOpen(true)}
                                style={({ pressed }) => [
                                    {
                                        opacity: pressed ? 0.5 : 1.0,
                                    },
                                ]}
                            >
                                <View style={styles.button}>
                                    <View
                                        style={{
                                            flexDirection: "row",
                                            alignItems: "center",
                                            gap: 6,
                                        }}
                                    >
                                        <CardBrandSvgIcon
                                            brand={brand.type}
                                            width={40}
                                            height={25}
                                        />
                                        <Text style={{ fontSize: 17 }}>{brand.desc}</Text>
                                    </View>
                                </View>
                            </Pressable>

                            <View style={{ marginTop: 10 }}>
                                <Button title="Confirmar" onPress={addCard} />
                            </View>
                        </ScrollView>
                    </KeyboardAvoidingView>
                </BottomSheetScrollView>
            </BottomSheetModal>

            <Modal
                visible={open}
                animationType="fade"
                transparent={true}
                onRequestClose={() => {
                    setOpen(false)
                }}
            >
                <Pressable style={{ flex: 1 }} onPress={() => setOpen(false)}>
                    <TouchableWithoutFeedback>
                        <View style={styles.modalContent}>
                            <View style={{ width: "100%", paddingBottom: 10 }}>
                                <Text style={[styles.title, fontStyle.semiBold]}>
                                    Escolha a Bandeira
                                </Text>
                            </View>
                            {SupportedBrands.map((card, idx) => {
                                let selected: ViewStyle = {}

                                if (card.id === brand.id) {
                                    selected = {
                                        borderColor: colors.primary,
                                        backgroundColor: colors.primaryMuted,
                                    }
                                }

                                if (card.id == 0) return null

                                return (
                                    <Pressable
                                        key={`brand_${idx}`}
                                        style={{ width: "100%" }}
                                        onPress={() => handleBrandChange(card)}
                                    >
                                        <View style={[styles.button, styles.brandButton, selected]}>
                                            <CardBrandSvgIcon
                                                brand={card.type}
                                                width={40}
                                                height={25}
                                            />

                                            <Text style={{ fontSize: 17, ...fontStyle.regular }}>
                                                {card.desc}
                                            </Text>
                                        </View>
                                    </Pressable>
                                )
                            })}
                        </View>
                    </TouchableWithoutFeedback>
                </Pressable>
            </Modal>
        </>
    )
})

const styles = StyleSheet.create({
    title: { textAlign: "center", fontSize: 20, fontWeight: "bold" },
    label: {
        fontSize: fontSize.sm,
        ...fontStyle.regular,
    },
    button: {
        width: "100%",
        borderColor: colors.textMuted,
        borderWidth: StyleSheet.hairlineWidth,
        padding: 14,
        borderRadius: 14,
    },
    brandButton: { flexDirection: "row", alignItems: "center", gap: 6 },
    modalContent: {
        margin: 20,
        backgroundColor: "white",
        borderRadius: 20,
        padding: 35,
        alignItems: "flex-start",
        justifyContent: "center",
        shadowColor: "#000",
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.25,
        shadowRadius: 4,
        elevation: 5,
        gap: 6,
    },
})

export default OnlinePayment
