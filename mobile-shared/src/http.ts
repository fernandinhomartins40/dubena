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
 *  - renovação do token antes de vencer e uma tentativa em 401 (`renovacao`, RC7);
 *  - 401 que a renovação não resolve → onUnauthorized (o app faz logout/redireciona);
 *  - erro normalizado no contrato Laravel { message, errors };
 *  - multipart (foto/assinatura) via PrepareForm.
 */

export type HttpVerbs = "GET" | "POST" | "PATCH" | "PUT" | "DELETE"

export interface HttpError {
    status: number
    message: string
    errors: Record<string, string[]>
}

/**
 * Renovação da sessão (PIE RC7 — P2-B-03 / P2-C-09).
 *
 * O token Sanctum dos apps vence em 24 h (`SANCTUM_EXPIRATION=1440`) e nada o
 * renovava: o entregador era deslogado no meio da rota (GPS parado, jornada
 * aberta no servidor) e o consumidor caía na verificação por SMS de novo. O
 * backend já tinha `POST app/v1/token/refresh` (emite um novo, revoga o atual);
 * faltava alguém chamar.
 *
 * **Por que aqui, no núcleo.** Toda escrita dos dois apps passa por este
 * `client` — inclusive a fila offline do entregador, que usa o axios cru. Pôr a
 * renovação nos interceptadores cobre todos os caminhos de uma vez; numa tela,
 * cobriria só aquela.
 */
export interface RenovacaoConfig {
    /** Rota de rotação do token, relativa à baseURL (ex.: "app/v1/token/refresh"). */
    endpoint: string
    /** Grava o token novo no store do app (que persiste e alimenta o `getToken`). */
    onToken: (token: string) => void
    /**
     * Idade a partir da qual o token é trocado antes de vencer. Default 6 h —
     * um quarto da validade de 24 h: o app aberto uma vez por turno renova
     * muito antes do fim, e o app parado não gera requisição nenhuma.
     */
    intervaloMs?: number
    /** Espera depois de uma renovação que falhou por rede, para não martelar sem sinal. */
    esperaAposFalhaMs?: number
    /** Relógio injetável (testes). */
    agora?: () => number
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
    /** Liga a renovação automática do token. Sem isto, o comportamento é o antigo. */
    renovacao?: RenovacaoConfig
}

/** Opções do upload multipart. */
export interface FormOpcoes {
    /**
     * `Idempotency-Key` estável entre tentativas (P2-C-05). Sem ela, o reenvio
     * de uma conclusão cujo 1º envio chegou ao servidor dava 422 e deixava
     * arquivo órfão no disco.
     */
    chave?: string
    /** Timeout próprio: foto de câmera em 3G passa fácil dos 20 s do JSON. */
    timeoutMs?: number
}

/** Timeout padrão de upload: foto de ~1 MB a 200 kbit/s leva ~40 s. */
export const UPLOAD_TIMEOUT_MS = 90_000

const delay = (ms: number) => new Promise((r) => setTimeout(r, ms))

/** Token que de fato viajou nesta requisição (o store pode já ter outro). */
const tokenDoCabecalho = (config: any): string | null => {
    const h = config?.headers
    const valor: unknown = typeof h?.get === "function" ? h.get("Authorization") : h?.Authorization
    return typeof valor === "string" && valor.startsWith("Bearer ") ? valor.slice(7) : null
}

