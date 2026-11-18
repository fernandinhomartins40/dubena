import { describe, expect, it, vi } from "vitest"
import { assinaturaDaVenda, tentativaPara } from "./tentativaVenda"

describe("tentativa de venda em campo (P2-C-05)", () => {
    const itens = [{ produto_id: 1, quantidade: 2 }]

    it("reenvio da mesma venda repete chave E posição (o servidor compara o conteúdo)", async () => {
        let n = 0
        const gerar = () => `chave-${++n}`
        let lat = -25.39
        const ler = vi.fn(async () => ({ latitude: lat++, longitude: -51.46 }))

        const a = assinaturaDaVenda(7, itens, "file:///foto.jpg")
        const t1 = await tentativaPara(null, a, gerar, ler)
        const t2 = await tentativaPara(t1, assinaturaDaVenda(7, [{ produto_id: 1, quantidade: 2 }], "file:///foto.jpg"), gerar, ler)

        expect(t2.chave).toBe(t1.chave)
        expect(t2.coords).toEqual(t1.coords)
        expect(ler).toHaveBeenCalledTimes(1)
    })

    it("mudou cliente, quantidade ou foto: outra venda, outra chave", async () => {
        let n = 0
        const gerar = () => `chave-${++n}`
        const ler = async () => null
        const t1 = await tentativaPara(null, assinaturaDaVenda(7, itens, null), gerar, ler)

        for (const outra of [
            assinaturaDaVenda(8, itens, null),
            assinaturaDaVenda(7, [{ produto_id: 1, quantidade: 3 }], null),
            assinaturaDaVenda(7, itens, "file:///nova.jpg"),
        ]) {
            expect((await tentativaPara(t1, outra, gerar, ler)).chave).not.toBe(t1.chave)
        }
    })
})
