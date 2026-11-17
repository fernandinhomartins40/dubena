/**
 * P2-B-08 — para onde o toque numa notificação leva.
 *
 * O servidor manda `{pedido_id, acao: 'orderUpdated', efeito}`
 * (`PedidoMobileService::notificarStatus`), e o app só sabia tratar `imageurl`
 * — dado de um banner que não está montado em tela nenhuma. "Seu pedido saiu
 * para entrega" não abria o pedido.
 *
 * Função pura: o hook só aplica o destino. Pedido aberto vai ao acompanhamento;
 * entregue ou cancelado vai ao histórico (o acompanhamento de pedido encerrado
 * devolveria o usuário à Home na hora, sem mostrar nada).
 */
export type DestinoNotificacao =
    | { tipo: "pedido"; pedidoId: number; aberto: boolean }
    | { tipo: "imagem"; imageUrl: string }
    | null

export function destinoDaNotificacao(data: Record<string, unknown> | null | undefined): DestinoNotificacao {
    if (!data) return null

    if (data.acao === "orderUpdated") {
        const id = Number(data.pedido_id)
        if (!Number.isInteger(id) || id <= 0) return null
        const efeito = typeof data.efeito === "string" ? data.efeito : ""
        // Sem efeito (servidor anterior ao campo): trata como aberto — o
        // acompanhamento sabe se virar para a Home se o pedido já acabou.
        return { tipo: "pedido", pedidoId: id, aberto: efeito === "" || efeito === "PENDENTE" }
    }

    if (data.imageurl) return { tipo: "imagem", imageUrl: String(data.imageurl) }

    return null
}
