import Http from "@/helpers/http"
import { PedidoEntrega } from "@/types/types"

/**
 * EntregaService (P7) — ciclo da entrega pelo app do entregador, contra os
 * endpoints `app/v1/entregador/*` do ERP-NOVO. Toda regra (estados, estoque,
 * eventos de tempo real) vive no backend; aqui só montamos as requisições.
 *
 * Anti-IDOR: o servidor resolve o pedido por empresa + entregador do token; o app
 * só passa o id.
 */

/** GET — pedidos atribuídos ao entregador autenticado. */
const Pedidos = (): Promise<PedidoEntrega[]> =>
    Http.PrepareRequest("app/v1/entregador/pedidos", "GET")

/** Aceite da corrida. */
const Aceitar = (pedidoId: number): Promise<{ id: number }> =>
    Http.PrepareRequest(`app/v1/entregador/pedidos/${pedidoId}/aceitar`, "POST")

/** Recusa — gera ocorrência e desvincula o entregador (volta para a fila). */
const Recusar = (pedidoId: number, motivo?: string): Promise<{ ocorrencia_id: number }> =>
    Http.PrepareRequest(`app/v1/entregador/pedidos/${pedidoId}/recusar`, "POST", {
        motivo: motivo ?? null,
    })

/** Muda a situação do pedido (com geoloc opcional). */
const AtualizarStatus = (
    pedidoId: number,
    pedidosituacaoId: number,
    coord?: { lat?: number; lng?: number },
): Promise<{ id: number; situacao_id: number }> =>
    Http.PrepareRequest(`app/v1/entregador/pedidos/${pedidoId}/status`, "POST", {
        pedidosituacao_id: pedidosituacaoId,
        lat: coord?.lat ?? null,
        lng: coord?.lng ?? null,
    })

/** Ping de posição (P6) — publicado nos pedidos ATIVOS do entregador. */
const Posicao = (payload: {
    latitude: number
    longitude: number
    velocidade?: number
    direcao?: number
}): Promise<{ pedidos_notificados: number }> =>
    Http.PrepareRequest("app/v1/entregador/posicao", "POST", payload)

type ArquivoUpload = { uri: string; name: string; type: string }

/** Registra uma ocorrência (com foto opcional) — multipart. */
const Ocorrencia = (
    pedidoId: number,
    dados: {
        tipo: string
        descricao?: string
        latitude?: number | null
        longitude?: number | null
    },
    foto?: ArquivoUpload | null,
): Promise<{ id: number; tipo: string }> => {
    const form = new FormData()
    form.append("tipo", dados.tipo)
    if (dados.descricao) form.append("descricao", dados.descricao)
    if (dados.latitude != null) form.append("latitude", String(dados.latitude))
    if (dados.longitude != null) form.append("longitude", String(dados.longitude))
    if (foto) form.append("foto", foto as any)
    return Http.PrepareForm(`app/v1/entregador/pedidos/${pedidoId}/ocorrencia`, form)
}

/**
 * Conclui a entrega: comprovação (foto E/OU assinatura — uma é obrigatória) e
 * move o pedido para CONCLUÍDO no servidor. Multipart.
 */
const Concluir = (
    pedidoId: number,
    dados: {
        recebido_por?: string
        latitude?: number | null
        longitude?: number | null
    },
    arquivos: { foto?: ArquivoUpload | null; assinatura?: ArquivoUpload | null },
): Promise<{ comprovacao_id: number; concluido: boolean }> => {
    const form = new FormData()
    if (dados.recebido_por) form.append("recebido_por", dados.recebido_por)
    if (dados.latitude != null) form.append("latitude", String(dados.latitude))
    if (dados.longitude != null) form.append("longitude", String(dados.longitude))
    if (arquivos.foto) form.append("foto", arquivos.foto as any)
    if (arquivos.assinatura) form.append("assinatura", arquivos.assinatura as any)
    return Http.PrepareForm(`app/v1/entregador/pedidos/${pedidoId}/concluir`, form)
}

const EntregaService = {
    Pedidos,
    Aceitar,
    Recusar,
    AtualizarStatus,
    Posicao,
    Ocorrencia,
    Concluir,
}

export default EntregaService
