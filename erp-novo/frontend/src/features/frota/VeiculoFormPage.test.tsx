import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { afterEach, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { VeiculoFormPage } from './VeiculosPage'

vi.mock('@/lib/auth', () => ({ useAuth: () => ({ can: () => true }) }))

const veiculo = {
  id: 3, placa: 'ABC1D23', descricao: 'Caminhão', veiculotipo_id: 1, tipocombustivel_id: 1, tipo_label: 'Caminhão', combustivel_label: 'Diesel',
  kmatual: 52000, km_atual: 52000, renavam: '00123456789', km_troca_oleo: 10000, km_ultima_troca_oleo: 45000,
}

afterEach(() => vi.restoreAllMocks())

/**
 * O formulário mandava `kmatual` (a validação só aceita `km_atual`) e um "KM
 * inicial" sem coluna: atualizar a quilometragem não gravava nada, e o aviso de
 * troca de óleo não tinha como ser configurado pela tela.
 */
it('salva a quilometragem e os dados de troca de óleo com os nomes que o servidor grava', async () => {
  vi.spyOn(api, 'get').mockImplementation(async (url: string) => ({ data: { data: url === '/veiculos/3' ? veiculo : [] } }) as never)
  const put = vi.spyOn(api, 'put').mockResolvedValue({ data: { data: { id: 3 } } })
  const user = userEvent.setup()
  render(
    <MemoryRouter initialEntries={['/veiculos/3']}>
      <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
        <Routes><Route path="/veiculos/:id" element={<VeiculoFormPage />} /></Routes>
      </QueryClientProvider>
    </MemoryRouter>,
  )

  const km = await screen.findByDisplayValue('52000')
  await user.clear(km)
  await user.type(km, '53500')
  await user.click(screen.getByRole('button', { name: /Salvar/ }))

  await waitFor(() => expect(put).toHaveBeenCalled())
  const enviado = put.mock.calls[0][1] as Record<string, unknown>
  expect(enviado).toMatchObject({ km_atual: '53500', renavam: '00123456789', km_troca_oleo: 10000, km_ultima_troca_oleo: 45000 })
  expect(enviado).not.toHaveProperty('kminicial')
})
