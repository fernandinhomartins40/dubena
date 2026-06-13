import useAppStore from "@/store/appStore"
import useFlashStore from "@/store/flashStore"
import storage from "@/store/storage"
import { GMapsAddress, GMapsResult } from "@/types/types"
import { AddressComponent, Geometry } from "react-native-google-places-autocomplete"
import * as FileSystem from "expo-file-system"

const VIDEOMETA_PATH = FileSystem.cacheDirectory + "startupvideo-meta.json"

type CardPatterns = {
    [key: string]: RegExp
}

type VideoMeta = {
    url: string
    localUri: string
    updated: number
}

const cardPatterns = {
    visa: /^4[0-9]{12}(?:[0-9]{3})?$/,
    mastercard: /^5[1-5][0-9]{14}$/,
    hipercard: /^(6062|3841|6370|6371|6372|6373|6374|6375)[0-9]{8,11}$/,
    elo: /^(4011|4312|4389|4514|4576|5041|5067|5090|6277|6362|6363|6364|6370|6371|6372|6373|6374|6375)[0-9]{8,11}$/,
    amex: /^3[47][0-9]{13}$/,
} as CardPatterns

export function removeAlphaNumericCharacter(str: string) {
    const arrPatterns = [
        "\ud83c[\udf00-\udfff]", // U+1F300 to U+1F3FF
        "\ud83d[\udc00-\ude4f]", // U+1F400 to U+1F64F
        "\ud83d[\ude80-\udeff]", // U+1F680 to U+1F6FF
    ]

    return str.replace(new RegExp(arrPatterns.join("|")), "")
}

export function capitalizeFirstLetter(str: string) {
    return str[0].toUpperCase() + str.toLowerCase().slice(1)
}

export function truncateText(str: string, maxSize: number, tail: string = "...") {
    if (str.length > maxSize) {
        str = str.substring(0, maxSize) + tail
    }

    return str
}

export function getBrandByCardNumber(card: string) {
    let results = ""

    for (const key in cardPatterns) {
        if (Object.prototype.hasOwnProperty.call(cardPatterns, key)) {
            const element: RegExp = cardPatterns[key]

            if (element.test(card)) {
                results = key
            }
        }
    }

    return results
}

export const createPlacesAutocompleteSessionToken = (a?: number): string => {
    return a
        ? (a ^ ((Math.random() * 16) >> (a / 4))).toString(16)
        : (1e7 + -1e3 + -4e3 + -8e3 + -1e11)
              .toString()
              .replace(/[018]/g, () => createPlacesAutocompleteSessionToken())
}

export const formatFromGMaps = (
    resultMaps: AddressComponent[] | undefined,
    geometry: Geometry | undefined,
): GMapsAddress | null => {
    let formatted = {
        countryCode: "",
        countryName: "",
        postalCode: "",
        uf: "",
        subAdministrativeArea: "",
        subLocality: "",
        subThoroughfare: "",
        thoroughfare: "",
    } as GMapsResult

    if (!resultMaps || !geometry) return null

    for (let i = 0; i < resultMaps.length; i++) {
        let ad = resultMaps[i]
        if (ad.types.indexOf("country") > -1) {
            formatted.countryCode = ad.short_name
            formatted.countryName = ad.long_name
        } else if (ad.types.indexOf("postal_code") > -1) {
            formatted.postalCode = ad.long_name
        } else if (ad.types.indexOf("administrative_area_level_1") > -1) {
            formatted.uf = ad.short_name
        } else if (ad.types.indexOf("administrative_area_level_2") > -1) {
            formatted.subAdministrativeArea = ad.long_name
        } else if (ad.types.indexOf("sublocality") > -1) {
            formatted.subLocality = ad.long_name
        } else if (ad.types.indexOf("route") > -1) {
            formatted.thoroughfare = ad.long_name
        } else if (ad.types.indexOf("street_number") > -1) {
            formatted.subThoroughfare = ad.long_name
        }
    }

    let lat = geometry.location.lat
    let lng = geometry.location.lng

    return formatAddress(formatted, formatted.uf, lat, lng)
}

