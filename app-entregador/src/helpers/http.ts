import { APP } from "@/constants/app"
import useAppStore from "@/store/appStore"
import axios, { AxiosError, AxiosInstance, AxiosRequestConfig, Method } from "axios"

/**
 * Camada HTTP do App do Entregador (P7) — cliente ÚNICO do ERP-NOVO (`app/v1`),
 * idêntico em filosofia ao do app do cliente:
 *  - instância única com baseURL/timeout;
 *  - interceptor que injeta o Bearer do entregador (o tenant vem do token no servidor);
 *  - retry com backoff em GET/HEAD em erros de rede/5xx;
 *  - 401 → logout (o roteador leva para /login);
 *  - normalização do erro no padrão Laravel (`{ message, errors }`);
 *  - suporte a multipart (foto/assinatura da comprovação) via PrepareForm.
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

const client: AxiosInstance = axios.create({
    baseURL: APP.api_url,
    timeout: TIMEOUT_MS,
    headers: { "Content-Type": "application/json", Accept: "application/json" },
})

/** Injeta o Bearer do entregador autenticado em cada requisição protegida. */
client.interceptors.request.use((config) => {
    const token = useAppStore.getState().apiToken
    if (token && config.headers && (config as any).__protected !== false) {
        config.headers.Authorization = `Bearer ${token}`
    }
    return config
})

const handleUnauthorized = () => {
    const state = useAppStore.getState()
    if (state.apiToken) state.logout()
}

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

/** Requisição JSON. `endpoint` é relativo à baseURL (ex.: "app/v1/entregador/pedidos"). */
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
 * Requisição multipart/form-data (upload de foto/assinatura — comprovação P7).
 * Recebe um FormData já montado e deixa o axios/RN definir o boundary.
 */
const PrepareForm = <T = any>(endpoint: string, form: FormData): Promise<T> => {
    const config: AxiosRequestConfig = {
        url: endpoint,
        method: "POST",
        data: form,
        headers: { "Content-Type": "multipart/form-data" },
    }
    return request<T>(config)
}

const Http = { PrepareRequest, PrepareForm, client }

export default Http
