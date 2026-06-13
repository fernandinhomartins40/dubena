import { useState } from "react"
import {
    Animated,
    GestureResponderEvent,
    Pressable,
    PressableProps,
    PressableStateCallbackType,
    StyleProp,
    StyleSheet,
    ViewStyle,
} from "react-native"

interface AnimatedPressableProps extends PressableProps {
    viewStyle?: StyleProp<ViewStyle>
    children: React.ReactNode | ((state: PressableStateCallbackType) => React.ReactNode)
}

const AnimatedPressable = ({
    style,
    onLongPress,
    onPressOut,
    viewStyle,
    children,
    ...rest
}: AnimatedPressableProps) => {
    const [scale] = useState(new Animated.Value(1))
    const containerStyle = StyleSheet.flatten(style)

    const handlePressOut = (e: GestureResponderEvent) => {
        Animated.spring(scale, {
            toValue: 1,
            useNativeDriver: true,
        }).start()

        if (typeof onPressOut === "function") {
            onPressOut(e)
        }
    }

    const handleLongPress = (e: GestureResponderEvent) => {
        Animated.spring(scale, {
            toValue: 1.05,
            useNativeDriver: true,
        }).start()

        if (typeof onLongPress === "function") {
            onLongPress(e)
        }
    }

    return (
        <Pressable
            style={containerStyle}
            onPressOut={handlePressOut}
            onLongPress={handleLongPress}
            {...rest}
        >
            <Animated.View style={[{ transform: [{ scale }] }, viewStyle]}>
                {typeof children === "function" ? children({ pressed: false }) : children}
            </Animated.View>
        </Pressable>
    )
}

export default AnimatedPressable
