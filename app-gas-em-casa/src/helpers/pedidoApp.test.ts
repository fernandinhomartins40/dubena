import { describe, it, expect } from "vitest"
import fs from "node:fs"
import path from "node:path"
import {
    acoesDoPedido,
    chaveDoPedido,
    deveAbrirPix,
    enderecoDeEntrega,
    montarPayloadPedido,
    mostraGasDoPovo,
    podeAvaliar,
    rotuloEndereco,
    textoConfirmaCancelamento,
    textoPrazoEntrega,
    validadePix,
} from "./pedidoApp"
import { destinoDaNotificacao } from "./notificacao"
import { mensagemErroSms, tratarErroLogin } from "./erroLogin"
import { gerarChaveCifra } from "./chaveCifra"
import { vigiarFimDeSessao } from "./sessao"

/** PIE R3-E — regras do fluxo de compra do app do consumidor. */

const payload = montarPayloadPedido({ enderecoId: 7, condicaoId: 2, cupom: null, gasdopovo: false, itens: [{ produto_id: 13, quantidade: 1 }] })

describe("contrato do POST app/v1/pedidos (P2-B-21)", () => {
    it("o payload montado pelo app é o do arquivo de contrato lido também pelo teste PHP", () => {
        const contrato = JSON.parse(
            fs.readFileSync(path.resolve(__dirname, "..", "..", "contrato", "app-v1.json"), "utf8"),
        )
        const esperado = JSON.parse(
            JSON.stringify(contrato.requisicoes["POST app/v1/pedidos"])
                .replace('"<endereco_id>"', "7")
                .replace('"<condicao_id>"', "2")
                .replace('"<produto_id>"', "13"),
        )
        expect(payload).toEqual(esperado)
    })
})

describe("chave de idempotência (R2-13)", () => {
    let n = 0
    const gerar = () => `k${++n}`
    it("mesmo carrinho reaproveita a chave; carrinho diferente gera outra", () => {
        const a = chaveDoPedido(payload, null, gerar)
        expect(chaveDoPedido(payload, a, gerar).chave).toBe(a.chave)
        const outro = { ...payload, itens: [{ produto_id: 13, quantidade: 2 }] }
        expect(chaveDoPedido(outro, a, gerar).chave).not.toBe(a.chave)
    })
})

describe("PIX e cancelamento (P2-B-04)", () => {
    it("só condição PIX abre a tela do PIX (resíduo R2-02)", () => {
        expect(deveAbrirPix({ pix_online: true })).toBe(true)
        expect(deveAbrirPix({ pix_online: false })).toBe(false)
        expect(deveAbrirPix(undefined)).toBe(false)
    })

    it("pedido aberto com PIX não pago oferece pagar e cancelar", () => {
        const a = acoesDoPedido({ id: 1, efeito: "PENDENTE", situacao_papel: "NENHUM", pagamento: { pix: true, pago: false } })
        expect(a).toEqual({ aberto: true, emRota: false, podePagarPix: true, podeCancelar: true })
    })

    it("PIX pago não oferece pagar de novo; o cancelamento avisa do estorno", () => {
        const o = { id: 1, efeito: "PENDENTE", pagamento: { pix: true, pago: true } }
        expect(acoesDoPedido(o).podePagarPix).toBe(false)
        expect(textoConfirmaCancelamento(o)).toMatch(/estorno/)
    })

    it("pedido encerrado não oferece nada", () => {
        for (const efeito of ["CONCLUIDO", "CANCELADO"]) {
            const a = acoesDoPedido({ id: 1, efeito, pagamento: { pix: true, pago: false } })
            expect(a.podePagarPix || a.podeCancelar).toBe(false)
        }
    })

    it("em rota vem da marca declarada, não do texto (classe do P2-C-04)", () => {
        expect(acoesDoPedido({ id: 1, efeito: "PENDENTE", situacao: "Saiu para entrega", situacao_papel: "NENHUM" }).emRota).toBe(false)
        expect(acoesDoPedido({ id: 1, efeito: "PENDENTE", situacao: "Despachado", situacao_papel: "EM_ROTA" }).emRota).toBe(true)
    })

    it("o texto do PIX não promete cancelamento automático e diz a validade real", () => {
        const agora = new Date("2026-10-31T10:00:00").getTime()
        const v = validadePix(new Date("2026-10-31T10:05:00").toISOString(), agora)
        expect(v.expirado).toBe(false)
        expect(v.texto).toContain("10:05")
        expect(v.texto).not.toMatch(/ser[aá] cancelado/)
        expect(validadePix(new Date("2026-10-31T09:59:00").toISOString(), agora).expirado).toBe(true)
        expect(validadePix(null, agora).texto).toBe("")
    })
})

