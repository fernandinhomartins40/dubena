import Loader from "@/components/templates/loader"
import { useRouter } from "expo-router"
import { useEffect } from "react"

/**
 * Vídeo de abertura desativado na F3 (dependia de endpoint público inexistente no
 * app/v1 — virá de uma config pública na F7). Mantido como rota inerte que volta ao boot.
 */
const StartupVideoScreen = () => {
    const router = useRouter()

    useEffect(() => {
        router.replace("/")
    }, [])

    return <Loader />
}

export default StartupVideoScreen
