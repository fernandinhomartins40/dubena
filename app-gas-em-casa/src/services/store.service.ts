import Http from "@/helpers/http"
import { Store, Video } from "@/types/types"

interface GasPovo {
    isAllowed: boolean
}

/**
 * StoreService (F2 → ERP-NOVO).
 *
 * O ERP-NOVO NÃO tem o conceito de "revenda/reseller" do legado: cada token já
 * pertence a uma empresa (tenant). Estes métodos não têm equivalente 1:1 ainda.
 *
 * TODO(F3): repensar a Home para o modelo "empresa do token":
 *  - GetOpenStore → deixa de existir; a empresa vem do login (token). Hoje aponta para
 *    app/v1/init só para não quebrar o fluxo; o shape será adaptado na F3.
 *  - GetStartupVideo / GetIsGasPovoAllowed → precisam de endpoints novos no servidor
 *    (config de app por empresa). Até lá, retornam vazio/false sem chamar o legado.
 */

/** TODO(F3): a "loja aberta" deixa de fazer sentido — empresa vem do token. */
const GetOpenStore = (_address_id?: number): Promise<Store[]> => {
    return Http.PrepareRequest(`app/v1/init`, "GET")
}

/** TODO(F3): endpoint de vídeo de abertura ainda não existe no ERP-NOVO. */
const GetStartupVideo = async (): Promise<Video | null> => {
    return null
}

/** TODO(F3): flag Gás do Povo migra para config de app por empresa no ERP-NOVO. */
const GetIsGasPovoAllowed = async (): Promise<GasPovo> => {
    return { isAllowed: false }
}

const StoreService = {
    GetOpenStore,
    GetStartupVideo,
    GetIsGasPovoAllowed,
}

export default StoreService