export const formatAddress = (
    result: GMapsResult,
    uf: string,
    lat: number,
    lng: number,
): GMapsAddress => {
    return {
        uf: uf,
        siglaPais: result.countryCode,
        pais: result.countryName,
        cep: result.postalCode,
        cidade: result.subAdministrativeArea,
        bairro: result.subLocality,
        numero: result.subThoroughfare,
        rua: result.thoroughfare,
        latitude: lat,
        longitude: lng,
    }
}

export const validateBirthDate = (date: string): { isValid: boolean; message: string } => {
    if (date === "" || date === null) {
        return { isValid: true, message: "" }
    }

    let message = "padrão aceito é dd/MM/AAAA"
    let isValid = !!date && date.length === 10

    if (isValid) {
        try {
            let valYear = new Date().getFullYear() - 3
            let dateS = date.split("/")
            let day = parseInt(dateS[0])
            let month = parseInt(dateS[1])
            let year = parseInt(dateS[2])

            if (year > valYear || year < 1900) {
                message = "ano inválido"
                isValid = false
            } else if (month === 0 || month > 12) {
                message = "mês inválido"
                isValid = false
            } else {
                let maxDay = 30
                switch (month) {
                    case 1:
                    case 3:
                    case 5:
                    case 7:
                    case 8:
                    case 10:
                    case 12:
                        maxDay = 31
                        break
                    case 2:
                        maxDay = year % 4 === 0 ? 29 : 28
                }
                if (maxDay < day || day === 0 || isNaN(day)) {
                    message = "dia inválido"
                    isValid = false
                }
            }
        } catch (e) {
            console.warn(e)
            isValid = false
        }
    }

    return { isValid, message }
}

export const validateCpf = (cpf: string) => {
    let sum = 0
    let rest = 0
    let strCpf = cpf.replace(/\./g, "").replace(/\-/g, "")

    if (strCpf == "00000000000") return false

    for (let i = 1; i <= 9; i++) {
        sum += parseInt(strCpf.substring(i - 1, i)) * (11 - i)
    }

    rest = (sum * 10) % 11

    if (rest == 10 || rest == 11) rest = 0

    if (rest != parseInt(strCpf.substring(9, 10))) return false

    sum = 0

    for (let i = 1; i <= 10; i++) {
        sum += parseInt(strCpf.substring(i - 1, i)) * (12 - i)
    }

    rest = (sum * 10) % 11

    if (rest == 10 || rest == 11) rest = 0

    if (rest != parseInt(strCpf.substring(10, 11))) return false

    return true
}

export function delay(timeInMilliseconds: number) {
    return new Promise<null>((resolve) => {
        setTimeout(() => resolve(null), timeInMilliseconds)
    })
}

export async function loadVideoMeta(): Promise<VideoMeta | null> {
    const info = await FileSystem.getInfoAsync(VIDEOMETA_PATH)

    if (!info.exists) return null

    try {
        const content = await FileSystem.readAsStringAsync(VIDEOMETA_PATH)

        return JSON.parse(content)
    } catch (err) {
        return null
    }
}

export async function saveVideoMeta(meta: VideoMeta) {
    await FileSystem.writeAsStringAsync(VIDEOMETA_PATH, JSON.stringify(meta))
}

export async function clearVideoCache() {
    const meta = await loadVideoMeta()

    if (meta?.localUri) {
        const info = await FileSystem.getInfoAsync(meta.localUri)

        if (info.exists && !info.isDirectory) {
            await FileSystem.deleteAsync(meta.localUri, { idempotent: true })
        }
    }

    const metaInfo = await FileSystem.getInfoAsync(VIDEOMETA_PATH)

    if (metaInfo.exists && !metaInfo.isDirectory) {
        await FileSystem.deleteAsync(VIDEOMETA_PATH, { idempotent: true })
    }
}

export default function resetStorage() {
    storage.clearAll()
    useAppStore.getState().logout()
    useFlashStore.getState().clearStore()
}
