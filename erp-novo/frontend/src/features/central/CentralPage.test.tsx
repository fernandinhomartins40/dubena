import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { CentralPage } from './CentralPage'

vi.mock('@/lib/auth', () => ({ useAuth: () => ({ can: () => true }) }))

const pedido = {
  id: 501, cliente: 'Joana', endereco: 'Rua XV de Novembro 1771, Centro, Guarapuava', referencia: 'Portão azul',
  telefone: '42999990000', itens: [{ quantidade: 2, produto: 'Glp P13' }], lat: null, lng: null,
  valor_venda: 240, urgente: false, datahora: new Date(Date.now() - 40 * 60_000).toISOString(), situacao: 'Pendente', entregador: null,
}

afterEach(() => vi.restoreAllMocks())

/**
 * A Central mostrava só o número da casa e nada do que ia no pedido. Quem
 * distribui precisa de: o que vai, onde (com referência), como falar com o
 * cliente e há quanto tempo ele espera.
 */
it('cartão da fila mostra o que vai, o endereço inteiro, o telefone e a espera', async () => {
  vi.spyOn(api, 'get').mockImplementation(async (url: string) => ({ data: { data: url.startsWith('/central/fila') ? [pedido] : [] } }) as never)
  render(<MemoryRouter><QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><CentralPage /></QueryClientProvider></MemoryRouter>)

  expect(await screen.findByText('2× Glp P13')).toBeInTheDocument()
  expect(screen.getByText(/Rua XV de Novembro 1771, Centro, Guarapuava/)).toBeInTheDocument()
  expect(screen.getByText('Portão azul')).toBeInTheDocument()
  expect(screen.getByText('42999990000')).toBeInTheDocument()
  expect(screen.getByText('Espera 40 min')).toBeInTheDocument()
})
