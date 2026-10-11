import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter, Routes, Route } from 'react-router-dom'
import { it, expect, vi, afterEach } from 'vitest'
import { api } from '@/lib/api'
import { ClienteFormPage } from './ClienteFormPage'
vi.mock('@/lib/auth', () => ({ useAuth: () => ({ can: () => true }) }))
afterEach(() => vi.restoreAllMocks())
function mount(path: string) {
  render(<MemoryRouter initialEntries={[path]}><QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })}>
    <Routes><Route path="/clientes/:id" element={<ClienteFormPage />} /></Routes>
  </QueryClientProvider></MemoryRouter>)
}
it('abre endereço e foca o campo rejeitado sem apagar o nome digitado', async () => {
  vi.spyOn(api, 'post').mockRejectedValue({ response: { status: 422, data: { errors: { numero: ['Informe o número.'] } } } })
  const user = userEvent.setup()
  mount('/clientes/novo')
  await user.type(screen.getByRole('textbox', { name: /Nome/ }), 'Cliente em edição')
  await user.click(screen.getByRole('button', { name: 'Salvar' }))
  await waitFor(() => expect(screen.getByRole('tab', { name: 'Endereço' })).toHaveAttribute('data-state', 'active'))
  await waitFor(() => expect(screen.getByRole('textbox', { name: /Número/ })).toHaveFocus())
  await user.click(screen.getByRole('tab', { name: 'Dados Gerais' }))
  expect(screen.getByRole('textbox', { name: /Nome/ })).toHaveValue('Cliente em edição')
})
it('falha ao abrir cadastro não permite salvar um formulário vazio', async () => {
  vi.spyOn(api, 'get').mockRejectedValue(new Error('Sem conexão'))
  mount('/clientes/42')
  expect(await screen.findByRole('alert')).toHaveTextContent('Sem conexão')
  expect(screen.queryByRole('button', { name: 'Salvar' })).not.toBeInTheDocument()
  expect(screen.getByRole('button', { name: 'Tentar novamente' })).toBeVisible()
})
// O CEP não preenchia nada; agora a própria base devolve cidade, bairro e rua,
// e só o que está vazio é preenchido.
it('CEP com 8 dígitos preenche cidade, bairro e rua', async () => {
  vi.spyOn(api, 'get').mockImplementation(async (url: string) => ({
    data: { data: url === '/geo/cep/85010100'
      ? { cidade: { id: 9, label: 'Guarapuava', uf: 'PR' }, bairro: { id: 4, label: 'Centro' }, rua: { id: 77, label: 'Rua XV' }, fonte: 'rua' }
      : [] },
  }) as never)
  const user = userEvent.setup()
  mount('/clientes/novo')
  await user.click(screen.getByRole('tab', { name: 'Endereço' }))
  await user.type(screen.getByRole('textbox', { name: /CEP/ }), '85010-100')
  expect(await screen.findByText(/Endereço preenchido pelo CEP: Rua XV, Centro, Guarapuava/)).toBeInTheDocument()
  expect(screen.getByRole('button', { name: /^Cidade/ })).toHaveTextContent('Guarapuava')
  expect(screen.getByRole('button', { name: /^Rua/ })).toHaveTextContent('Rua XV')
  expect(screen.getByRole('textbox', { name: /UF/ })).toHaveValue('PR')
})
