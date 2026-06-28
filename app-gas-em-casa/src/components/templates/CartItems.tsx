import { View, Text, StyleSheet, Pressable, Alert } from "react-native"
import React from "react"
import useFlashStore from "@/store/flashStore"
import { colors, fontStyle } from "@/styles/theme"
import { CotacaoItem } from "@/types/types"
import EvilIcons from "@expo/vector-icons/EvilIcons"

type CartItemsProps = {
    /** Itens vindos de uma cotação/pedido específico (ex.: histórico); senão usa o carrinho. */
    orderProds?: CotacaoItem[] | null | undefined
    isGasPovo?: boolean
    deliveryTax?: number | null
}

const CartItems = ({ orderProds, isGasPovo = false, deliveryTax = null }: CartItemsProps) => {
    const { cart, catalog } = useFlashStore()

    const descricaoDe = (id: number) =>
        catalog.find((p) => p.id === id)?.descricao ?? `Produto #${id}`

    const renderLine = (key: string, quantity: number, descricao: string) => (
        <View key={`cart_prod_${key}`} style={styles.container}>
            <View style={styles.qtyBox}>
                <Text style={{ color: colors.primary, fontSize: 14, ...fontStyle.regular }}>
                    {quantity}
                </Text>
            </View>
            <Text style={{ color: colors.textMuted, ...fontStyle.regular }}>{descricao}</Text>
        </View>
    )

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

    if (orderProds) {
        return (
            <>
                {orderProds.map((prod, idx) =>
                    renderLine(String(idx), prod.quantidade, prod.descricao),
                )}
                {isGasPovo ? renderEntrega() : null}
            </>
        )
    }

    return (
        <>
            {Object.entries(cart).map(([id, qty]) => renderLine(id, qty, descricaoDe(Number(id))))}
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
