import Http from "@/helpers/http"
import { Dashboard, Jornada, Rota, VeiculoOpcao } from "@/types/types"

/**
 * JornadaService (L4) — turno do entregador contra `app/v1/entregador/*`. A jornada
 * é o vínculo entregador↔veículo e o "estar em serviço" (o backend só usa o GPS e
 * distribui para quem tem jornada ativa).
 */

/** Veículos ativos da empresa (seleção no início da jornada). */
const Veiculos = (): Promise<VeiculoOpcao[]> =>
    Http.PrepareRequest("app/v1/entregador/veiculos", "GET")

/** Jornada ativa (ou null). */
const Atual = (): Promise<Jornada | null> =>
    Http.PrepareRequest("app/v1/entregador/jornada", "GET")

/** Inicia a jornada com veículo + checklist. */
const Iniciar = (payload: {
    veiculo_id?: number | null
    km_inicial?: number | null
    checklist?: Record<string, string | boolean>
}): Promise<Jornada> =>
    Http.PrepareRequest("app/v1/entregador/jornada/iniciar", "POST", payload)

/** Encerra a jornada (km final opcional). */
const Encerrar = (kmFinal?: number | null): Promise<Jornada> =>
    Http.PrepareRequest("app/v1/entregador/jornada/encerrar", "POST", { km_final: kmFinal ?? null })

/** Resumo do dia. */
const Dashboard = (): Promise<Dashboard> =>
    Http.PrepareRequest("app/v1/entregador/dashboard", "GET")

/** Rota otimizada das entregas ativas (L5/L6). */
const Rota = (): Promise<Rota> =>
    Http.PrepareRequest("app/v1/entregador/rota", "GET")

const JornadaService = { Veiculos, Atual, Iniciar, Encerrar, Dashboard, Rota }

export default JornadaService
