import { fontSize, fontStyle } from "@/styles/theme"
import { Product } from "@/types/types"
import { Animated, Dimensions, StyleSheet, Text, View } from "react-native"
import FastImage from "react-native-fast-image"
import Feather from "@expo/vector-icons/Feather"
import { PER_WIDTH } from "@/constants/app"
import useFlashStore from "@/store/flashStore"
import IconButton from "../atoms/IconButton"
import useAppStore from "@/store/appStore"

interface ProductListItem {
    index: number
    product: Product
    productsLength: number
    scrollX: Animated.Value
}

const { width } = Dimensions.get("window")

const AnimatedFastImage = Animated.createAnimatedComponent(FastImage)

const ProductListItem = ({ product, index, productsLength, scrollX }: ProductListItem) => {
    const { user } = useAppStore()
    const { cart, addToCart, removeFromCart } = useFlashStore()
    const { products } = cart
    const cartProduct = products[product.id]
    const inputRange = [
        (index - 1) * (width * PER_WIDTH),
        index * (width * PER_WIDTH),
        (index + 1) * (width * PER_WIDTH),
    ]
    const scale = scrollX.interpolate({
        inputRange,
        outputRange: [0.9, 1, 0.9],
        extrapolate: "clamp",
    })
    const blur = scrollX.interpolate({
        inputRange,
        outputRange: [0.4, 1, 0.4],
        extrapolate: "clamp",
    })
    const translateY = scrollX.interpolate({
        inputRange,
        outputRange: [25, 0, 25],
        extrapolate: "clamp",
    })

    const shouldDisable =
        user?.gasdopovo && cartProduct?.quantity && cartProduct.quantity >= 1 ? true : false

    return (
        <Animated.View
            style={[
                styles.container,
                {
                    transform: [{ scale }, { translateY }, { perspective: 1000 }],
                },
            ]}
        >
            <View style={styles.card}>
                <View>
                    <AnimatedFastImage
                        source={{ uri: product.base64Img }}
                        style={[styles.image, { opacity: blur }]}
                    />
                </View>
                <View>
                    <Text style={[styles.title, fontStyle.semiBold]}>{product.descricao}</Text>
                </View>
                <Animated.View style={[styles.cartControls, { opacity: blur }]}>
                    <IconButton width={46} height={46} onPress={() => removeFromCart(product)}>
                        <Feather name="minus" size={18} color="black" />
                    </IconButton>

                    <Text style={{ fontSize: 20 }}>{cartProduct ? cartProduct.quantity : 0}</Text>

                    <IconButton
                        disabled={shouldDisable}
                        width={46}
                        height={46}
                        onPress={() => addToCart(product)}
                    >
                        <Feather name="plus" size={18} color="black" />
                    </IconButton>
                </Animated.View>
            </View>
        </Animated.View>
    )
}

const styles = StyleSheet.create({
    container: {
        width: width * PER_WIDTH,
    },
    image: {
        width: 100,
        height: 120,
    },
    title: {
        fontSize: fontSize.sm,
    },
    card: {
        display: "flex",
        justifyContent: "center",
        flexDirection: "column",
        alignItems: "center",
        gap: 8,
        borderRadius: 16,
        marginVertical: 10,
        paddingVertical: 20,
        borderWidth: StyleSheet.hairlineWidth,
        borderColor: "#E1E1E1",
    },
    cartControls: {
        display: "flex",
        flexDirection: "row",
        alignItems: "center",
        gap: 8,
        borderWidth: StyleSheet.hairlineWidth,
        borderColor: "#E8E8E8",
        borderRadius: 28,
        paddingVertical: 5,
        paddingHorizontal: 8,
    },
})

export default ProductListItem
