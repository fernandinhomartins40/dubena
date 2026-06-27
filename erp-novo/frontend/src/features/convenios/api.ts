import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

/** Convênios (faturamento consolidado por cliente) — domínio do ERP (N8). */
export interface Convenio {
  id: number; cliente_id: number | null; descricao: string; dia_fechamento: number | null; dia_vencimento: number | null; ativo: boolean
  cliente?: { id: number; nome: string }
}
export const useConvenios = () =>
  useQuery<Convenio[]>({ queryKey: ['convenios'], queryFn: async () => (await api.get('/convenios')).data.data })
export function useCriarConvenio() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (data: Record<string, unknown>) => (await api.post('/convenios', data)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['convenios'] }),
  })
}
export function useFecharConvenio() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => (await api.post(`/convenios/${id}/fechar`)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['convenios'] }),
  })
}
