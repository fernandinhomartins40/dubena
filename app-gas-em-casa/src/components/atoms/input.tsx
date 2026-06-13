import React, { useImperativeHandle } from "react"
import { colors, fontSize, fontStyle } from "@/styles/theme"
import { useRef } from "react"
import {
    Pressable,
    StyleProp,
    StyleSheet,
    Text,
    TextInput,
    TextInputProps,
    TextProps,
    TextStyle,
    TouchableWithoutFeedback,
    View,
    ViewStyle,
} from "react-native"
import { MaskedTextInput, MaskedTextInputRef } from "react-native-advanced-input-mask"

export interface InputProps extends TextInputProps {
    component?: string
    noDisabledStyle?: boolean
    disabled?: boolean
    textStyle?: StyleProp<TextStyle>
    label?: string
    labelTextProps?: TextProps
    labelStyle?: StyleProp<TextStyle>
    mask?: string
    onChangeText?: (text: string, rawText?: string | null) => void
    inputSufix?: any
    onPress?: () => void
}

type Ref = TextInput | MaskedTextInputRef

const Input = React.forwardRef<Ref, InputProps>(
    (
        {
            noDisabledStyle,
            component,
            disabled,
            textStyle,
            label,
            labelStyle,
            labelTextProps,
            style,
            onChangeText,
            inputSufix,
            onPress,
            mask,
            ...textInputProps
        },
        ref,
    ) => {
        const containerStyle = StyleSheet.flatten(style) as ViewStyle
        const textRef = useRef<TextInput>(null)
        const maskedRef = useRef<MaskedTextInputRef>(null)
        const disabledStyle = disabled && !noDisabledStyle ? styles.disabledStyle : {}

        useImperativeHandle(ref, () => {
            if (component === "masked") {
                return (
                    maskedRef.current ??
                    ({
                        focus: () => {},
                        blur: () => {},
                    } as MaskedTextInputRef)
                )
            }
            return (
                textRef.current ??
                ({
                    focus: () => {},
                    blur: () => {},
                } as TextInput)
            )
        })

        const focus = () => {
            if (onPress) return onPress()

            if (component === "masked") {
                maskedRef.current?.focus()
                return
            }

            textRef.current?.focus()
        }

        const internalOnchangeText = (text: string) => onChangeText?.(text)

        const internalOnchangeTextMasked = (text: string, rawText: string) =>
            onChangeText?.(text, rawText)

        const renderInput = () => {
            if (component === "masked") {
                return (
                    <MaskedTextInput
                        ref={maskedRef}
                        placeholderTextColor={colors.textMuted}
                        {...textInputProps}
                        mask={mask ?? ""}
                        style={[styles.text, textStyle, disabledStyle]}
                        editable={!disabled}
                        onChangeText={internalOnchangeTextMasked}
                    />
                )
            }

            return (
                <TextInput
                    ref={textRef}
                    placeholderTextColor={colors.textMuted}
                    {...textInputProps}
                    style={[styles.text, textStyle, disabledStyle]}
                    editable={!disabled}
                    onChangeText={internalOnchangeText}
                />
            )
        }

        return (
            <Pressable onPress={focus} style={{ flex: 1 }} android_disableSound>
                <View style={[{ ...containerStyle }]}>
                    <FalsyText
                        title={label}
                        style={[styles.label, labelStyle]}
                        {...labelTextProps}
                    />

                    <View style={[styles.inputContainer, { padding: !!inputSufix ? 5 : 0 }]}>
                        {renderInput()}

                        {!!inputSufix && inputSufix}
                    </View>
                </View>
            </Pressable>
        )
    },
)

const FalsyText = ({ title, ...textProps }: TextProps & { title?: string }) => {
    if (!title) return null

    return <Text {...textProps}>{title}</Text>
}

const styles = StyleSheet.create({
    inputContainer: {
        flexDirection: "row",
        alignItems: "center",
        width: "100%",
        borderColor: "#000",
        borderWidth: 0.25,
        borderRadius: 8,
    },
    label: {
        color: "#000",
        fontSize: fontSize.sm,
        marginBottom: 5,
        ...fontStyle.regular,
    },
    text: {
        flex: 1,
        padding: 12,
        fontSize: fontSize.sm,
        color: colors.text,
    },
    disabledStyle: {
        backgroundColor: colors.disabled,
        overflow: "hidden",
    },
})

export default Input
