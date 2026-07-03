import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { Can } from './can'

// Mocka o useAuth: o Can é só açúcar de UX sobre can(); testamos o gate de render.
const canMock = vi.fn()
vi.mock('@/lib/auth', () => ({ useAuth: () => ({ can: canMock }) }))

describe('<Can>', () => {
  it('renderiza os filhos quando permitido', () => {
    canMock.mockReturnValue(true)
    render(<Can permission="pedido.create"><span>ok</span></Can>)
    expect(screen.getByText('ok')).toBeInTheDocument()
  })

  it('renderiza o fallback quando negado', () => {
    canMock.mockReturnValue(false)
    render(<Can permission="pedido.create" fallback={<span>bloqueado</span>}><span>ok</span></Can>)
    expect(screen.queryByText('ok')).not.toBeInTheDocument()
    expect(screen.getByText('bloqueado')).toBeInTheDocument()
  })
})
