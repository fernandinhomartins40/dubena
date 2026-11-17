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

/** Revenda escolhida no marketplace (F7). Persistida entre sessões. */
export type EmpresaAtiva = {
    id: number
    nome: string
    distancia_km?: number | null
    tempo_entrega_min?: number | null
}

export interface AppStore {
    user?: User | null
    apiToken?: string
    config: Config
    loginData: LoginData
    onlineTries: OnlinePaymentTries | null
    notificationsId: Array<NotificationIds> | null
    empresaAtiva: EmpresaAtiva | null
    setEmpresaAtiva: (empresa: EmpresaAtiva) => void
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
            empresaAtiva: null,
            // F7 (segurança): trocar de revenda INVALIDA a sessão local — o token
            // Sanctum pertence a UMA empresa e nunca é reutilizado em outra.
            setEmpresaAtiva: (empresa: EmpresaAtiva) =>
                set((state) => {
                    const trocou = state.empresaAtiva && state.empresaAtiva.id !== empresa.id

                    return {
                        empresaAtiva: empresa,
                        ...(trocou
                            ? {
                                  user: null,
                                  apiToken: "",
                                  onlineTries: null,
                                  config: { ...state.config, token: "" },
                              }
                            : {}),
                    }
                }),
            setUser: (user: any) => set(() => ({ user })),
            // PIE R3-E-06: estes setters MUTAVAM o estado e devolviam o mesmo
            // objeto. O zustand compara por referência: nada era notificado nem
            // PERSISTIDO. Com a renovação do token, o token novo ficaria só na
            // memória — ao reabrir, o app carregaria do disco o token já revogado
            // pelo refresh e cairia no login. Sempre um objeto novo.
            setNewAddress: (address_id: number) =>
                set((state) => (state.user ? { user: { ...state.user, enderecopadrao_id: address_id } } : {})),
            setConfig: (config: Config) => set(() => ({ config })),
            setPermissions: (granted: boolean) =>
                set((state) => ({ config: { ...state.config, permissions: granted } })),
            setLoginData: (data: LoginData) => set({ loginData: data }),
            setToken: (token) => set({ apiToken: token }),
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
            // Hidratação manual: só após initSecureStorage() montar o MMKV cifrado
            // (ver src/app/_layout.tsx). Evita ler do storage antes da chave estar pronta.
            skipHydration: true,
        },
    ),
)

export default useAppStore
