import { colors, fontStyle } from "@/styles/theme"
import { Payment, PaymentTypes } from "@/types/types"
import BottomSheet, {
    BottomSheetModal,
    BottomSheetScrollView,
    BottomSheetView,
    TouchableOpacity,
    useBottomSheetModal,
} from "@gorhom/bottom-sheet"
import { forwardRef, useCallback, useMemo, useRef } from "react"
import { StyleSheet, Text, View } from "react-native"
import PaymentIcon from "../atoms/PaymentIcon"
import useBottomSheetBackHandler from "@/hooks/useBottomSheetBackHandler"

interface PaymentMethodSheetProps {
    selectedId?: number
    methods?: Payment[]
    setPayment: (payment: Payment) => void
}

type Ref = BottomSheetModal

const PaymentMethodSheet = forwardRef<Ref, PaymentMethodSheetProps>(
    ({ methods, selectedId, setPayment }, ref) => {
        const snapPoints = useMemo(() => ["50%", "90%"], [])
        const { handleSheetPositionChange } = useBottomSheetBackHandler(
            ref as React.RefObject<BottomSheetModal>,
        )
        const { dismiss } = useBottomSheetModal()

        const handleOnPress = (method: Payment) => {
            setPayment(method)
            dismiss()
        }

        return (
            <BottomSheetModal
                enablePanDownToClose
                ref={ref}
                snapPoints={snapPoints}
                onChange={handleSheetPositionChange}
                index={2}
            >
                <BottomSheetScrollView style={{ flex: 1 }}>
                    <View style={{ flexDirection: "column", gap: 6, marginBottom: 50 }}>
                        <View style={{ marginBottom: 10 }}>
                            <Text style={[styles.title, fontStyle.semiBold]}>
                                Formas de Pagamento
                            </Text>
                        </View>

                        {methods?.map((method, idx) => (
                            <TouchableOpacity
                                key={`method_${idx}`}
                                onPress={() => handleOnPress(method)}
                            >
                                <View
                                    style={[
                                        styles.button,
                                        method.id == selectedId && styles.selected,
                                    ]}
                                >
                                    <PaymentIcon
                                        type={method.tipo}
                                        color={
                                            method.id == selectedId
                                                ? colors.primary
                                                : colors.textMuted
                                        }
                                    />

                                    <View style={{ flexDirection: "column" }}>
                                        <Text style={{ fontSize: 16, ...fontStyle.regular }}>
                                            {method.descricao}
                                        </Text>

                                        {[
                                            PaymentTypes.DebitDelivery,
                                            PaymentTypes.CreditDelivery,
                                        ].includes(method.tipo) ? (
                                            <Text
                                                style={{
                                                    color: colors.textMuted,
                                                    ...fontStyle.regular,
                                                }}
                                            >
                                                Pagamento na Entrega
                                            </Text>
                                        ) : (
                                            ""
                                        )}
                                        {[PaymentTypes.Online].includes(method.tipo) ? (
                                            <Text
                                                style={{
                                                    color: colors.textMuted,
                                                    ...fontStyle.regular,
                                                }}
                                            >
                                                Pagamento Online
                                            </Text>
                                        ) : (
                                            ""
                                        )}
                                    </View>
                                </View>
                            </TouchableOpacity>
                        ))}
                    </View>
                </BottomSheetScrollView>
            </BottomSheetModal>
        )
    },
)

const styles = StyleSheet.create({
    title: {
        textAlign: "center",
        fontSize: 20,
        fontWeight: "bold",
    },
    button: {
        borderWidth: StyleSheet.hairlineWidth,
        flexDirection: "row",
        justifyContent: "flex-start",
        alignItems: "center",
        padding: 20,
        marginHorizontal: 20,
        borderRadius: 14,
        borderColor: colors.textMuted,
        gap: 6,
    },
    selected: {
        borderColor: colors.primary,
    },
})

export default PaymentMethodSheet
