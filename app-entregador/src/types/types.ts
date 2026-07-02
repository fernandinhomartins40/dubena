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

// ── Roteirização (L5/L6) ──

export interface Parada {
    sequencia: number
    pedido_id: number
    cliente: string | null
    endereco: string
    lat: number | null
    lng: number | null
    distancia_trecho_km: number | null
    duracao_trecho_min: number | null
    eta_min: number | null
}

export interface Rota {
    paradas: Parada[]
    distancia_total_km: number
    duracao_total_min: number
    proximo: Parada | null
}

// ── Missões (L7/L8) ──

export type StatusVisita = "visitada" | "ausente" | "interessado" | "venda" | "frustrada"

export interface MissaoAtiva {
    id: number
    status: "atribuida" | "em_andamento" | "concluida" | "adiada" | "cancelada"
    iniciada_em: string | null
    missao: {
        id: number
        tipo: string
        titulo: string
        descricao: string | null
        meta_visitas: number | null
        exige_foto: boolean
    }
    metricas: {
        visitas_total: number
        vendas: number
        interessados: number
        distancia_km: number
        duracao_min: number | null
        pontos_trilha: number
    }
}

export interface ProximaCasa {
    cliente_id: number
    nome: string
    endereco: string
    lat: number
    lng: number
    distancia_m: number
}

export interface ProdutoVenda {
    id: number
    descricao: string
    preco: number
}
