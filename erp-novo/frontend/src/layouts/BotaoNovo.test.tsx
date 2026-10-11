import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom'
import { afterEach, expect, it, vi } from 'vitest'
import { BotaoNovo } from './BotaoNovo'

const access = vi.hoisted(() => ({ permissions: [] as string[] }))
vi.mock('@/lib/auth', () => ({ useAuth: () => ({ can: (p: string) => access.permissions.includes(p) }) }))

function Onde() {
  const l = useLocation()
  return <p data-testid="onde">{l.pathname}{l.search}</p>
}

function montar() {
  render(
    <MemoryRouter initialEntries={['/estoque']}>
      <BotaoNovo />
      <Routes><Route path="*" element={<Onde />} /></Routes>
    </MemoryRouter>,
  )
}

afterEach(() => { access.permissions = [] })

it('de qualquer tela, "Novo → Pedido" leva direto ao diálogo de pedido', async () => {
  access.permissions = ['pedido.create', 'cliente.create']
  const user = userEvent.setup()
  montar()
  await user.click(screen.getByRole('button', { name: 'Criar novo' }))
  // Só o que a pessoa pode criar.
  expect(screen.queryByRole('menuitem', { name: /Produto/ })).not.toBeInTheDocument()
  await user.click(screen.getByRole('menuitem', { name: /Pedido/ }))
  expect(screen.getByTestId('onde')).toHaveTextContent('/pedidos?novo=1')
})

it('sem permissão para criar nada, o botão nem aparece', () => {
  montar()
  expect(screen.queryByRole('button', { name: 'Criar novo' })).not.toBeInTheDocument()
})
