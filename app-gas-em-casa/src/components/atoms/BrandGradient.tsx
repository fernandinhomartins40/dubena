import { LinearGradient } from "expo-linear-gradient"
import { StyleSheet, ViewStyle } from "react-native"
import { ReactNode } from "react"

/**
 * Fundo em gradiente da marca (laranja Supergasbras). Substitui o ImageBackground
 * roxo/rosa da marca antiga nas telas pré-login (login/SMS). Sem depender de PNG.
 */
const BrandGradient = ({ children, style }: { children: ReactNode; style?: ViewStyle }) => (
    <LinearGradient
        colors={["#FF7A1A", "#FF6200", "#E04E00"]}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 1 }}
        style={[styles.fill, style]}
    >
        {children}
    </LinearGradient>
)

const styles = StyleSheet.create({
    fill: { flex: 1 },
})

export default BrandGradient
