import axios, { AxiosError, AxiosInstance, AxiosRequestConfig, Method } from "axios"

/**
 * Camada HTTP COMPARTILHADA dos apps (M-2/Q-1 da auditoria).
 *
 * Antes cada app (consumidor e entregador) tinha um `http.ts` quase idêntico —
 * bug corrigido num precisava ser copiado no outro. Aqui o cliente é uma FÁBRICA
 * store-agnóstica: recebe por injeção o `getToken` e o `onUnauthorized` (cada app
 * liga ao seu próprio store), então o mesmo código serve os dois. Mantém:
 *  - instância única com baseURL/timeout;
 *  - Bearer injetado nas requisições protegidas;
 *  - retry com backoff em GET/HEAD (rede/5xx);
 *  - 401 → onUnauthorized (o app faz logout/redireciona);
 *  - erro normalizado no contrato Laravel { message, errors };
 *  - multipart (foto/assinatura) via PrepareForm.
 */

export type HttpVerbs = "GET" | "POST" | "PATCH" | "PUT" | "DELETE"

export interface HttpError {
    status: number
    message: string
    errors: Record<string, string[]>
}

export interface HttpConfig {
    /** Base da API do ERP-NOVO (com barra final). */
    baseURL: string
    /** Token Bearer do usuário atual (ou null/undefined se não logado). */
    getToken: () => string | null | undefined
    /** Chamado quando a API responde 401 (sessão expirada). */
    onUnauthorized: () => void
    /** Timeout por requisição (ms). Default 20000. */
    timeoutMs?: number
    /** Máximo de re-tentativas em GET/HEAD. Default 2. */
    maxRetries?: number
    /** Backoff base entre tentativas (ms). Default 400. */
    retryBaseDelayMs?: number
}

const delay = (ms: number) => new Promise((r) => setTimeout(r, ms))

export function createHttp(cfg: HttpConfig) {
    const timeout = cfg.timeoutMs ?? 20000
    const maxRetries = cfg.maxRetries ?? 2
    const retryBase = cfg.retryBaseDelayMs ?? 400

    const client: AxiosInstance = axios.create({
        baseURL: cfg.baseURL,
        timeout,
        headers: { "Content-Type": "application/json", Accept: "application/json" },
    })

    // Injeta o Bearer nas requisições protegidas (__protected !== false).
    client.interceptors.request.use((config) => {
        const token = cfg.getToken()
        if (token && config.headers && (config as any).__protected !== false) {
            config.headers.Authorization = `Bearer ${token}`
        }
        return config
    })

    const normalizeError = (error: unknown): HttpError => {
        const err = error as AxiosError<any>
        const status = err.response?.status ?? 0
        const data = err.response?.data

        if (status === 401) cfg.onUnauthorized()

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
        if (attempt >= maxRetries) return false
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
                // O ERP-NOVO responde `{ data: ... }`; devolve o payload desembrulhado.
                const body = response.data as any
                return body && typeof body === "object" && "data" in body ? body.data : body
            } catch (error) {
                const axErr = error as AxiosError
                if (shouldRetry(axErr, config.method as Method, attempt)) {
                    attempt++
                    await delay(retryBase * 2 ** (attempt - 1))
                    continue
                }
                throw normalizeError(error)
            }
        }
    }

    /** Requisição JSON. `endpoint` é relativo à baseURL. `isProtected=false` pula o Bearer. */
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

    /** Requisição multipart/form-data (upload de foto/assinatura). */
    const PrepareForm = <T = any>(endpoint: string, form: FormData): Promise<T> => {
        const config: AxiosRequestConfig = {
            url: endpoint,
            method: "POST",
            data: form,
            headers: { "Content-Type": "multipart/form-data" },
        }
        return request<T>(config)
    }

    /** Requisição a um host EXTERNO arbitrário (ex.: Google Geocode), sem o Bearer. */
    const SendRequest = <T = any>(url: string, method: HttpVerbs = "GET", data: any = null): Promise<T> => {
        const config = {
            url,
            method,
            baseURL: "",
            __protected: false,
            ...(data != null ? { data } : {}),
        } as AxiosRequestConfig
        return request<T>(config)
    }

    return { PrepareRequest, PrepareForm, SendRequest, client }
}
