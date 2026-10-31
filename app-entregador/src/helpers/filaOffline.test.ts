import { beforeEach, describe, expect, it, vi } from "vitest"
import axios, { AxiosError, type AxiosAdapter, type InternalAxiosRequestConfig } from "axios"

/**
 * Fila offline do entregador (PIE-16, P2-C-06) contra os módulos REAIS — só
 * storage, store e http são dublês. Nasceu como sonda da auditoria PIE rodada 2:
 * os casos D–H reproduziam perda e inversão de ação e agora exigem o contrário.
 */

const disco = new Map<string, string>()
vi.mock("@/store/storage", () => ({
    default: {
        getString: (k: string) => disco.get(k),
        set: (k: string, v: string) => void disco.set(k, v),
        delete: (k: string) => void disco.delete(k),
        clearAll: () => disco.clear(),
    },
}))

const estado: { user?: { id: number } | null } = { user: { id: 1 } }
vi.mock("@/store/appStore", () => ({ default: { getState: () => estado } }))

type Chamada = { url: string; chave: string | undefined }
let recebidas: Chamada[] = []
let roteiro: (cfg: InternalAxiosRequestConfig) => Promise<{ status: number; data: unknown }>

const adaptador: AxiosAdapter = async (cfg) => {
    const chave = (cfg.headers as any)?.["Idempotency-Key"]
    const r = await roteiro(cfg)
    recebidas.push({ url: String(cfg.url), chave })
    const resposta = { data: r.data, status: r.status, statusText: "", headers: {}, config: cfg }
    if (r.status >= 200 && r.status < 300) return resposta
    throw new AxiosError(`Request failed with status code ${r.status}`, "ERR_BAD_REQUEST", cfg, null, resposta as any)
}
const semRede = (cfg: InternalAxiosRequestConfig): never => {
    throw new AxiosError("Network Error", "ERR_NETWORK", cfg)
}

const cliente = axios.create({ baseURL: "https://erp.teste/api/", adapter: adaptador })
vi.mock("@/helpers/http", () => ({ default: { client: { request: (c: any) => cliente.request(c) } } }))

import * as fila from "@/helpers/filaOffline"
import { enviarOuEnfileirar, sincronizarPendencias } from "@/helpers/envioResiliente"

beforeEach(() => {
    disco.clear()
    recebidas = []
    estado.user = { id: 1 }
    roteiro = async () => ({ status: 200, data: { data: { ok: true } } })
})

describe("chave de idempotência (PIE-16 / R2-03)", () => {
    it("o reenvio usa a MESMA chave da 1ª tentativa", async () => {
        const chaves: string[] = []
        roteiro = async (cfg) => {
            chaves.push((cfg.headers as any)["Idempotency-Key"])
            return semRede(cfg)
        }
        const r = await enviarOuEnfileirar("app/v1/entregador/solicitacoes", "POST", { cliente_id: 7 })
        expect(r.enfileirado).toBe(true)
        const [item] = fila.pendentes()
        expect(item.id).toBe(chaves[0])
        expect(item.dono).toBe(1)

        roteiro = async () => ({ status: 201, data: { data: { id: 99 } } })
        const s = await sincronizarPendencias()
        expect(recebidas.at(-1)?.chave).toBe(chaves[0])
        expect(s.restantes).toBe(0)
    })
})

describe("dono e sessão (PIE-16 / R2-04)", () => {
    it("401 no reenvio não apaga a pendência", async () => {
        fila.enfileirar("x/1", "POST", { a: 1 }, "k1", 1)
        roteiro = async () => ({ status: 401, data: { message: "Unauthenticated." } })
        await sincronizarPendencias()
        expect(fila.quantidadePendente()).toBe(1)
    })

    it("item de outro entregador não sai com o token de quem está logado", async () => {
        fila.enfileirar("x/1", "POST", { a: 1 }, "k-de-A", 1)
        estado.user = { id: 2 }
        await sincronizarPendencias()
        expect(recebidas.length).toBe(0)
    })

    it("a fila sobrevive ao app morto", async () => {
        fila.enfileirar("x/1", "POST", { a: 1 }, "kI1", 1)
        fila.enfileirar("x/2", "POST", { a: 2 }, "kI2", 1)
        vi.resetModules()
        const fila2 = await import("@/helpers/filaOffline")
        expect(fila2.quantidadePendente()).toBe(2)
    })
})

