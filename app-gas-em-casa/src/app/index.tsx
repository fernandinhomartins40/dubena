import { useEffect } from "react"
import useAppStore from "@/store/appStore"
import { Href, useRouter } from "expo-router"
import { SplashScreen } from "expo-router"
import Loader from "@/components/templates/loader"
import { APP } from "@/constants/app"

/**
 * Boot do app (F3/F7). Roteia conforme sessão/permissões. Sem revenda escolhida
 * (marketplace) e sem empresa de build (white-label), o fluxo passa pela seleção
 * de revenda ANTES do login.
 */
const Index = () => {
    const { user, config, empresaAtiva } = useAppStore()
    const router = useRouter()

    useEffect(() => {
        const timer = setTimeout(() => {
            if (user) {
                router.replace("/(auth)/(tabs)/home")
            } else if (!config.permissions) {
                router.replace("/(tutorial)/page1" as Href)
            } else if (!empresaAtiva && !APP.empresa_id) {
                router.replace("/selecionar-revenda" as Href)
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
