import { QueryClient } from '@tanstack/react-query'
import { describe, expect, it } from 'vitest'

/**
 * F9-03 — o cache não sobrevive à troca de identidade.
 *
 * ## O defeito
 *
 * O logout zerava apenas `['me']`:
 *
 * ```ts
 * onSuccess: () => qc.setQueryData(['me'], null)
 * ```
 *
 * Todo o resto do cache permanecia — clientes, pedidos, financeiro da sessão
 * anterior seguiam em memória. Numa revenda só isso passava despercebido: o
 * próximo login era a mesma pessoa.
 *
 * Num SaaS é vazamento entre concorrentes. A sai, B entra na mesma máquina, e
 * vê a carteira de A na tela até o refetch chegar — porque o React Query
 * renderiza o dado cacheado imediatamente, por desenho.
 *
 * ## Por que `cancelQueries` antes de limpar
 *
 * Uma requisição **em voo**, disparada com o token de A, chega depois do
 * `clear()` e repovoa o cache já sob a sessão de B. Cancelar antes fecha essa
 * janela — e é a parte que se esquece, porque em teste manual a rede é rápida
 * demais para o problema aparecer.
 *
 * ## `removeQueries`, não `invalidateQueries`, na troca de empresa
 *
 * `invalidateQueries` marca como obsoleto e refaz, mas **o dado antigo continua
 * no cache até a resposta chegar**. Numa rede de filiais isso é um susto ("cadê
 * meus pedidos?"); entre revendas é a carteira da outra empresa na tela com o
 * rótulo da nova no cabeçalho.
 */
describe('isolamento de cache entre identidades', () => {
  /** Simula o cache de uma sessão com dados de várias telas. */
  function cacheDaSessaoA(): QueryClient {
    const qc = new QueryClient()

    qc.setQueryData(['me'], { id: 1, empresa_id: 10 })
    qc.setQueryData(['clientes'], [{ id: 1, nome: 'Cliente da revenda A' }])
    qc.setQueryData(['pedidos'], [{ id: 99, valor: 250 }])
    qc.setQueryData(['financeiro', 'titulos'], [{ id: 7 }])

    return qc
  }

  it('o logout apaga o cache INTEIRO, não só o usuário', async () => {
    const qc = cacheDaSessaoA()

    // O que o logout faz hoje.
    await qc.cancelQueries()
    qc.clear()

    expect(qc.getQueryData(['me'])).toBeUndefined()
    expect(qc.getQueryData(['clientes'])).toBeUndefined()
    expect(qc.getQueryData(['pedidos'])).toBeUndefined()
    expect(qc.getQueryData(['financeiro', 'titulos'])).toBeUndefined()
  })

  it('zerar apenas o usuário deixaria o resto para trás', () => {
    const qc = cacheDaSessaoA()

    // O comportamento ANTIGO, aqui só para deixar o defeito explícito.
    qc.setQueryData(['me'], null)

    expect(qc.getQueryData(['me'])).toBeNull()
    expect(qc.getQueryData(['clientes'])).toBeDefined()
    expect(qc.getQueryData(['pedidos'])).toBeDefined()
  })

  it('a troca de empresa REMOVE, não apenas invalida', async () => {
    const qc = cacheDaSessaoA()

    // O que o EmpresaSwitcher faz hoje.
    await qc.cancelQueries()
    qc.removeQueries()

    expect(qc.getQueryData(['clientes'])).toBeUndefined()
    expect(qc.getQueryData(['pedidos'])).toBeUndefined()
  })

  it('invalidar sozinho deixa o dado antigo visível até o refetch', async () => {
    const qc = cacheDaSessaoA()

    await qc.invalidateQueries()

    // É esta a janela que a correção fecha: o dado da empresa anterior continua
    // no cache, e as telas o renderizam enquanto a resposta nova não chega.
    expect(qc.getQueryData(['clientes'])).toBeDefined()
  })

  it('uma requisição em voo não repovoa o cache depois da limpeza', async () => {
    const qc = new QueryClient({
      defaultOptions: { queries: { retry: false, gcTime: 0 } },
    })

    let resolver: ((v: unknown) => void) | undefined
    const emVoo = new Promise((r) => { resolver = r })

    // Query lenta, como uma listagem grande disparada antes do logout.
    const busca = qc.fetchQuery({
      queryKey: ['clientes'],
      queryFn: () => emVoo,
    }).catch(() => undefined)

    await qc.cancelQueries()
    qc.clear()

    // A resposta chega DEPOIS — com o dado da sessão anterior.
    resolver?.([{ id: 1, nome: 'Cliente da revenda A' }])
    await busca

    expect(qc.getQueryData(['clientes'])).toBeUndefined()
  })
})

/**
 * F9-03 — a fronteira entre plataforma e tenant no cache compartilhado.
 *
 * A aplicacao usa UM QueryClient. A separacao entre os dois mundos e o prefixo
 * `['sa']` nas queries de plataforma — o que funciona, e so enquanto todas o
 * carregarem.
 *
 * O logout do SuperAdmin remove por esse prefixo, e nao `clear()`, porque
 * limpar tudo derrubaria a sessao de tenant que pode estar aberta na mesma aba.
 */
describe('fronteira entre plataforma e tenant no cache', () => {
  it('o logout do SuperAdmin nao derruba o cache do tenant', async () => {
    const qc = new QueryClient()

    qc.setQueryData(['sa', 'me'], { id: 1 })
    qc.setQueryData(['sa', 'empresas'], [{ id: 10 }, { id: 20 }])
    qc.setQueryData(['clientes'], [{ id: 1, nome: 'Cliente do tenant' }])

    await qc.cancelQueries({ queryKey: ['sa'] })
    qc.removeQueries({ queryKey: ['sa'] })

    expect(qc.getQueryData(['sa', 'me'])).toBeUndefined()
    expect(qc.getQueryData(['sa', 'empresas'])).toBeUndefined()
    expect(qc.getQueryData(['clientes'])).toBeDefined()
  })

  it('toda query de plataforma carrega o prefixo que a separa', () => {
    // O prefixo e o que torna a remocao seletiva possivel: uma query de
    // plataforma sem ele sobreviveria ao logout do SuperAdmin, com dado de
    // TODAS as revendas em memoria.
    //
    // `import.meta.glob` com `eager` e `?raw` le o arquivo em tempo de build do
    // Vite — deterministico, e sem depender de @types/node. A assercao de que
    // ACHOU o arquivo vem primeiro: um glob que nao casa nada devolveria lista
    // vazia e o teste passaria sem verificar coisa alguma, que e exatamente o
    // guardiao que nao guarda.
    const arquivos = import.meta.glob('../features/superadmin/api.ts', {
      eager: true, query: '?raw', import: 'default',
    }) as Record<string, string>

    const fonte = Object.values(arquivos)[0] ?? ''

    expect(fonte.length).toBeGreaterThan(500)

    const chaves = fonte.match(/queryKey: \[[^\]]*\]/g) ?? []

    expect(chaves.length).toBeGreaterThan(5)
    for (const chave of chaves) {
      expect(chave).toContain("'sa'")
    }
  })
})
