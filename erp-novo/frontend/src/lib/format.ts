/**
 * Formatadores centrais do SPA. Use SEMPRE estes helpers nas páginas — não
 * reescreva `toLocaleString`/`new Date` espalhado. Padrão pt-BR.
 */

/** Moeda BRL. Aceita string|number (a API devolve decimais como string). */
export function brl(v: string | number | null | undefined): string {
  const n = typeof v === 'string' ? Number(v) : v
  return (n ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

/**
 * Número com agrupamento pt-BR. `casas` = casas decimais FIXAS (default 0).
 * Para "até N casas" (sem zeros à direita), passe `maxCasas`.
 */
export function num(v: string | number | null | undefined, casas = 0, maxCasas?: number): string {
  const n = typeof v === 'string' ? Number(v) : v
  return (n ?? 0).toLocaleString('pt-BR', {
    minimumFractionDigits: casas,
    maximumFractionDigits: maxCasas ?? casas,
  })
}

/** Quantidade de estoque/produto: agrupamento pt-BR com até 4 casas (sem zeros à direita). */
export function qtd(v: string | number | null | undefined): string {
  return num(v, 0, 4)
}

/** Percentual (recebe 0–100). */
export function pct(v: string | number | null | undefined, casas = 0): string {
  const n = typeof v === 'string' ? Number(v) : v
  return `${(n ?? 0).toLocaleString('pt-BR', { minimumFractionDigits: casas, maximumFractionDigits: casas })}%`
}

/** Data (dd/mm/aaaa) ou travessão quando vazia. */
export function data(s: string | null | undefined): string {
  if (!s) return '—'
  // Data civil não é instante UTC: interpretar YYYY-MM-DD diretamente subtraía
  // um dia no Brasil. Horários completos continuam respeitando seu fuso.
  const civil = /^\d{4}-\d{2}-\d{2}$/.test(s)
  const date = new Date(civil ? `${s}T12:00:00` : s)
  if (civil && (date.getFullYear() !== Number(s.slice(0, 4)) || date.getMonth() + 1 !== Number(s.slice(5, 7)) || date.getDate() !== Number(s.slice(8, 10)))) return 'Data inválida'
  return Number.isNaN(date.getTime()) ? 'Data inválida' : date.toLocaleDateString('pt-BR')
}

/** Data e hora (dd/mm/aaaa hh:mm) ou travessão. */
export function dataHora(s: string | null | undefined): string {
  return s ? new Date(s).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '—'
}

/**
 * Lê um número digitado à brasileira: "12,50", "1.000,00", "R$ 5", "5%".
 * Devolve NaN quando não é número — NUNCA null. Isso importa: `Number("5%")` é
 * NaN, e o JSON serializa NaN como `null`; na contraproposta da Central de
 * Vendas `null` significa "aprovar o desconto pedido", e no teto de alçada
 * "sem teto" (PIE R2-07). O chamador decide o que fazer com o NaN.
 */
export function decimalBR(texto: string): number {
  let t = texto.trim().replace(/^R\$\s*/i, '').replace(/\s*%$/, '').replace(/\s/g, '')
  if (t.includes(',')) t = t.replace(/\./g, '').replace(',', '.')
  if (!/^-?\d+(\.\d+)?$/.test(t)) return Number.NaN
  return Number(t)
}

const dois = (n: number) => String(n).padStart(2, '0')

/**
 * Data civil LOCAL (AAAA-MM-DD) para campos e filtros. `toISOString()` é UTC:
 * no Brasil, depois das 21h ele já devolve AMANHÃ — malote, ponto, devolução e
 * "até hoje" dos relatórios saíam com o dia seguinte (PIE R2-06).
 */
export function hojeISO(d: Date = new Date()): string {
  return `${d.getFullYear()}-${dois(d.getMonth() + 1)}-${dois(d.getDate())}`
}

/** Competência local (AAAA-MM). */
export function competenciaISO(d: Date = new Date()): string {
  return hojeISO(d).slice(0, 7)
}

/**
 * Data e hora LOCAIS (AAAA-MM-DD HH:MM:SS) para gravar um instante de parede,
 * como abertura/fechamento de caixa — em UTC ficavam 3 horas adiantadas e o
 * fechamento das 22h caía no dia seguinte (PIE R2-05).
 */
export function agoraLocal(d: Date = new Date()): string {
  return `${hojeISO(d)} ${dois(d.getHours())}:${dois(d.getMinutes())}:${dois(d.getSeconds())}`
}

/** Travessão para valores vazios — padroniza o "sem dado" nas tabelas. */
export const vazio = '—'
