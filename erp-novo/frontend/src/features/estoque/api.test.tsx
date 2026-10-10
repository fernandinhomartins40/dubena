import type { ReactNode } from 'react'
import { renderHook } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { it, expect, vi, afterEach, describe } from 'vitest'
import { api } from '@/lib/api'
import { useLancamentoManual, useCriarRequisicao, useAtenderRequisicao, useCriarFisico, useFechar } from './api'

/**
 * O contrato de escrita do estoque, do lado da SPA.
 *
 * Estas telas enviavam os nomes do ERP antigo (`movimentacao`, `observacoes`,
 * `datacompetencia`, `quantidadefisica`, `datahorafechamento`) e o servidor
 * respondia 422 a todas — sem teste nenhum acusar, porque os que existiam só
 * cobriam a LEITURA. Os nomes abaixo são os que `EstoqueController` valida.
 */
afterEach(() => vi.restoreAllMocks())

function wrapper({ children }: { children: ReactNode }) {
  return <QueryClientProvider client={new QueryClient()}>{children}</QueryClientProvider>
}
const post = () => vi.spyOn(api, 'post').mockResolvedValue({ data: { data: {} } })

describe('lançamento manual', () => {
  const base = { chave: 'chave-0001', setor_id: 3, produto_id: 9, quantidade: 5, motivo: 'Sobra na conferência' }

  it('entrada vai para /estoque/entrada com a chave no cabeçalho', async () => {
    const spy = post()
    const { result } = renderHook(() => useLancamentoManual(), { wrapper })
    await result.current.mutateAsync({ ...base, movimentacao: 'ENTRADA' })
    expect(spy).toHaveBeenCalledWith(
      '/estoque/entrada',
      { setor_id: 3, produto_id: 9, quantidade: 5, motivo: 'Sobra na conferência' },
      { headers: { 'Idempotency-Key': 'chave-0001' } },
    )
  })

  it('saída vai para /estoque/saida, nunca para /estoque/acerto', async () => {
    const spy = post()
    const { result } = renderHook(() => useLancamentoManual(), { wrapper })
    await result.current.mutateAsync({ ...base, movimentacao: 'SAIDA' })
    expect(spy.mock.calls[0][0]).toBe('/estoque/saida')
    // `/estoque/acerto` ajusta para a quantidade CONTADA: mandar uma saída de 5
    // para lá zeraria o saldo até 5.
    expect(spy.mock.calls.some(([url]) => url === '/estoque/acerto')).toBe(false)
  })

  it('o reenvio do mesmo formulário repete a mesma chave', async () => {
    const spy = post()
    const { result } = renderHook(() => useLancamentoManual(), { wrapper })
    await result.current.mutateAsync({ ...base, movimentacao: 'ENTRADA' })
    await result.current.mutateAsync({ ...base, movimentacao: 'ENTRADA' })
    expect(spy.mock.calls.map(([, , cfg]) => (cfg as any).headers['Idempotency-Key'])).toEqual(['chave-0001', 'chave-0001'])
  })
})

it('requisição envia os campos que o servidor valida', async () => {
  const spy = post()
  const { result } = renderHook(() => useCriarRequisicao(), { wrapper })
  const corpo = { setor_origem_id: null, setor_destino_id: 4, produto_id: 9, quantidade: 2, observacao: null, atender: false }
  await result.current.mutateAsync(corpo)
  expect(spy).toHaveBeenCalledWith('/estoque/requisicoes', corpo)
})

it('atender requisição usa a rota do registro e leva a origem escolhida', async () => {
  const spy = post()
  const { result } = renderHook(() => useAtenderRequisicao(), { wrapper })
  await result.current.mutateAsync({ id: 17, setor_origem_id: 3 })
  expect(spy).toHaveBeenCalledWith('/estoque/requisicoes/17/atender', { setor_origem_id: 3 })
})

it('estoque físico envia setor, data e quantidade_contada por item', async () => {
  const spy = post()
  const { result } = renderHook(() => useCriarFisico(), { wrapper })
  const corpo = { setor_id: 3, data: '2026-10-10', itens: [{ produto_id: 9, quantidade_contada: 80 }] }
  await result.current.mutateAsync(corpo)
  expect(spy).toHaveBeenCalledWith('/estoque/fisico', corpo)
})

it('fechamento envia setor, produto e data_fechamento', async () => {
  const spy = post()
  const { result } = renderHook(() => useFechar(), { wrapper })
  const corpo = { setor_id: 3, produto_id: 9, data_fechamento: '2026-10-10' }
  await result.current.mutateAsync(corpo)
  expect(spy).toHaveBeenCalledWith('/estoque/fechamentos', corpo)
})
