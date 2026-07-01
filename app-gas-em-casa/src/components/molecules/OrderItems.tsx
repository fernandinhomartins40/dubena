import React from "react"
import FastImage from "react-native-fast-image"
import { StyleSheet, Text, View } from "react-native"
import { colors, fontSize, fontStyle, radius } from "@/styles/theme"
import { CotacaoItem } from "@/types/types"
import { GasImgUri } from "@/constants/images"

type Props = {
    products: CotacaoItem[]
    totalPrice?: string | null | undefined
    isGasPovo?: boolean
    deliveryTax?: number | null
}

const brl = new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" })

/** Resumo de itens do pedido (thumbnail + itens alinhados + total). Espaçamento por tokens. */
const OrderItems = ({ products, totalPrice, isGasPovo = false, deliveryTax = null }: Props) => {
    const total = totalPrice ? brl.format(Number(totalPrice)) : ""

    return (
        <View style={styles.wrapper}>
            <View style={styles.thumb}>
                <FastImage source={{ uri: GasImgUri }} style={styles.thumbImg} resizeMode="contain" />
            </View>

            <View style={{ flex: 1, gap: 6 }}>
                {products.map((it, idx) => (
                    <View key={idx} style={styles.itemRow}>
                        <View style={styles.qtyBadge}>
                            <Text style={styles.qtyText}>{it.quantidade}</Text>
                        </View>
                        <Text style={styles.itemName} numberOfLines={1}>
                            {it.descricao}
                        </Text>
                    </View>
                ))}

                {isGasPovo && deliveryTax ? (
                    <View style={styles.itemRow}>
                        <View style={styles.qtyBadge}>
                            <Text style={styles.qtyText}>1</Text>
                        </View>
                        <Text style={styles.itemName}>Taxa de entrega</Text>
                    </View>
                ) : null}

                {total ? <Text style={styles.total}>{total}</Text> : null}
            </View>
        </View>
    )
}

const styles = StyleSheet.create({
    wrapper: { flexDirection: "row", alignItems: "center", gap: 12 },
    thumb: {
        width: 60,
        height: 60,
        borderRadius: radius.md,
        backgroundColor: colors.background,
        borderWidth: 1,
        borderColor: colors.border,
        alignItems: "center",
        justifyContent: "center",
    },
    thumbImg: { width: 44, height: 44 },
    itemRow: { flexDirection: "row", alignItems: "center", gap: 8 },
    qtyBadge: {
        minWidth: 22,
        height: 22,
        paddingHorizontal: 5,
        borderRadius: 6,
        backgroundColor: colors.primaryMuted,
        alignItems: "center",
        justifyContent: "center",
    },
    qtyText: { fontSize: fontSize.xs, color: colors.primary, ...fontStyle.bold },
    itemName: { flex: 1, fontSize: fontSize.sm, color: colors.text, ...fontStyle.regular },
    total: { fontSize: fontSize.md, color: colors.primary, marginTop: 4, ...fontStyle.bold },
})

export default OrderItems
