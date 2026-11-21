import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { vi, it, expect, afterEach } from 'vitest'
import { ProntoParaVender } from './ProntoParaVender'
import { api } from '@/lib/api'

afterEach(() => { vi.restoreAllMocks() })

function mount() {
  return render(<MemoryRouter><QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><ProntoParaVender /></QueryClientProvider></MemoryRouter>)
}

it('explica cada pendência e leva à tela que resolve', async () => {
  vi.spyOn(api, 'get').mockResolvedValue({ data: { data: { pronto: false, itens: [
    { chave: 'preco', titulo: 'Produto com preço', ok: false, porque: 'Os produtos iniciais nascem com preço zero.', link: '/produtos/precos' },
    { chave: 'condicao', titulo: 'Condição de pagamento', ok: true, porque: 'x', link: '/configuracoes?tab=financeiro' },
  ] } } })
  mount()

  expect(await screen.findByText('Os produtos iniciais nascem com preço zero.')).toBeVisible()
  expect(screen.getByRole('link', { name: /Resolver.*Produto com preço/ })).toHaveAttribute('href', '/produtos/precos')
  expect(screen.getByText('1 de 2 itens prontos.', { exact: false })).toBeVisible()
  // Item pronto não pede ação.
  expect(screen.queryByRole('link', { name: /Condição de pagamento/ })).not.toBeInTheDocument()
  expect(api.get).toHaveBeenCalledWith('/dashboard/pronto-para-vender')
})

it('some quando a revenda está pronta', async () => {
  vi.spyOn(api, 'get').mockResolvedValue({ data: { data: { pronto: true, itens: [] } } })
  const { container } = mount()
  await waitFor(() => expect(api.get).toHaveBeenCalled())
  await waitFor(() => expect(container).toBeEmptyDOMElement())
})

it('falha não vira "tudo pronto"', async () => {
  vi.spyOn(api, 'get').mockRejectedValue(new Error('Sem conexão'))
  mount()
  expect(await screen.findByRole('alert')).toBeVisible()
  expect(screen.getByText('Pronto para vender?')).toBeVisible()
})
