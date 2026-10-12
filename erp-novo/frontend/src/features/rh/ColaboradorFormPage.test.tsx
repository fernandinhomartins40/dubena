import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { afterEach, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { ColaboradorFormPage } from './ColaboradoresPage'

globalThis.ResizeObserver ??= class { observe() {} unobserve() {} disconnect() {} } as never
vi.mock('@/lib/auth', () => ({ useAuth: () => ({ can: () => true }) }))

const colaborador = {
  id: 12, nome: 'João Entregador', telefone: '42999990000', entregador: true, cargo_id: 1, cargo_label: 'Entregador',
  cidade_id: 9, cidade_label: 'Guarapuava', bairro_id: 4, bairro_label: 'Centro', rua_id: 77, rua_label: 'Rua XV',
  numero: '10', complemento: 'casa', uf: 'PR', cep: '85010100', cpf: '', rg: '', datanascimento: '', dataadmissao: '', datadesligamento: '',
}

afterEach(() => vi.restoreAllMocks())

/**
 * O formulário não oferecia telefone nem a marca de entregador (nem rua,
 * complemento, UF): só dava para ajustar direto no banco.
 */
it('mostra e devolve telefone, marca de entregador e endereço completo', async () => {
  vi.spyOn(api, 'get').mockImplementation(async (url: string) => ({ data: { data: url === '/colaboradores/12' ? colaborador : [] } }) as never)
  const put = vi.spyOn(api, 'put').mockResolvedValue({ data: { data: { id: 12 } } })
  const user = userEvent.setup()
  render(
    <MemoryRouter initialEntries={['/colaboradores/12']}>
      <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
        <Routes><Route path="/colaboradores/:id" element={<ColaboradorFormPage />} /></Routes>
      </QueryClientProvider>
    </MemoryRouter>,
  )

  // Prazo maior: sob a suíte inteira a tela demora a hidratar.
  expect(await screen.findByDisplayValue('42999990000', undefined, { timeout: 5000 })).toBeInTheDocument()
  expect(screen.getByRole('checkbox', { name: /É entregador/ })).toBeChecked()
  // O rótulo chega num efeito logo depois dos campos: espera-se por ele.
  await waitFor(() => expect(screen.getByRole('button', { name: /^Rua/ })).toHaveTextContent('Rua XV'))

  await user.click(screen.getByRole('button', { name: /Salvar/ }))
  await waitFor(() => expect(put).toHaveBeenCalled(), { timeout: 5000 })
  expect(put.mock.calls[0][1]).toMatchObject({ telefone: '42999990000', entregador: true, rua_id: 77, complemento: 'casa', uf: 'PR' })
}, 15_000) // página pesada (7 abas): sob a suíte inteira passa dos 5 s padrão
