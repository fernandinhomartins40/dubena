import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { describe, it, expect, beforeEach } from 'vitest'
import { ModuleFinder, RouteTrail } from './ModuleFinder'

beforeEach(() => localStorage.clear())
const items = [{ label: 'Pedidos', to: '/pedidos', group: 'Operações' }, { label: 'Clientes', to: '/clientes', group: 'Cadastros' }]

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
