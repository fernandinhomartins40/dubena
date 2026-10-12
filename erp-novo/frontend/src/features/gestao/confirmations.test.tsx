import { render, screen, within, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { it, expect, vi, afterEach } from 'vitest'
import { api } from '@/lib/api'
import { CupomPage } from './CupomPage'
import { ConvenioPage } from '@/features/convenios/ConvenioPage'
// O sorteio saiu desta lista: a confirmação dele é o palco (ver
// `crm/sorteio/SorteioPalco.test.tsx`, que cobre o mesmo contrato).
afterEach(() => vi.restoreAllMocks())
it.each([
  { Page: CupomPage, action: 'Emitir', endpoint: '/cupons-fiscais/1/emitir', row: { id: 1, numero: 42, situacao: 'rascunho', itens: [], valor_total: 10 } },
  { Page: ConvenioPage, action: 'Fechar período', endpoint: '/convenios/1/fechar', row: { id: 1, descricao: 'Convênio mensal', ativo: true } },
])('$action exige confirmação e mantém contexto em caso de falha', async ({ Page, action, endpoint, row }) => {
  vi.spyOn(api, 'get').mockResolvedValue({ data: { data: [row] } })
  const post = vi.spyOn(api, 'post').mockRejectedValue(new Error('Offline'))
  const user = userEvent.setup()
  render(<QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })}><Page /></QueryClientProvider>)
  await user.click(await screen.findByRole('button', { name: action }))
  expect(post).not.toHaveBeenCalled()
  await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: action }))
  // O endereço é o contrato; o convênio agora também manda o período (que o
  // servidor exige — antes ia sem ele e voltava 422).
  await waitFor(() => expect(post.mock.calls[0]?.[0]).toBe(endpoint))
  await waitFor(() => expect(within(screen.getByRole('dialog')).getByRole('button', { name: action })).toBeEnabled())
})
