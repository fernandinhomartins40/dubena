import Http from "@/helpers/http"
import { Product } from "@/types/types"

/**
 * ProductService (F2 → ERP-NOVO).
 * Endpoint: GET app/v1/produtos (catálogo da empresa, derivada do token).
 *
 * Assinatura preservada (`store_id` ignorado) para não quebrar as telas nesta fase.
 * TODO(F3): o shape do ERP-NOVO é { id, descricao, preco, preco_gasdopovo } e NÃO
 * traz mais `revenda_id`/preços por forma de pagamento — a Home e o tipo `Product`
 * serão ajustados na F3 (preço/cotação 100% server-side). Aí o parâmetro sai.
 */
const GetAll = (_store_id?: number | undefined): Promise<Product[]> => {
    return Http.PrepareRequest(`app/v1/produtos`, "GET")
}

const ProductService = {
    GetAll,
}

export default ProductService
