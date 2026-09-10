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
