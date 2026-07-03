import { describe, it, expect } from 'vitest'
import { brl, num, qtd, pct, data } from './format'

/** FE-3 — formatadores centrais (pt-BR). Fixa o contrato que as páginas dependem. */
describe('format', () => {
  it('brl formata moeda e aceita string|number|null', () => {
    //   = espaço não-quebrável que o Intl usa após "R$".
    expect(brl(1234.5)).toBe('R$ 1.234,50')
    expect(brl('1234.5')).toBe('R$ 1.234,50')
    expect(brl(null)).toBe('R$ 0,00')
  })

  it('num agrupa e respeita casas fixas / máximas', () => {
    expect(num(1234)).toBe('1.234')
    expect(num(1234.5, 2)).toBe('1.234,50')
    expect(num(1234.5, 0, 4)).toBe('1.234,5') // até 4 casas, sem zeros à direita
  })

  it('qtd usa até 4 casas sem zeros à direita', () => {
    expect(qtd(10)).toBe('10')
    expect(qtd(10.25)).toBe('10,25')
  })

  it('pct anexa o símbolo', () => {
    expect(pct(50)).toBe('50%')
    expect(pct(12.5, 1)).toBe('12,5%')
  })

  it('data devolve travessão quando vazia', () => {
    expect(data(null)).toBe('—')
    expect(data(undefined)).toBe('—')
  })
})
