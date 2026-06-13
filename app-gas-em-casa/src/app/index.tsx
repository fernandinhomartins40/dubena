import { useEffect } from "react"
import useAppStore from "@/store/appStore"
import { Href, useRouter } from "expo-router"
import { SplashScreen } from "expo-router"
import Loader from "@/components/templates/Loader"
import useFlashStore from "@/store/flashStore"
import { useQuery } from "@tanstack/react-query"
import StoreService from "@/services/store.service"
import { clearVideoCache } from "@/helpers/utils"

const Index = () => {
    const { user, config } = useAppStore()
    const { startupVideoShown, setStartupVideoShown } = useFlashStore()
    const { data: video, isLoading } = useQuery({
        queryKey: ["startup-video"],
        queryFn: () => StoreService.GetStartupVideo(),
        enabled: true,
    })
    const router = useRouter()

    useEffect(() => {
        if (isLoading) return

        const clearCache = async () => await clearVideoCache()

        if (video && !startupVideoShown) {
            setStartupVideoShown(true)
            SplashScreen.hideAsync()
            router.replace("/startupvideo")
            return
        } else if (!video) {
            clearCache()
        }

        const timer = setTimeout(() => {
            if (user) {
                router.replace("/(auth)/(tabs)/home")
            }

            if (!config.permissions && !user) {
                router.replace("/(tutorial)/page1" as Href)
            }

            if (config.permissions && !user) {
                router.replace("/login")
            }

            SplashScreen.hideAsync()
        }, 250)

        return () => clearTimeout(timer)
    }, [video, isLoading])

    return <Loader />
}

export default Index
