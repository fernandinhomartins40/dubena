import { OnlinePaymentTypes } from "@/constants/app"

export interface Policy {
    title: string
    description: string[]
    isHeader: boolean
}

export interface User {
    id: number
    nome: string
    primeironome: string
    telefone: string
    ativo: boolean
    conveniado: boolean
    datanascimento: string
    email: string
    acessadonovodispositivo: boolean
    appbuildnumber: string
    enderecopadrao_id: number
    created_at: string
    updated_at: string
    cpf?: string
    sexo?: string
    gasdopovo?: boolean
    telefoneantigo?: string
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
