import { describe, expect, it } from "vitest"
import { etapaDoPedido } from "@/helpers/situacao"

describe("etapaDoPedido (PIE P2-C-04)", () => {
    it("'Saiu para entrega' é em andamento, não histórico", () => {
        expect(etapaDoPedido({ situacao: "Saiu para entrega", situacao_efeito: "PENDENTE", situacao_papel: "EM_ROTA" })).toBe("andamento")
    })

    it("vale pelo papel, não pelo texto: nome livre da revenda", () => {
        expect(etapaDoPedido({ situacao: "Em trânsito", situacao_efeito: "PENDENTE", situacao_papel: "EM_ROTA" })).toBe("andamento")
        expect(etapaDoPedido({ situacao: "Entregue ao porteiro", situacao_efeito: "PENDENTE", situacao_papel: "NENHUM" })).toBe("pendente")
    })

    it("concluído e cancelado vão para o histórico", () => {
        expect(etapaDoPedido({ situacao: "Finalizado", situacao_efeito: "CONCLUIDO", situacao_papel: "NENHUM" })).toBe("finalizado")
        expect(etapaDoPedido({ situacao: "Desistiu", situacao_efeito: "CANCELADO", situacao_papel: null })).toBe("finalizado")
    })

    it("servidor antigo (sem efeito): texto, com 'em rota' antes de 'entreg'", () => {
        expect(etapaDoPedido({ situacao: "Saiu para entrega" })).toBe("andamento")
        expect(etapaDoPedido({ situacao: "Entregue" })).toBe("finalizado")
        expect(etapaDoPedido({ situacao: "Aguardando" })).toBe("pendente")
    })
})
