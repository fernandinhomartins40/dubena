import { APP } from "@/constants/app"
import useAppStore from "@/store/appStore"
import axios, { AxiosError, AxiosInstance, AxiosRequestConfig, Method } from "axios"

/**
 * Camada HTTP (F2) — cliente ÚNICO do ERP-NOVO (`app/v1`).
 *
 * Substitui o axios cru do legado. Agora há:
 *  - instância única com baseURL/timeout;
 *  - interceptor que injeta o Bearer do usuário (o tenant é derivado do token no servidor);
 *  - retry com backoff em métodos idempotentes (GET/HEAD) em erros de rede/5xx;
 *  - tratamento de 401 (sessão expirada → logout, o app redireciona para login);
 *  - normalização do erro no padrão Laravel (`{ message, errors }`).
 *
 * Não há mais token-mestre nem `app_key`: a identidade vem do login real por usuário (F1).
 */

export type HttpVerbs = "GET" | "POST" | "PATCH" | "PUT" | "DELETE"

export interface HttpError {
    status: number
    message: string
    errors: Record<string, string[]>
}

const TIMEOUT_MS = 20000
const MAX_RETRIES = 2
const RETRY_BASE_DELAY_MS = 400

const delay = (ms: number) => new Promise((r) => setTimeout(r, ms))

/** Instância base apontando para o ERP-NOVO. */
const client: AxiosInstance = axios.create({
    baseURL: APP.api_url,
    timeout: TIMEOUT_MS,
    headers: { "Content-Type": "application/json", Accept: "application/json" },
})

/** Injeta o Bearer do usuário autenticado em cada requisição protegida. */
client.interceptors.request.use((config) => {
    const token = useAppStore.getState().apiToken
    if (token && config.headers && (config as any).__protected !== false) {
        config.headers.Authorization = `Bearer ${token}`
    }
    return config
})

/** Sessão expirada/inválida → derruba o login local; o roteador leva para /login. */
const handleUnauthorized = () => {
    const state = useAppStore.getState()
    if (state.apiToken) state.logout()
}

/** Converte qualquer erro do axios no contrato Laravel `{ message, errors }`. */
const normalizeError = (error: unknown): HttpError => {
    const err = error as AxiosError<any>
    const status = err.response?.status ?? 0
    const data = err.response?.data

    if (status === 401) handleUnauthorized()

    return {
        status,
        message:
            data?.message ||
            (status === 0 ? "Sem conexão. Verifique sua internet." : err.message) ||
            "Ocorreu um erro inesperado.",
        errors: data?.errors ?? {},
    }
}

/** Decide se um erro é "retryable" (rede sem resposta ou 5xx) e o método é idempotente. */
const shouldRetry = (error: AxiosError, method: Method | undefined, attempt: number): boolean => {
    if (attempt >= MAX_RETRIES) return false
    const idempotent = !method || ["get", "head"].includes(String(method).toLowerCase())
    if (!idempotent) return false
    const status = error.response?.status
    return status === undefined || status >= 500
}

const request = async <T = any>(config: AxiosRequestConfig): Promise<T> => {
    let attempt = 0
    // eslint-disable-next-line no-constant-condition
    while (true) {
        try {
            const response = await client.request<T>(config)
            // O ERP-NOVO responde `{ data: ... }`; devolvemos o payload já desembrulhado.
            const body = response.data as any
            return body && typeof body === "object" && "data" in body ? body.data : body
        } catch (error) {
            const axErr = error as AxiosError
            if (shouldRetry(axErr, config.method as Method, attempt)) {
                attempt++
                await delay(RETRY_BASE_DELAY_MS * 2 ** (attempt - 1))
                continue
            }
            throw normalizeError(error)
        }
    }
}

/**
 * Requisição contra o ERP-NOVO. `endpoint` é relativo à baseURL (ex.: "app/v1/produtos").
 * `isProtected=false` pula a injeção do Bearer (rotas públicas como login).
 */
const PrepareRequest = <T = any>(
    endpoint: string,
    method: HttpVerbs = "GET",
    data: any = null,
    isProtected: boolean = true,
): Promise<T> => {
    const config: AxiosRequestConfig = {
        url: endpoint,
        method,
        ...(data != null ? { data } : {}),
        ...(isProtected ? {} : ({ __protected: false } as any)),
    }
    return request<T>(config)
}

/**
 * Requisição a um host EXTERNO arbitrário (ex.: Google Geocode), sem o Bearer do ERP
 * e fora da baseURL. Mantida para integrações de terceiros.
 */
const SendRequest = <T = any>(
    url: string,
    method: HttpVerbs = "GET",
    data: any = null,
): Promise<T> => {
    const config: AxiosRequestConfig = {
        url,
        method,
        baseURL: "",
        __protected: false,
        ...(data != null ? { data } : {}),
    } as any
    return request<T>(config)
}

const Http = { PrepareRequest, SendRequest, client }

export default Http
