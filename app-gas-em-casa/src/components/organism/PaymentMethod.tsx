import { Payment } from "@/types/types"
import { StyleSheet, Text, View } from "react-native"
import { colors, fontStyle } from "@/styles/theme"
import Feather from "@expo/vector-icons/Feather"
import PaymentIcon from "../atoms/PaymentIcon"
import { Pressable, PressableProps } from "react-native-gesture-handler"

interface PaymentMethodProps extends PressableProps {
    payment?: Payment | null | undefined
}

const PaymentMethod = ({ payment, onPress }: PaymentMethodProps) => {
    if (!payment) return

    return (
        <View
            style={{
                width: 360,
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
                            <PaymentIcon type={payment.tipo} color={colors.primary} />

                            <Text style={{ fontSize: 16, ...fontStyle.regular }}>
                                {payment.descricao}
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
                            <Feather name="edit-3" size={24} color="black" />
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
