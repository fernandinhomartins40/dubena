import { beforeEach, describe, expect, it, vi } from "vitest"

/**
 * PIE rodada 3 — leitura offline e estados (P2-C-10/11), rastreamento fora do
 * turno (P2-C-07) e chave do MMKV (P2-C-20). Módulos REAIS; storage e store
 * são dublês em memória.
 */

const disco = new Map<string, string>()
vi.mock("@/store/storage", () => ({
    default: {
        getString: (k: string) => disco.get(k),
        set: (k: string, v: string) => void disco.set(k, v),
        delete: (k: string) => void disco.delete(k),
    },
}))
const estado = { user: { id: 1 } as { id: number } | null }
vi.mock("@/store/appStore", () => ({ default: { getState: () => estado } }))

import { avisoOffline, comCache, estadoDaLista, iniciarDoCache, lerCache, limparCaches } from "@/helpers/cacheLocal"
import { deveEncerrarRastreamento, deveRastrear } from "@/helpers/rastreamentoRegra"
import { gerarChaveMmkv } from "@/store/chaveMmkv"

beforeEach(() => {
    disco.clear()
    estado.user = { id: 1 }
})

describe("leitura offline após reiniciar (P2-C-11)", () => {
    it("a última resposta boa volta como initialData no próximo boot", async () => {
        const pedidos = [{ id: 10, cliente: "Ana", endereco: "Rua A, 1" }]
        await comCache("pedidos", async () => pedidos)()
        // "reinício": nada em memória, só o storage
        const op = iniciarDoCache<typeof pedidos>("pedidos")
        expect(op.initialData()).toEqual(pedidos)
        expect(typeof op.initialDataUpdatedAt()).toBe("number")
    })

    it("outro entregador no aparelho não vê a lista do anterior; logout apaga", async () => {
        await comCache("pedidos", async () => [{ id: 10 }])()
        estado.user = { id: 2 }
        expect(lerCache("pedidos")).toBeUndefined()
        estado.user = { id: 1 }
        limparCaches()
        expect(lerCache("pedidos")).toBeUndefined()
    })

    it("falha da consulta não apaga o que estava guardado", async () => {
        await comCache("rota", async () => ({ paradas: [1] }))()
        await expect(comCache("rota", async () => Promise.reject({ status: 0 }))()).rejects.toBeTruthy()
        expect(lerCache<{ paradas: number[] }>("rota")?.dados.paradas).toEqual([1])
    })
})

describe("vazio × sem conexão (P2-C-10)", () => {
    it("falha de rede sem dado guardado é 'sem conexão', não 'vazio'", () => {
        expect(estadoDaLista({ isLoading: false, isError: true, error: { status: 0 }, data: undefined })).toBe("sem-conexao")
        expect(estadoDaLista({ isLoading: false, isError: true, error: { status: 500 }, data: [] })).toBe("erro")
        expect(estadoDaLista({ isLoading: false, isError: false, data: [] })).toBe("vazio")
        expect(estadoDaLista({ isLoading: false, isError: true, error: { status: 0 }, data: [{ id: 1 }] })).toBe("ok")
    })

    it("com dado guardado e sem rede, avisa a hora do dado", () => {
        const t = new Date(2026, 9, 31, 14, 32).getTime()
        expect(avisoOffline({ isError: true, error: { status: 0 }, dataUpdatedAt: t })).toBe(
            "Sem conexão — mostrando o que foi carregado às 14:32.",
        )
        expect(avisoOffline({ isError: false, dataUpdatedAt: t })).toBeNull()
    })
})

describe("rastreamento fora do turno (P2-C-07)", () => {
    it("task sem token ou fora de serviço não coleta", () => {
        expect(deveRastrear({ apiToken: "", emServico: true })).toBe(false)
        expect(deveRastrear({ apiToken: "t", emServico: false })).toBe(false)
        expect(deveRastrear({ apiToken: "t", emServico: true })).toBe(true)
    })

    it("422 'Inicie a jornada', 401 e 403 desligam; falta de sinal e 5xx não", () => {
        expect(deveEncerrarRastreamento({ status: 422 })).toBe(true)
        expect(deveEncerrarRastreamento({ status: 401 })).toBe(true)
        expect(deveEncerrarRastreamento({ response: { status: 403 } })).toBe(true)
        expect(deveEncerrarRastreamento({ status: 0 })).toBe(false)
        expect(deveEncerrarRastreamento({ status: 503 })).toBe(false)
    })
})

describe("chave do MMKV (P2-C-20)", () => {
    const uuidReal = () => crypto.randomUUID()

    it("16 caracteres (o limite do MMKV), ASCII seguro, de gerador criptográfico", () => {
        const k = gerarChaveMmkv(uuidReal)
        expect(k).toHaveLength(16)
        expect(k).toMatch(/^[A-Za-z0-9_-]{16}$/)
        const amostra = new Set(Array.from({ length: 200 }, () => gerarChaveMmkv(uuidReal)))
        expect(amostra.size).toBe(200)
    })

    it("sem gerador seguro, recusa em vez de cair no Math.random", () => {
        expect(() => gerarChaveMmkv(undefined)).toThrow(/Gerador seguro/)
    })

    it("não usa os nibbles fixos do uuid v4 (versão/variante)", () => {
        // uuid com tudo zero menos versão e variante: se elas entrassem, a chave
        // teria símbolos além de 'A' (byte 0).
        const k = gerarChaveMmkv(() => "00000000-0000-4000-8000-000000000000")
        expect(k).toBe("A".repeat(16))
    })
})
