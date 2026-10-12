import { expect, it } from 'vitest'
import { periodos } from './periodos'

it('atalhos de período cobrem mês passado, virada de ano e semana', () => {
  const p = Object.fromEntries(periodos(new Date(2026, 0, 14)).map((x) => [x.rotulo, [x.inicio, x.fim]]))
  expect(p['Hoje']).toEqual(['2026-01-14', '2026-01-14'])
  expect(p['Ontem']).toEqual(['2026-01-13', '2026-01-13'])
  expect(p['Mês passado']).toEqual(['2025-12-01', '2025-12-31'])
  expect(p['Este mês']).toEqual(['2026-01-01', '2026-01-14'])
  expect(p['Esta semana']).toEqual(['2026-01-11', '2026-01-14']) // 14/01/2026 é quarta
  expect(periodos(new Date(2026, 2, 1)).find((x) => x.rotulo === 'Ontem')).toMatchObject({ inicio: '2026-02-28' })
})
