import Http from "@/helpers/http"
import { enviarOuEnfileirar } from "@/helpers/envioResiliente"

/**
 * VendaService (F4/F5) — solicitação à Central, extrato e mercadoria em poder.
 *
 * O franqueado **não fecha o pedido**: ele solicita, e a central de vendas cria,
 * aprova o desconto e fatura. Este service é o lado do campo desse fluxo.
 *
 * O preço vem do servidor: o app manda `produto_id` e `quantidade`, nunca valor.
 * O que o vendedor pode pedir é DESCONTO — que passa pela Central.
 */

export interface ItemSolicitacao {
    produto_id: number
    quantidade: number
}

export interface Solicitacao {
    id: number
    cliente?: { id: number; nome: string | null } | null
    itens: Array<{ produto_id: number; quantidade: number; preco_unitario: number }>
    desconto_solicitado: number
    desconto_aprovado: number | null
    justificativa: string | null
    situacao: "pendente" | "aprovada" | "recusada" | "cancelada"
    motivo_decisao: string | null
    pedido?: { id: number } | null
    created_at: string
}

export interface ExtratoPedido {
    pedido_id: number
    datahora: string
    itens: number
    valor_venda: number
    comissao: number
}

export interface Extrato {
    periodo: { inicio: string; fim: string }
    total: { percentual: number; repasse: number; total: number }
    pedidos: ExtratoPedido[]
}

export interface EstoqueEmPoder {
    modo_estoque: "consignacao" | "compra" | null
    itens: Array<{ produto_id: number; produto: string; quantidade: number }>
}

const Solicitacoes = (): Promise<{ data: Solicitacao[] }> =>
    Http.PrepareRequest("app/v1/entregador/solicitacoes", "GET")

/**
 * Cria a solicitação. Passa pela fila offline: o vendedor está na porta do
 * cliente e não pode depender de sinal para registrar o pedido.
 */
const Solicitar = (dados: {
    cliente_id: number
    itens: ItemSolicitacao[]
    desconto_solicitado?: number
    justificativa?: string
    condicaopagamento_id?: number | null
    setor_id?: number | null
}) => enviarOuEnfileirar<{ data: Solicitacao }>("app/v1/entregador/solicitacoes", "POST", dados)

const Cancelar = (id: number) =>
    enviarOuEnfileirar(`app/v1/entregador/solicitacoes/${id}/cancelar`, "POST")

/** Quanto ganhei no período (default: mês corrente). */
const MeuExtrato = (periodo?: { inicio?: string; fim?: string }): Promise<{ data: Extrato }> => {
    const q = new URLSearchParams()
    if (periodo?.inicio) q.set("inicio", periodo.inicio)
    if (periodo?.fim) q.set("fim", periodo.fim)
    const sufixo = q.toString() ? `?${q.toString()}` : ""

    return Http.PrepareRequest(`app/v1/entregador/extrato${sufixo}`, "GET")
}

/** O que estou carregando — a conferência antes de sair e durante a rota. */
const MeuEstoque = (): Promise<{ data: EstoqueEmPoder }> =>
    Http.PrepareRequest("app/v1/entregador/estoque", "GET")

/** Linhas do comprovante para a impressora térmica (o app só transmite). */
const CupomPedido = (pedidoId: number, largura?: number): Promise<{ data: { largura: number; linhas: string[] } }> =>
    Http.PrepareRequest(
        `app/v1/entregador/pedidos/${pedidoId}/cupom${largura ? `?largura=${largura}` : ""}`,
        "GET",
    )

const VendaService = {
    Solicitacoes,
    Solicitar,
    Cancelar,
    MeuExtrato,
    MeuEstoque,
    CupomPedido,
}

export default VendaService
