import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import { ErrorBoundary } from './ErrorBoundary'

// FE-1 — o boundary isola um erro de render e mostra o fallback (sem "tela branca").
function Explode(): never {
  throw new Error('boom')
}

describe('<ErrorBoundary>', () => {
  beforeEach(() => vi.spyOn(console, 'error').mockImplementation(() => {}))
  afterEach(() => vi.restoreAllMocks())

  it('mostra o fallback quando um filho estoura', () => {
    render(
      <ErrorBoundary fallback={<span>capturado</span>}>
        <Explode />
      </ErrorBoundary>,
    )
    expect(screen.getByText('capturado')).toBeInTheDocument()
  })

  it('renderiza os filhos normalmente sem erro', () => {
    render(
      <ErrorBoundary>
        <span>conteudo</span>
      </ErrorBoundary>,
    )
    expect(screen.getByText('conteudo')).toBeInTheDocument()
  })
})
