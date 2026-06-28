export interface RootOrderRequest {
    headers: Headers
    original: Original
    exception: any
}

export interface Headers {}

export interface Original {
    data: Order
    msg: string
    status: string
}

export interface Order {
    id: number
    data?: string | undefined
    created_at: string
    updated_at: string
    observacoes: any
    datahoraenvioentregador: any
    datahoraentrega: any
    datahoracancelamento: any
    condicaopagamento_id: number
    pedidosituacao_id: number
    cliente_id: number
    endereco_id: number
    erp_id: number
    user_id: number
    datahoraprevisao: string
    nao_avaliado: number
    placa: any
    cupom_id: number
    desconto_cupons: string
    pago: number
    ignorado?: number | null
    latitude: number
    longitude: number
    status: string
    tipo_pag: number
    cancelado: number
    entregue: number
    pendente: number
    ementrega: number
    avaliado: number
    track?: TrackInfo | undefined
    reseller_position?: ResellerPosition | undefined
    reseller_name: string
    delivery_time: string
    reseller_phone: string
    whatsapp: string
    total?: string | undefined
    total_price?: string | undefined
    items: string
    produtos?: OrderProduct[] | undefined
    rating?: number | null
    valorfrete?: string | undefined
    gasdopovo?: number
}

export interface TrackInfo {
    motorista: string
    placa: string
    location: Location
}

export interface Location {
    latitude: number
    longitude: number
}

export interface ResellerPosition {
    latitude: number
    longitude: number
}

export interface OrderProduct {
    codigo_pedido?: number
    precovendatotal: string
    precovendaunitario: string
    quantidade: number
    produto_id: number
    descricao?: string
    id?: number
    codigogb?: string
    created_at?: string
    pedido_id?: number
    updated_at?: string
}

/** Cobrança PIX do pedido (F4 — ERP-NOVO app/v1). */
export interface Pix {
    txid: string
    copia_e_cola: string
    qrcode: string | null
    valor: number
    expira_em: string | null
    situacao: string
}

export interface OrderPix {
    id: number // id do pedido
    pix: Pix
}
