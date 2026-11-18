/**
 * Tentativa de venda em campo (P2-C-05, parte missão).
 *
 * O `missao/venda` passa pelo middleware `idempotente`, mas o app mandava a
 * venda SEM chave: o 1º envio que estourava o timeout com a foto já tinha
 * gravado o pedido, o entregador tocava "Confirmar" de novo e saía uma 2ª venda.
 *
 * A chave sozinha não basta: o servidor compara o conteúdo (mesma chave com
 * outro payload é 409), e o GPS lido a cada toque mudaria latitude/longitude.
 * Por isso a tentativa congela chave E posição enquanto cliente, itens e foto
 * forem os mesmos; mudou o que se vende, é outra venda e nasce outra chave.
 */
export interface Coordenadas {
    latitude: number
    longitude: number
}

export interface TentativaVenda {
    assinatura: string
    chave: string
    coords: Coordenadas | null
}

export const assinaturaDaVenda = (
    clienteId: number | null,
    itens: { produto_id: number; quantidade: number }[],
    fotoUri: string | null | undefined,
): string => JSON.stringify([clienteId, itens, fotoUri ?? null])

/** Reaproveita a tentativa anterior se for a mesma venda; senão abre outra. */
export const tentativaPara = async (
    anterior: TentativaVenda | null,
    assinatura: string,
    gerarChave: () => string,
    lerPosicao: () => Promise<Coordenadas | null>,
): Promise<TentativaVenda> =>
    anterior?.assinatura === assinatura
        ? anterior
        : { assinatura, chave: gerarChave(), coords: await lerPosicao() }
