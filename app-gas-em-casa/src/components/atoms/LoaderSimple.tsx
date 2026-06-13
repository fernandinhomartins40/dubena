import { colors } from "@/styles/theme"
import React from "react"
import { ActivityIndicator, View } from "react-native"

const LoaderSimple = () => (
    <View
        style={{
            marginTop: 15,
            height: "40%",
            display: "flex",
            justifyContent: "space-around",
            alignItems: "center",
        }}
    >
        <ActivityIndicator size="large" color={colors.primary} />
    </View>
)

export default LoaderSimple
