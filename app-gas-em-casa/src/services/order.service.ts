import Http from "@/helpers/http"
import { Order, OrderPix, RootOrderRequest } from "@/types/order"
import { CartProduct, Coupon, Root, VerifiedCoupon } from "@/types/types"

/**
 * OrderService (F2 → ERP-NOVO `app/v1`).
 *
 * Endpoints re-apontados para o ERP-NOVO. As ASSINATURAS foram preservadas para não
 * quebrar as telas nesta fase; o `cliente_id` agora é derivado do token no servidor
 * (deixa de ser confiável vindo do cliente — fim do IDOR).
 *
 * TODO(F3/F4/F6): os SHAPES de resposta do ERP-NOVO diferem do legado:
 *  - GetInitial → app/v1/init devolve { produtos, condicoes } (não `Root` do legado);
 *  - CreateOrder → app/v1/pedidos devolve { id, valor_venda, valor_desconto } (sem PIX inline);
 *  - cupom passa a ser GET app/v1/cupom?codigo= (validação server-side);
 *  - PIX por pedido (IsPixPaid) depende de novo endpoint do servidor (F4 — ainda inexistente).
 * As telas Home/OrderConfirm/Track/Pedidos e os tipos serão ajustados nessas fases.
 */

/** F3: pacote de abertura (produtos + condições de pagamento) da empresa do token. */
const GetInitial = (_store_id?: number, _client_id?: number): Promise<Root> => {
    return Http.PrepareRequest(`app/v1/init`, "GET")
}

/** F3: validação de cupom por código (server-side). `data.codigo_cupom` esperado. */
const VerifyCoupon = (data: any): Promise<VerifiedCoupon> => {
    const codigo = encodeURIComponent(data?.codigo_cupom ?? data?.codigo ?? "")
    return Http.PrepareRequest(`app/v1/cupom?codigo=${codigo}`, "GET")
}

/** F3: cupom disponível para o cliente (derivado do token). */
const GetCoupon = (_client_id?: number): Promise<Coupon> => {
    return Http.PrepareRequest(`app/v1/cupom`, "GET")
}

/** F4: cria pedido. Payload será ajustado na F3/F4 (sem preços do cliente). */
const CreateOrder = (data: any): Promise<RootOrderRequest | OrderPix> => {
    return Http.PrepareRequest("app/v1/pedidos", "POST", data)
}

/** F6: último pedido / acompanhamento — histórico do cliente do token. */
const GetLatestOrder = (_client_id?: number): Promise<Order> => {
    return Http.PrepareRequest(`app/v1/pedidos`, "GET")
}

/** F6: acompanhar pedido por id. (Sem id ainda nesta assinatura — ajustado na F6.) */
const Track = (_client_id?: number): Promise<Order> => {
    return Http.PrepareRequest(`app/v1/pedidos`, "GET")
}

/** F6: itens do pedido vêm embutidos no histórico/acompanhar do ERP-NOVO. */
const GetItems = (order_id: number | undefined | null): Promise<CartProduct[]> => {
    return Http.PrepareRequest(`app/v1/pedidos/${order_id}`, "GET")
}

/** F6: histórico do cliente do token. */
const GetHistory = (_client_id?: number): Promise<Order[]> => {
    return Http.PrepareRequest(`app/v1/pedidos`, "GET")
}

/** F6: avalia um pedido (nota 1–5 ou ignorado). `payload.pedido_id` esperado. */
const Evaluate = ({ payload }: any) => {
    const id = payload?.pedido_id ?? payload?.order_id
    return Http.PrepareRequest(`app/v1/pedidos/${id}/avaliar`, "POST", payload)
}

/** F4: status do PIX. DEPENDE de endpoint novo no servidor (ainda inexistente). */
const IsPixPaid = (order_id: any) => {
    // TODO(F4): trocar por GET app/v1/pedidos/{id}/pix/status quando criado no ERP-NOVO.
    return Http.PrepareRequest(`app/v1/pedidos/${order_id}`, "GET")
}

const OrderService = {
    GetInitial,
    VerifyCoupon,
    GetCoupon,
    CreateOrder,
    GetLatestOrder,
    Track,
    GetItems,
    GetHistory,
    Evaluate,
    IsPixPaid,
}

export default OrderService
