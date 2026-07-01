import { CondicaoPagamento } from "@/types/types"
import { StyleSheet, Text, View } from "react-native"
import { colors, fontStyle } from "@/styles/theme"
import { CreditCard, Pencil } from "lucide-react-native"
import { Pressable, PressableProps } from "react-native-gesture-handler"

interface PaymentMethodProps extends PressableProps {
    condicao?: CondicaoPagamento | null | undefined
}

const PaymentMethod = ({ condicao, onPress }: PaymentMethodProps) => {
    if (!condicao) return null

    return (
        <View
            style={{
                width: "100%",
                borderColor: colors.primary,
                borderWidth: StyleSheet.hairlineWidth,
                padding: 14,
                borderRadius: 14,
            }}
        >
            <Pressable
                onPress={onPress}
                style={({ pressed }) => [
                    {
                        opacity: pressed ? 0.5 : 1.0,
                    },
                ]}
            >
                <View
                    style={{
                        flexDirection: "row",
                        alignItems: "center",
                        justifyContent: "space-between",
                    }}
                >
                    <View style={{ flexDirection: "column", justifyContent: "flex-start" }}>
                        <Text style={{ fontSize: 16, ...fontStyle.semiBold }}>
                            Forma de Pagamento
                        </Text>
                        <View style={{ flexDirection: "row", alignItems: "center", gap: 6 }}>
                            <CreditCard size={20} color={colors.primary} strokeWidth={2} />

                            <Text style={{ fontSize: 16, ...fontStyle.regular }}>
                                {condicao.descricao}
                            </Text>
                        </View>
                    </View>

                    <View>
                        <View
                            style={{
                                width: 45,
                                height: 45,
                                borderRadius: 28,
                                backgroundColor: colors.primaryMuted,
                                justifyContent: "center",
                                alignItems: "center",
                            }}
                        >
                            <Pencil size={20} color={colors.primary} strokeWidth={2} />
                        </View>

                        <Text
                            style={{ fontSize: 10, color: colors.textMuted, ...fontStyle.regular }}
                        >
                            ALTERAR
                        </Text>
                    </View>
                </View>
            </Pressable>
        </View>
    )
}

export default PaymentMethod
