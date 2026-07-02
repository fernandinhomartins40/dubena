import useAppStore from "@/store/appStore"
import { Redirect } from "expo-router"

/** Gate de entrada: com token → app; sem token → login. */
export default function Index() {
    const token = useAppStore((s) => s.apiToken)
    return <Redirect href={token ? "/(app)/(tabs)/inicio" : "/login"} />
}
