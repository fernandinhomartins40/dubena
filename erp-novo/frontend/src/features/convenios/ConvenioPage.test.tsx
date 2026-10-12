import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { ConvenioPage } from './ConvenioPage'

vi.mock('@/lib/auth', () => ({ useAuth: () => ({ can: () => true }) }))
afterEach(() => vi.restoreAllMocks())

/** O fechamento ia sem o período que o servidor exige: 422 em todo fechamento. */
it('fechar período manda as datas sugeridas, a partir do último fechamento', async () => {
  vi.spyOn(api, 'get').mockImplementation(async (url: string) => ({
    data: { data: url === '/convenios'
      ? [{ id: 4, cliente_id: 1, descricao: 'Prefeitura', dia_fechamento: 5, dia_vencimento: 15, ativo: true }]
      : url === '/convenios/4/fechamentos' ? [{ id: 1, periodo_inicio: '2026-08-06', periodo_fim: '2026-09-05' }] : [] },
  }) as never)
  const post = vi.spyOn(api, 'post').mockResolvedValue({ data: { data: { id: 2 } } })
  const user = userEvent.setup()
  render(<MemoryRouter><QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><ConvenioPage /></QueryClientProvider></MemoryRouter>)

  await user.click(await screen.findByRole('button', { name: /Fechar período/ }))
  const dialogo = await screen.findByRole('dialog')
  await waitFor(() => expect(within(dialogo).getByLabelText(/^De/)).toHaveValue('2026-09-06'))
  await user.click(within(dialogo).getByRole('button', { name: 'Fechar período' }))

  await waitFor(() => expect(post).toHaveBeenCalledWith('/convenios/4/fechar', expect.objectContaining({ periodo_inicio: '2026-09-06' })))
  const corpo = post.mock.calls[0][1] as { periodo_fim: string }
  expect(corpo.periodo_fim).toMatch(/^\d{4}-\d{2}-05$/)
})
