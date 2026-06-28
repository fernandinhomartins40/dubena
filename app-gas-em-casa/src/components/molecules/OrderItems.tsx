import React from "react"
import FastImage from "react-native-fast-image"
import CartItems from "../templates/CartItems"
import { StyleSheet, Text, View } from "react-native"
import { colors, fontStyle, screenPadding } from "@/styles/theme"
import { CotacaoItem } from "@/types/types"
import { GasImgUri } from "@/constants/images"

type Props = {
    products: CotacaoItem[]
    totalPrice?: string | null | undefined
    isGasPovo?: boolean
    deliveryTax?: number | null
}

const formatter = new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" })

const OrderItems = ({ products, totalPrice, isGasPovo = false, deliveryTax = null }: Props) => {
    const total = totalPrice ? formatter.format(Number(totalPrice)) : ""

    return (
        <View style={{ flexDirection: "row", gap: 4 }}>
            <View style={styles.productImage}>
                <FastImage source={{ uri: GasImgUri }} style={{ width: 80, height: 80 }} />
            </View>
            <View style={{ flexDirection: "column", gap: 4, justifyContent: "space-between" }}>
                <View>
                    <CartItems
                        orderProds={products}
                        isGasPovo={isGasPovo}
                        deliveryTax={deliveryTax}
                    />
                </View>

                <View>
                    <Text style={{ fontSize: 18, color: colors.primary, ...fontStyle.semiBold }}>
                        {total}
                    </Text>
                </View>
            </View>
        </View>
    )
}

const styles = StyleSheet.create({
    productImage: {
        padding: screenPadding.horizontal,
        borderColor: colors.primary,
        borderWidth: StyleSheet.hairlineWidth,
        borderRadius: 14,
    },
})

export default OrderItems
