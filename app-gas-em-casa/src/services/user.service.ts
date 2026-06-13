import { APP } from "@/constants/app"
import Http from "@/helpers/http"
import { Address, Policy, User } from "@/types/types"

const GetPolicies = (): Promise<Policy[]> => {
    return Http.PrepareRequest(
        "termos.php?type=json",
        "GET",
        null,
        false,
        "https://www.gasemcasa.com.br/",
    )
}

interface Token {
    token_type: string
    access_token: string
    client_id: string
}

interface Error {
    msg: string
    status: string
}

const GetToken = (): Promise<Token> => {
    let key = APP.app_key
    return Http.PrepareRequest(`getToken?app_key=${key}`, "GET", null, false)
}

const GetClient = ({ fullName, phone }: { fullName: string; phone: string }): Promise<User> => {
    let tel = phone.replace(" ", "--")
    let firstName = fullName.split(" ")[0]

    return Http.PrepareRequest(`client/get?telefone=${tel}&firstName=${firstName}`, "GET")
}

const GetById = (client_id: number | undefined): Promise<User> => {
    return Http.PrepareRequest(`v2/client/getById?cliente_id=${client_id}`, "GET")
}

const StoreFcmToken = ({ client_id, token }: { client_id: number; token: string }) => {
    return Http.PrepareRequest("client/setPushToken", "POST", {
        pushregistration_id: token,
        cliente_id: client_id,
    })
}

const GetAddress = (client_id: number): Promise<Address> => {
    return Http.PrepareRequest(`address/getStandard?cliente_id=${client_id}`, "GET")
}

const GetAllAddress = (client_id: number): Promise<Address[]> => {
    return Http.PrepareRequest(`address/getAll?cliente_id=${client_id}`, "GET")
}

const Store = ({ data }: any): Promise<User> => {
    return Http.PrepareRequest("client/create", "POST", data)
}

const Update = ({ data }: any): Promise<User> => {
    return Http.PrepareRequest("client/update", "PUT", data)
}

const Delete = ({ client_id }: any): Promise<any> => {
    return Http.PrepareRequest(`client/delete?id=${client_id}`, "DELETE")
}

const UserService = {
    GetPolicies,
    GetToken,
    GetClient,
    GetById,
    StoreFcmToken,
    GetAddress,
    GetAllAddress,
    Store,
    Update,
    Delete,
}

export default UserService
