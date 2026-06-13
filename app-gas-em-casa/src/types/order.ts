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

export interface OrderPix {
    cliente_id: number
    condicaopagamento_id: number
    created_at: string
    datahoraprevisao: string
    desconto_cupons: number
    endereco_id: number
    erp_id: number
    id: number
    items: OrderProduct[]
    pedidosituacao_id: number
    pix: Pix
    updated_at: string
    user_id: number
}

export interface Pix {
    emv: string
    imagem_base64: string
    pix_link: string
    pixcopiaecola: string
}
