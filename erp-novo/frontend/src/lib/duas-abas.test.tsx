import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { act, render, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { AuthProvider, useAuth } from './auth'
import { TOKEN_KEY } from './api'

/**
 * F9-08 — duas abas do mesmo navegador.
 *
 * ## O cenário
 *
 * O token vive em `localStorage`, que é **compartilhado entre abas**. Cada aba,
 * porém, tem o seu próprio cache do React Query, em memória.
 *
 * Sem escutar o evento `storage`, as duas visões divergem em silêncio:
 *
 *  - **logout na aba A** → a aba B segue exibindo carteira, financeiro e
 *    pedidos da sessão encerrada. O operador acha que está logado, e a próxima
 *    requisição falha sem explicar por quê;
 *  - **login de outra pessoa na aba A** → a aba B passa a mandar o token da
 *    revenda B enquanto a tela ainda mostra o dado cacheado da revenda A. Dado
 *    de um concorrente na tela, credencial de outro nas requisições.
 *
 * ## Por que dá para testar sem navegador
 *
 * Eu tinha declarado este cenário como "exige Playwright" e estava **errado**.
 * O mecanismo é o evento `storage`, e o jsdom o despacha — o que muda entre abas
 * é só quem escreveu. `StorageEvent` construído à mão reproduz exatamente o que
 * o navegador entrega à outra aba.
 *
 * O que realmente exigiria navegador é a parte visual (duas janelas, foco,
 * render concorrente). O comportamento que importa — o cache morrer quando a
 * identidade muda em outro lugar — é este.
 */
describe('duas abas: a identidade muda em outro lugar', () => {
  beforeEach(() => {
    localStorage.clear()
    sessionStorage.clear()
  })

  /**
   * Monta o provider de verdade.
   *
   * Testar uma cópia da lógica provaria que a cópia funciona. O que precisa
   * valer é que ESTE componente registra o listener — se alguém remover o
   * `useEffect`, o teste tem de reprovar.
   */
  function abaMontada() {
    const qc = new QueryClient({
      defaultOptions: { queries: { retry: false } },
    })

    // O `AuthProvider` dispara um GET /me ao montar. Sem rede no jsdom ele
    // falha e devolve `null`, que é o caminho já tratado (`catch` → não logado).
    qc.setQueryData(['me'], { id: 1, empresa_id: 10 })
    qc.setQueryData(['clientes'], [{ id: 1, nome: 'Cliente da revenda A' }])
    qc.setQueryData(['financeiro', 'titulos'], [{ id: 7, valor: 250 }])

    render(
      <QueryClientProvider client={qc}>
        <AuthProvider>
          <div>conteudo</div>
        </AuthProvider>
      </QueryClientProvider>,
    )

    return qc
  }

  /** O que o navegador entrega à OUTRA aba quando esta escreve no storage. */
  function outraAbaMexeuNoToken(chave: string | null, valorNovo: string | null) {
    window.dispatchEvent(
      new StorageEvent('storage', {
        key: chave,
        newValue: valorNovo,
        storageArea: localStorage,
      }),
    )
  }

  it('logout em outra aba derruba o cache desta', async () => {
    const qc = abaMontada()

    expect(qc.getQueryData(['clientes'])).toBeDefined()

    outraAbaMexeuNoToken(TOKEN_KEY, null)

    await waitFor(() => {
      expect(qc.getQueryData(['clientes'])).toBeUndefined()
      expect(qc.getQueryData(['financeiro', 'titulos'])).toBeUndefined()
    })
  })

  /**
   * O caso grave: outra pessoa entra na outra aba.
   *
   * Sem isto, esta aba renderiza o dado da revenda A com o token da revenda B —
   * e a tela nem parece errada, porque o cabeçalho já mostra o nome novo.
   */
  it('login de outra identidade em outra aba derruba o cache desta', async () => {
    const qc = abaMontada()

    outraAbaMexeuNoToken(TOKEN_KEY, 'token-da-revenda-B')

    await waitFor(() => {
      expect(qc.getQueryData(['clientes'])).toBeUndefined()
      expect(qc.getQueryData(['financeiro', 'titulos'])).toBeUndefined()
    })
  })

  /**
   * `localStorage.clear()` chega como `key: null`.
   *
   * É o que acontece quando outra aba limpa tudo. O token pode ter ido junto,
   * então trata-se como troca de identidade — o contrário (ignorar) deixaria a
   * aba com cache de uma sessão que já não existe.
   */
  it('limpeza total em outra aba derruba o cache desta', async () => {
    const qc = abaMontada()

    outraAbaMexeuNoToken(null, null)

    await waitFor(() => {
      expect(qc.getQueryData(['clientes'])).toBeUndefined()
    })
  })

  /**
   * Outra chave não pode derrubar o cache.
   *
   * Se qualquer escrita no storage limpasse tudo, uma preferência de UI salva em
   * outra aba faria o operador perder a tela no meio de um lançamento. O
   * guardião precisa ser específico, senão vira ruído — e ruído se desliga.
   */
  it('mexer em OUTRA chave não derruba nada', async () => {
    const qc = abaMontada()

    outraAbaMexeuNoToken('erpnovo_tema', 'escuro')

    // Espera um ciclo para o efeito rodar, se fosse rodar.
    await new Promise((r) => setTimeout(r, 20))

    expect(qc.getQueryData(['clientes'])).toBeDefined()
    expect(qc.getQueryData(['financeiro', 'titulos'])).toBeDefined()
  })

  /**
   * O logout REAL do provider limpa o cache — não uma cópia da lógica.
   *
   * `cache-isolamento.test.ts` já cobre este cenário, mas simulando: ele chama
   * `qc.cancelQueries()` e `qc.clear()` à mão, sob o comentário "o que o logout
   * faz hoje". Isso prova que a **cópia** funciona; se alguém mudar o
   * `onSuccess` do `logoutMut`, o teste segue verde.
   *
   * É a mesma armadilha que apareceu no backend nesta rodada — teste que passa
   * pela verificação errada. Aqui o `logout()` do contexto é chamado de
   * verdade.
   *
   * ## E foi assim que achei um defeito
   *
   * Sem rede (o caso do jsdom), o POST /logout falha. O `setToken(null)` está num
   * `finally` e rodava; o `qc.clear()` estava em `onSuccess` e **não rodava**.
   *
   * Em produção: o operador clica em "Sair" com a rede caindo, o token some e a
   * carteira continua na tela. Ele acha que saiu. Corrigido para `onSettled` —
   * falha de rede não pode manter dado de sessão encerrada visível, porque a
   * decisão de sair é do operador, não do servidor.
   *
   * Este teste roda justamente no cenário de falha, então guarda a correção.
   */
  it('o logout do provider limpa o cache inteiro', async () => {
    const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })

    qc.setQueryData(['me'], { id: 1, empresa_id: 10 })
    qc.setQueryData(['clientes'], [{ id: 1, nome: 'Cliente da revenda A' }])
    qc.setQueryData(['pedidos'], [{ id: 99, valor: 250 }])

    let sair: (() => Promise<void>) | null = null

    function Sonda() {
      sair = useAuth().logout

      return null
    }

    render(
      <QueryClientProvider client={qc}>
        <AuthProvider>
          <Sonda />
        </AuthProvider>
      </QueryClientProvider>,
    )

    await waitFor(() => expect(sair).not.toBeNull())

    // O POST /logout falha sem rede no jsdom; o `finally` do provider apaga o
    // token e o `onSuccess` limpa o cache. É o caminho real.
    await act(async () => {
      try {
        await sair!()
      } catch {
        // A falha de rede não interessa aqui — o que se mede é o cache.
      }
    })

    await waitFor(() => {
      expect(qc.getQueryData(['clientes'])).toBeUndefined()
      expect(qc.getQueryData(['pedidos'])).toBeUndefined()
    })
  })

  /**
   * O listener some quando o provider desmonta.
   *
   * Listener órfão apontando para um `QueryClient` morto vaza memória e, pior,
   * pode limpar o cache de um provider que já não é o da tela.
   */
  it('o listener e removido ao desmontar', () => {
    const remover = vi.spyOn(window, 'removeEventListener')

    const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })

    const { unmount } = render(
      <QueryClientProvider client={qc}>
        <AuthProvider>
          <div>conteudo</div>
        </AuthProvider>
      </QueryClientProvider>,
    )

    unmount()

    expect(remover).toHaveBeenCalledWith('storage', expect.any(Function))

    remover.mockRestore()
  })
})
