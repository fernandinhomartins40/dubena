import type { PedidoEntrega } from "@/types/types"

export type EtapaEntrega = "pendente" | "andamento" | "finalizado"

/**
 * Em que etapa o pedido está para o entregador.
 *
 * Usa o efeito e o papel declarados na situação (PIE P2-C-04). Classificar pelo
 * texto mandava "Saiu para entrega" para o Histórico — contém "entreg" — e
 * quebra de vez na revenda que chama a situação de outro nome.
 *
 * O texto só é consultado quando o servidor ainda não manda efeito/papel; e,
 * nesse caso, "em rota" é testado ANTES de "finalizado" pelo mesmo motivo.
 */
export function etapaDoPedido(p: Pick<PedidoEntrega, "situacao" | "situacao_efeito" | "situacao_papel">): EtapaEntrega {
    if (p.situacao_efeito) {
        if (p.situacao_efeito === "CONCLUIDO" || p.situacao_efeito === "CANCELADO") return "finalizado"
        return p.situacao_papel === "EM_ROTA" ? "andamento" : "pendente"
    }
    const sit = (p.situacao ?? "").toLowerCase()
    if (sit.includes("saiu") || sit.includes("caminho") || sit.includes("rota") || sit.includes("atend")) return "andamento"
    if (sit.includes("entreg") || sit.includes("conclu") || sit.includes("cancel")) return "finalizado"
    return "pendente"
}