describe("checkout (Entregar em, prazo, Gás do Povo)", () => {
    const e = (id: number, favorito = false) =>
        ({ id, endereco: `Rua ${id}`, numero: "1", bairro: "Centro", favorito }) as any
    it("escolhido > favorito > primeiro", () => {
        expect(enderecoDeEntrega([e(1), e(2, true)], null)?.id).toBe(2)
        expect(enderecoDeEntrega([e(1), e(2, true)], 1)?.id).toBe(1)
        expect(enderecoDeEntrega([e(1)], 99)?.id).toBe(1)
        expect(enderecoDeEntrega([], null)).toBeNull()
        expect(rotuloEndereco(e(3))).toBe("Rua 3, 1 - Centro")
    })
    it("prazo só quando a revenda configurou", () => {
        expect(textoPrazoEntrega(40)).toBe("Entrega em cerca de 40 min")
        expect(textoPrazoEntrega(null)).toBe("")
    })
    it("Gás do Povo exige programa ligado E cliente beneficiário", () => {
        expect(mostraGasDoPovo(true, true)).toBe(true)
        expect(mostraGasDoPovo(true, false)).toBe(false)
        expect(mostraGasDoPovo(false, true)).toBe(false)
    })
})

describe("avaliação (P2-B-15)", () => {
    it("só concluído e não avaliado", () => {
        expect(podeAvaliar({ efeito: "CONCLUIDO", avaliado: false })).toBe(true)
        expect(podeAvaliar({ efeito: "CONCLUIDO", avaliado: true })).toBe(false)
        expect(podeAvaliar({ efeito: "PENDENTE" })).toBe(false)
    })
})

describe("notificação (P2-B-08)", () => {
    it("pedido aberto vai ao acompanhamento; encerrado ao histórico", () => {
        expect(destinoDaNotificacao({ acao: "orderUpdated", pedido_id: "501", efeito: "PENDENTE" })).toEqual({ tipo: "pedido", pedidoId: 501, aberto: true })
        expect(destinoDaNotificacao({ acao: "orderUpdated", pedido_id: "501", efeito: "CONCLUIDO" })).toEqual({ tipo: "pedido", pedidoId: 501, aberto: false })
        expect(destinoDaNotificacao({ acao: "orderUpdated", pedido_id: "x" })).toBeNull()
        expect(destinoDaNotificacao({})).toBeNull()
    })
})

describe("login por SMS (P2-B-12)", () => {
    it("só o 422 de telefone não encontrado leva ao cadastro", () => {
        expect(tratarErroLogin({ status: 422, errors: { cliente: ["x"] } })).toEqual({ acao: "cadastro" })
        expect(tratarErroLogin({ status: 422, message: "empresa inválida", errors: { empresa_id: ["x"] } }).acao).toBe("mensagem")
    })
    it("401, 429, sem rede e 500 viram mensagem com o que fazer", () => {
        for (const status of [0, 401, 429, 500]) {
            const d = tratarErroLogin({ status })
            expect(d.acao).toBe("mensagem")
            expect((d as any).texto.length).toBeGreaterThan(10)
        }
    })
    it("erro do Firebase tem frase própria e há um padrão para o desconhecido", () => {
        expect(mensagemErroSms("auth/too-many-requests")).toMatch(/Aguarde/)
        expect(mensagemErroSms("auth/invalid-phone-number")).toMatch(/inválido/)
        expect(mensagemErroSms(undefined)).toMatch(/Reenviar/)
    })
})

describe("chave de cifra (P2-B-18)", () => {
    it("16 caracteres, da fonte nativa, sem Math.random", () => {
        const original = Math.random
        Math.random = () => {
            throw new Error("Math.random não pode ser usado")
        }
        try {
            const uuids = ["3f2a9c1e-7b4d-4e8a-9c21-5d6e7f8a9b0c", "a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d"]
            let i = 0
            const k1 = gerarChaveCifra(() => uuids[i++ % 2])
            expect(k1).toHaveLength(16)
            expect(k1).toMatch(/^[A-Za-z0-9_-]{16}$/)
            const k2 = gerarChaveCifra(() => "00000000-0000-4000-8000-000000000001")
            expect(k2).not.toBe(k1)
        } finally {
            Math.random = original
        }
    })
})

describe("fim de sessão (P2-B-17)", () => {
    it("limpa quando o token some, não a cada mudança", () => {
        let ouvinte: (s: any, p: any) => void = () => {}
        let limpezas = 0
        vigiarFimDeSessao({ subscribe: (fn) => ((ouvinte = fn), () => {}) }, () => limpezas++)
        ouvinte({ apiToken: "a" }, { apiToken: "" })
        ouvinte({ apiToken: "a", user: 1 }, { apiToken: "a" })
        expect(limpezas).toBe(0)
        ouvinte({ apiToken: "" }, { apiToken: "a" })
        expect(limpezas).toBe(1)
    })
})
