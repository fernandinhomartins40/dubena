import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'


// ---- Vale-Gás ----
export const useValeGas = (q: string) => useQuery<any[]>({ queryKey: ['valegas', q], queryFn: async () => (await api.get('/vale-gas', { params: { q } })).data.data })
export const useValeGasSituacoes = () => useQuery<any[]>({ queryKey: ['valegas-sit'], queryFn: async () => (await api.get('/vale-gas/situacoes')).data.data })
export function useBaixarValeGas() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (d: { codigo: string; situacao_id: number }) => (await api.post('/vale-gas/baixar', d)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['valegas'] }) })
}

// ---- Satélites status ----
export const useRelatorios = () => useQuery<any[]>({ queryKey: ['sat-rel'], queryFn: async () => (await api.get('/satelites/relatorios')).data.data })
export const useMonitoramento = () => useQuery({ queryKey: ['sat-mon'], queryFn: async () => (await api.get('/satelites/monitoramento')).data.data })
export const useIntegracoes = () => useQuery({ queryKey: ['sat-int'], queryFn: async () => (await api.get('/satelites/integracoes')).data.data })
