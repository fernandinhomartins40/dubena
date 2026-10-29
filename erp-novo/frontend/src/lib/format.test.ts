import { describe, it, expect } from 'vitest'
import { brl, num, qtd, pct, data, decimalBR, hojeISO, competenciaISO, agoraLocal } from './format'

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
  it('preserva datas civis e rejeita datas impossíveis', () => {
    expect(data('2026-09-30')).toBe('30/09/2026')
    expect(data('2024-02-29')).toBe('29/02/2024')
    expect(data('2026-02-30')).toBe('Data inválida')
    expect(data('invalida')).toBe('Data inválida')
  })
})

/** PIE R2-05/R2-06/R2-07 — datas locais e número digitado à brasileira. */
describe('format — entrada e datas locais', () => {
  it('hojeISO/agoraLocal usam a hora de parede, não UTC (22h30 no Brasil não é amanhã)', () => {
    const noite = new Date(2026, 9, 28, 22, 30, 5) // construtor local
    expect(hojeISO(noite)).toBe('2026-10-28')
    expect(competenciaISO(noite)).toBe('2026-10')
    expect(agoraLocal(noite)).toBe('2026-10-28 22:30:05')
  })

  it('decimalBR lê o que o operador digita e devolve NaN (nunca null) para o resto', () => {
    expect(decimalBR('12,50')).toBe(12.5)
    expect(decimalBR('1.000,00')).toBe(1000)
    expect(decimalBR('R$ 5')).toBe(5)
    expect(decimalBR('5%')).toBe(5)
    expect(decimalBR('7.5')).toBe(7.5)
    expect(decimalBR('abc')).toBeNaN()
    expect(decimalBR('')).toBeNaN()
    // O defeito: NaN viajava como null e virava "aprovar tudo".
    expect(JSON.stringify({ v: decimalBR('cinco') })).toBe('{"v":null}')
  })
})
