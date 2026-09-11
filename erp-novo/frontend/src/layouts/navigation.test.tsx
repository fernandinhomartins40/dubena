import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { describe, it, expect, beforeEach } from 'vitest'
import { ModuleFinder, RouteTrail } from './ModuleFinder'
import { prefixosDeOutroItem, NAV_ITENS } from './AppShell'

beforeEach(() => localStorage.clear())
const items = [{ label: 'Pedidos', to: '/pedidos', group: 'Operações' }, { label: 'Clientes', to: '/clientes', group: 'Cadastros' }]

describe('Indicador de item ativo', () => {
  it('marca como exato todo item cujo caminho é prefixo de outro do menu', () => {
    // O bug: `/clientes/revisoes` começa com `/clientes`, então sem `end` os
    // DOIS itens acendiam ao mesmo tempo — visto em produção na sidebar.
    const exatos = prefixosDeOutroItem([
      { to: '/clientes' }, { to: '/clientes/revisoes' }, { to: '/pedidos' },
    ])
    expect(exatos.has('/clientes')).toBe(true)
    expect(exatos.has('/clientes/revisoes')).toBe(false)
    expect(exatos.has('/pedidos')).toBe(false)
  })

  it('protege o menu real: nenhum par prefixo/filho fica sem marcação exata', () => {
    const exatos = prefixosDeOutroItem(NAV_ITENS)
    const pares = NAV_ITENS.filter((a) => NAV_ITENS.some((b) => b.to !== a.to && b.to.startsWith(a.to + '/')))
    // Guardião precisa provar que varreu algo, não só que passou.
    expect(NAV_ITENS.length).toBeGreaterThan(20)
    for (const p of pares) expect(exatos.has(p.to)).toBe(true)
  })

  it('cada item do menu aponta para um caminho único', () => {
    const vistos = NAV_ITENS.map((i) => i.to)
    expect(new Set(vistos).size).toBe(vistos.length)
  })
})

describe('Navegação orientada a tarefas', () => {
  it('busca sem acento e persiste favoritos somente para a pessoa atual', async () => {
    const user = userEvent.setup()
    const view = render(<MemoryRouter><ModuleFinder items={items} userKey="erp.1" onNavigate={() => {}} /></MemoryRouter>)
    await user.type(screen.getByRole('textbox', { name: 'Buscar módulos' }), 'operacoes')
    expect(screen.getByRole('link', { name: 'Pedidos' })).toBeVisible()
    await user.click(screen.getByRole('button', { name: 'Adicionar Pedidos aos favoritos' }))
    await user.clear(screen.getByRole('textbox', { name: 'Buscar módulos' }))
    expect(screen.getByText('Favoritos')).toBeVisible()
    view.rerender(<MemoryRouter><ModuleFinder items={items} userKey="erp.2" onNavigate={() => {}} /></MemoryRouter>)
    expect(screen.queryByRole('link', { name: 'Pedidos' })).not.toBeInTheDocument()
  })

  it('não mostra favorito cuja permissão foi removida', () => {
    localStorage.setItem('erpnovo.ui.favorites.erp.1', JSON.stringify(['/financeiro']))
    render(<MemoryRouter><ModuleFinder items={items} userKey="erp.1" onNavigate={() => {}} /></MemoryRouter>)
    expect(screen.queryByRole('link')).not.toBeInTheDocument()
  })

  it('mostra caminho de retorno ao cadastro ao abrir detalhe', () => {
    render(<MemoryRouter initialEntries={['/clientes/42']}><RouteTrail items={items} /></MemoryRouter>)
    expect(screen.getByRole('link', { name: 'Clientes' })).toHaveAttribute('href', '/clientes')
    expect(screen.getByText('Detalhes')).toHaveAttribute('aria-current', 'page')
  })
})
