import { describe, expect, it } from 'vitest'
import { periodoSugerido } from './periodo'

describe('período sugerido do fechamento de convênio', () => {
  it('dia de fechamento já passou neste mês: fecha neste mês, a partir do último fechamento', () => {
    expect(periodoSugerido(5, '2026-09-05', '2026-10-11')).toEqual({ inicio: '2026-09-06', fim: '2026-10-05' })
  })

  it('ainda não chegou o dia: fecha o mês anterior', () => {
    expect(periodoSugerido(20, '2026-08-20', '2026-10-11')).toEqual({ inicio: '2026-08-21', fim: '2026-09-20' })
  })

  it('sem fechamento anterior: um mês antes do fim', () => {
    expect(periodoSugerido(10, null, '2026-10-11')).toEqual({ inicio: '2026-09-11', fim: '2026-10-10' })
  })

  it('dia 31 em mês curto vira o último dia; virada de ano', () => {
    expect(periodoSugerido(31, null, '2026-03-01')).toEqual({ inicio: '2026-02-01', fim: '2026-02-28' })
    expect(periodoSugerido(15, null, '2026-01-10')).toEqual({ inicio: '2025-11-16', fim: '2025-12-15' })
  })

  it('sem dia configurado: fecha no fim do mês', () => {
    expect(periodoSugerido(null, null, '2026-10-31')).toEqual({ inicio: '2026-10-01', fim: '2026-10-31' })
  })
})
