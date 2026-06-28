import { CatalogItem } from "@/types/types"
import { Animated, Dimensions, FlatList } from "react-native"
import { useEffect, useRef, useState } from "react"
import { PER_WIDTH } from "@/constants/app"
import ProductListItem from "../molecules/ProductListItem"

interface ProductListProps {
    products: CatalogItem[] | undefined
}

const { width } = Dimensions.get("window")

const ProductList = ({ products }: ProductListProps) => {
    if (!products) return null

    const [_idx, setCurrentIndex] = useState(0)
    const scrollX = useRef(new Animated.Value(0)).current
    const flatListRef = useRef<FlatList>(null)

    const handleScroll = Animated.event([{ nativeEvent: { contentOffset: { x: scrollX } } }], {
        useNativeDriver: false,
        listener: (event: any) => {
            const index = Math.floor(event.nativeEvent.contentOffset.x / (width * PER_WIDTH))
            setCurrentIndex(index)
        },
    })

    useEffect(() => {
        if (typeof products === "undefined" || products.length <= 0) return

        let idx = products.findIndex((item) => item.descricao.includes("13"))

        if (idx < 0) return

        flatListRef.current?.scrollToIndex({
            index: idx,
            animated: true,
        })
    }, [products.length])

    return (
        <FlatList
            horizontal
            ref={flatListRef}
            data={products}
            keyExtractor={(product) => String(product.id)}
            showsHorizontalScrollIndicator={false}
            renderItem={({ item: product, index }: { item: CatalogItem; index: number }) => (
                <ProductListItem
                    scrollX={scrollX}
                    index={index}
                    product={product}
                    productsLength={products.length}
                />
            )}
            snapToInterval={width * PER_WIDTH}
            decelerationRate="fast"
            scrollEventThrottle={16}
            onScroll={handleScroll}
            getItemLayout={(_, index) => ({
                length: width * PER_WIDTH,
                offset: index * width * PER_WIDTH,
                index,
            })}
            contentContainerStyle={{
                paddingHorizontal: (width * (1 - PER_WIDTH)) / 2,
            }}
        />
    )
}

export default ProductList