describe("nada some em silêncio (P2-C-06)", () => {
    it("recusa do servidor (422) sai da fila mas fica em 'precisa de atenção' com a frase do servidor", async () => {
        fila.enfileirar("app/v1/entregador/pedidos/5/status", "POST", { pedidosituacao_id: 3 }, "k5", 1)
        roteiro = async () => ({ status: 422, data: { message: "O pedido mudou de situação (Cancelado)." } })
        const s = await sincronizarPendencias()
        expect(s.falharam).toBe(1)
        expect(fila.quantidadePendente()).toBe(0)
        expect(fila.precisamDeAtencao().map((i) => i.ultimoErro)).toEqual(["O pedido mudou de situação (Cancelado)."])

        fila.dispensarAtencao("k5")
        expect(fila.precisamDeAtencao()).toEqual([])
    })

    it("409 'em processamento' NÃO conta como sucesso: o item continua na fila", async () => {
        fila.enfileirar("app/v1/entregador/solicitacoes", "POST", { cliente_id: 7 }, "kE", 1)
        roteiro = async () => ({ status: 409, data: { message: "Requisição ainda em processamento." } })
        await sincronizarPendencias()
        expect(fila.pendentes().map((i) => i.id)).toEqual(["kE"])
    })

    it("duas sincronizações simultâneas enviam cada item uma vez só", async () => {
        fila.enfileirar("x/1", "POST", { a: 1 }, "kC", 1)
        roteiro = async () => {
            await new Promise((r) => setTimeout(r, 20))
            return { status: 200, data: {} }
        }
        await Promise.all([sincronizarPendencias(), sincronizarPendencias()])
        expect(recebidas.length).toBe(1)
    })

    it("5xx permanente sai do topo depois de esgotar e libera o resto da fila", async () => {
        fila.enfileirar("x/envenenado", "POST", { a: 1 }, "kF1", 1)
        fila.enfileirar("x/legitimo", "POST", { b: 2 }, "kF2", 1)
        roteiro = async (cfg) =>
            String(cfg.url).includes("envenenado")
                ? { status: 500, data: { message: "erro" } }
                : { status: 200, data: { data: {} } }
        for (let i = 0; i < 12; i++) await sincronizarPendencias()
        expect(recebidas.some((c) => c.url.includes("legitimo"))).toBe(true)
        expect(fila.precisamDeAtencao().map((i) => i.id)).toEqual(["kF1"])
        expect(fila.quantidadePendente()).toBe(0)
    })

    it("falta de sinal não esgota: oito janelas sem rede mantêm o item na fila", async () => {
        fila.enfileirar("x/1", "POST", { a: 1 }, "kT", 1)
        roteiro = async (cfg) => semRede(cfg)
        for (let i = 0; i < 12; i++) await sincronizarPendencias()
        expect(fila.pendentes().map((i) => i.id)).toEqual(["kT"])
        expect(fila.precisamDeAtencao()).toEqual([])
    })
})

describe("ordem e mensagem (P2-C-06, P2-C-08)", () => {
    it("ação nova não passa à frente da pendente", async () => {
        roteiro = async (cfg) => semRede(cfg)
        await enviarOuEnfileirar("app/v1/entregador/pedidos/9/aceitar", "POST")

        roteiro = async () => ({ status: 200, data: { data: {} } })
        await enviarOuEnfileirar("app/v1/entregador/pedidos/9/status", "POST", { pedidosituacao_id: 4 })
        await sincronizarPendencias()
        expect(recebidas.map((c) => c.url.split("/").slice(-1)[0])).toEqual(["aceitar", "status"])
    })

    it("4xx online chega à tela com a frase do servidor, não a do axios", async () => {
        roteiro = async () => ({ status: 422, data: { message: "Já existe cliente com este telefone.", errors: {} } })
        await expect(enviarOuEnfileirar("app/v1/entregador/clientes", "POST", { nome: "X" })).rejects.toThrow(
            "Já existe cliente com este telefone.",
        )
    })
})
