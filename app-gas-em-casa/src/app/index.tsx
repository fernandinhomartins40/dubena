import { useEffect } from "react"
import useAppStore from "@/store/appStore"
import { Href, useRouter } from "expo-router"
import { SplashScreen } from "expo-router"
import Loader from "@/components/templates/Loader"

/**
 * Boot do app (F3). Roteia conforme sessão/permissões. O vídeo de abertura do legado
 * dependia de um endpoint público que ainda não existe no app/v1 — virá de uma config
 * pública (TODO F7), então foi desativado aqui para não quebrar o boot.
 */
const Index = () => {
    const { user, config } = useAppStore()
    const router = useRouter()

    useEffect(() => {
        const timer = setTimeout(() => {
            if (user) {
                router.replace("/(auth)/(tabs)/home")
            } else if (!config.permissions) {
                router.replace("/(tutorial)/page1" as Href)
            } else {
                router.replace("/login")
            }

            SplashScreen.hideAsync()
        }, 250)

        return () => clearTimeout(timer)
    }, [])

    return <Loader />
}

export default Index
