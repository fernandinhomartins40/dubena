import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, useLocation } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { afterEach, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { ConfiguracoesPage } from './ConfiguracoesPage'

vi.mock('@/lib/auth', () => ({ useAuth: () => ({ can: () => true, hasFeature: () => true }) }))
afterEach(() => vi.restoreAllMocks())

function Url() { return <output data-testid="url">{useLocation().search}</output> }
function montar(rota: string) {
  vi.spyOn(api, 'get').mockResolvedValue({ data: { data: [] } } as never)
  render(<MemoryRouter initialEntries={[rota]}><QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><ConfiguracoesPage /><Url /></QueryClientProvider></MemoryRouter>)
}
const ativa = (nome: RegExp) => expect(screen.getByRole('tab', { name: nome })).toHaveAttribute('aria-selected', 'true')

/**
 * A aba "Entrega" não estava na lista de abas válidas: o clique gravava
 * `?tab=entrega`, a validação recusava e a página voltava para "Geral". O
 * teste clica em TODAS as abas: qualquer uma esquecida na lista reprova.
 */
it('toda aba de primeiro nível abre ao clicar', async () => {
  montar('/configuracoes?tab=geral')
  const abas = ['Clientes', 'Financeiro', 'Equipe', 'Taxas de entrega', 'Sistema (NF-e, e-mail, SAT)']
  // Guardião precisa provar que varreu algo.
  expect(screen.getAllByRole('tab').length).toBeGreaterThanOrEqual(abas.length)
  for (const nome of abas) {
    await userEvent.click(screen.getByRole('tab', { name: nome }))
    await waitFor(() => expect(screen.getByRole('tab', { name: nome })).toHaveAttribute('aria-selected', 'true'))
  }
})

it('sem ?tab= abre no negócio, não no técnico', () => {
  montar('/configuracoes')
  ativa(/Taxas de entrega/)
})

it('links antigos de Clientes e Colaboradores continuam abrindo a área certa', () => {
  montar('/configuracoes?tab=colaboradores')
  ativa(/Equipe/)
  ativa(/Cargos/)
})

it('a sub-aba vai na URL, e uma que não é da área cai na primeira', async () => {
  montar('/configuracoes?tab=colaboradores')
  await userEvent.click(screen.getByRole('tab', { name: 'Parentescos' }))
  await waitFor(() => expect(screen.getByTestId('url')).toHaveTextContent('tab=colaboradores&sub=parentescos'))
})

it('sub-aba de outra área não deixa a área em branco', () => {
  montar('/configuracoes?tab=financeiro&sub=cargos')
  ativa(/Bancos/)
})
