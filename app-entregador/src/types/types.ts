/** Tipos do App do Entregador (P7) — espelham o contrato da API `app/v1`. */

export interface Entregador {
    id: number
    name: string
    empresa_id: number
}

/** Item da lista de pedidos do entregador (GET app/v1/entregador/pedidos). */
export interface PedidoEntrega {
    id: number
    valor_venda: number
    situacao: string | null
    cliente: string | null
    endereco: string
    lat: number | null
    lng: number | null
}

/** Tipos de ocorrência que o entregador pode registrar em campo. */
export type TipoOcorrencia = "ausente" | "endereco_errado" | "recusou" | "avaria" | "outro"

export interface Coordenada {
    latitude: number
    longitude: number
}

/** Payload de conclusão (comprovação) — foto e/ou assinatura é obrigatória. */
export interface ComprovacaoPayload {
    recebido_por?: string | null
    latitude?: number | null
    longitude?: number | null
}
