import { getApp } from "@react-native-firebase/app"
import { getMessaging, onMessage } from "@react-native-firebase/messaging"
import * as Notifications from "expo-notifications"
import { useEffect } from "react"
import { Platform } from "react-native"

Notifications.setNotificationHandler({
    handleNotification: async () => ({
        shouldPlaySound: true,
        shouldSetBadge: false,
        shouldShowBanner: true,
        shouldShowList: true,
    }),
})

const app = getApp()

const schedulePushNotification = async (
    title = "Nova mensagem",
    body = "Você tem uma nova mensagem!",
    data = {},
    imageUrl: any = null,
) => {
    let content = {
        title,
        body,
        data,
    } as any

    if (imageUrl && Platform.OS === "ios") {
        content.attachments = [
            {
                url: imageUrl,
            },
        ]
    }

    if (imageUrl && Platform.OS === "android") {
        content.imageurl = imageUrl
    }

    await Notifications.scheduleNotificationAsync({
        content,
        trigger: null,
    })
}

const useForegroundNotifications = () => {
    useEffect(() => {
        const unsub = onMessage(getMessaging(app), async (remoteMessage) => {
            let title = remoteMessage.notification?.title
            let body = remoteMessage.notification?.body
            let data = remoteMessage?.data || {}

            const imageUrl = data.imageurl || null

            await schedulePushNotification(title, body, data, imageUrl)
        })

        return () => unsub()
    }, [])
}

export default useForegroundNotifications
