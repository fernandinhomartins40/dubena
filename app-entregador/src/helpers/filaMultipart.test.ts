import { beforeEach, describe, expect, it, vi } from "vitest"
import { AxiosError, type AxiosAdapter, type InternalAxiosRequestConfig } from "axios"

/**
 * Concluir/ocorrência pela fila offline (PIE P2-C-05) e a fila sob sessão
 * renovada (RC7), contra os módulos REAIS da fila, do envio resiliente e do
 * núcleo `@shared/http`. Dublês: storage (Map), arquivos (registro em memória),
 * store e o transporte do axios.
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

// "Sistema de arquivos": o que foi copiado para a pasta da fila e o que foi apagado.
const copiados = new Map<string, Record<string, any>>()
const apagados: string[] = []
vi.mock("@/helpers/arquivosFila", () => ({
    guardarArquivos: async (chave: string, arqs: Record<string, any>) => {
        const out: Record<string, any> = {}
        for (const [k, a] of Object.entries(arqs)) if (a) out[k] = { ...a, uri: `doc://fila-offline/${chave}/${k}` }
        copiados.set(chave, out)
        return out
    },
    apagarArquivos: async (chave: string) => {
        apagados.push(chave)
    },
}))

const estado = { user: { id: 7 } as { id: number } | null, apiToken: "t0", deslogou: 0 }
vi.mock("@/store/appStore", () => ({
    default: {
        getState: () => ({
            ...estado,
            setToken: (t: string) => {
                estado.apiToken = t
            },
            logout: () => {
                estado.deslogou++
                estado.apiToken = ""
            },
        }),
    },
}))

type Visto = { url: string; chave?: string; token: string | null; campos: Record<string, string>; arquivos: string[]; multipart: boolean }
let vistos: Visto[]
let roteiro: (cfg: InternalAxiosRequestConfig) => Promise<{ status: number; data: unknown }>
let validos: Set<string>

const ler = (cfg: InternalAxiosRequestConfig): Visto => {
    const h: any = cfg.headers
    const auth = typeof h?.get === "function" ? h.get("Authorization") : h?.Authorization
    const campos: Record<string, string> = {}
    const arquivos: string[] = []
    const multipart = cfg.data instanceof FormData
    if (multipart) {
        for (const [k, v] of (cfg.data as any).entries() as Iterable<[string, unknown]>) {
            // No node, o FormData converte o {uri,name,type} do RN em texto: o
            // que importa é o campo existir e ser montado a partir do caminho.
            if (k === "foto" || k === "assinatura") arquivos.push(k)
            else campos[k] = String(v)
        }
    }
    return {
        url: String(cfg.url),
        chave: h?.["Idempotency-Key"] ?? (typeof h?.get === "function" ? h.get("Idempotency-Key") : undefined),
        token: typeof auth === "string" ? auth.replace("Bearer ", "") : null,
        campos,
        arquivos,
        multipart,
    }
}

const adaptador: AxiosAdapter = async (cfg) => {
    const v = ler(cfg)
    const responder = (status: number, data: unknown) => {
        const r = { data, status, statusText: "", headers: {}, config: cfg }
        if (status < 300) return r
        throw new AxiosError(`Request failed with status code ${status}`, "ERR_BAD_REQUEST", cfg, null, r as any)
    }
    vistos.push(v)
    if (!v.token || !validos.has(v.token)) return responder(401, { message: "Unauthenticated." })
    if (v.url.endsWith("token/refresh")) {
        const novo = `t${validos.size + 1}`
        validos.delete(v.token)
        validos.add(novo)
        return responder(200, { token: novo })
    }
    const r = await roteiro(cfg)
    return responder(r.status, r.data)
}
;(globalThis as any).__adaptadorFila = adaptador
const semRede = (cfg: InternalAxiosRequestConfig): never => {
    throw new AxiosError("timeout of 90000ms exceeded", "ECONNABORTED", cfg)
}

// Usa o núcleo REAL (com renovação), só trocando o transporte.
vi.mock("@/helpers/http", async () => {
    const { createHttp } = await import("@shared/http")
    const store = (await import("@/store/appStore")).default
    const http = createHttp({
        baseURL: "https://erp.teste/api/",
        getToken: () => store.getState().apiToken,
        onUnauthorized: () => store.getState().logout(),
        renovacao: { endpoint: "app/v1/token/refresh", onToken: (t) => store.getState().setToken(t) },
    })
    // Indireção: o vi.mock é içado acima da definição do adaptador.
    http.client.defaults.adapter = (cfg) => (globalThis as any).__adaptadorFila(cfg)
    return { default: http }
})

import * as fila from "@/helpers/filaOffline"
import { acaoPendenteDoPedido, sincronizarPendencias } from "@/helpers/envioResiliente"
import EntregaService from "@/services/entrega.service"
import Http from "@/helpers/http"

const FOTO = { uri: "file:///cache/ImagePicker/abc.jpg", name: "abc.jpg", type: "image/jpeg" }
const ASS = { uri: "file:///cache/assinatura-5.png", name: "assinatura.png", type: "image/png" }

beforeEach(() => {
    disco.clear()
    copiados.clear()
    apagados.length = 0
    vistos = []
    validos = new Set(["t0"])
    estado.user = { id: 7 }
    estado.apiToken = "t0"
    estado.deslogou = 0
    ;(Http as any).marcarTokenNovo()
    roteiro = async () => ({ status: 201, data: { data: { comprovacao_id: 1, concluido: true } } })
})

const concluirAgora = () =>
    EntregaService.Concluir(5, { recebido_por: "Ana", latitude: -25.39, longitude: -51.46 }, { foto: FOTO, assinatura: ASS })

describe("concluir entrega (P2-C-05)", () => {
    it("com sinal: multipart com Idempotency-Key, anexos copiados para fora do cache e apagados depois", async () => {
        const r = await concluirAgora()
        expect(r.enfileirado).toBe(false)
        const v = vistos.find((x) => x.url.endsWith("/concluir"))!
        expect(v.multipart).toBe(true)
        expect(v.chave).toMatch(/[0-9a-f-]{36}/)
        expect(v.campos).toEqual({ recebido_por: "Ana", latitude: "-25.39", longitude: "-51.46" })
        expect(v.arquivos.sort()).toEqual(["assinatura", "foto"])
        expect(copiados.get(v.chave!)?.foto.uri).toBe(`doc://fila-offline/${v.chave}/foto`)
        expect(apagados).toContain(v.chave)
        expect(fila.quantidadePendente()).toBe(0)
    })

    it("timeout no upload: entra na fila com a MESMA chave e os mesmos campos; reenvio após 'reinício' conclui", async () => {
        roteiro = async (cfg) => semRede(cfg)
        const r = await concluirAgora()
        expect(r.enfileirado).toBe(true)
        const chave1 = vistos.find((x) => x.url.endsWith("/concluir"))!.chave

        // "Reinício": a fila vem do storage (JSON), não de memória.
        const [item] = JSON.parse(disco.get("fila-offline-v1")!)
        expect(item.id).toBe(chave1)
        expect(item.formulario.arquivos.foto.uri).toBe(`doc://fila-offline/${chave1}/foto`)
        expect(JSON.stringify(item)).not.toContain("base64")
        expect(acaoPendenteDoPedido(5, "concluir")).toBe(true)
        expect(apagados).not.toContain(chave1)

        roteiro = async () => ({ status: 201, data: { data: { comprovacao_id: 1, concluido: true } } })
        const s = await sincronizarPendencias()
        expect(s).toMatchObject({ enviados: 1, restantes: 0 })
        const reenvio = vistos.filter((x) => x.url.endsWith("/concluir")).at(-1)!
        expect(reenvio.chave).toBe(chave1)
        expect(reenvio.campos).toEqual({ recebido_por: "Ana", latitude: "-25.39", longitude: "-51.46" })
        expect(reenvio.arquivos.sort()).toEqual(["assinatura", "foto"])
        expect(apagados).toContain(chave1)
    })

    it("422 real (pedido cancelado): mostra a frase do servidor, não enfileira e apaga os anexos", async () => {
        roteiro = async () => ({ status: 422, data: { message: "O pedido mudou de situação (Cancelado)." } })
        await expect(concluirAgora()).rejects.toMatchObject({ message: "O pedido mudou de situação (Cancelado)." })
        expect(fila.quantidadePendente()).toBe(0)
        expect(apagados).toHaveLength(1)
    })

    it("ocorrência também vai pela fila sem sinal", async () => {
        roteiro = async (cfg) => semRede(cfg)
        const r = await EntregaService.Ocorrencia(5, { tipo: "ausente", descricao: "Ninguém atendeu" }, FOTO)
        expect(r.enfileirado).toBe(true)
        expect(acaoPendenteDoPedido(5, "ocorrencia")).toBe(true)
    })
})

describe("fila × sessão renovada (RC7)", () => {
    it("token vencido no meio do turno: a fila renova e reenvia, sem perder o item e sem deslogar", async () => {
        roteiro = async (cfg) => semRede(cfg)
        await concluirAgora()
        expect(fila.quantidadePendente()).toBe(1)

        // O servidor rotacionou o token por fora (outro dispositivo/refresh perdido
        // não) — aqui: o t0 ficou inválido para rotas comuns mas o refresh o aceita.
        roteiro = async (cfg) => {
            const v = ler(cfg)
            return v.token === "t0" ? { status: 401, data: {} } : { status: 201, data: { data: { ok: true } } }
        }
        const s = await sincronizarPendencias()
        expect(s.restantes).toBe(0)
        expect(estado.apiToken).not.toBe("t0")
        expect(estado.deslogou).toBe(0)
    })

    it("sessão vencida de vez: o item continua na fila esperando o novo login", async () => {
        roteiro = async (cfg) => semRede(cfg)
        await concluirAgora()
        validos = new Set()
        await sincronizarPendencias()
        expect(fila.quantidadePendente()).toBe(1)
        expect(vistos.filter((v) => v.url.endsWith("token/refresh"))).toHaveLength(1)
    })
})
