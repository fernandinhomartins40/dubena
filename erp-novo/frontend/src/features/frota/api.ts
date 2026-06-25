import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'


// ---- Frota / Veículos ----
export interface Veiculo { id: number; placa: string; descricao: string; kmatual: number; veiculotipo_id: number; tipocombustivel_id: number }
export const useVeiculos = (q: string) => useQuery<Veiculo[]>({ queryKey: ['veic', q], queryFn: async () => (await api.get('/veiculos', { params: { q } })).data.data })
export function useVeiculo(id: number | null) {
  return useQuery({ queryKey: ['veic', id], queryFn: async () => (await api.get(`/veiculos/${id}`)).data.data, enabled: id !== null })
}
export function useSalvarVeiculo() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, data }: { id: number | null; data: Record<string, unknown> }) => id ? (await api.put(`/veiculos/${id}`, data)).data.data : (await api.post('/veiculos', data)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['veic'] }),
  })
}
export function useExcluirVeiculo() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (id: number) => (await api.delete(`/veiculos/${id}`)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['veic'] }) })
}
export const useAbastecimentos = (id: number) => useQuery<any[]>({ queryKey: ['veic-ab', id], queryFn: async () => (await api.get(`/veiculos/${id}/abastecimentos`)).data.data })
export const useTrocasOleo = (id: number) => useQuery<any[]>({ queryKey: ['veic-ol', id], queryFn: async () => (await api.get(`/veiculos/${id}/trocas-oleo`)).data.data })
export const usePneus = (id: number) => useQuery<any[]>({ queryKey: ['veic-pn', id], queryFn: async () => (await api.get(`/veiculos/${id}/pneus`)).data.data })
