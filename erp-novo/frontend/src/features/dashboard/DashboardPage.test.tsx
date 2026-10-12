import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { vi, it, expect, afterEach } from 'vitest'
import { DashboardPage } from './DashboardPage'
import { api } from '@/lib/api'
const access = vi.hoisted(() => ({ permissions: ['financeiro.view'] }))
vi.mock('@/lib/auth', () => ({ useAuth: () => ({ user: { name: 'Ana' }, can: (p: string) => access.permissions.includes(p) }) }))
afterEach(() => { vi.restoreAllMocks(); access.permissions = ['financeiro.view'] })
function mount() { render(<MemoryRouter><QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><DashboardPage /></QueryClientProvider></MemoryRouter>) }
it('mostra o significado da contagem financeira e somente atalhos autorizados', async () => {
  vi.spyOn(api, 'get').mockResolvedValue({ data: { clientes: 1, produtos: 2, pedidos: 3, financeiro: 0 } })
  mount()
  expect(await screen.findByText('Parcelas a receber em aberto')).toBeVisible()
  expect(screen.getByText('Quantidade de parcelas, não valor em reais')).toBeVisible()
  expect(screen.queryByRole('link', { name: /Pedidos/ })).not.toBeInTheDocument()
  expect(api.get).not.toHaveBeenCalledWith('/alertas', expect.anything())
})
it('apresenta pendências somente quando a central é autorizada', async () => {
  access.permissions = ['alerta.view']
  vi.spyOn(api, 'get').mockImplementation(async (url) => url === '/alertas'
    ? { data: { data: [{ id: 1, titulo: 'Conferir comodato', situacao: 'ABERTO', severidade: 'ALTA', responsavel: null }] } }
    : { data: { clientes: 1, produtos: 2, pedidos: 3, financeiro: 0 } })
  mount()
  expect(await screen.findByText('Conferir comodato')).toBeVisible()
  expect(screen.getByRole('link', { name: 'Ver avisos e pendências' })).toHaveAttribute('href', '/alertas')
})
it('falha não vira uma contagem zero', async () => {
  vi.spyOn(api, 'get').mockRejectedValue(new Error('Sem conexão'))
  mount()
  // Resumo e 'Para hoje' falham cada um no seu lugar, com a sua tentativa.
  const alertas = await screen.findAllByRole('alert')
  expect(alertas.some((a) => a.textContent?.includes('Não foi possível carregar'))).toBe(true)
  expect(screen.queryByText('0')).not.toBeInTheDocument()
  expect(screen.getAllByRole('button', { name: 'Tentar novamente' }).length).toBeGreaterThanOrEqual(2)
})
