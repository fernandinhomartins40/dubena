import Http from "@/helpers/http"
import { Store, Video } from "@/types/types"

interface GasPovo {
    isAllowed: boolean
}

const GetOpenStore = (address_id: number | undefined): Promise<Store[]> => {
    return Http.PrepareRequest(`reseller/get?endereco_id=${address_id}`, "GET")
}

const GetStartupVideo = (): Promise<Video | null> => {
    return Http.PrepareRequest("video/get", "GET")
}

const GetIsGasPovoAllowed = (): Promise<GasPovo> => {
    return Http.PrepareRequest("reseller/isGpAllowed", "GET")
}

const StoreService = {
    GetOpenStore,
    GetStartupVideo,
    GetIsGasPovoAllowed,
}

export default StoreService
