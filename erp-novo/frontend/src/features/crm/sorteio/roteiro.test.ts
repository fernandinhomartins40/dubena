import { describe, expect, it } from 'vitest'
import { montarRoteiro } from './roteiro'

const participantes = Array.from({ length: 40 }, (_, i) => ({ numero: String(i + 1).padStart(4, '0'), nome: `P${i + 1}` }))
const ganhador = { numero: '0017', nome: 'Maria' }

// Gerador determinístico: o teste não pode depender da sorte.
function semente(s: number) {
  return () => {
    s = (s * 16807) % 2147483647
    return (s - 1) / 2147483646
  }
}

describe('roteiro da roleta', () => {
  it('termina sempre no ganhador que o servidor decidiu', () => {
    for (let s = 1; s < 50; s++) {
      const r = montarRoteiro(participantes, ganhador, { sortear: semente(s) })
      expect(r[r.length - 1]).toMatchObject({ numero: '0017', nome: 'Maria' })
    }
  })

  it('desacelera: a última troca é bem mais lenta que a primeira', () => {
    const r = montarRoteiro(participantes, ganhador, { sortear: semente(7) })
    expect(r.length).toBeGreaterThan(20)
    expect(r[0].duracao).toBeLessThan(80)
    expect(r[r.length - 1].duracao).toBeGreaterThan(400)
    // Nunca acelera de novo no meio do caminho.
    for (let k = 1; k < r.length; k++) expect(r[k].duracao).toBeGreaterThanOrEqual(r[k - 1].duracao)
  })

  it('dura o tempo combinado', () => {
    const r = montarRoteiro(participantes, ganhador, { duracaoTotal: 7000, sortear: semente(3) })
    const total = r.reduce((s, q) => s + q.duracao, 0)
    expect(total).toBeGreaterThan(6500)
    expect(total).toBeLessThan(8000)
  })

  it('não repete o mesmo número em sequência quando há escolha', () => {
    const r = montarRoteiro(participantes.slice(0, 3), ganhador, { sortear: semente(11) })
    for (let k = 1; k < r.length - 1; k++) expect(r[k].numero).not.toBe(r[k - 1].numero)
  })

  it('funciona com um único participante', () => {
    const r = montarRoteiro([ganhador], ganhador)
    expect(r.every((q) => q.numero === '0017')).toBe(true)
  })
})
