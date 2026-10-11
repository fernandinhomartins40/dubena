import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { ConfigTab } from './ConfigTab'

vi.mock('@/lib/auth', () => ({ useAuth: () => ({ can: () => true, user: { id: 1 } }) }))

// O GET devolve campos de LEITURA junto (empresa_id, tem_senhamestre, blocos).
const config = {
  empresa_id: 2, tem_senhamestre: true, fiscal: { serie: 1 }, sped: {}, contador: {},
  tempoentrega: 40, pedido_valida_cartao_dias: 30, permite_estoque_negativo: false,
}

function montar() {
  vi.spyOn(api, 'get').mockImplementation(async (url: string) => ({ data: { data: url.endsWith('/config') ? config : [] } }) as never)
  const put = vi.spyOn(api, 'put').mockResolvedValue({ data: { data: config } })
  render(<MemoryRouter><QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><ConfigTab empresaId={2} /></QueryClientProvider></MemoryRouter>)
  return put
}

afterEach(() => vi.restoreAllMocks())

/**
 * Antes o salvar devolvia o objeto inteiro do GET, e o servidor recusava
 * `empresa_id`/`tem_senhamestre`/blocos com 422 em TODO salvar.
 */
it('salvar sem mexer em nada não envia nada', async () => {
  const put = montar()
  const user = userEvent.setup()
  await screen.findByDisplayValue('40')
  await user.click(screen.getByRole('button', { name: /Salvar configurações/ }))
  expect(put).not.toHaveBeenCalled()
})

it('envia só o campo alterado, com o nome que o servidor aceita', async () => {
  const put = montar()
  const user = userEvent.setup()
  const tempo = await screen.findByDisplayValue('40')
  await user.clear(tempo)
  await user.type(tempo, '35')
  await user.click(screen.getByRole('button', { name: /Salvar configurações/ }))
  await waitFor(() => expect(put).toHaveBeenCalledWith('/empresas/2/config', { tempoentrega: '35' }))
})
