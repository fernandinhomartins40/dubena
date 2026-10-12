import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { LancamentosTab } from './LancamentosTab'

globalThis.ResizeObserver ??= class { observe() {} unobserve() {} disconnect() {} } as never
vi.mock('@/lib/auth', () => ({ useAuth: () => ({ can: () => true }) }))

const aberta = { id: 9001, numero: 1, datavencimento: '2026-10-11', valor: 120, valorefetivado: 0, baixado: 0, datahorabaixa: null, pagarreceber: 'R', documento: 'BOL-1', descricao: 'Pedido #456', cliente: 'Joana' }
const resumo = { receber_aberto: 1059876.57, pagar_aberto: 0, receber_mes: 3200, pagar_mes: 0 }

beforeEach(() => localStorage.clear())
afterEach(() => vi.restoreAllMocks())

function montar() {
  vi.spyOn(api, 'get').mockImplementation(async (url: string) => {
    if (url === '/financeiro/lancamentos/resumo') return { data: { data: resumo } } as never
    if (url === '/financeiro/lancamentos') return { data: { data: [aberta], meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 } } } as never
    if (url === '/lookups/contas') return { data: { data: [{ id: 3, label: 'Caixa da loja' }] } } as never
    return { data: { data: [] } } as never
  })
  const post = vi.spyOn(api, 'post').mockResolvedValue({ data: { data: { id: 1 } } })
  render(<MemoryRouter><QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><LancamentosTab /></QueryClientProvider></MemoryRouter>)
  return post
}

/**
 * Não existia tela para receber uma conta; a conta aberta aparecia com R$ 0,00;
 * e os cartões de resumo mostravam "—" sempre.
 */
it('mostra o valor e os totais e recebe uma conta em três passos', async () => {
  const post = montar()
  const user = userEvent.setup()

  expect(await screen.findByText('R$ 120,00')).toBeInTheDocument()
  expect(screen.getByText(/1\.059\.876,57/)).toBeInTheDocument()
  expect(screen.getByText('Recebido no mês')).toBeInTheDocument()

  await user.click(screen.getByRole('button', { name: 'Receber' }))                    // 1. a conta
  const dialogo = await screen.findByRole('dialog')
  await user.click(within(dialogo).getByRole('button', { name: /Entrou em qual conta/ })) // 2. onde entrou
  await user.click(await screen.findByRole('button', { name: 'Caixa da loja' }))
  await user.click(within(dialogo).getByRole('button', { name: /Receber R\$\s120,00/ }))   // 3. confirmar

  await waitFor(() => expect(post).toHaveBeenCalledWith('/caixa/3/baixar', { parcela_id: 9001, juros: 0, multa: 0, desconto: 0 }))
})
