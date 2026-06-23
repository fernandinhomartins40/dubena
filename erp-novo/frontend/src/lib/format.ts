/**
 * Formatadores centrais do SPA. Use SEMPRE estes helpers nas páginas — não
 * reescreva `toLocaleString`/`new Date` espalhado. Padrão pt-BR.
 */

/** Moeda BRL. Aceita string|number (a API devolve decimais como string). */
export function brl(v: string | number | null | undefined): string {
  const n = typeof v === 'string' ? Number(v) : v
  return (n ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

/** Número com agrupamento pt-BR. */
export function num(v: string | number | null | undefined, casas = 0): string {
  const n = typeof v === 'string' ? Number(v) : v
  return (n ?? 0).toLocaleString('pt-BR', { minimumFractionDigits: casas, maximumFractionDigits: casas })
}

/** Percentual (recebe 0–100). */
export function pct(v: string | number | null | undefined, casas = 0): string {
  const n = typeof v === 'string' ? Number(v) : v
  return `${(n ?? 0).toLocaleString('pt-BR', { minimumFractionDigits: casas, maximumFractionDigits: casas })}%`
}

/** Data (dd/mm/aaaa) ou travessão quando vazia. */
export function data(s: string | null | undefined): string {
  return s ? new Date(s).toLocaleDateString('pt-BR') : '—'
}

/** Data e hora (dd/mm/aaaa hh:mm) ou travessão. */
export function dataHora(s: string | null | undefined): string {
  return s ? new Date(s).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '—'
}

/** Travessão para valores vazios — padroniza o "sem dado" nas tabelas. */
export const vazio = '—'
