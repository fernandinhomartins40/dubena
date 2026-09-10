import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { it, expect, vi, afterEach } from 'vitest'
import { api } from '@/lib/api'
import { EstoquePage } from './EstoquePage'
vi.mock('@/lib/auth', () => ({ useAuth: () => ({ user: { id: 1, empresa_id: 1 }, can: () => true }) }))
afterEach(() => vi.restoreAllMocks())
function mount(tab: string) {
  render(<MemoryRouter initialEntries={['/estoque?tab=' + tab]}><QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><EstoquePage /></QueryClientProvider></MemoryRouter>)
}
it.each(['saldos', 'transferencia', 'requisicao', 'inventario', 'fisico', 'fechamento'])('consulta %s não representa falha como lista vazia', async (tab) => {
  vi.spyOn(api, 'get').mockRejectedValue(new Error('Offline'))
  mount(tab)
  expect(await screen.findByRole('alert')).toHaveTextContent('Offline')
  expect(screen.getByRole('button', { name: 'Tentar novamente' })).toBeVisible()
})
it('protege e descarta itens ainda não registrados do estoque físico', async () => {
  vi.spyOn(api, 'get').mockResolvedValue({ data: { data: [] } })
  const user = userEvent.setup()
  mount('fisico')
  await user.click(screen.getByRole('button', { name: 'Novo estoque físico' }))
  await user.click(screen.getByRole('button', { name: 'Adicionar' }))
  await user.click(screen.getByRole('button', { name: 'Cancelar' }))
  expect(screen.getByRole('heading', { name: 'Descartar alterações?' })).toBeVisible()
  await user.click(screen.getByRole('button', { name: 'Descartar alterações' }))
  await user.click(screen.getByRole('button', { name: 'Novo estoque físico' }))
  expect(within(screen.getByRole('dialog')).queryByRole('button', { name: 'Remover item 1' })).not.toBeInTheDocument()
})
