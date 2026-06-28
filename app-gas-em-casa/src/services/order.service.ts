import Http from "@/helpers/http"
import { Cotacao, InitData } from "@/types/types"

/**
 * OrderService (F3 → ERP-NOVO `app/v1`).
 *
 * Catálogo + cotação (preço server-side) + ciclo do pedido + PIX. O cliente NÃO
 * calcula total/desconto: envia itens e recebe a cotação. Cupom validado por
 * GET app/v1/cupom. Pedido/histórico/acompanhar derivam o cliente do token (F1).
 */

const GetInit = (gasdopovo = false): Promise<InitData> =>
    Http.PrepareRequest(`app/v1/init?gasdopovo=${gasdopovo ? 1 : 0}`, "GET")

const Cotar = (payload: {
    itens: { produto_id: number; quantidade: number }[]
    condicao_id?: number | null
    codigo_cupom?: string | null
    gasdopovo?: boolean
}): Promise<Cotacao> => Http.PrepareRequest("app/v1/carrinho/cotacao", "POST", payload)

const VerifyCoupon = (
    codigo: string,
): Promise<{ codigo: string; descricao: string; desconto_percentual: number }> =>
    Http.PrepareRequest(`app/v1/cupom?codigo=${encodeURIComponent(codigo)}`, "GET")

const CreateOrder = (
    payload: any,
): Promise<{ id: number; valor_venda: number; valor_desconto: number }> =>
    Http.PrepareRequest("app/v1/pedidos", "POST", payload)

const GetHistory = (): Promise<any[]> => Http.PrepareRequest(`app/v1/pedidos`, "GET")

const Track = (orderId: number): Promise<any> =>
    Http.PrepareRequest(`app/v1/pedidos/${orderId}`, "GET")

const Evaluate = (
    orderId: number,
    payload: { rating?: number; mensagem?: string; ignorado?: boolean },
) => Http.PrepareRequest(`app/v1/pedidos/${orderId}/avaliar`, "POST", payload)

const Cancel = (orderId: number) =>
    Http.PrepareRequest(`app/v1/pedidos/${orderId}/cancelar`, "POST")

/** F4: gera a cobrança PIX do pedido (copia-e-cola + QR + expiração). */
const GerarPix = (
    orderId: number,
): Promise<{
    txid: string
    copia_e_cola: string
    qrcode: string | null
    valor: number
    expira_em: string | null
    situacao: string
}> => Http.PrepareRequest(`app/v1/pedidos/${orderId}/pix`, "POST")

/** F4: status da cobrança PIX do pedido (polling). */
const PixStatus = (
    orderId: number,
): Promise<{ situacao: string | null; pago: boolean; pago_em: string | null }> =>
    Http.PrepareRequest(`app/v1/pedidos/${orderId}/pix/status`, "GET")

const OrderService = {
    GetInit,
    Cotar,
    VerifyCoupon,
    CreateOrder,
    GetHistory,
    Track,
    Evaluate,
    Cancel,
    GerarPix,
    PixStatus,
}

export default OrderService
