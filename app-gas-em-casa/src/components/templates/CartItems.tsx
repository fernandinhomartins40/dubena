import { View, Text, StyleSheet, Pressable, Alert } from "react-native"
import React from "react"
import useFlashStore from "@/store/flashStore"
import { colors, fontStyle } from "@/styles/theme"
import { CartProduct } from "@/types/types"
import EvilIcons from "@expo/vector-icons/EvilIcons"

type CartItemsProps = {
    orderProds?: CartProduct[] | null | undefined
    isGasPovo?: boolean
    deliveryTax?: number | null
}

const CartItems = ({ orderProds, isGasPovo = false, deliveryTax = null }: CartItemsProps) => {
    const { cart } = useFlashStore()
    const { products } = cart

    const renderItems = (prod: CartProduct, idx: number) => {
        return (
            <View key={`cart_prod_${idx}`} style={styles.container}>
                <View style={styles.qtyBox}>
                    <Text style={{ color: colors.primary, fontSize: 14, ...fontStyle.regular }}>
                        {parseInt(String(prod.quantity))}
                    </Text>
                </View>
                <Text style={{ color: colors.textMuted, ...fontStyle.regular }}>
                    {prod.descricao}
                </Text>
            </View>
        )
    }

    const renderEntrega = () => {
        if (!deliveryTax) return null

        return (
            <Pressable onPress={() => Alert.alert(`Valor deve ser pago separadamente.`)}>
                <View style={styles.container}>
                    <View style={styles.qtyBox}>
                        <Text style={{ color: colors.primary, fontSize: 14, ...fontStyle.regular }}>
                            1
                        </Text>
                    </View>
                    <Text style={{ color: colors.textMuted, ...fontStyle.regular }}>
                        Taxa de Entrega
                    </Text>
                    <EvilIcons name="question" size={16} color={colors.textMuted} />
                </View>
            </Pressable>
        )
    }

    if (!orderProds) {
        return (
            <>
                {Object.keys(products).map((idx: any) => {
                    const prod = products[idx]

                    if (!prod) return null

                    return renderItems(prod, idx)
                })}

                {isGasPovo ? renderEntrega() : null}
            </>
        )
    }

    return (
        <>
            {orderProds.map((prod, idx) => renderItems(prod, idx))}

            {isGasPovo ? renderEntrega() : null}
        </>
    )
}

const styles = StyleSheet.create({
    qtyBox: {
        display: "flex",
        justifyContent: "center",
        alignItems: "center",
        backgroundColor: colors.primaryMuted,
        width: 20,
        height: 20,
        borderRadius: 2,
    },
    container: {
        display: "flex",
        flexDirection: "row",
        alignItems: "center",
        justifyContent: "flex-start",
        gap: 2,
    },
})

export default CartItems
