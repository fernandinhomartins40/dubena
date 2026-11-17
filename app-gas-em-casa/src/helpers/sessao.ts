/**
 * P2-B-17 — fim de sessão limpa o que era do usuário anterior.
 *
 * O cache do React Query é de módulo e sobrevivia ao "Sair": num aparelho
 * compartilhado, o perfil, os endereços e o histórico do usuário anterior
 * apareciam até o refetch. O WebSocket guardava o Bearer da primeira conexão
 * (`helpers/realtime.ts`), e o tempo real falhava no login seguinte.
 *
 * Em vez de lembrar de limpar em cada porta de saída (botão Sair, troca de
 * revenda, 401 que desloga — este último é do núcleo de sessão), vigia a
 * TRANSIÇÃO do token: tinha token e deixou de ter → limpa. Qualquer caminho que
 * encerre a sessão, inclusive um que ainda não existe, passa por aqui.
 */
interface ComToken {
    apiToken?: string | null
}

interface StoreObservavel<S extends ComToken> {
    subscribe: (fn: (estado: S, anterior: S) => void) => () => void
}

export function vigiarFimDeSessao<S extends ComToken>(store: StoreObservavel<S>, limpar: () => void): () => void {
    return store.subscribe((estado, anterior) => {
        if (anterior?.apiToken && !estado?.apiToken) limpar()
    })
}
