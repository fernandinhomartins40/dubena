import { colors } from "@/styles/theme"
import * as Notifications from "expo-notifications"
import { useRouter } from "expo-router"
import { Pressable, Text } from "react-native"

const TestNotificationButton = () => {
    const router = useRouter()
    const handleClick = async () => {
        await Notifications.scheduleNotificationAsync({
            content: {
                title: "Teste de Imagem",
                body: "Testeeee",
                data: {
                    imageurl:
                        "http://qtidevel.ddns.net:8181/ctrl2qti/public/storage/img/notificacoes/791/notification-img.jpg",
                },
            },
            trigger: null, // immediate
        })
        // router.push({
        //     pathname: "/(auth)/(tabs)/home",
        //     params: {
        //         imageurl:
        //             "http://qtidevel.ddns.net:8181/ctrl2qti/public/storage/img/notificacoes/791/notification-img.jpg",
        //     } as any,
        // })
    }

    return (
        <Pressable
            onPress={handleClick}
            style={{ margin: 5, backgroundColor: colors.secondary, padding: 5, borderRadius: 10 }}
        >
            <Text>Enviar Notificação Teste</Text>
        </Pressable>
    )
}

export default TestNotificationButton
