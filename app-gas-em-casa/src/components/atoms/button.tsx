import renderNode from "@/helpers/renderNode"
import { colors, fontSize, fontStyle } from "@/styles/theme"
import React from "react"
import {
    Platform,
    StyleProp,
    StyleSheet,
    Text,
    TextStyle,
    TouchableNativeFeedback,
    TouchableNativeFeedbackProps,
    TouchableOpacity,
    TouchableOpacityProps,
    View,
    ViewStyle,
} from "react-native"

export interface ButtonProps extends TouchableOpacityProps, TouchableNativeFeedbackProps {
    TouchableComponent?: React.ReactElement
    title?: string | React.ReactElement<{}>
    onPress?: () => void
    rounded?: boolean
    disabled?: boolean
    uppercase?: boolean
    type?: "solid" | "outline" | "clear"
    textStyle?: StyleProp<TextStyle>
    buttonStyle?: StyleProp<ViewStyle>
}

const Button = ({
    TouchableComponent,
    onPress,
    title,
    rounded = true,
    disabled = false,
    uppercase = true,
    type = "solid",
    textStyle: textStyleProp = {},
    children = title,
    buttonStyle = {},
}: ButtonProps) => {
    const TouchableComponentInternal =
        TouchableComponent ||
        Platform.select({
            android: TouchableNativeFeedback,
            default: TouchableOpacity as any,
        })

    const textStyle = uppercase ? styles.uppercase : {}

    return (
        <TouchableComponentInternal disabled={disabled} activeOpacity={0.3} onPress={onPress}>
            <View
                style={[
                    styles.button,
                    rounded && styles.rounded,
                    disabled && styles.disabled,
                    type != "solid" && { backgroundColor: "transparent" },
                    buttonStyle,
                ]}
            >
                {React.Children.toArray(children).map((child, index) => (
                    <React.Fragment key={index}>
                        {typeof child === "string"
                            ? renderNode(Text, child, {
                                  style: [
                                      styles.buttonText,
                                      textStyle,
                                      type == "clear" && styles.textClear,
                                      textStyleProp,
                                      fontStyle.semiBold,
                                  ],
                              })
                            : child}
                    </React.Fragment>
                ))}
            </View>
        </TouchableComponentInternal>
    )
}

const styles = StyleSheet.create({
    button: {
        width: "100%",
        backgroundColor: colors.primary,
        paddingHorizontal: 15,
        paddingVertical: 10,
    },
    rounded: {
        borderRadius: 12,
    },
    buttonText: {
        fontSize: fontSize.base,
        fontWeight: "bold",
        color: "#fff",
        textAlign: "center",
    },
    disabled: {
        backgroundColor: "#ccc",
        color: "#999",
    },
    uppercase: {
        textTransform: "uppercase",
    },
    textClear: {
        color: "#000",
        fontSize: fontSize.base,
    },
})

export default Button
