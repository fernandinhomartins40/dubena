/**
 * Atalhos de período dos relatórios: "mês passado" ou "ontem" exigiam acertar
 * duas datas no calendário. Datas locais em `AAAA-MM-DD` (o relatório é por
 * dia do calendário da revenda, não por instante UTC).
 */
const iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`

export function periodos(hoje = new Date()): { rotulo: string; inicio: string; fim: string }[] {
  const d = (ano: number, mes: number, dia: number) => iso(new Date(ano, mes, dia))
  const a = hoje.getFullYear()
  const m = hoje.getMonth()
  const ontem = new Date(a, m, hoje.getDate() - 1)
  // Semana de domingo a hoje — como a revenda conta a semana no balcão.
  const domingo = new Date(a, m, hoje.getDate() - hoje.getDay())
  return [
    { rotulo: 'Hoje', inicio: iso(hoje), fim: iso(hoje) },
    { rotulo: 'Ontem', inicio: iso(ontem), fim: iso(ontem) },
    { rotulo: 'Esta semana', inicio: iso(domingo), fim: iso(hoje) },
    { rotulo: 'Este mês', inicio: d(a, m, 1), fim: iso(hoje) },
    { rotulo: 'Mês passado', inicio: d(a, m - 1, 1), fim: d(a, m, 0) },
    { rotulo: 'Este ano', inicio: d(a, 0, 1), fim: iso(hoje) },
  ]
}
