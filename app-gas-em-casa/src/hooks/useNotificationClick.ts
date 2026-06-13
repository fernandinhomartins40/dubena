import { useEffect } from "react"
import * as Notifications from "expo-notifications"
import useFlashStore from "@/store/flashStore"
import useAppStore from "@/store/appStore"

export default function useNotificationClick() {
    const { setPendingNavigation } = useFlashStore()
    const { hasNotificationId, addNotificationId } = useAppStore()

    const handleResponse = (response: Notifications.NotificationResponse) => {
        let id = response.notification.request.identifier

        if (hasNotificationId(id)) return

        addNotificationId(id)

        const data = response.notification.request.content.data

        if (data?.imageurl) {
            setPendingNavigation({ imageUrl: String(data.imageurl) })
            return
        }

        const trigger = response.notification.request?.trigger

        if (trigger && "payload" in trigger) {
            const payload = trigger.payload

            if (payload?.imageurl) {
                setPendingNavigation({ imageUrl: String(payload.imageurl) })
                return
            }
        }
    }

    useEffect(() => {
        const sub = Notifications.addNotificationResponseReceivedListener((res) => {
            return handleResponse(res)
        })

        const checkLastNoti = async () => {
            const lastResponse = await Notifications.getLastNotificationResponseAsync()

            if (lastResponse) {
                handleResponse(lastResponse)
            }
        }

        checkLastNoti()

        return () => sub.remove()
    }, [])
}
