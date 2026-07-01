import { colors, fontStyle } from "@/styles/theme"
import { CondicaoPagamento } from "@/types/types"
import {
    BottomSheetModal,
    BottomSheetScrollView,
    TouchableOpacity,
    useBottomSheetModal,
} from "@gorhom/bottom-sheet"
import { forwardRef, useMemo } from "react"
import { StyleSheet, Text, View } from "react-native"
import { CreditCard } from "lucide-react-native"
import useBottomSheetBackHandler from "@/hooks/useBottomSheetBackHandler"

interface PaymentMethodSheetProps {
    selectedId?: number
    condicoes?: CondicaoPagamento[]
    setCondicao: (condicao: CondicaoPagamento) => void
}

type Ref = BottomSheetModal

const PaymentMethodSheet = forwardRef<Ref, PaymentMethodSheetProps>(
    ({ condicoes, selectedId, setCondicao }, ref) => {
        const snapPoints = useMemo(() => ["50%", "90%"], [])
        const { handleSheetPositionChange } = useBottomSheetBackHandler(
            ref as React.RefObject<BottomSheetModal>,
        )
        const { dismiss } = useBottomSheetModal()

        const handleOnPress = (condicao: CondicaoPagamento) => {
            setCondicao(condicao)
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

                        {condicoes?.map((condicao, idx) => (
                            <TouchableOpacity
                                key={`condicao_${idx}`}
                                onPress={() => handleOnPress(condicao)}
                            >
                                <View
                                    style={[
                                        styles.button,
                                        condicao.id == selectedId && styles.selected,
                                    ]}
                                >
                                    <CreditCard
                                        size={22}
                                        strokeWidth={2}
                                        color={
                                            condicao.id == selectedId
                                                ? colors.primary
                                                : colors.textMuted
                                        }
                                    />

                                    <View style={{ flexDirection: "column" }}>
                                        <Text style={{ fontSize: 16, ...fontStyle.regular }}>
                                            {condicao.descricao}
                                        </Text>
                                        <Text
                                            style={{ color: colors.textMuted, ...fontStyle.regular }}
                                        >
                                            {condicao.a_vista
                                                ? "À vista"
                                                : `${condicao.num_parcelas}x`}
                                        </Text>
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
