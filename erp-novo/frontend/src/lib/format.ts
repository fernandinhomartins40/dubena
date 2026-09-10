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

/** Travessão para valores vazios — padroniza o "sem dado" nas tabelas. */
export const vazio = '—'
