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
    // O servidor EXIGE o período; antes ia só o id e todo fechamento pela
    // tela voltava 422.
    mutationFn: async ({ id, periodo_inicio, periodo_fim }: { id: number; periodo_inicio: string; periodo_fim: string }) =>
      (await api.post(`/convenios/${id}/fechar`, { periodo_inicio, periodo_fim })).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['convenios'] }),
  })
}

/** Fechamentos anteriores — o último define onde o próximo período começa. */
export const useFechamentosConvenio = (id: number | null) =>
  useQuery<{ id: number; periodo_inicio: string; periodo_fim: string }[]>({
    queryKey: ['convenios', id, 'fechamentos'], enabled: id !== null,
    queryFn: async () => (await api.get(`/convenios/${id}/fechamentos`)).data.data,
  })
