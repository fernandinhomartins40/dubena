import Http from "@/helpers/http"
import { enviarFormularioOuEnfileirar, enviarOuEnfileirar } from "@/helpers/envioResiliente"
import { PedidoEntrega } from "@/types/types"

/**
 * EntregaService (P7) — ciclo da entrega pelo app do entregador, contra os
 * endpoints `app/v1/entregador/*` do ERP-NOVO. Toda regra (estados, estoque,
 * eventos de tempo real) vive no backend; aqui só montamos as requisições.
 *
 * Anti-IDOR: o servidor resolve o pedido por empresa + entregador do token; o app
 * só passa o id.
 *
 * F7 — as escritas JSON (aceitar/recusar/status) passam por `enviarOuEnfileirar`:
 * sem sinal, ficam na fila local e vão quando a rede voltar, com chave de
 * idempotência para não duplicarem. `Ocorrencia` e `Concluir` (multipart)
 * também, guardando o caminho dos anexos (P2-C-05). Fica FORA da fila só
 * `Posicao`, porque ping antigo não vale nada (poluiria o rastro).
 */

/** GET — pedidos atribuídos ao entregador autenticado. */
const Pedidos = (): Promise<PedidoEntrega[]> =>
    Http.PrepareRequest("app/v1/entregador/pedidos", "GET")

/** Aceite da corrida. */
const Aceitar = (pedidoId: number) =>
    enviarOuEnfileirar<{ id: number }>(`app/v1/entregador/pedidos/${pedidoId}/aceitar`, "POST")

/** Recusa — gera ocorrência e desvincula o entregador (volta para a fila). */
const Recusar = (pedidoId: number, motivo?: string) =>
    enviarOuEnfileirar<{ ocorrencia_id: number }>(
        `app/v1/entregador/pedidos/${pedidoId}/recusar`,
        "POST",
        { motivo: motivo ?? null },
    )

/** Muda a situação do pedido (com geoloc opcional). */
const AtualizarStatus = (
    pedidoId: number,
    pedidosituacaoId: number,
    coord?: { lat?: number; lng?: number },
) =>
    enviarOuEnfileirar<{ id: number; situacao_id: number }>(
        `app/v1/entregador/pedidos/${pedidoId}/status`,
        "POST",
        {
            pedidosituacao_id: pedidosituacaoId,
            lat: coord?.lat ?? null,
            lng: coord?.lng ?? null,
        },
    )

/** Ping de posição (P6) — publicado nos pedidos ATIVOS do entregador. */
const Posicao = (payload: {
    latitude: number
    longitude: number
    velocidade?: number
    direcao?: number
}): Promise<{ pedidos_notificados: number }> =>
    Http.PrepareRequest("app/v1/entregador/posicao", "POST", payload)

type ArquivoUpload = { uri: string; name: string; type: string }

/**
 * Registra uma ocorrência (com foto opcional) — multipart, pela fila (P2-C-05):
 * ocorrência acontece justamente onde não há sinal (endereço errado, zona rural).
 */
const Ocorrencia = (
    pedidoId: number,
    dados: {
        tipo: string
        descricao?: string
        latitude?: number | null
        longitude?: number | null
    },
    foto?: ArquivoUpload | null,
) =>
    enviarFormularioOuEnfileirar<{ data: { id: number; tipo: string } }>(
        `app/v1/entregador/pedidos/${pedidoId}/ocorrencia`,
        { tipo: dados.tipo, descricao: dados.descricao, latitude: dados.latitude, longitude: dados.longitude },
        { foto },
    )

/**
 * Conclui a entrega: comprovação (foto E/OU assinatura — uma é obrigatória) e
 * move o pedido para CONCLUÍDO no servidor. Multipart, com chave de
 * idempotência e pela fila offline (P2-C-05): sem sinal, fica guardada.
 */
const Concluir = (
    pedidoId: number,
    dados: {
        recebido_por?: string
        latitude?: number | null
        longitude?: number | null
    },
    arquivos: { foto?: ArquivoUpload | null; assinatura?: ArquivoUpload | null },
) =>
    enviarFormularioOuEnfileirar<{ data: { comprovacao_id: number; concluido: boolean } }>(
        `app/v1/entregador/pedidos/${pedidoId}/concluir`,
        { recebido_por: dados.recebido_por, latitude: dados.latitude, longitude: dados.longitude },
        { foto: arquivos.foto, assinatura: arquivos.assinatura },
    )

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
