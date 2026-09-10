import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { it, expect, vi, afterEach } from 'vitest'
import { api } from '@/lib/api'
import { saApi } from '@/features/superadmin/api'
import { CentralPage } from '@/features/central/CentralPage'
import { CentralVendasPage } from '@/features/central-vendas/CentralVendasPage'
import { SaDashboardPage } from '@/features/superadmin/SaDashboardPage'
vi.mock('@/lib/auth', () => ({ useAuth: () => ({ can: () => true }) }))
afterEach(() => vi.restoreAllMocks())
function mount(page: React.ReactNode) {
  render(<MemoryRouter><QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>{page}</QueryClientProvider></MemoryRouter>)
}
it('logística preserva a seção disponível e não inventa zero para a fila com falha', async () => {
  vi.spyOn(api, 'get').mockImplementation(async (url) => {
    if (url === '/central/entregadores') return { data: { data: [] } }
    throw new Error('Consulta indisponível')
  })
  mount(<CentralPage />)
  expect(await screen.findByRole('alert')).toHaveTextContent('Não foi possível carregar')
  expect(await screen.findByText('Ninguém em serviço')).toBeVisible()
  expect(screen.queryByText('Fila vazia')).not.toBeInTheDocument()
  expect(screen.getAllByText('—')).toHaveLength(2)
})
it('central de vendas não apresenta ausência de pendências quando a consulta falha', async () => {
  vi.spyOn(api, 'get').mockRejectedValue(new Error('Offline'))
  mount(<CentralVendasPage />)
  expect(await screen.findByRole('alert')).toHaveTextContent('Offline')
  expect(screen.queryByText('Nenhuma solicitação')).not.toBeInTheDocument()
  expect(screen.getAllByText('—')).toHaveLength(3)
})
it('plataforma distingue falha das seções auxiliares de ausência de suspensões e auditoria', async () => {
  vi.spyOn(saApi, 'get').mockImplementation(async (url) => {
    if (url === '/dashboard') return { data: { data: { empresas_total: 4, empresas_ativas: 3, assinaturas_ativas: 3, assinaturas_inadimplentes: 1, planos: 2, por_plano: {} } } }
    throw new Error('Seção indisponível')
  })
  mount(<SaDashboardPage />)
  expect(await screen.findAllByRole('alert')).toHaveLength(3)
  expect(screen.queryByText('Nenhuma empresa suspensa.')).not.toBeInTheDocument()
  expect(screen.queryByText('Sem ações registradas ainda.')).not.toBeInTheDocument()
  expect(screen.getByText('Empresas')).toBeVisible()
})
