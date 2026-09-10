import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { createMemoryRouter, RouterProvider, Link } from 'react-router-dom'
import { it, expect } from 'vitest'
import { UnsavedChangesProvider, useRequestLeave } from './UnsavedChanges'
import { useState } from 'react'
import { useResourceForm } from './useResourceForm'
function Editor() {
  const { form, campo, submit } = useResourceForm({ vazio: { nome: '' } })
  return <><input aria-label="Nome" value={form.nome} onChange={(e) => campo('nome', e.target.value)} /><button onClick={() => submit(async () => ({}))}>Salvar</button><Link to="/destino">Sair</Link></>
}
it('bloqueia navegação, permite continuar editando e libera após salvar', async () => {
  const user = userEvent.setup()
  const router = createMemoryRouter([{ path: '*', element: <UnsavedChangesProvider><Editor /></UnsavedChangesProvider> }])
  render(<RouterProvider router={router} />)
  await user.type(screen.getByRole('textbox', { name: 'Nome' }), 'Ana')
  await user.click(screen.getByRole('link', { name: 'Sair' }))
  expect(await screen.findByRole('heading', { name: 'Sair sem salvar?' })).toBeVisible()
  expect(router.state.location.pathname).toBe('/')
  await user.click(screen.getByRole('button', { name: 'Cancelar' }))
  expect(screen.getByRole('textbox')).toHaveValue('Ana')
  await user.click(screen.getByRole('button', { name: 'Salvar' }))
  await user.click(screen.getByRole('link', { name: 'Sair' }))
  await waitFor(() => expect(router.state.location.pathname).toBe('/destino'))
})

function ContextEditor() {
  const requestLeave = useRequestLeave()
  const [revision, setRevision] = useState(0)
  const [failed, setFailed] = useState(false)
  return <><Editor key={revision} />
    <button onClick={() => requestLeave(() => setFailed(true))}>Troca com falha</button>
    <button onClick={() => requestLeave(() => setRevision((v) => v + 1))}>Troca concluída</button>
    {failed && <p role="status">Troca não concluída</p>}
  </>
}
it('preserva proteção após falha e limpa rascunho somente após troca concluída', async () => {
  const user = userEvent.setup()
  const router = createMemoryRouter([{ path: '*', element: <UnsavedChangesProvider><ContextEditor /></UnsavedChangesProvider> }])
  render(<RouterProvider router={router} />)
  await user.type(screen.getByRole('textbox'), 'Rascunho')
  await user.click(screen.getByRole('button', { name: 'Troca com falha' }))
  await user.click(screen.getByRole('button', { name: 'Descartar e continuar' }))
  expect(screen.getByRole('status')).toHaveTextContent('Troca não concluída')
  expect(screen.getByRole('textbox')).toHaveValue('Rascunho')
  await user.click(screen.getByRole('button', { name: 'Troca concluída' }))
  expect(screen.getByRole('heading', { name: 'Sair sem salvar?' })).toBeVisible()
  await user.click(screen.getByRole('button', { name: 'Descartar e continuar' }))
  expect(screen.getByRole('textbox')).toHaveValue('')
  await user.click(screen.getByRole('link', { name: 'Sair' }))
  await waitFor(() => expect(router.state.location.pathname).toBe('/destino'))
})
