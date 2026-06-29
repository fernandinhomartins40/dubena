import { create } from "zustand"
import { createJSONStorage, persist } from "zustand/middleware"
import { zustandStorage } from "./storage"
import { Entregador } from "@/types/types"

/**
 * Store do App do Entregador (P7).
 *
 * Mais enxuta que a do cliente: o entregador só precisa do token Sanctum, do seu
 * perfil e do estado de "em serviço" (envia GPS) — não há carrinho/pagamento. O
 * tenant (empresa) é derivado do token no servidor; o app não decide nada disso.
 */
export interface AppStore {
    user?: Entregador | null
    apiToken?: string
    /** Quando true, o app envia pings de posição (P6) periodicamente. */
    emServico: boolean
    setUser: (user: Entregador) => void
    setToken: (token: string) => void
    setEmServico: (on: boolean) => void
    logout: () => void
}

const useAppStore = create<AppStore>()(
    persist(
        (set) => ({
            user: null,
            apiToken: "",
            emServico: false,
            setUser: (user) => set(() => ({ user })),
            setToken: (token) => set(() => ({ apiToken: token })),
            setEmServico: (on) => set(() => ({ emServico: on })),
            logout: () =>
                set(() => ({
                    user: null,
                    apiToken: "",
                    emServico: false,
                })),
        }),
        {
            name: "entregador-storage",
            storage: createJSONStorage(() => zustandStorage),
            // Hidratação manual: só após initSecureStorage() montar o MMKV cifrado
            // (ver src/app/_layout.tsx).
            skipHydration: true,
        },
    ),
)

export default useAppStore
