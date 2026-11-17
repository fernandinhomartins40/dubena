import Http from "@/helpers/http"
import { PerfilCliente, Policy } from "@/types/types"

/**
 * UserService (F1/F3b → ERP-NOVO). Login/cadastro reais por usuário (Firebase →
 * Sanctum), perfil do cliente e device de push. Fim do token-mestre/app_key.
 */

export interface AppLoginPayload {
    firebase_id_token: string
    empresa_id: number
    device_id?: string
    push_token?: string
    plataforma?: "ios" | "android"
    app_versao?: string
}

export interface AppCadastroPayload extends AppLoginPayload {
    nome: string
    cpf?: string | null
    email?: string | null
    datanascimento?: string | null
}

export interface AppLoginResponse {
    token: string
    user: { id: number; name: string; empresa_id: number }
}

/** F1: login do cliente (Firebase ID token + empresa) → token Sanctum. */
const Login = (payload: AppLoginPayload): Promise<AppLoginResponse> =>
    Http.PrepareRequest("app/v1/cliente/login", "POST", payload, false)

/** F3b: cadastro do cliente (newuser) → cria cliente + token. */
const Cadastro = (payload: AppCadastroPayload): Promise<AppLoginResponse> =>
    Http.PrepareRequest("app/v1/cliente/cadastro", "POST", payload, false)

/** F3b: perfil do cliente do token. */
const GetPerfil = (): Promise<PerfilCliente> => Http.PrepareRequest("app/v1/perfil", "GET")

/** F3b: atualiza o perfil do cliente. */
const UpdatePerfil = (data: {
    nome?: string
    cpf?: string | null
    email?: string | null
    datanascimento?: string | null
}): Promise<{ id: number; nome: string }> => Http.PrepareRequest("app/v1/perfil", "PUT", data)

/** F3b: exclui a conta do cliente. */
const DeleteAccount = (): Promise<{ excluido: boolean }> =>
    Http.PrepareRequest("app/v1/perfil", "DELETE")

/**
 * F1: registra/atualiza o device de push (FCM).
 *
 * P2-B-08: antes só o login mandava o token; quem negou a permissão no login
 * ou teve o token rotacionado pelo FCM deixava de receber push até entrar de
 * novo. Agora o layout autenticado chama isto ao abrir e no `onTokenRefresh`.
 * O `device_id` segue o mesmo formato do login (`sms.tsx`), senão o mesmo
 * aparelho viraria duas linhas em `app_devices`.
 */
const StoreFcmToken = ({ token, plataforma }: { token: string; plataforma?: "ios" | "android" }) =>
    Http.PrepareRequest("app/v1/devices", "POST", {
        device_id: token.slice(0, 64),
        push_token: token,
        plataforma: plataforma ?? null,
    })

/** P2-B-17: "Sair" revoga o token no servidor — antes ele valia até vencer. */
const Logout = (): Promise<unknown> => Http.PrepareRequest("app/v1/logout", "POST")

/** Termos/políticas — host externo (site institucional). */
const GetPolicies = (): Promise<Policy[]> =>
    Http.SendRequest("https://www.gasemcasa.com.br/termos.php?type=json", "GET")

const UserService = {
    Login,
    Cadastro,
    GetPerfil,
    UpdatePerfil,
    DeleteAccount,
    StoreFcmToken,
    Logout,
    GetPolicies,
}

export default UserService
