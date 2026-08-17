import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { abrirPdf } from '@/lib/pdf'

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

/** Abre o vale numa aba para impressão. */
export const abrirValePdf = (id: number) => abrirPdf(`/vale-gas/${id}/pdf`)

/** Abre a duplicata (vale vendido a prazo). */
export const abrirDuplicata = (id: number) => abrirPdf(`/vale-gas/${id}/duplicata`)
