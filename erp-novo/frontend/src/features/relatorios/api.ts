import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'

/** Relatórios (C8): catálogo + consulta JSON + export CSV/PDF (blob). */

export interface RelatorioDef {
  slug: string
  titulo: string
  /** precisa de período (inicio/fim)? */
  periodo: boolean
  /** parâmetro extra opcional, ex.: mês para aniversariantes */
  mes?: boolean
}

export const RELATORIOS: RelatorioDef[] = [
  { slug: 'vendas', titulo: 'Vendas por período', periodo: true },
  { slug: 'financeiro', titulo: 'Posição financeira', periodo: true },
  { slug: 'dre', titulo: 'DRE (resultado)', periodo: true },
  { slug: 'movimentacao-caixa', titulo: 'Movimentação de caixa', periodo: true },
  { slug: 'fechamentos-caixa', titulo: 'Fechamentos de caixa', periodo: true },
  { slug: 'comissoes', titulo: 'Comissões por colaborador', periodo: true },
  { slug: 'vale-gas', titulo: 'Vale-gás por situação', periodo: true },
  { slug: 'estoque-baixo', titulo: 'Estoque abaixo do mínimo', periodo: false },
  { slug: 'comodatos', titulo: 'Comodatos em aberto', periodo: false },
  { slug: 'aniversariantes', titulo: 'Aniversariantes do mês', periodo: false, mes: true },
]

export function useRelatorio(slug: string, params: Record<string, unknown>, enabled: boolean) {
  return useQuery<unknown>({
    queryKey: ['relatorio', slug, params],
    queryFn: async () => (await api.get(`/relatorios/${slug}`, { params })).data.data,
    enabled,
  })
}

/** Baixa o relatório no formato dado (csv|pdf) disparando o download no browser. */
export async function baixarRelatorio(slug: string, formato: 'csv' | 'pdf', params: Record<string, unknown>): Promise<void> {
  const resp = await api.get(`/relatorios/${slug}`, { params: { ...params, formato }, responseType: 'blob' })
  const url = URL.createObjectURL(resp.data as Blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `${slug}.${formato}`
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(url)
}
