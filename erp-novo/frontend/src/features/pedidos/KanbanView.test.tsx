import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { it, expect, vi, afterEach } from 'vitest'
import { api } from '@/lib/api'
import { KanbanView } from './KanbanView'
vi.mock('@/lib/auth', () => ({ useAuth: () => ({ can: () => true }) }))
afterEach(() => vi.restoreAllMocks())
const columns = [
  { situacao_id: 1, descricao: 'Recebido', efeito: 'PENDENTE', total: 1, valor: 100, pedidos: [{ id: 42, cliente: 'Ana', valorvenda: 100, datahora: null }] },
  { situacao_id: 2, descricao: 'Finalizado', efeito: 'CONCLUIDO', total: 0, valor: 0, pedidos: [] },
]
function mount(onOpen = vi.fn()) {
  render(<QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })}><KanbanView onOpen={onOpen} /></QueryClientProvider>)
}
it('abre por teclado e só efetiva transição com efeito após confirmação', async () => {
  vi.spyOn(api, 'get').mockResolvedValue({ data: { data: columns } })
  const put = vi.spyOn(api, 'put').mockRejectedValue(new Error('Offline'))
  const open = vi.fn()
  const user = userEvent.setup()
  mount(open)
  const button = await screen.findByRole('button', { name: 'Abrir pedido #42' })
  button.focus()
  await user.keyboard('{Enter}')
  expect(open).toHaveBeenCalledExactlyOnceWith(42)
  await user.click(screen.getByRole('button', { name: 'Mover pedido #42' }))
  await user.click(screen.getByRole('menuitem', { name: 'Finalizado' }))
  expect(screen.getByRole('dialog')).toHaveTextContent('concluir a venda')
  expect(put).not.toHaveBeenCalled()
  await user.click(screen.getByRole('button', { name: 'Mover' }))
  await waitFor(() => expect(put).toHaveBeenCalledWith('/pedidos/42/situacao', { pedidosituacao_id: 2 }))
  await waitFor(() => expect(screen.getByRole('button', { name: 'Mover' })).toBeEnabled())
  expect(screen.getByRole('dialog')).toBeVisible()
})
it('uma falha no quadro oferece recuperação sem representar zero pedidos', async () => {
  vi.spyOn(api, 'get').mockRejectedValue(new Error('Offline'))
  mount()
  expect(await screen.findByRole('alert')).toHaveTextContent('Offline')
  expect(screen.getByRole('button', { name: 'Tentar novamente' })).toBeVisible()
  expect(screen.queryByText('Sem pedidos')).not.toBeInTheDocument()
})
