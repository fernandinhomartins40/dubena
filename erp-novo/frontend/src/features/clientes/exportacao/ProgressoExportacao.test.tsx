import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { render, screen, act } from '@testing-library/react'
import { ProgressoExportacao } from './ProgressoExportacao'

/**
 * O contrato do feedback de exportação.
 *
 * A regra que mais importa: a barra NUNCA chega a 100% sozinha. O backend
 * devolve o arquivo numa resposta só — não há progresso real para ouvir —,
 * então a barra é estimativa. Se ela completasse antes da resposta, ficaria
 * cheia com o usuário esperando, que é o pior sinal que uma barra pode dar.
 */
describe('ProgressoExportacao', () => {
  beforeEach(() => vi.useFakeTimers())
  afterEach(() => vi.useRealTimers())

  function avancar(ms: number) {
    act(() => { vi.advanceTimersByTime(ms) })
  }

  it('nunca alcança 100%, por mais que o tempo passe', () => {
    render(<ProgressoExportacao formato="xlsx" totalLinhas={50000} />)

    // Muito além da estimativa (~95s para 50 mil linhas em xlsx).
    avancar(10 * 60 * 1000)

    const barra = screen.getByRole('progressbar')
    const valor = Number(barra.getAttribute('aria-valuenow'))
    expect(valor).toBeLessThanOrEqual(95)
    expect(valor).toBeGreaterThan(80) // mas também não estaciona no começo
  })

  it('avança ao longo do tempo', () => {
    render(<ProgressoExportacao formato="xlsx" totalLinhas={50000} />)

    avancar(5000)
    const inicial = Number(screen.getByRole('progressbar').getAttribute('aria-valuenow'))

    avancar(30000)
    const depois = Number(screen.getByRole('progressbar').getAttribute('aria-valuenow'))

    expect(depois).toBeGreaterThan(inicial)
  })

  it('mostra o volume e o formato para o usuário saber o que espera', () => {
    render(<ProgressoExportacao formato="xlsx" totalLinhas={51793} />)

    expect(screen.getByText(/51\.793 clientes em XLSX/)).toBeInTheDocument()
    // A promessa que acalma: a janela pode ficar aberta, o download vem só.
    expect(screen.getByText(/download começa sozinho/)).toBeInTheDocument()
  })

  it('estima menos tempo para CSV do que para XLSX no mesmo volume', () => {
    // É o dado que sustenta a recomendação do modal ("o CSV sai em segundos").
    // Medido: 55 mil linhas levam ~100s em XLSX e ~1s em CSV.
    const { unmount } = render(<ProgressoExportacao formato="csv" totalLinhas={50000} />)
    avancar(3000)
    const csv = Number(screen.getByRole('progressbar').getAttribute('aria-valuenow'))
    unmount()

    render(<ProgressoExportacao formato="xlsx" totalLinhas={50000} />)
    avancar(3000)
    const xlsx = Number(screen.getByRole('progressbar').getAttribute('aria-valuenow'))

    expect(csv).toBeGreaterThan(xlsx)
  })
})
