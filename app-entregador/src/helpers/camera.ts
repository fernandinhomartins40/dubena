import * as ImagePicker from "expo-image-picker"

/** Arquivo no formato que o FormData do RN espera (uri/name/type). */
export type ArquivoUpload = { uri: string; name: string; type: string }

const nomeDeUri = (uri: string, fallback: string): string => {
    const base = uri.split("/").pop() || fallback
    return base.includes(".") ? base : `${base}.jpg`
}

/** Abre a câmera para uma foto (comprovação/ocorrência). null se cancelado/negado. */
export async function tirarFoto(): Promise<ArquivoUpload | null> {
    const perm = await ImagePicker.requestCameraPermissionsAsync()
    if (!perm.granted) return null

    const r = await ImagePicker.launchCameraAsync({
        mediaTypes: ["images"],
        quality: 0.6,
    })
    if (r.canceled || !r.assets?.[0]) return null

    const a = r.assets[0]
    return {
        uri: a.uri,
        name: nomeDeUri(a.uri, "foto"),
        type: a.mimeType ?? "image/jpeg",
    }
}
