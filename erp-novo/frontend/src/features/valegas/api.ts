import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

/** Vale-Gás (cupom pré-pago) — domínio do ERP (N8). */
export interface ValeGasRow {
  id: number
  codigo: string
  valor: number | string
  validade: string | null
  situacao: string
  financeiro_id: number | null
  cliente?: { id: number; nome: string } | null
}

export const useValeGas = (q: string) => useQuery<ValeGasRow[]>({
  queryKey: ['valegas', q],
  queryFn: async () => (await api.get('/vale-gas', { params: { q } })).data.data,
})

/** As situações vêm como lista de strings do enum `SituacaoValeGas`. */
export const useValeGasSituacoes = () => useQuery<string[]>({
  queryKey: ['valegas-sit'],
  queryFn: async () => (await api.get('/vale-gas/situacoes')).data.data,
})

export function useBaixarValeGas() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (d: { codigo: string; situacao: string }) => (await api.post('/vale-gas/baixar', d)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['valegas'] }),
  })
}

/**
 * Abre o vale numa aba para impressão.
 *
 * Vai por blob, e não por link direto, porque o Bearer token viaja no header.
 */
export async function abrirValePdf(id: number): Promise<void> {
  await abrirPdf(`/vale-gas/${id}/pdf`)
}

/** Abre a duplicata (vale vendido a prazo). */
export async function abrirDuplicata(id: number): Promise<void> {
  await abrirPdf(`/vale-gas/${id}/duplicata`)
}

async function abrirPdf(url: string): Promise<void> {
  const resp = await api.get(url, { responseType: 'blob' })
  const objectUrl = URL.createObjectURL(resp.data as Blob)
  window.open(objectUrl, '_blank', 'noopener')
  // Revoga tarde: revogar antes de a aba ler o blob mostra página em branco.
  setTimeout(() => URL.revokeObjectURL(objectUrl), 60_000)
}
