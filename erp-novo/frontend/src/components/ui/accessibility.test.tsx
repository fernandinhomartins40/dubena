import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, it, expect, vi } from 'vitest'
import { Field } from './field'
import { Input } from './input'
import { Textarea } from './textarea'
import { Select, SelectTrigger, SelectValue } from './select'
import { DataTable } from './data-table'

describe('Contratos de acessibilidade compartilhados', () => {
  it('associa rótulo, erro e obrigatoriedade ao input e preserva IDs explícitos', async () => {
    const user = userEvent.setup()
    render(<Field label="Nome" required error="Informe o nome"><Input id="cliente-nome" /></Field>)
    const input = screen.getByRole('textbox', { name: /Nome/ })
    expect(input).toHaveAttribute('id', 'cliente-nome')
    expect(input).toHaveAttribute('aria-invalid', 'true')
    expect(input).toHaveAttribute('aria-required', 'true')
    expect(input).toHaveAccessibleDescription('Informe o nome')
    await user.click(screen.getByText('Nome'))
    expect(input).toHaveFocus()
  })

  it('associa controles dentro de wrappers sem duplicar IDs', () => {
    render(<><Field label="Observação" hint="Até 200 caracteres"><div><Textarea /></div></Field>
      <Field label="Situação"><Select><SelectTrigger><SelectValue placeholder="Selecione" /></SelectTrigger></Select></Field></>)
    const text = screen.getByRole('textbox', { name: 'Observação' })
    const select = screen.getByRole('combobox', { name: 'Situação' })
    expect(text).toHaveAccessibleDescription('Até 200 caracteres')
    expect(select.id).not.toBe(text.id)
  })

  it('abre registro por teclado uma vez sem disparar ação secundária', async () => {
    const user = userEvent.setup()
    const open = vi.fn()
    const secondary = vi.fn()
    render(<DataTable rows={[{ id: 42 }]} rowKey={(r) => r.id} onRowClick={open}
      columns={[{ key: 'id', header: 'Código', cell: (r) => r.id },
        { key: 'action', header: 'Ações', cell: () => <button onClick={(e) => { e.stopPropagation(); secondary() }}>Editar</button> }]} />)
    await user.tab()
    expect(screen.getByRole('button', { name: 'Abrir registro 42' })).toHaveFocus()
    await user.keyboard('{Enter}')
    expect(open).toHaveBeenCalledTimes(1)
    await user.tab()
    await user.keyboard('{Enter}')
    expect(secondary).toHaveBeenCalledTimes(1)
    expect(open).toHaveBeenCalledTimes(1)
  })
})
