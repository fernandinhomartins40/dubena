import { StyleSheet, View } from "react-native"
import Button, { ButtonProps } from "./Button"
import { colors } from "@/styles/theme"
import React from "react"

interface IconButtonProps extends ButtonProps {
    noBackground?: boolean | undefined
    width: number
    height: number
    children: React.ReactNode
}

const IconButton = ({ width, height, children, noBackground, ...buttonProps }: IconButtonProps) => {
    return (
        <View style={{ borderRadius: 50, overflow: "hidden", width, height }}>
            <Button
                {...buttonProps}
                buttonStyle={[
                    styles.iconButton,
                    {
                        width,
                        height,
                        backgroundColor: noBackground ? "transparent" : colors.primaryMuted,
                    },
                ]}
            >
                {children}
            </Button>
        </View>
    )
}

const styles = StyleSheet.create({
    iconButton: {
        alignItems: "center",
        justifyContent: "center",
        borderRadius: 28,
    },
})

export default IconButton
