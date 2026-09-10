import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { it, expect, vi, afterEach } from 'vitest'
import { api } from '@/lib/api'
import { ChequesTab } from './FinanceiroExtraTabs'
vi.mock('@/lib/auth', () => ({ useAuth: () => ({ user: { id: 1, empresa_id: 1 }, can: () => true }) }))
afterEach(() => vi.restoreAllMocks())
function mount() { render(<MemoryRouter><QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })}><ChequesTab /></QueryClientProvider></MemoryRouter>) }
it('envia somente campos aceitos pelo cadastro canônico sem situação fictícia', async () => {
  vi.spyOn(api, 'get').mockResolvedValue({ data: { data: [] } })
  const post = vi.spyOn(api, 'post').mockResolvedValue({ data: { data: { id: 1 } } })
  const user = userEvent.setup()
  mount()
  await user.click(screen.getByRole('button', { name: 'Novo' }))
  await user.type(screen.getByLabelText('Número'), '000123')
  fireEvent.change(screen.getByLabelText(/Valor/), { target: { value: '125.50' } })
  await user.type(screen.getByLabelText('Conta corrente'), '00987')
  fireEvent.change(screen.getByLabelText('Bom para'), { target: { value: '2026-09-30' } })
  await user.click(screen.getByRole('button', { name: 'Salvar' }))
  await waitFor(() => expect(post).toHaveBeenCalledWith('/cheques', {
    especie: 'R', numero: '000123', valor: 125.5, banco_id: null, agencia: null,
    conta_corrente: '00987', titular: null, bom_para: '2026-09-30',
  }))
  expect(api.get).not.toHaveBeenCalledWith('/cheques/situacoes', expect.anything())
})
it('mostra número e data fornecidos pela API atual', async () => {
  vi.spyOn(api, 'get').mockResolvedValue({ data: { data: [{ id: 1, numero: '000456', valor: '10.00', bom_para: '2026-09-30', situacao: 'CARTEIRA' }] } })
  mount()
  expect(await screen.findByText('000456')).toBeVisible()
  expect(screen.getByText('30/09/2026')).toBeVisible()
})
