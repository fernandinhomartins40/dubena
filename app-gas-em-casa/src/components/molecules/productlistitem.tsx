import { colors, fontSize, fontStyle } from "@/styles/theme"
import { CatalogItem } from "@/types/types"
import { Animated, Dimensions, StyleSheet, Text, View } from "react-native"
import FastImage from "react-native-fast-image"
import Feather from "@expo/vector-icons/Feather"
import { PER_WIDTH } from "@/constants/app"
import { GasImgUri } from "@/constants/images"
import useFlashStore from "@/store/flashStore"
import IconButton from "../atoms/IconButton"

const brl = (v: number) => new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(v)

interface ProductListItem {
    index: number
    product: CatalogItem
    productsLength: number
    scrollX: Animated.Value
}

const { width } = Dimensions.get("window")

const AnimatedFastImage = Animated.createAnimatedComponent(FastImage)

const ProductListItem = ({ product, index, productsLength, scrollX }: ProductListItem) => {
    const { cart, gasdopovo, addToCart, removeFromCart } = useFlashStore()
    const quantity = cart[product.id] ?? 0
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

    // Gás do Povo: limita a 1 unidade por pedido.
    const shouldDisable = gasdopovo && quantity >= 1
    const selecionado = quantity > 0
    const preco = gasdopovo && product.preco_gasdopovo != null ? product.preco_gasdopovo : product.preco

    return (
        <Animated.View
            style={[
                styles.container,
                {
                    transform: [{ scale }, { translateY }, { perspective: 1000 }],
                },
            ]}
        >
            <View style={[styles.card, selecionado && styles.cardSelected]}>
                {selecionado && (
                    <View style={styles.badge}>
                        <Text style={styles.badgeText}>{quantity}</Text>
                    </View>
                )}

                <AnimatedFastImage source={{ uri: GasImgUri }} style={[styles.image, { opacity: blur }]} />

                <Text style={[styles.title, fontStyle.semiBold]} numberOfLines={2}>
                    {product.descricao}
                </Text>

                {preco ? <Text style={styles.price}>{brl(preco)}</Text> : null}

                <Animated.View style={[styles.cartControls, { opacity: blur }]}>
                    <IconButton width={44} height={44} onPress={() => removeFromCart(product.id)}>
                        <Feather name="minus" size={18} color={colors.graphite} />
                    </IconButton>

                    <Text style={styles.qty}>{quantity}</Text>

                    <IconButton
                        disabled={shouldDisable}
                        width={44}
                        height={44}
                        onPress={() => addToCart(product.id)}
                    >
                        <Feather name="plus" size={18} color={colors.graphite} />
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
        width: 96,
        height: 116,
    },
    title: {
        fontSize: fontSize.sm,
        textAlign: "center",
        color: colors.text,
    },
    price: {
        fontSize: fontSize.md,
        color: colors.primary,
        ...fontStyle.bold,
    },
    qty: {
        fontSize: 20,
        minWidth: 24,
        textAlign: "center",
        color: colors.text,
        ...fontStyle.semiBold,
    },
    card: {
        justifyContent: "center",
        flexDirection: "column",
        alignItems: "center",
        gap: 8,
        borderRadius: 18,
        marginVertical: 10,
        marginHorizontal: 6,
        paddingVertical: 18,
        paddingHorizontal: 10,
        backgroundColor: colors.surface,
        borderWidth: 1,
        borderColor: colors.border,
    },
    cardSelected: {
        borderColor: colors.primary,
        borderWidth: 2,
        backgroundColor: colors.primaryMuted,
    },
    badge: {
        position: "absolute",
        top: 10,
        right: 12,
        minWidth: 24,
        height: 24,
        paddingHorizontal: 6,
        borderRadius: 999,
        backgroundColor: colors.primary,
        alignItems: "center",
        justifyContent: "center",
        zIndex: 2,
    },
    badgeText: {
        color: colors.white,
        fontSize: fontSize.xs,
        ...fontStyle.bold,
    },
    cartControls: {
        flexDirection: "row",
        alignItems: "center",
        gap: 8,
        borderWidth: 1,
        borderColor: colors.border,
        borderRadius: 28,
        paddingVertical: 5,
        paddingHorizontal: 8,
        backgroundColor: colors.surface,
    },
})

export default ProductListItem
