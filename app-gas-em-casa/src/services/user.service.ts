import Http from "@/helpers/http"
import { Address, Policy, User } from "@/types/types"

/**
 * UserService (F2 → ERP-NOVO).
 *
 * O modelo de identidade muda radicalmente:
 *  - ACABA o token-mestre via `getToken?app_key=` (qualquer app gerava token).
 *  - O login real por usuário é POST app/v1/login (F1), que devolve token Sanctum.
 *  - O `cliente_id` deixa de ser confiável vindo do cliente; o servidor o deriva do token.
 *
 * Nesta fase as assinaturas são preservadas para não quebrar telas; os métodos de
 * identidade do legado ficam marcados para a F1, e o registro de push já vai ao app/v1.
 */

interface Token {
    token_type: string
    access_token: string
    client_id: string
}

/** Payload de login (F1). O app envia o ID token do Firebase (telefone verificado). */
export interface AppLoginPayload {
    firebase_id_token: string
    device_id?: string
    push_token?: string
    plataforma?: "ios" | "android"
    app_versao?: string
}

/** Resposta de login do ERP-NOVO. */
export interface AppLoginResponse {
    token: string
    user: { id: number; name: string; empresa_id: number }
}

const naoImplementado = (nome: string): Promise<never> =>
    Promise.reject({
        status: 501,
        message: `"${nome}" será migrado na F1 (auth real) / F3 (cadastros) do ERP-NOVO.`,
        errors: {},
    })

/**
 * F1: login real do cliente. Envia o ID token do Firebase (já feito o SMS) e recebe
 * o token Sanctum do usuário. O ERP-NOVO valida o token via Firebase Admin.
 * TODO(F1): habilitar quando o endpoint aceitar `firebase_id_token` (hoje exige email/senha).
 */
const Login = (payload: AppLoginPayload): Promise<AppLoginResponse> => {
    return Http.PrepareRequest("app/v1/login", "POST", payload, false)
}

/** Termos/políticas — host externo (site institucional), sem Bearer do ERP. */
const GetPolicies = (): Promise<Policy[]> => {
    return Http.SendRequest("https://www.gasemcasa.com.br/termos.php?type=json", "GET")
}

/** @deprecated F1: substituído por Login(). Mantido só para compat de import. */
const GetToken = (): Promise<Token> => naoImplementado("getToken (token-mestre)")

/** @deprecated F1: identidade vem do token; não se busca cliente por nome+telefone. */
const GetClient = (_args: { fullName: string; phone: string }): Promise<User> =>
    naoImplementado("client/get por telefone")

/** F6/perfil: dados do usuário do token. TODO(F1): usar GET /me ou app/v1/perfil. */
const GetById = (_client_id?: number): Promise<User> => naoImplementado("client/getById")

/** F1: registra/atualiza o device para push no ERP-NOVO. */
const StoreFcmToken = ({ token }: { client_id: number; token: string }) => {
    return Http.PrepareRequest("app/v1/devices", "POST", {
        push_token: token,
        device_id: token.slice(0, 64),
    })
}

const GetAddress = (_client_id: number): Promise<Address> => naoImplementado("address/getStandard")

const GetAllAddress = (_client_id: number): Promise<Address[]> =>
    naoImplementado("address/getAll")

const Store = (_args: any): Promise<User> => naoImplementado("client/create")

const Update = (_args: any): Promise<User> => naoImplementado("client/update")

const Delete = (_args: any): Promise<any> => naoImplementado("client/delete")

const UserService = {
    Login,
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
