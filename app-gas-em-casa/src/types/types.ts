import { OnlinePaymentTypes } from "@/constants/app"

export interface Policy {
    title: string
    description: string[]
    isHeader: boolean
}

/**
 * Usuário autenticado (F1). Após o login real, guardamos a identidade do token
 * (`id`, `name`, `empresa_id`). Os demais campos (perfil do cliente) são OPCIONAIS e
 * vêm de GET app/v1/perfil quando necessários — não são mais parte do token.
 */
export interface User {
    id: number
    name?: string
    empresa_id?: number
    // Campos de perfil (opcionais; preenchidos via PerfilCliente quando exibidos).
    nome?: string
    primeironome?: string
    telefone?: string
    ativo?: boolean
    conveniado?: boolean
    datanascimento?: string
    email?: string
    enderecopadrao_id?: number
    cpf?: string
    sexo?: string
    gasdopovo?: boolean
    user_id?: number
}

export interface UserFormSchema {
    id?: number
    nome: string
    telefone: string
    datanascimento: string
    sexo: string
    cpf: string
    conveniado: boolean
    gasdopovo?: boolean
    pushregistration_id?: string
    internal_build_number: number
}

export interface ApiResponse<T> {
    data: T
    msg: string
    status: string
}

export interface Address {
    id?: number
    created_at?: string
    updated_at?: string
    numero: number
    complemento: string
    rua: string
    cep: string
    titulo: string
    latitude: number
    longitude: number
    cliente_id: number
    bairro: string
    uf: string
    cidade: string
    pontoreferencia: string
    ativo: number
}

export interface Store {
    revenda_id: number
    revenda_nome: string
    horariofuncionamento: string
    horariodom: string
    enderecocompleto: string
    delivery_time: string
    delivery_res: string
    avaliacao: number
    totalavaliacoes: number
    telefone: string
    permiteagendamento: number
    base64Img: string
    whatsapp: string
    abertura: string
    fechamento: string
    shouldRefetch?: boolean
    latitude?: number
    longitude?: number
    valorfretegp?: number
    gaspovoativado?: number
}

export interface Video {
    url: string
    titulo: string
    updated: number
}

export interface Product {
    descricao: string
    id: number
    avaliable: number
    base64Img: string
}

export interface CartProduct extends Product {
    quantity?: number
    total?: number | string
    unitPrice?: number | string
}

export interface CartProducts {
    [id: number]: CartProduct
}

export interface Cart {
    products: CartProducts
    total: number
    qtyTotal: number
    discountTotal?: number | null
    coupon?: VerifiedCoupon | null
}

export interface ProductPrices {
    [id: string]: string
}

export interface Payment {
    id: number
    descricao: string
    tipo: PaymentTypes
    productPrices: ProductPrices
}

export interface Root {
    product: Product[]
    payment: Payment[]
}

export type CardBrands = "default" | "mastercard" | "visa" | "hipercard" | "elo" | "amex"
export interface OnlinePayment {
    brand: CardBrands
    holder_name: string
    card_number: string
    card_cvv: string
    expiration_month: string
    expiration_year: string
}

export interface CardInfoPayload {
    type: OnlinePaymentTypes
    card_number: string
    brand: CardBrands
    holder_name: string
    expiration_month: string
    expiration_year: string
    card_cvv: string
}

export enum AddressType {
    Home = "Residência",
    Workplace = "Trabalho",
    Default = "Outro",
}

export enum PaymentTypes {
    Money = 0,
    DebitDelivery = 1,
    CreditDelivery = 2,
    CardStamp = 3, // Vale gás
    ByCompany = 4,
    Online = 6,
    PIX = 7,
}

export interface GMapsResult {
    countryCode: string
    countryName: string
    postalCode: string
    uf: string
    subAdministrativeArea: string
    subLocality: string
    subThoroughfare: string
    thoroughfare: string
    latitude: number
    longitude: number
}

export interface GMapsAddress {
    uf: string
    siglaPais: string
    pais: string
    cep: string
    cidade: string
    bairro: string
    numero: string
    rua: string
    latitude: number
    longitude: number
}

export enum CouponType {
    Value = 0,
    Percentage = 1,
}
export interface Coupon {
    codigo: string
    tipo: CouponType
    valor: string
    quantidadeuso: number
}

export interface VerifiedCoupon {
    code: string
    type: CouponType
    value: string
}

export interface OnlinePaymentTries {
    tries: number
    date: number
}
export interface TimelineStep {
    title: string
    description: string
    completed: boolean
    isCurrent: boolean
}

/* ─────────────────────────────────────────────────────────────────────────────
 * F3 — Tipos alinhados ao ERP-NOVO (app/v1). O preço NÃO mora mais no cliente:
 * o catálogo traz preços apenas para EXIBIÇÃO; o total vem sempre da cotação.
 * ──────────────────────────────────────────────────────────────────────────── */

/** Item do catálogo (GET app/v1/produtos / init.produtos). */
export interface CatalogItem {
    id: number
    descricao: string
    preco: number
    preco_gasdopovo: number | null
}

/** Condição de pagamento (init.condicoes). */
export interface CondicaoPagamento {
    id: number
    descricao: string
    num_parcelas: number
    a_vista: boolean
}

/** Pacote de abertura (GET app/v1/init). */
export interface InitData {
    produtos: CatalogItem[]
    condicoes: CondicaoPagamento[]
}

/** Carrinho local: só id → quantidade. Total/desconto vêm do servidor. */
export interface CartLines {
    [produtoId: number]: number
}

/** Item retornado pela cotação (preço resolvido no servidor). */
export interface CotacaoItem {
    produto_id: number
    descricao: string
    quantidade: number
    preco_unitario: number
    total: number
}

/** Cupom aplicado, conforme a cotação. */
export interface CotacaoCupom {
    codigo: string
    descricao: string
    desconto_percentual: number
}

/** Resposta de POST app/v1/carrinho/cotacao — a AUTORIDADE de preço. */
export interface Cotacao {
    itens: CotacaoItem[]
    subtotal: number
    desconto: number
    total: number
    cupom: CotacaoCupom | null
    indisponiveis: number[]
}

/** Config do app por empresa (GET app/v1/config). */
export interface AppConfig {
    gaspovo_ativo: boolean
    frete_gaspovo: number | null
    video: { url: string; titulo?: string } | null
    tempo_entrega_min: number | null
}

/** Endereço (inline) do cliente (GET/PUT app/v1/perfil/endereco). */
export interface ClienteEndereco {
    endereco: string | null
    numero: string | null
    complemento: string | null
    ponto_referencia: string | null
    cep: string | null
    uf: string | null
    latitude: number | null
    longitude: number | null
}

/** Endereço de entrega (múltiplos) — GET/POST/PUT app/v1/enderecos. */
export interface ClienteEnderecoApi {
    id: number
    titulo: string | null
    endereco: string
    numero: string | null
    complemento: string | null
    ponto_referencia: string | null
    bairro: string | null
    cidade: string | null
    cep: string | null
    uf: string | null
    latitude: number | null
    longitude: number | null
    favorito: boolean
}

/** Item do histórico de pedidos (GET app/v1/pedidos). */
export interface HistoryItem {
    id: number
    datahora: string | null
    situacao: string | null
    efeito: "PENDENTE" | "CONCLUIDO" | "CANCELADO" | string | null
    valor_venda: number
    itens: CotacaoItem[]
}

/** Perfil do cliente (GET app/v1/perfil). */
export interface PerfilCliente {
    id: number
    nome: string
    cpf: string | null
    email: string | null
    datanascimento: string | null
    gasdopovo: boolean
    telefones: string[]
}
