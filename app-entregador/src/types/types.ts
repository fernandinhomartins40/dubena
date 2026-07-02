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

// ── Jornada (L4) ──

export interface VeiculoOpcao {
    id: number
    placa: string
    descricao: string | null
    km_atual: number | null
}

export interface Jornada {
    id: number
    status: "ativa" | "encerrada"
    iniciada_em: string | null
    encerrada_em: string | null
    km_inicial: number | null
    km_final: number | null
    veiculo: { id: number; placa: string; descricao: string | null } | null
}

export interface Dashboard {
    em_servico: boolean
    jornada: Jornada | null
    pendentes: number
    concluidos_hoje: number
}
