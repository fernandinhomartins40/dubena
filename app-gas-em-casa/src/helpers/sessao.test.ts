import { beforeEach, describe, expect, it, vi } from "vitest"
import { AxiosError } from "axios"

/**
 * PIE RC7/P2-B-03 no app do consumidor + R3-E-06.
 *
 * Roda o adaptador REAL (`helpers/http.ts`) com o store REAL (`appStore`, zustand
 * + persist). Só o disco (MMKV nativo) vira um Map e o transporte do axios vira um
 * servidor falso que se comporta como o Sanctum: o refresh emite um token novo e
 * revoga o antigo, e token revogado recebe 401.
 */
const disco = new Map<string, string>()
vi.mock("@/store/storage", () => ({
    zustandStorage: {
        setItem: (k: string, v: string) => void disco.set(k, v),
        getItem: (k: string) => disco.get(k) ?? null,
        removeItem: (k: string) => void disco.delete(k),
    },
    default: { clearAll: () => disco.clear() },
}))
vi.mock("@/constants/app", () => ({ APP: { api_url: "https://erp.teste/api/" } }))

import useAppStore from "@/store/appStore"
import Http from "@/helpers/http"

let validos: Set<string>
let seq: number
let urls: string[]

beforeEach(() => {
    validos = new Set(["t0"])
    seq = 0
    urls = []
    disco.clear()
    useAppStore.setState({ apiToken: "t0", user: { id: 1 } as any })
    Http.client.defaults.adapter = async (cfg: any) => {
        const h = cfg.headers
        const auth = typeof h?.get === "function" ? h.get("Authorization") : h?.Authorization
        const token = typeof auth === "string" ? auth.replace("Bearer ", "") : null
        const url = String(cfg.url)
        urls.push(url)
        const responder = (status: number, data: unknown) => {
            const r = { data, status, statusText: "", headers: {}, config: cfg }
            if (status < 300) return r
            throw new AxiosError("falha", "ERR_BAD_REQUEST", cfg, null, r as any)
        }
        if (!token || !validos.has(token)) return responder(401, { message: "Unauthenticated." })
        if (url.endsWith("token/refresh")) {
            const novo = `t${++seq}`
            validos.add(novo)
            validos.delete(token)
            // O refresh responde SEM `data` (AppAuthController::refresh).
            return responder(200, { token: novo, user: { id: 1 } })
        }
        return responder(200, { data: { ok: true } })
    }
})

const tokenNoDisco = (): string | undefined => {
    const bruto = disco.get("default-storage")
    return bruto ? JSON.parse(bruto)?.state?.apiToken : undefined
}

describe("sessão do app do consumidor", () => {
    it("R3-E-06: setToken notifica e PERSISTE (antes mutava e devolvia o mesmo objeto)", () => {
        let avisos = 0
        const parar = useAppStore.subscribe(() => avisos++)
        useAppStore.getState().setToken("tX")
        parar()
        expect(avisos).toBe(1)
        expect(tokenNoDisco()).toBe("tX")
    })

    it("token do disco é renovado na 1ª requisição e o novo vai para o disco", async () => {
        await Http.PrepareRequest("app/v1/pedidos")
        expect(urls).toEqual(["app/v1/token/refresh", "app/v1/pedidos"])
        expect(useAppStore.getState().apiToken).toBe("t1")
        expect(tokenNoDisco()).toBe("t1")
    })

    it("logo depois do login (marcarTokenNovo) não renova", async () => {
        Http.marcarTokenNovo()
        await Http.PrepareRequest("app/v1/pedidos")
        expect(urls).toEqual(["app/v1/pedidos"])
    })

    it("token revogado ou conta desativada (401 também no refresh) encerra a sessão", async () => {
        validos.clear()
        Http.marcarTokenNovo()
        await expect(Http.PrepareRequest("app/v1/pedidos")).rejects.toMatchObject({ status: 401 })
        expect(useAppStore.getState().apiToken).toBe("")
        expect(useAppStore.getState().user).toBeNull()
    })
})
