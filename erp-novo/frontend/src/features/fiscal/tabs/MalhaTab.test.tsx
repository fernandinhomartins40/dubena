import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { MalhaTab } from './MalhaTab'

// jsdom não tem ResizeObserver; a caixa de seleção do Radix o usa.
globalThis.ResizeObserver ??= class { observe() {} unobserve() {} disconnect() {} } as never

vi.mock('@/lib/auth', () => ({ useAuth: () => ({ can: () => true }) }))

// O que a API devolve: o model, com os nomes das colunas e booleanos.
const operacao = { id: 7, descricao: 'Venda consumidor', descricao_fiscal: 'VENDA', cfop: '5405', movimenta_estoque: true, movimenta_financeiro: false }

afterEach(() => vi.restoreAllMocks())

/**
 * A tela lia e mandava `movimentaestoque`/`descricaofiscal`: as caixas abriam
 * sempre desmarcadas e o servidor descartava o que voltava — desmarcar
 * "Movimenta estoque" numa operação não tinha efeito.
 */
it('operação fiscal abre com as marcas reais e salva com os nomes das colunas', async () => {
  vi.spyOn(api, 'get').mockImplementation(async (url: string) => ({ data: { data: url === '/fiscal/operacoes' ? [operacao] : [] } }) as never)
  const put = vi.spyOn(api, 'put').mockResolvedValue({ data: { data: operacao } })
  const user = userEvent.setup()
  render(<MemoryRouter><QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><MalhaTab /></QueryClientProvider></MemoryRouter>)

  await user.click(await screen.findByRole('tab', { name: /Operações/ }))
  await user.click(await screen.findByText('Venda consumidor'))
  const dialogo = await screen.findByRole('dialog')
  const estoque = within(dialogo).getByRole('checkbox', { name: 'Movimenta estoque' })
  expect(estoque).toBeChecked()

  await user.click(estoque)
  await user.click(within(dialogo).getByRole('button', { name: /Salvar/ }))

  await waitFor(() => expect(put).toHaveBeenCalledWith('/fiscal/operacoes/7', expect.objectContaining({
    descricao_fiscal: 'VENDA', movimenta_estoque: false, movimenta_financeiro: false,
  })))
})
