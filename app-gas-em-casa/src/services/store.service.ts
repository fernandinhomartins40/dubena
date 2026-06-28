import Http from "@/helpers/http"
import { AppConfig } from "@/types/types"

/**
 * StoreService (F3 → ERP-NOVO). A "revenda" é a empresa do token; config e reseller
 * vêm do app/v1. Vídeo de abertura e Gás do Povo saem de GET app/v1/config.
 */

export interface ResellerInfo {
    id: number
    nome: string
    telefone: string | null
    whatsapp: string | null
    latitude: number | null
    longitude: number | null
    tempo_entrega_min: number | null
}

const GetConfig = (): Promise<AppConfig> => Http.PrepareRequest("app/v1/config", "GET")

const GetReseller = (): Promise<ResellerInfo> => Http.PrepareRequest("app/v1/reseller", "GET")

const GetFeriados = (): Promise<{ descricao: string; data: string; recorrente: boolean }[]> =>
    Http.PrepareRequest("app/v1/feriados", "GET")

const GetPoligonos = (): Promise<any[]> => Http.PrepareRequest("app/v1/poligonos", "GET")

const StoreService = {
    GetConfig,
    GetReseller,
    GetFeriados,
    GetPoligonos,
}

export default StoreService
