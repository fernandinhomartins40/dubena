import Http from "@/helpers/http"
import { Order, OrderPix, RootOrderRequest } from "@/types/order"
import { CartProduct, Coupon, Root, VerifiedCoupon } from "@/types/types"

const GetInitial = (store_id: number | undefined, client_id: number | undefined): Promise<Root> => {
    return Http.PrepareRequest(
        `v2/order/root?revenda_id=${store_id}&cliente_id=${client_id}`,
        "GET",
    )
}

const VerifyCoupon = (data: any): Promise<VerifiedCoupon> => {
    return Http.PrepareRequest("coupons/verify", "POST", data)
}

const GetCoupon = (client_id: number | undefined): Promise<Coupon> => {
    return Http.PrepareRequest(`coupons/get?cliente_id=${client_id}`, "GET")
}

const CreateOrder = (data: any): Promise<RootOrderRequest | OrderPix> => {
    return Http.PrepareRequest("order/create", "POST", data)
}

const GetLatestOrder = (client_id: number | undefined): Promise<Order> => {
    return Http.PrepareRequest(`order/track?cliente_id=${client_id}`, "GET")
}

const Track = (client_id: number | undefined): Promise<Order> => {
    return Http.PrepareRequest(`order/getLastestStatus?cliente_id=${client_id}`, "GET")
}

const GetItems = (order_id: number | undefined | null): Promise<CartProduct[]> => {
    return Http.PrepareRequest(`order/getItems?order_id=${order_id}`, "GET")
}

const GetHistory = (client_id: number | undefined): Promise<Order[]> => {
    return Http.PrepareRequest(`order/history?cliente_id=${client_id}`, "GET")
}

const Evaluate = ({ payload }: any) => {
    return Http.PrepareRequest("order/evaluate", "POST", payload)
}

const IsPixPaid = (order_id: any) => {
    return Http.PrepareRequest(`order/ispaid/${order_id}`, "GET")
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
