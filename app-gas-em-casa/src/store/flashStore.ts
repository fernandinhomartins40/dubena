import { OnlinePaymentTypes } from "@/constants/app"
import { Order, OrderPix } from "@/types/order"
import {
    CardInfoPayload,
    Cart,
    CartProduct,
    CouponType,
    Payment,
    Product,
    Store,
    VerifiedCoupon,
} from "@/types/types"
import Toast from "react-native-toast-message"
import { create } from "zustand"

interface EmptyStore {
    shouldRefetch?: boolean
}

interface NavParam {
    imageUrl: string
}

export interface FlashStore {
    store?: Store | null
    payment?: Payment | null
    cart: Cart
    cardInfo: CardInfoPayload
    pendingOrder: Order | null
    pixOrder: OrderPix | null
    evaluateOrderId: number | null
    rebuyOrder: CartProduct[] | null
    startupVideoShown: boolean
    pendingNavigation: NavParam | null
    setStore: (store: Store | EmptyStore | null) => void
    addToCart: (product: Product) => void
    removeFromCart: (product: Product) => void
    setPayment: (payment: Payment) => void
    setCardInfo: (cardInfo: CardInfoPayload) => void
    applyCoupon: (coupon: VerifiedCoupon) => void
    setPendingOrder: (order: Order | null) => void
    setEvaluateOrderId: (evaluateOrderId: number | null) => void
    clearStore: () => void
    setPixOrder: (pixOrder: OrderPix | null) => void
    clearCart: () => void
    setRebuyOrder: (pros: CartProduct[] | null) => void
    setStartupVideoShown: (value: boolean) => void
    setPendingNavigation: (param: NavParam) => void
    clearPendingNavigation: () => void
}

const applyDiscount = (coupon: VerifiedCoupon, value: number) => {
    if (coupon.type === CouponType.Percentage) {
        let disc = (value * parseInt(coupon.value)) / 100
        value -= disc
    } else {
        value -= parseFloat(coupon.value)
    }

    return value
}

const calculateTotal = (cart: Cart, payment: Payment) => {
    if (cart.qtyTotal <= 0) {
        cart.total = 0

        return cart
    }

    let { products } = cart
    let keysToDelete = []
    let total = 0

    for (const key in products) {
        if (!Object.prototype.hasOwnProperty.call(products, key)) continue

        let prod = products[key]

        if (!prod.quantity) continue

        if (!(key in payment.productPrices)) {
            cart.qtyTotal -= prod.quantity

            keysToDelete.push(Number(key))

            continue
        }

        const price = Number(payment.productPrices[key])

        total += price * prod.quantity
        prod.total = price * prod.quantity
        prod.unitPrice = price
    }

    for (const key of keysToDelete) {
        Toast.show({
            text1: "Atenção!",
            text1Style: {
                fontSize: 15,
            },
            text2: "Produto indisponível para a Forma de Pagamento.",
            text2Style: {
                fontSize: 13,
            },
            type: "info",
        })
        delete products[key]
    }

    let disc = 0
    if (cart.coupon) {
        disc = applyDiscount(cart.coupon, total)
    }

    cart.total = total
    cart.discountTotal = disc > 0 ? disc : null

    return cart
}

const useFlashStore = create<FlashStore>((set) => ({
    store: null,
    payment: null,
    pendingOrder: null,
    pixOrder: null,
    evaluateOrderId: null,
    cart: {
        products: {},
        total: 0,
        qtyTotal: 0,
        discountTotal: null,
        coupon: null,
    },
    cardInfo: {
        brand: "mastercard",
        type: OnlinePaymentTypes.Credit,
        holder_name: "",
        expiration_month: "",
        expiration_year: "",
        card_number: "",
        card_cvv: "",
    },
    rebuyOrder: null,
    startupVideoShown: false,
    pendingNavigation: null,
    setStore: (store: any) => set(() => ({ store })),
    addToCart: (product: CartProduct) =>
        set((state) => {
            let { cart, payment } = state
            let { products } = cart
            let prod = products[product.id]

            if (prod && prod.quantity) {
                prod.quantity++

                products = {
                    ...products,
                    [product.id]: prod,
                }

                cart.qtyTotal++

                let newCart = { ...cart, products }

                if (payment) newCart = calculateTotal(newCart, payment)

                return { ...state, cart: newCart }
            }

            product.quantity = 1

            // products.set(product.id, product)
            products = {
                ...products,
                [product.id]: product,
            }

            cart.qtyTotal++

            let newCart = { ...cart, products }

            if (payment) newCart = calculateTotal(newCart, payment)

            return { ...state, cart: newCart }
        }),
    removeFromCart: (product: Product) =>
        set((state) => {
            let { cart, payment } = state
            let { products } = cart
            let prod = products[product.id]

            if (!prod || !prod.quantity) return state

            if (prod.quantity > 1) {
                prod.quantity--

                cart.qtyTotal--

                products = {
                    ...products,
                    [product.id]: prod,
                }

                let newCart = { ...cart, products }

                if (payment) newCart = calculateTotal(newCart, payment)

                return { ...state, cart: newCart }
            }

            cart.qtyTotal--

            delete products[product.id]

            let newCart = { ...cart, products }

            if (payment) newCart = calculateTotal(newCart, payment)

            return { ...state, cart: newCart }
        }),
    setPayment: (payment: Payment) =>
        set((state) => {
            let cart = calculateTotal(state.cart, payment)

            return { cart, payment }
        }),
    setCardInfo: (cardInfo: any) => set(() => ({ cardInfo })),
    applyCoupon: (coupon: VerifiedCoupon) =>
        set((state) => {
            let value = state.cart.total

            value = applyDiscount(coupon, value)

            return { cart: { ...state.cart, discountTotal: value, coupon: coupon } }
        }),
    setPendingOrder: (order: Order | null) => set({ pendingOrder: order }),
    setEvaluateOrderId: (evaluateOrderId: number | null) => set({ evaluateOrderId }),
    clearStore: () =>
        set(() => {
            return {
                store: null,
                payment: null,
                pendingOrder: null,
                pixOrder: null,
                evaluateOrderId: null,
                cart: {
                    products: {},
                    total: 0,
                    qtyTotal: 0,
                    discountTotal: null,
                    coupon: null,
                },
                cardInfo: {
                    brand: "mastercard",
                    type: OnlinePaymentTypes.Credit,
                    holder_name: "",
                    expiration_month: "",
                    expiration_year: "",
                    card_number: "",
                    card_cvv: "",
                },
            }
        }),
    setPixOrder: (pixOrder: OrderPix | null) => set({ pixOrder }),
    clearCart: () =>
        set({
            cart: {
                products: {},
                total: 0,
                qtyTotal: 0,
                discountTotal: null,
                coupon: null,
            },
        }),
    setRebuyOrder: (prods: CartProduct[] | null) =>
        set({
            rebuyOrder: prods,
        }),
    setStartupVideoShown: (value: boolean) => set({ startupVideoShown: value }),
    setPendingNavigation: (param: NavParam) => set({ pendingNavigation: param }),
    clearPendingNavigation: () => set({ pendingNavigation: null }),
}))

export default useFlashStore
