import { describe, it, expect, vi } from "vitest"

/**
 * R2-13 / P2-B-21 — teste de SERVIÇO: o que de fato sai pela rede.
 * O núcleo HTTP real (@shared/http) roda; só a configuração do Expo e o store
 * persistido (MMKV nativo) são trocados por dublês.
 */
vi.mock("@/constants/app", () => ({ APP: { api_url: "http://api.teste/api/" } }))
vi.mock("@/store/appStore", () => ({
    default: { getState: () => ({ apiToken: "tok", logout: () => {} }) },
}))

// `vi.mock` é içado para antes destes imports pelo vitest.
import OrderService from "./order.service"
import Http from "@/helpers/http"

function capturar() {
    const enviados: any[] = []
    Http.client.defaults.adapter = async (config: any) => {
        enviados.push(config)
        return { data: { data: { id: 501 } }, status: 201, statusText: "Created", headers: {}, config }
    }
    return enviados
}

const payload = { endereco_id: 7, condicaopagamento_id: 2, codigo_cupom: null, gasdopovo: false, itens: [{ produto_id: 13, quantidade: 1 }] }

describe("OrderService.CreateOrder", () => {
    it("manda Idempotency-Key no cabeçalho e o corpo sem a chave", async () => {
        const enviados = capturar()
        const r = await OrderService.CreateOrder(payload, "chave-123")
        expect(r).toEqual({ id: 501 })
        const cfg = enviados.find((c) => c.url === "app/v1/pedidos")
        const header = cfg.headers.get ? cfg.headers.get("Idempotency-Key") : cfg.headers["Idempotency-Key"]
        expect(header).toBe("chave-123")
        expect(JSON.parse(cfg.data)).toEqual(payload)
        expect(cfg.url).toBe("app/v1/pedidos")
    })

    it("sem chave, não inventa cabeçalho", async () => {
        const enviados = capturar()
        await OrderService.CreateOrder(payload)
        const cfg = enviados.find((c) => c.url === "app/v1/pedidos")
        const header = cfg.headers.get ? cfg.headers.get("Idempotency-Key") : cfg.headers["Idempotency-Key"]
        expect(header ?? undefined).toBeUndefined()
    })

    it("Cancel chama a rota de cancelamento do próprio pedido (P2-B-04)", async () => {
        const enviados = capturar()
        await OrderService.Cancel(501)
        const cfg = enviados.find((c) => c.url === "app/v1/pedidos/501/cancelar")
        expect(cfg?.method).toBe("post")
    })
})
