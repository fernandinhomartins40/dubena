import Http from "@/helpers/http"
import { Product } from "@/types/types"

const GetAll = (store_id: number | undefined): Promise<Product[]> => {
    return Http.PrepareRequest(`product/get?revenda_id=${store_id}`, "GET")
}

const ProductService = {
    GetAll,
}

export default ProductService
