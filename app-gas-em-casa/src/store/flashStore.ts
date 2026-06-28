import { create } from "zustand"
import {
    AppConfig,
    CartLines,
    CatalogItem,
    CondicaoPagamento,
    Cotacao,
} from "@/types/types"
import { Order, OrderPix } from "@/types/order"

/**
 * flashStore (F3) — estado efêmero do fluxo de compra, AGORA sem regra de preço.
 *
 * Mudança central da F3: o carrinho guarda só `produto_id → quantidade`. Não há mais
 * calculateTotal/applyDiscount no cliente — total/desconto vêm da COTAÇÃO do servidor
 * (OrderService.Cotar). Catálogo e condições ficam aqui só para EXIBIÇÃO.
 */
interface NavParam {
    imageUrl: string
}

export interface FlashStore {
    catalog: CatalogItem[]
    condicoes: CondicaoPagamento[]
    condicao: CondicaoPagamento | null
    cart: CartLines
    cotacao: Cotacao | null
    cupom: string | null
    gasdopovo: boolean
    appConfig: AppConfig | null
    pendingOrder: Order | null
    pixOrder: OrderPix | null
    evaluateOrderId: number | null
    rebuyOrder: CartLines | null
    startupVideoShown: boolean
    pendingNavigation: NavParam | null

    setCatalog: (catalog: CatalogItem[]) => void
    setCondicoes: (condicoes: CondicaoPagamento[]) => void
    setCondicao: (condicao: CondicaoPagamento | null) => void
    addToCart: (produtoId: number) => void
    removeFromCart: (produtoId: number) => void
    setCotacao: (cotacao: Cotacao | null) => void
    setCupom: (cupom: string | null) => void
    setGasDoPovo: (value: boolean) => void
    setAppConfig: (config: AppConfig | null) => void
    qtyTotal: () => number
    cartItensPayload: () => { produto_id: number; quantidade: number }[]

    setPendingOrder: (order: Order | null) => void
    setEvaluateOrderId: (evaluateOrderId: number | null) => void
    setPixOrder: (pixOrder: OrderPix | null) => void
    setRebuyOrder: (lines: CartLines | null) => void
    setStartupVideoShown: (value: boolean) => void
    setPendingNavigation: (param: NavParam) => void
    clearPendingNavigation: () => void
    clearCart: () => void
    clearStore: () => void
}

const emptyCart: CartLines = {}

const useFlashStore = create<FlashStore>((set, get) => ({
    catalog: [],
    condicoes: [],
    condicao: null,
    cart: { ...emptyCart },
    cotacao: null,
    cupom: null,
    gasdopovo: false,
    appConfig: null,
    pendingOrder: null,
    pixOrder: null,
    evaluateOrderId: null,
    rebuyOrder: null,
    startupVideoShown: false,
    pendingNavigation: null,

    setCatalog: (catalog) => set({ catalog }),
    setCondicoes: (condicoes) => set({ condicoes }),
    setCondicao: (condicao) => set({ condicao }),

    addToCart: (produtoId) =>
        set((state) => {
            const cart = { ...state.cart }
            cart[produtoId] = (cart[produtoId] ?? 0) + 1
            return { cart }
        }),

    removeFromCart: (produtoId) =>
        set((state) => {
            const cart = { ...state.cart }
            const qty = cart[produtoId] ?? 0
            if (qty <= 1) delete cart[produtoId]
            else cart[produtoId] = qty - 1
            return { cart }
        }),

    setCotacao: (cotacao) => set({ cotacao }),
    setCupom: (cupom) => set({ cupom }),
    setGasDoPovo: (gasdopovo) => set({ gasdopovo }),
    setAppConfig: (appConfig) => set({ appConfig }),

    qtyTotal: () => Object.values(get().cart).reduce((sum, q) => sum + q, 0),
    cartItensPayload: () =>
        Object.entries(get().cart).map(([id, qtd]) => ({
            produto_id: Number(id),
            quantidade: qtd,
        })),

    setPendingOrder: (pendingOrder) => set({ pendingOrder }),
    setEvaluateOrderId: (evaluateOrderId) => set({ evaluateOrderId }),
    setPixOrder: (pixOrder) => set({ pixOrder }),
    setRebuyOrder: (rebuyOrder) => set({ rebuyOrder }),
    setStartupVideoShown: (startupVideoShown) => set({ startupVideoShown }),
    setPendingNavigation: (pendingNavigation) => set({ pendingNavigation }),
    clearPendingNavigation: () => set({ pendingNavigation: null }),

    clearCart: () => set({ cart: { ...emptyCart }, cotacao: null, cupom: null }),
    clearStore: () =>
        set({
            cart: { ...emptyCart },
            cotacao: null,
            cupom: null,
            condicao: null,
            gasdopovo: false,
            pendingOrder: null,
            pixOrder: null,
            evaluateOrderId: null,
            rebuyOrder: null,
        }),
}))

export default useFlashStore
