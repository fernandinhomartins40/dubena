import { useState } from 'react'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { it, expect, vi } from 'vitest'
import { FormDialog } from './form-dialog'
import { Input } from './input'
import { Button } from './button'

it('Enter confirma e botão auxiliar não submete o formulário', async () => {
  const save = vi.fn()
  const user = userEvent.setup()
  render(<FormDialog open onOpenChange={() => {}} title="Novo cadastro" onConfirm={save}>
    <Input aria-label="Nome" /><Button>Pesquisar referência</Button>
  </FormDialog>)
  await user.click(screen.getByRole('button', { name: 'Pesquisar referência' }))
  expect(save).not.toHaveBeenCalled()
  await user.type(screen.getByRole('textbox'), 'Ana{Enter}')
  expect(save).toHaveBeenCalledTimes(1)
})
it('mantém o formulário alterado até confirmar descarte', async () => {
  const user = userEvent.setup()
  function Form() { const [open, setOpen] = useState(true); return <FormDialog open={open} onOpenChange={setOpen} title="Cadastro" onConfirm={() => {}}><Input aria-label="Nome" /></FormDialog> }
  render(<Form />)
  await user.type(screen.getByRole('textbox'), 'Ana')
  await user.click(screen.getByRole('button', { name: 'Cancelar' }))
  expect(screen.getByRole('heading', { name: 'Descartar alterações?' })).toBeVisible()
  await user.click(screen.getByRole('button', { name: 'Descartar alterações' }))
  expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
})
