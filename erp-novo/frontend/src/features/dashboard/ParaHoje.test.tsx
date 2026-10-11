import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { AcoesRapidas, ParaHoje, haQuanto } from './ParaHoje'

const access = vi.hoisted(() => ({ permissions: [] as string[] }))
vi.mock('@/lib/auth', () => ({ useAuth: () => ({ can: (p: string) => access.permissions.includes(p) }) }))

function montar(ui: React.ReactNode) {
  render(<MemoryRouter><QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>{ui}</QueryClientProvider></MemoryRouter>)
}

afterEach(() => { vi.restoreAllMocks(); access.permissions = [] })

describe('Para hoje', () => {
  it('mostra o fato e leva para onde se resolve', async () => {
    vi.spyOn(api, 'get').mockResolvedValue({ data: { data: {
      pedidos: { em_aberto: 3, mais_antigo: new Date(Date.now() - 2 * 3600_000).toISOString(), hoje: 12, hoje_valor: 1320 },
      financeiro: { receber_hoje: { quantidade: 2, valor: 220 }, receber_vencido: { quantidade: 0, valor: 0 }, pagar_hoje: { quantidade: 1, valor: 80 } },
      estoque_abaixo_minimo: 0,
    } } })
    montar(<ParaHoje />)

    const esperando = await screen.findByRole('link', { name: /Pedidos esperando/ })
    expect(esperando).toHaveAttribute('href', '/pedidos')
    expect(esperando).toHaveTextContent('O mais antigo chegou há 2 h')
    expect(screen.getByRole('link', { name: /Recebimentos atrasados/ })).toHaveTextContent('0 parcelas vencidas')
    expect(screen.getByRole('link', { name: /Estoque abaixo do mínimo/ })).toHaveAttribute('href', '/estoque')
    // Bloco que não veio (sem permissão) não aparece como zero.
    expect(screen.queryByText('Comodatos vencidos')).not.toBeInTheDocument()
  })

  it('falha aparece como falha, não como "nada pendente"', async () => {
    vi.spyOn(api, 'get').mockRejectedValue(new Error('Sem conexão'))
    montar(<ParaHoje />)
    expect(await screen.findByRole('alert')).toBeInTheDocument()
    expect(screen.queryByText('Nenhum pedido parado')).not.toBeInTheDocument()
  })
})

describe('Ações rápidas', () => {
  it('só oferece o que a pessoa pode fazer, e "Novo pedido" abre direto', () => {
    access.permissions = ['pedido.create']
    montar(<AcoesRapidas />)
    expect(screen.getByRole('link', { name: /Novo pedido/ })).toHaveAttribute('href', '/pedidos?novo=1')
    expect(screen.queryByRole('link', { name: /Novo cliente/ })).not.toBeInTheDocument()
  })
})

it('tempo em linguagem comum', () => {
  const agora = Date.parse('2026-10-11T12:00:00Z')
  expect(haQuanto('2026-10-11T11:45:00Z', agora)).toBe('há 15 min')
  expect(haQuanto('2026-10-11T09:00:00Z', agora)).toBe('há 3 h')
  expect(haQuanto('2026-10-10T12:00:00Z', agora)).toBe('há 1 dia')
  expect(haQuanto(null, agora)).toBeNull()
})
