import { beforeEach, describe, expect, it, vi } from "vitest"
import { AxiosError, type AxiosAdapter, type InternalAxiosRequestConfig } from "axios"
import { createHttp, UPLOAD_TIMEOUT_MS } from "@shared/http"

/**
 * Renovação da sessão no núcleo compartilhado (PIE RC7 — P2-B-03 / P2-C-09).
 *
 * Usa o `createHttp` REAL dos dois apps; só o transporte do axios é trocado por
 * um servidor falso que conhece os tokens válidos, como o Sanctum: o refresh
 * emite um novo e revoga o antigo; token revogado ou vencido → 401.
 */

type Visto = { url: string; token: string | null; chave?: string; timeout?: number }

let validos: Set<string>
let vistos: Visto[]
let seq: number
let refreshFalhaDeRede: boolean

const tokenDe = (cfg: InternalAxiosRequestConfig): string | null => {
    const h: any = cfg.headers
    const v = typeof h?.get === "function" ? h.get("Authorization") : h?.Authorization
    return typeof v === "string" ? v.replace("Bearer ", "") : null
}

const servidor: AxiosAdapter = async (cfg) => {
    const token = tokenDe(cfg)
    const url = String(cfg.url)
    vistos.push({ url, token, chave: (cfg.headers as any)?.["Idempotency-Key"], timeout: cfg.timeout })
    const responder = (status: number, data: unknown) => {
        const r = { data, status, statusText: "", headers: {}, config: cfg }
        if (status < 300) return r
        throw new AxiosError(`Request failed with status code ${status}`, "ERR_BAD_REQUEST", cfg, null, r as any)
    }
    if (url.endsWith("token/refresh") && refreshFalhaDeRede) {
        throw new AxiosError("Network Error", "ERR_NETWORK", cfg)
    }
    if (!token || !validos.has(token)) return responder(401, { message: "Unauthenticated." })
    if (url.endsWith("token/refresh")) {
        const novo = `t${++seq}`
        validos.add(novo)
        validos.delete(token) // Sanctum: revoga o atual
        return responder(200, { token: novo, user: { id: 1 } })
    }
    return responder(200, { data: { ok: true, url } })
}

let relogio: number
const store = { token: "t0" as string, deslogou: 0 }

const novoHttp = () => {
    const http = createHttp({
        baseURL: "https://erp.teste/api/",
        getToken: () => store.token,
        onUnauthorized: () => {
            store.deslogou++
            store.token = ""
        },
        renovacao: {
            endpoint: "app/v1/token/refresh",
            onToken: (t) => {
                store.token = t
            },
            intervaloMs: 6 * 3600_000,
            esperaAposFalhaMs: 60_000,
            agora: () => relogio,
        },
    })
    http.client.defaults.adapter = servidor
    return http
}

const refreshes = () => vistos.filter((v) => v.url.endsWith("token/refresh")).length

beforeEach(() => {
    validos = new Set(["t0"])
    vistos = []
    seq = 0
    refreshFalhaDeRede = false
    relogio = 1_000_000
    store.token = "t0"
    store.deslogou = 0
})

describe("renovação antes de vencer", () => {
    it("token do disco (idade desconhecida) é trocado na 1ª requisição, e a requisição já vai com o novo", async () => {
        const http = novoHttp()
        await http.PrepareRequest("app/v1/entregador/pedidos")
        expect(refreshes()).toBe(1)
        expect(store.token).toBe("t1")
        expect(vistos.at(-1)).toMatchObject({ url: "app/v1/entregador/pedidos", token: "t1" })
    })

    it("logo após o login não renova; renova quando passa da idade", async () => {
        const http = novoHttp()
        http.marcarTokenNovo()
        await http.PrepareRequest("a")
        relogio += 5 * 3600_000
        await http.PrepareRequest("b")
        expect(refreshes()).toBe(0)

        relogio += 1 * 3600_000 + 1
        await http.PrepareRequest("c")
        expect(refreshes()).toBe(1)
        expect(vistos.at(-1)?.token).toBe("t1")
    })

    it("dez requisições simultâneas fazem UMA renovação (duas revogariam uma à outra)", async () => {
        const http = novoHttp()
        const rs = await Promise.all(Array.from({ length: 10 }, (_, i) => http.PrepareRequest(`r${i}`)))
        expect(rs).toHaveLength(10)
        expect(refreshes()).toBe(1)
        expect(store.deslogou).toBe(0)
        expect(vistos.filter((v) => !v.url.endsWith("refresh")).every((v) => v.token === "t1")).toBe(true)
    })

    it("sem rede na renovação: segue com o token atual e não insiste antes da espera", async () => {
        const http = novoHttp()
        refreshFalhaDeRede = true
        await http.PrepareRequest("a")
        await http.PrepareRequest("b")
        expect(refreshes()).toBe(1)
        expect(vistos.filter((v) => v.url === "a" || v.url === "b").map((v) => v.token)).toEqual(["t0", "t0"])

        refreshFalhaDeRede = false
        relogio += 60_001
        await http.PrepareRequest("c")
        expect(store.token).toBe("t1")
    })

    it("rota pública (login) não dispara renovação", async () => {
        const http = novoHttp()
        await http.PrepareRequest("app/v1/login", "POST", { email: "x" }, false).catch(() => null)
        expect(refreshes()).toBe(0)
    })
})

