import React from "react"
import Button from "@/components/atoms/Button"
import UserService from "@/services/user.service"
import { defaultStyles, screenPadding } from "@/styles/theme"
import { useQuery } from "@tanstack/react-query"
import { useRouter } from "expo-router"
import { ScrollView, StyleSheet, Text, View } from "react-native"
import useAppStore from "@/store/appStore"
import { useSafeAreaInsets } from "react-native-safe-area-context"

const Policies = () => {
    const insets = useSafeAreaInsets()
    const { config, setConfig } = useAppStore()
    const { data: policies, isLoading } = useQuery({
        queryKey: ["policies"],
        queryFn: UserService.GetPolicies,
    })

    const router = useRouter()

    const handleOnPress = () => {
        setConfig({ ...config, termsAccepted: true })

        router.navigate("/sms")
    }

    return (
        <View style={defaultStyles.container}>
            <ScrollView
                style={[
                    styles.container,
                    defaultStyles.androidPadding,
                    { marginBottom: insets.bottom },
                ]}
            >
                {policies &&
                    policies.map((policy, idx) => (
                        <React.Fragment key={`policy_${idx}`}>
                            <Text style={policy.isHeader ? styles.header : styles.title}>
                                {policy.title}
                            </Text>
                            <Text>
                                {policy.description &&
                                    policy.description.map((desc, idx) => (
                                        <Text key={`text_${idx}`} style={styles.content}>
                                            {desc}
                                        </Text>
                                    ))}
                            </Text>
                        </React.Fragment>
                    ))}
                <View style={styles.button}>
                    <Button title="Aceitar" onPress={handleOnPress} disabled={isLoading} />
                </View>
            </ScrollView>
        </View>
    )
}

const styles = StyleSheet.create({
    container: {
        flex: 1,
        paddingHorizontal: screenPadding.horizontal,
    },
    header: {
        fontSize: 28,
        fontWeight: "bold",
        paddingTop: 10,
    },
    title: {
        fontSize: 24,
        paddingTop: 12,
    },
    content: {
        fontSize: 18,
        textAlign: "justify",
    },
    button: {
        paddingTop: 10,
        paddingBottom: 50,
    },
})

export default Policies
