import { clearVideoCache, loadVideoMeta, saveVideoMeta } from "@/helpers/utils"
import * as FileSystem from "expo-file-system"

export async function getValidVideoCache(updatedAt: number | null): Promise<string | null> {
    if (!updatedAt) {
        await clearVideoCache()
        return null
    }

    const meta = await loadVideoMeta()

    if (!meta || meta.updated != updatedAt) return null

    const info = await FileSystem.getInfoAsync(meta.localUri)

    if (!info.exists) return null

    return meta.localUri
}

export default async function startupVideoCache(updatedAt: number, backendUrl: string) {
    const filename = "startup-video.mp4"
    const localUri = FileSystem.cacheDirectory + filename

    await clearVideoCache()

    await FileSystem.downloadAsync(backendUrl, localUri)

    await saveVideoMeta({ url: backendUrl, localUri: localUri, updated: updatedAt })
}
