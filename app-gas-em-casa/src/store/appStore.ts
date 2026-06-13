import { create } from "zustand"
import { createJSONStorage, persist } from "zustand/middleware"
import { zustandStorage } from "./storage"
import { OnlinePaymentTries, User } from "@/types/types"

type Config = {
    permissions: boolean
    token: string
    termsAccepted: boolean
}

type LoginData = {
    name: string
    phone: string
}

type NotificationIds = {
    id: string
}

export interface AppStore {
    user?: User | null
    apiToken?: string
    config: Config
    loginData: LoginData
    onlineTries: OnlinePaymentTries | null
    notificationsId: Array<NotificationIds> | null
    setUser: (user: User) => void
    setNewAddress: (address_id: number) => void
    setConfig: (config: Config) => void
    setPermissions: (granted: boolean) => void
    setLoginData: (data: LoginData) => void
    setToken: (token: string) => void
    tryPayOnline: () => void
    voidOnlineTries: () => void
    logout: () => void
    addNotificationId: (id: string) => void
    hasNotificationId: (id: string) => boolean
}

const useAppStore = create<AppStore>()(
    persist(
        (set) => ({
            user: null,
            apiToken: "",
            onlineTries: null,
            config: {
                permissions: false,
                token: "",
                termsAccepted: false,
            },
            loginData: {
                name: "",
                phone: "",
            },
            notificationsId: null,
            setUser: (user: any) => set(() => ({ user })),
            setNewAddress: (address_id: number) =>
                set((state) => {
                    if (state.user) {
                        state.user.enderecopadrao_id = address_id
                    }

                    return state
                }),
            setConfig: (config: Config) => set(() => ({ config })),
            setPermissions: (granted: boolean) =>
                set((state) => {
                    state.config.permissions = granted

                    return state
                }),
            setLoginData: (data: LoginData) =>
                set((state) => {
                    state.loginData = data
                    return state
                }),
            setToken: (token) =>
                set((state) => {
                    state.apiToken = token
                    return state
                }),
            tryPayOnline: () =>
                set((state) => {
                    let tries = state.onlineTries

                    if (!tries) {
                        tries = {
                            date: Date.now(),
                            tries: 0,
                        }
                    }

                    tries.tries++

                    return { onlineTries: { ...tries } }
                }),
            voidOnlineTries: () => set({ onlineTries: null }),
            logout: () =>
                set((state) => {
                    return {
                        user: null,
                        apiToken: "",
                        onlineTries: null,
                        config: {
                            ...state.config,
                            token: "",
                        },
                        loginData: {
                            name: "",
                            phone: "",
                        },
                    }
                }),
            addNotificationId: (id: string) =>
                set((state) => {
                    const notifications = state.notificationsId ?? []

                    if (notifications.some((noti) => noti.id === id)) return state

                    const updated = [...notifications, { id }]

                    if (updated.length > 5) {
                        updated.shift()
                    }

                    return { notificationsId: updated }
                }),
            hasNotificationId: (id): boolean => {
                let { notificationsId } = useAppStore.getState()

                if (!notificationsId) return false

                return notificationsId.some((noti) => noti.id === id)
            },
        }),
        {
            name: "default-storage",
            storage: createJSONStorage(() => zustandStorage),
        },
    ),
)

export default useAppStore
