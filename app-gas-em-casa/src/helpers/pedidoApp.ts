import type { ClienteEndereco, ClienteEnderecoApi, CondicaoPagamento } from "@/types/types"

/**
 * Regras do fluxo de compra que a tela só desenha (PIE R3-E).
 *
 * Ficam fora dos componentes de propósito: o app não tem renderização em teste
 * (sem react-native-web), então o que decide "pode pagar", "pode cancelar",
 * "qual chave de idempotência" e "o que o pedido manda" precisa ser função pura
 * para que o vitest prove o comportamento. As telas importam daqui.
 */

export interface ItemPedido {
    produto_id: number
    quantidade: number
}

/** O corpo de `POST app/v1/pedidos` — contrato em `contrato/app-v1.json`. */
export interface PayloadPedido {
    endereco_id: number | null
    condicaopagamento_id: number
    codigo_cupom: string | null
    gasdopovo: boolean
    itens: ItemPedido[]
}

export function montarPayloadPedido(e: {
    enderecoId: number | null
    condicaoId: number
    cupom: string | null
    gasdopovo: boolean
    itens: ItemPedido[]
}): PayloadPedido {
    return {
        endereco_id: e.enderecoId,
        condicaopagamento_id: e.condicaoId,
        codigo_cupom: e.cupom,
        gasdopovo: e.gasdopovo,
        itens: e.itens,
    }
}

export interface ChavePedido {
    chave: string
    assinatura: string
}

/**
 * R2-13: a chave de idempotência acompanha o CONTEÚDO do pedido.
 *
 * Mesmo carrinho = mesma chave: o segundo toque em "Finalizar" depois de um
 * timeout devolve o pedido que já nasceu (o servidor repete a resposta) em vez
 * de criar outro. Carrinho diferente = chave nova — reaproveitar a antiga com
 * outro conteúdo dá 409 no servidor, de propósito.
 */
export function chaveDoPedido(
    payload: PayloadPedido,
    anterior: ChavePedido | null,
    gerar: () => string,
): ChavePedido {
    const assinatura = JSON.stringify(payload)
    if (anterior && anterior.assinatura === assinatura) return anterior
    return { chave: gerar(), assinatura }
}

/** Resíduo de R2-02: só condição PIX abre a tela do PIX. */
export function deveAbrirPix(condicao: Pick<CondicaoPagamento, "pix_online"> | null | undefined): boolean {
    return condicao?.pix_online === true
}

/** O que a tela de acompanhamento recebe de `GET app/v1/pedidos/{id}`. */
export interface PedidoAcompanhamento {
    id: number
    situacao?: string | null
    efeito?: string | null
    situacao_papel?: string | null
    pagamento?: { pix: boolean; pago: boolean } | null
}

export interface AcoesDoPedido {
    aberto: boolean
    emRota: boolean
    podePagarPix: boolean
    podeCancelar: boolean
}

/**
 * P2-B-04: o pedido aberto oferece pagar o PIX (quando a condição é PIX e ele
 * ainda não foi pago) e cancelar — a regra de cancelamento é a do servidor
 * (`PedidoMobileService::cancelar`: só enquanto o efeito é PENDENTE).
 */
export function acoesDoPedido(order: PedidoAcompanhamento | null | undefined): AcoesDoPedido {
    if (!order) return { aberto: false, emRota: false, podePagarPix: false, podeCancelar: false }

    const aberto = order.efeito === "PENDENTE"
    // A marca declarada manda. O texto só vale quando a API é anterior ao campo
    // (app novo falando com servidor antigo) — e é exatamente a heurística que
    // o P2-C-04 mostrou ser frágil, por isso fica só como compatibilidade.
    const emRota =
        aberto &&
        (order.situacao_papel !== undefined
            ? order.situacao_papel === "EM_ROTA"
            : /saiu|rota|caminho/i.test(order.situacao ?? ""))

    return {
        aberto,
        emRota,
        podePagarPix: aberto && order.pagamento?.pix === true && order.pagamento?.pago !== true,
        podeCancelar: aberto,
    }
}

/** Texto da confirmação de cancelamento: pedido pago vira estorno manual (regra do dono, 29/10). */
export function textoConfirmaCancelamento(order: PedidoAcompanhamento | null | undefined): string {
    if (order?.pagamento?.pago) {
        return "Seu pagamento já foi confirmado. Ao cancelar, a revenda fará o estorno do valor. Deseja cancelar?"
    }
    return "Tem certeza que deseja cancelar este pedido?"
}

const doisDigitos = (n: number) => String(n).padStart(2, "0")

/**
 * P2-B-04: a tela dizia "o pedido será cancelado em 5 minutos", e nenhum código
 * cancela pedido por PIX vencido (`PixService::expirarVencidas` só marca a
 * cobrança). O texto agora diz o que é verdade: até quando o CÓDIGO vale e que
 * dá para gerar outro.
 */
export function validadePix(expiraEm: string | null | undefined, agora: number): { expirado: boolean; texto: string } {
    if (!expiraEm) return { expirado: false, texto: "" }
    const limite = new Date(expiraEm)
    if (Number.isNaN(limite.getTime())) return { expirado: false, texto: "" }
    if (limite.getTime() <= agora) {
        return { expirado: true, texto: "Este código PIX expirou. Gere um novo código para pagar." }
    }
    const hora = `${doisDigitos(limite.getHours())}:${doisDigitos(limite.getMinutes())}`
    return {
        expirado: false,
        texto: `Este código vale até ${hora}. Se expirar, você pode gerar um novo aqui ou no acompanhamento do pedido.`,
    }
}

/** Endereço que o checkout mostra em "Entregar em": o escolhido, senão o favorito, senão o primeiro. */
export function enderecoDeEntrega(
    enderecos: ClienteEnderecoApi[] | null | undefined,
    escolhidoId: number | null | undefined,
): ClienteEnderecoApi | null {
    const lista = enderecos ?? []
    return (
        lista.find((e) => e.id === escolhidoId) ??
        lista.find((e) => e.favorito) ??
        lista[0] ??
        null
    )
}

/** "Rua X, 100 - Bairro" — sem pedaços vazios. */
export function rotuloEndereco(e: Pick<ClienteEnderecoApi, "endereco" | "numero" | "bairro"> | ClienteEndereco | null | undefined): string {
    if (!e || !e.endereco) return ""
    const rua = [e.endereco, e.numero].filter(Boolean).join(", ")
    const bairro = "bairro" in e ? (e as ClienteEnderecoApi).bairro : null
    return bairro ? `${rua} - ${bairro}` : rua
}

/** P2-B-13: prazo que a revenda configurou (`tempo_entrega_min`), quando existe. */
export function textoPrazoEntrega(min: number | null | undefined): string {
    if (min == null || !Number.isFinite(min) || min <= 0) return ""
    return `Entrega em cerca de ${Math.round(min)} min`
}

/**
 * P2-B-13: Gás do Povo só aparece quando a revenda ligou o programa E o
 * cadastro do cliente está marcado como beneficiário — as duas condições que o
 * servidor exige para aceitar o preço.
 */
export function mostraGasDoPovo(gaspovoAtivo: boolean | null | undefined, clienteBeneficiario: boolean | null | undefined): boolean {
    return gaspovoAtivo === true && clienteBeneficiario === true
}

/** P2-B-15: "Avaliar" só em pedido concluído e ainda não avaliado (o servidor recusa a repetição). */
export function podeAvaliar(h: { efeito?: string | null; avaliado?: boolean } | null | undefined): boolean {
    return !!h && h.efeito === "CONCLUIDO" && h.avaliado !== true
}
