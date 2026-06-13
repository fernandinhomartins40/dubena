import React from "react"
import FastImage from "react-native-fast-image"
import CartItems from "../templates/CartItems"
import { StyleSheet, Text, View } from "react-native"
import { colors, fontStyle, screenPadding } from "@/styles/theme"
import { CartProduct } from "@/types/types"

type Props = {
    products: CartProduct[]
    totalPrice?: string | null | undefined
    isGasPovo?: boolean
    deliveryTax?: number | null
}

const OrderItems = ({ products, totalPrice, isGasPovo = false, deliveryTax = null }: Props) => {
    let first = products[0]

    return (
        <View style={{ flexDirection: "row", gap: 4 }}>
            <View style={styles.productImage}>
                <FastImage source={{ uri: first.base64Img }} style={{ width: 80, height: 80 }} />
            </View>
            <View
                style={{
                    flexDirection: "column",
                    gap: 4,
                    justifyContent: "space-between",
                }}
            >
                <View>
                    <CartItems
                        orderProds={products}
                        isGasPovo={isGasPovo}
                        deliveryTax={deliveryTax}
                    />
                </View>

                <View>
                    <Text style={{ fontSize: 18, color: colors.primary, ...fontStyle.semiBold }}>
                        {totalPrice}
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
