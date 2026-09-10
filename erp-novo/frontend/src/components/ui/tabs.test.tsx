import { render, screen } from '@testing-library/react'
import { MemoryRouter, useLocation } from 'react-router-dom'
import userEvent from '@testing-library/user-event'
import { it, expect } from 'vitest'
import { Tabs, TabsList, TabsTrigger, TabsContent } from './tabs'
function Fixture() { const location = useLocation(); return <><output>{location.search}</output><Tabs urlKey="tab" defaultValue="lista"><TabsList><TabsTrigger value="lista">Lista</TabsTrigger><TabsTrigger value="caixa">Caixa</TabsTrigger></TabsList><TabsContent value="lista">Lista de registros</TabsContent><TabsContent value="caixa">Seu caixa</TabsContent></Tabs></> }
it('restaura aba válida e preserva outros parâmetros ao navegar', async () => {
  render(<MemoryRouter initialEntries={['/?tab=caixa&origem=home']}><Fixture /></MemoryRouter>)
  expect(screen.getByRole('tab', { name: 'Caixa' })).toHaveAttribute('aria-selected', 'true')
  await userEvent.click(screen.getByRole('tab', { name: 'Lista' }))
  expect(screen.getByRole('status')).toHaveTextContent('tab=lista&origem=home')
})
it('não ativa aba desconhecida pela URL', () => {
  render(<MemoryRouter initialEntries={['/?tab=nao-autorizada']}><Fixture /></MemoryRouter>)
  expect(screen.getByRole('tab', { name: 'Lista' })).toHaveAttribute('aria-selected', 'true')
})