export function createHttp(cfg: HttpConfig) {
    const timeout = cfg.timeoutMs ?? 20000
    const maxRetries = cfg.maxRetries ?? 2
    const retryBase = cfg.retryBaseDelayMs ?? 400
    const ren = cfg.renovacao
    const agora = ren?.agora ?? (() => Date.now())
    const intervalo = ren?.intervaloMs ?? 6 * 60 * 60 * 1000
    const esperaFalha = ren?.esperaAposFalhaMs ?? 60_000

    const client: AxiosInstance = axios.create({
        baseURL: cfg.baseURL,
        timeout,
        headers: { "Content-Type": "application/json", Accept: "application/json" },
    })

    // ── Estado da renovação (por instância: um por app) ──
    // `renovadoEm` nulo = não sabemos a idade do token (acabou de abrir o app
    // com um token do disco). Renovar já é o seguro: o token pode ter 23 h.
    let renovadoEm: number | null = null
    let naoTentarAntes = 0
    let emVoo: Promise<string | null> | null = null

    /**
     * Troca o token. UMA renovação por vez: dez requisições que chegam juntas
     * esperam a mesma promessa — duas renovações simultâneas revogariam o token
     * uma da outra e derrubariam a sessão.
     *
     * Devolve o token novo, ou null se não deu (sem token, sessão já vencida,
     * sem rede). Nunca lança: quem chamou segue com o que tem.
     */
    const renovar = (): Promise<string | null> => {
        if (!ren) return Promise.resolve(null)
        if (emVoo) return emVoo

        const antes = cfg.getToken()
        if (!antes) return Promise.resolve(null)

        emVoo = client
            .request({
                url: ren.endpoint,
                method: "POST",
                headers: { Authorization: `Bearer ${antes}` },
                // Marca a própria renovação: o 401 DELA não pode disparar outra
                // renovação (seria o laço infinito com token vencido).
                __renovacao: true,
            } as AxiosRequestConfig)
            .then((resp) => {
                const body = resp.data as any
                const novo: unknown = body?.token ?? body?.data?.token
                if (typeof novo !== "string" || novo === "") return null
                renovadoEm = agora()
                // Se o usuário saiu (ou outro entrou) enquanto a renovação
                // voava, o token novo não é mais dele: não ressuscita a sessão.
                if (cfg.getToken() === antes) ren.onToken(novo)
                return cfg.getToken() === novo ? novo : null
            })
            .catch((e: AxiosError) => {
                // 401 aqui = o token já venceu; não há o que renovar. Qualquer
                // outra falha (rede, 5xx) espera um pouco antes de tentar de novo.
                if (e?.response?.status !== 401) naoTentarAntes = agora() + esperaFalha
                return null
            })
            .finally(() => {
                emVoo = null
            })

        return emVoo
    }

    const precisaRenovar = (): boolean =>
        Boolean(ren) && agora() >= naoTentarAntes && (renovadoEm === null || agora() - renovadoEm >= intervalo)

    // Injeta o Bearer nas requisições protegidas (__protected !== false) e, antes
    // disso, renova o token se ele já passou da idade (renova ANTES de vencer).
    client.interceptors.request.use(async (config) => {
        const c = config as any
        if (c.__renovacao) return config
        if (c.__protected !== false && cfg.getToken() && precisaRenovar()) {
            await renovar()
        }
        const token = cfg.getToken()
        if (token && config.headers && c.__protected !== false) {
            config.headers.Authorization = `Bearer ${token}`
        }
        return config
    })

    // 401 numa requisição protegida: tenta UMA renovação e repete UMA vez.
    // Cobre o relógio do aparelho adiantado/atrasado e a corrida em que outra
    // requisição já trocou o token enquanto esta viajava com o antigo.
    client.interceptors.response.use(undefined, async (error: AxiosError) => {
        const config = error?.config as any
        if (
            !ren ||
            error?.response?.status !== 401 ||
            !config ||
            config.__protected === false ||
            config.__renovacao ||
            config.__repetida
        ) {
            throw error
        }

        const usado = tokenDoCabecalho(config)
        const atual = cfg.getToken()
        // Outro caminho já renovou: basta repetir com o token vigente.
        const novo = atual && usado && atual !== usado ? atual : await renovar()
        if (!novo) throw error

        config.__repetida = true
        if (config.headers) {
            if (typeof config.headers.set === "function") config.headers.set("Authorization", `Bearer ${novo}`)
            else config.headers.Authorization = `Bearer ${novo}`
        }
        return client.request(config)
    })

    /**
     * O app acabou de receber um token NOVO (login): não há por que renová-lo
     * na primeira requisição. Sem esta chamada, só custa uma renovação a mais.
     */
    const marcarTokenNovo = (): void => {
        renovadoEm = agora()
        naoTentarAntes = 0
    }

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
    const PrepareForm = <T = any>(endpoint: string, form: FormData, opcoes: FormOpcoes = {}): Promise<T> => {
        const config: AxiosRequestConfig = {
            url: endpoint,
            method: "POST",
            data: form,
            timeout: opcoes.timeoutMs ?? UPLOAD_TIMEOUT_MS,
            headers: {
                "Content-Type": "multipart/form-data",
                ...(opcoes.chave ? { "Idempotency-Key": opcoes.chave } : {}),
            },
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

    return { PrepareRequest, PrepareForm, SendRequest, client, renovarSessao: renovar, marcarTokenNovo }
}
