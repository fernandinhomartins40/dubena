import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

export interface CadastroRow {
  id: number
  descricao: string
  ativo: number
  [k: string]: unknown
}

export function useCadastro(tipo: string) {
  return useQuery<CadastroRow[]>({
    queryKey: ['cadastro', tipo],
    queryFn: async () => (await api.get(`/cadastros/${tipo}`)).data.data,
  })
}

export function useSalvarCadastro(tipo: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...data }: { id?: number } & Record<string, unknown>) =>
      id ? (await api.put(`/cadastros/${tipo}/${id}`, data)).data : (await api.post(`/cadastros/${tipo}`, data)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['cadastro', tipo] }),
  })
}

export function useExcluirCadastro(tipo: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => (await api.delete(`/cadastros/${tipo}/${id}`)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['cadastro', tipo] }),
  })
}
