import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { ComodatoPage } from './ComodatoPage'

globalThis.ResizeObserver ??= class { observe() {} unobserve() {} disconnect() {} } as never
vi.mock('@/lib/auth', () => ({ useAuth: () => ({ can: () => true, hasFeature: () => true }) }))
afterEach(() => vi.restoreAllMocks())

/** Quem assina costuma ser o próprio cliente: nome e CPF vêm do cadastro. */
it('ao escolher um cliente pessoa física, representante e CPF já vêm preenchidos', async () => {
  vi.spyOn(api, 'get').mockImplementation(async (url: string) => {
    if (url === '/lookups/clientes') return { data: { data: [{ id: 7, label: 'Joana Silva' }] } } as never
    if (url === '/clientes/7') return { data: { data: { id: 7, nome: 'Joana Silva', cpf: '12345678900', cnpj: null } } } as never
    return { data: { data: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 } } } as never
  })
  const user = userEvent.setup()
  render(<MemoryRouter><QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><ComodatoPage /></QueryClientProvider></MemoryRouter>)

  await user.click(await screen.findByRole('button', { name: /Novo comodato/ }))
  const dialogo = await screen.findByRole('dialog')
  await user.click(within(dialogo).getByRole('button', { name: /^Cliente/ }))
  await user.click(await screen.findByRole('button', { name: 'Joana Silva' }))

  await waitFor(() => expect(within(dialogo).getByDisplayValue('12345678900')).toBeInTheDocument())
  expect(within(dialogo).getByLabelText(/Representante/)).toHaveValue('Joana Silva')
})
