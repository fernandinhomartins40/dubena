import { colors, fontSize } from "@/styles/theme"
import { useRef, useState } from "react"
import {
    Keyboard,
    Platform,
    Pressable,
    StyleSheet,
    Text,
    TextInput,
    TextInputProps,
    View,
} from "react-native"

const CODE_LEN = 6

interface InputProps extends TextInputProps {
    code: string
    setCode: React.Dispatch<React.SetStateAction<string>>
}

const SmsInput = ({ code, setCode, onSubmitEditing }: InputProps) => {
    const codeDigits = new Array(CODE_LEN).fill("")
    const [containerIsFocused, setContainerIsFocused] = useState(false)
    const inputRef = useRef<TextInput>(null)

    const digitToText = (_v: number, idx: number) => {
        const digit = code[idx] || " "
        const isCurrentDigit = idx === code.length
        const isLastDigit = idx === CODE_LEN - 1
        const isCodeFull = code.length === CODE_LEN

        const isFocused = isCurrentDigit || (isLastDigit && isCodeFull)

        const containerStyle =
            containerIsFocused && isFocused
                ? { ...styles.inputsContainer, ...styles.inputContainerFocused }
                : styles.inputsContainer

        return (
            <View key={idx} style={containerStyle}>
                <Text style={styles.inputText}>{digit}</Text>
            </View>
        )
    }

    const handleOnPress = () => {
        setCode("")

        setContainerIsFocused(true)

        if (Platform.OS === "android") Keyboard.dismiss()

        setTimeout(() => {
            inputRef.current?.focus()
        }, 55)
    }

    const handleOnBlur = (e: any) => {
        setContainerIsFocused(false)

        if (onSubmitEditing) onSubmitEditing(e)
    }

    return (
        <View>
            <Pressable onPress={handleOnPress}>
                <View style={{ display: "flex", flexDirection: "row", gap: 8 }}>
                    {codeDigits.map(digitToText)}
                </View>
            </Pressable>

            <TextInput
                ref={inputRef}
                value={code}
                onChangeText={setCode}
                keyboardType="number-pad"
                returnKeyType="done"
                textContentType="oneTimeCode"
                maxLength={CODE_LEN}
                style={styles.hiddenCodeInput}
                onSubmitEditing={handleOnBlur}
            />
        </View>
    )
}

const styles = StyleSheet.create({
    container: {
        flex: 1,
        flexDirection: "row",
        display: "flex",
        justifyContent: "center",
        alignItems: "center",
    },
    hiddenCodeInput: {
        position: "absolute",
        opacity: 0,
    },
    inputsContainer: {
        borderColor: "#000",
        borderWidth: 0.25,
        borderRadius: 6,
        padding: 14,
    },
    inputText: {
        fontSize: fontSize.lg,
    },
    inputContainerFocused: {
        borderColor: colors.primary,
    },
})

export default SmsInput
