import { View, Text, StyleSheet, Modal, ActivityIndicator } from "react-native"
import React from "react"

interface Props {
    isLoading: boolean
}

const LoaderOverlay = ({ isLoading }: Props) => {
    return (
        <Modal
            transparent={true}
            visible={isLoading}
            animationType="fade"
            onRequestClose={() => {}}
        >
            <View style={styles.overlay}>
                <View style={styles.loaderContainer}>
                    <ActivityIndicator size="large" color="#fff" />
                </View>
            </View>
        </Modal>
    )
}

const styles = StyleSheet.create({
    overlay: {
        flex: 1,
        justifyContent: "center",
        alignItems: "center",
        backgroundColor: "rgba(0, 0, 0, 0.5)",
    },
    loaderContainer: {
        backgroundColor: "rgba(0, 0, 0, 0.7)",
        padding: 20,
        borderRadius: 14,
    },
})

export default LoaderOverlay
