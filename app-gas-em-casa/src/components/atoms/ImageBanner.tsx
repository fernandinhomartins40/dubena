import { Animated, Dimensions, Pressable, StyleSheet, Text } from "react-native"
import FastImage from "react-native-fast-image"
import { useEffect, useRef, useState } from "react"
import useFlashStore from "@/store/flashStore"

const ImageBanner = () => {
    const { pendingNavigation, clearPendingNavigation } = useFlashStore()

    useEffect(() => {
        if (!pendingNavigation) return

        if (pendingNavigation.imageUrl) {
            setImage(pendingNavigation.imageUrl)

            Animated.parallel([
                Animated.timing(opacity, {
                    toValue: 1,
                    duration: 220,
                    useNativeDriver: true,
                }),
                Animated.spring(scale, {
                    toValue: 1,
                    speed: 12,
                    bounciness: 6,
                    useNativeDriver: true,
                }),
            ]).start()
        }
    }, [pendingNavigation])

    const [image, setImage] = useState<string>("")
    const opacity = useRef(new Animated.Value(0)).current
    const scale = useRef(new Animated.Value(0.8)).current
    const screen = Dimensions.get("window")

    const handlePress = () => {
        Animated.parallel([
            Animated.timing(opacity, {
                toValue: 0,
                duration: 160,
                useNativeDriver: true,
            }),
            Animated.timing(scale, {
                toValue: 0.8,
                duration: 160,
                useNativeDriver: true,
            }),
        ]).start(() => {
            setImage("")

            clearPendingNavigation()
        })
    }

    const isSmaller = screen.width <= 360
    const height = isSmaller ? screen.height * 0.8 : screen.height * 0.85

    if (!pendingNavigation) return null

    return (
        <Animated.View style={[styles.overlay, { opacity: opacity }]}>
            <Animated.View
                style={[
                    styles.bannerContainer,
                    {
                        transform: [{ scale }],
                        width: screen.width * 0.9,
                        height: height,
                        marginTop: screen.height * 0.035,
                    },
                ]}
            >
                <Pressable style={styles.closeButton} onPress={handlePress}>
                    <Text style={styles.closeText}>X</Text>
                </Pressable>

                <FastImage source={{ uri: image }} style={styles.image} resizeMode="contain" />
            </Animated.View>
        </Animated.View>
    )
}

const styles = StyleSheet.create({
    overlay: {
        position: "absolute",
        top: 0,
        left: 0,
        width: "100%",
        height: "100%",
        backgroundColor: "rgba(0,0,0,0.6)",
        justifyContent: "flex-start",
        alignItems: "center",
        zIndex: 999,
    },
    bannerContainer: {
        backgroundColor: "#000",
        borderRadius: 16,
        overflow: "hidden",
        justifyContent: "center",
        alignItems: "center",
        position: "relative",
    },
    closeButton: {
        position: "absolute",
        top: 12,
        right: 12,
        zIndex: 50,
        backgroundColor: "rgba(255,255,255,0.2)",
        width: 36,
        height: 36,
        borderRadius: 18,
        justifyContent: "center",
        alignItems: "center",
    },
    closeText: {
        color: "#fff",
        fontSize: 20,
        lineHeight: 20,
        fontWeight: "600",
    },
    image: {
        top: 0,
        position: "absolute",
        width: "100%",
        height: "100%",
    },
})

export default ImageBanner
