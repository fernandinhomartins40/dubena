import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

/** Vale-Gás (cupom pré-pago) — domínio do ERP (N8). */
export const useValeGas = (q: string) => useQuery<any[]>({ queryKey: ['valegas', q], queryFn: async () => (await api.get('/vale-gas', { params: { q } })).data.data })
export const useValeGasSituacoes = () => useQuery<any[]>({ queryKey: ['valegas-sit'], queryFn: async () => (await api.get('/vale-gas/situacoes')).data.data })
export function useBaixarValeGas() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (d: { codigo: string; situacao_id: number }) => (await api.post('/vale-gas/baixar', d)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['valegas'] }) })
}
