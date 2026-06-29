import Http from "@/helpers/http"
import { Entregador } from "@/types/types"

/**
 * AuthService (P7) — login do entregador por e-mail/senha (colaborador).
 *
 * Usa o mesmo endpoint do app (POST app/v1/login), que tem hardening de segurança
 * (lockout + 2FA TOTP — P1). O tenant (empresa) é derivado do token no servidor;
 * o app nunca envia empresa_id no login do entregador.
 */

interface LoginResposta {
    token: string
    user: Entregador
}

const Login = (payload: {
    email: string
    password: string
    otp?: string
    device_id?: string
    push_token?: string
    plataforma?: string
    app_versao?: string
}): Promise<LoginResposta> => Http.PrepareRequest("app/v1/login", "POST", payload, false)

const Logout = (): Promise<{ message: string }> => Http.PrepareRequest("app/v1/logout", "POST")

/** Rotação do token (P1) — troca o token vigente sem refazer login. */
const Refresh = (): Promise<LoginResposta> => Http.PrepareRequest("app/v1/token/refresh", "POST")

/** Registra/atualiza o device para push (FCM). */
const RegisterDevice = (payload: {
    device_id: string
    push_token: string
    plataforma?: string
}): Promise<any> => Http.PrepareRequest("app/v1/devices", "POST", payload)

const AuthService = { Login, Logout, Refresh, RegisterDevice }

export default AuthService
