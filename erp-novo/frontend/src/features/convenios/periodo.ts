/**
 * Período sugerido para fechar um convênio — o que a pessoa só precisa conferir.
 *
 * Fim: a data de fechamento mais recente que já chegou (o "dia de fechamento"
 * do convênio neste mês, ou no anterior se ainda não chegou; em mês curto, o
 * último dia). Início: o dia seguinte ao último fechamento; sem fechamento
 * anterior, um mês antes do fim.
 *
 * Datas em texto `AAAA-MM-DD` e contas em UTC: o fuso do navegador não pode
 * empurrar o dia (o mesmo cuidado do restante da base com datas puras).
 */
export function periodoSugerido(diaFechamento: number | null, ultimoFim: string | null, hoje: string): { inicio: string; fim: string } {
  const [a, m, d] = hoje.split('-').map(Number)
  const dia = diaFechamento && diaFechamento >= 1 && diaFechamento <= 31 ? diaFechamento : ultimoDia(a, m)

  let ano = a
  let mes = m
  if (d < Math.min(dia, ultimoDia(a, m))) {
    mes -= 1
    if (mes === 0) { mes = 12; ano -= 1 }
  }
  const fim = data(ano, mes, Math.min(dia, ultimoDia(ano, mes)))

  if (ultimoFim) return { inicio: somarDias(ultimoFim, 1), fim }

  let mi = mes - 1
  let ai = ano
  if (mi === 0) { mi = 12; ai -= 1 }
  return { inicio: somarDias(data(ai, mi, Math.min(dia, ultimoDia(ai, mi))), 1), fim }
}

function ultimoDia(ano: number, mes: number): number {
  return new Date(Date.UTC(ano, mes, 0)).getUTCDate()
}

function data(ano: number, mes: number, dia: number): string {
  return `${ano}-${String(mes).padStart(2, '0')}-${String(dia).padStart(2, '0')}`
}

function somarDias(iso: string, n: number): string {
  const [a, m, d] = iso.slice(0, 10).split('-').map(Number)
  const t = new Date(Date.UTC(a, m - 1, d + n))
  return data(t.getUTCFullYear(), t.getUTCMonth() + 1, t.getUTCDate())
}