describe("401: uma renovação, uma repetição, sem laço", () => {
    it("token que o servidor já não aceita é renovado e a requisição repetida com sucesso", async () => {
        const http = novoHttp()
        http.marcarTokenNovo()
        // O servidor revogou o t0, mas ainda há um token renovável: simulamos o
        // relógio do aparelho atrasado — o app acha o token novo, o servidor não.
        validos = new Set(["t0"])
        const original = servidor
        let primeira = true
        http.client.defaults.adapter = async (cfg) => {
            if (primeira && String(cfg.url) === "x") {
                primeira = false
                vistos.push({ url: "x", token: tokenDe(cfg) })
                throw new AxiosError("401", "ERR_BAD_REQUEST", cfg, null, {
                    data: {}, status: 401, statusText: "", headers: {}, config: cfg,
                } as any)
            }
            return original(cfg)
        }
        const r = await http.PrepareRequest<any>("x")
        expect(r.ok).toBe(true)
        expect(refreshes()).toBe(1)
        expect(store.deslogou).toBe(0)
        expect(vistos.filter((v) => v.url === "x").map((v) => v.token)).toEqual(["t0", "t1"])
    })

    it("token vencido de verdade: tenta renovar uma vez, desiste e desloga — sem laço", async () => {
        const http = novoHttp()
        http.marcarTokenNovo()
        validos = new Set() // t0 venceu no servidor
        await expect(http.PrepareRequest("x")).rejects.toMatchObject({ status: 401 })
        expect(refreshes()).toBe(1)
        expect(vistos.filter((v) => v.url === "x")).toHaveLength(1)
        expect(store.deslogou).toBe(1)
    })

    it("requisição que viajou com o token antigo enquanto outra renovava é repetida com o vigente, sem 2ª renovação", async () => {
        const http = novoHttp()
        http.marcarTokenNovo()
        // A requisição sai com t0; antes de a resposta chegar, outra rota renova.
        http.client.defaults.adapter = async (cfg) => {
            if (String(cfg.url) === "lenta" && tokenDe(cfg) === "t0") {
                validos.delete("t0")
                validos.add("t9")
                store.token = "t9"
            }
            return servidor(cfg)
        }
        const r = await http.PrepareRequest<any>("lenta")
        expect(r.ok).toBe(true)
        expect(refreshes()).toBe(0)
        expect(vistos.filter((v) => v.url === "lenta").map((v) => v.token)).toEqual(["t0", "t9"])
    })

    it("usuário saiu enquanto a renovação voava: o token novo não ressuscita a sessão", async () => {
        const http = novoHttp()
        http.client.defaults.adapter = async (cfg) => {
            if (String(cfg.url).endsWith("token/refresh")) store.token = "" // logout no meio
            return servidor(cfg)
        }
        await http.PrepareRequest("x").catch(() => null)
        expect(store.token).toBe("")
    })
})

describe("upload multipart (P2-C-05)", () => {
    it("leva a Idempotency-Key e um timeout de upload maior que o do JSON", async () => {
        const http = novoHttp()
        http.marcarTokenNovo()
        const form = new FormData()
        form.append("recebido_por", "Ana")
        await http.PrepareForm("app/v1/entregador/pedidos/5/concluir", form, { chave: "k-123" })
        const v = vistos.at(-1)!
        expect(v.chave).toBe("k-123")
        expect(v.timeout).toBe(UPLOAD_TIMEOUT_MS)
        expect(UPLOAD_TIMEOUT_MS).toBeGreaterThan(20000)
    })
})

describe("sem `renovacao` o comportamento antigo é mantido (o outro app não muda sem querer)", () => {
    it("não chama refresh e 401 desloga direto", async () => {
        const http = createHttp({
            baseURL: "https://erp.teste/api/",
            getToken: () => store.token,
            onUnauthorized: () => store.deslogou++,
        })
        http.client.defaults.adapter = servidor
        validos = new Set()
        await expect(http.PrepareRequest("x")).rejects.toMatchObject({ status: 401 })
        expect(refreshes()).toBe(0)
        expect(store.deslogou).toBe(1)
    })
})

void vi
