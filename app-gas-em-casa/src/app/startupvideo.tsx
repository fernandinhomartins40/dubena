import Loader from "@/components/templates/Loader"
import startupVideoCache, { getValidVideoCache } from "@/helpers/startupVideoCache"
import { clearVideoCache } from "@/helpers/utils"
import StoreService from "@/services/store.service"
import { fontSize } from "@/styles/theme"
import { useQuery } from "@tanstack/react-query"
import { useRouter } from "expo-router"
import { useVideoPlayer, VideoView } from "expo-video"
import { useEffect, useState } from "react"
import { Dimensions, Pressable, StyleSheet, Text, View } from "react-native"

const { width } = Dimensions.get("window")

const StartupVideoScreen = () => {
    const router = useRouter()
    const [localUri, setLocalUri] = useState<string | null>(null)
    const [currentTime, setCurrentTime] = useState(0)
    const [duration, setDuration] = useState(0)
    const { data: video, isLoading } = useQuery({
        queryKey: ["startup-video"],
        queryFn: () => StoreService.GetStartupVideo(),
        enabled: true,
    })
    const player = useVideoPlayer(null, (player) => {
        player.loop = false
        player.timeUpdateEventInterval = 1
    })
    const isSmaller = width <= 360

    useEffect(() => {
        if (isLoading) return

        const cache = async () => {
            const url = video?.url ?? null
            const updated = video?.updated ?? null

            if (!url || !updated) {
                await clearVideoCache()
                router.replace("/")
                return
            }

            const cached = await getValidVideoCache(updated)

            if (cached) {
                setLocalUri(cached)
            } else {
                setLocalUri(url)

                startupVideoCache(updated, url).catch((err) => {
                    console.warn("background cache failed")
                })
            }
        }

        cache()
    }, [video, isLoading])

    useEffect(() => {
        if (!localUri) return

        player.addListener("playToEnd", () => {
            router.replace("/")
        })

        player.addListener("timeUpdate", (src) => {
            setCurrentTime(src.currentTime ?? 0)
        })

        player.addListener("sourceLoad", (src) => {
            setDuration(src.duration ?? 0)
        })

        /**
         * Fall back listener for IOS
         * SourceLoad is not called when the video is streamed, so the duration is not updated
         */
        player.addListener("statusChange", (src) => {
            if (src.status === "readyToPlay" && duration === 0) {
                setDuration(player.duration ?? 0)
            }
        })

        player.replaceAsync(localUri).then(() => {
            player.play()
        })

        return () => {
            player.removeAllListeners("playToEnd")
            player.removeAllListeners("sourceLoad")
            player.removeAllListeners("timeUpdate")
            player.removeAllListeners("statusChange")
        }
    }, [localUri])

    const formatTime = (seconds: number) => {
        const m = Math.floor(seconds / 60)
        const s = Math.floor(seconds % 60)
        return `${m.toString().padStart(2, "0")}:${s.toString().padStart(2, "0")}`
    }

    if (isLoading || !video || !localUri) return <Loader />

    return (
        <View style={styles.container}>
            <VideoView
                allowsFullscreen
                nativeControls={false}
                style={styles.video}
                player={player}
            />

            <View
                style={[
                    styles.timeContainer,
                    {
                        bottom: isSmaller ? 60 : 99,
                    },
                ]}
            >
                <Text style={styles.timeText}>
                    {formatTime(currentTime)} / {formatTime(duration)}
                </Text>
            </View>

            <Pressable
                style={[
                    styles.skipButton,
                    {
                        bottom: isSmaller ? 60 : 100,
                    },
                ]}
                onPress={() => router.replace("/")}
            >
                <Text style={styles.skipText}>Pular</Text>
            </Pressable>
        </View>
    )
}

const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: "#000",
    },
    video: {
        width: "100%",
        height: "95%",
    },
    skipButton: {
        position: "absolute",
        right: 20,
        paddingHorizontal: 20,
        paddingVertical: 12,
        backgroundColor: "rgba(255,255,255,0.2)",
        borderRadius: 12,
    },
    skipText: {
        color: "white",
        fontSize: fontSize.lg,
        fontWeight: "600",
    },
    timeContainer: {
        position: "absolute",
        left: 20,
        paddingHorizontal: 12,
        paddingVertical: 6,
        backgroundColor: "rgba(0,0,0,0.4)",
        borderRadius: 8,
    },
    timeText: {
        color: "white",
        fontSize: 14,
        fontWeight: "500",
    },
})

export default StartupVideoScreen
