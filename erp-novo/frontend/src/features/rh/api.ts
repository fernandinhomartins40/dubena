import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

interface Paginated<T> { data: T[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }

// ---- RH / Colaboradores ----
export interface Colaborador {
  id: number; nome: string; cpf: string | null; dataadmissao: string | null
  datadesligamento: string | null; cargo: string | null
  // Situacao do CADASTRO — distinta do desligamento, que e fato trabalhista.
  ativo: boolean
  desativado_em: string | null
  motivo_desativacao: string | null
  desativado_por_nome: string | null
}

/** Filtro da lista. 'ativos' e o default do backend. */
export type SituacaoColaborador = 'ativos' | 'inativos' | 'todos'

export function useColaboradores(q: string, page: number, situacao: SituacaoColaborador = 'ativos') {
  return useQuery<Paginated<Colaborador>>({ queryKey: ['colab', q, page, situacao], queryFn: async () => (await api.get('/colaboradores', { params: { q, page, situacao } })).data, placeholderData: (p) => p })
}
export function useColaborador(id: number | null) {
  return useQuery({ queryKey: ['colab', id], queryFn: async () => (await api.get(`/colaboradores/${id}`)).data.data, enabled: id !== null })
}
export function useSalvarColaborador() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, data }: { id: number | null; data: Record<string, unknown> }) => id ? (await api.put(`/colaboradores/${id}`, data)).data.data : (await api.post('/colaboradores', data)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['colab'] }),
  })
}
/**
 * Desativa o colaborador (ativo = false). O DELETE nao apaga: familia, recessos,
 * comissoes, exames, turnos e ponto sao cascadeOnDelete e sumiriam junto.
 */
export function useDesativarColaborador() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, motivo }: { id: number; motivo?: string }) => (await api.delete(`/colaboradores/${id}`, { data: { motivo } })).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['colab'] }),
  })
}
export function useReativarColaborador() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (id: number) => (await api.post(`/colaboradores/${id}/reativar`)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['colab'] }) })
}
export const useFamilia = (id: number) => useQuery<any[]>({ queryKey: ['colab-fam', id], queryFn: async () => (await api.get(`/colaboradores/${id}/familia`)).data.data })
export function useAddFamilia(id: number) {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (d: Record<string, unknown>) => (await api.post(`/colaboradores/${id}/familia`, d)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['colab-fam', id] }) })
}
export function useDelFamilia(id: number) {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (famId: number) => (await api.delete(`/colaboradores/${id}/familia/${famId}`)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['colab-fam', id] }) })
}
export const useRecessos = (id: number) => useQuery<any[]>({ queryKey: ['colab-rec', id], queryFn: async () => (await api.get(`/colaboradores/${id}/recessos`)).data.data })
export const useComissoes = (id: number) => useQuery<any[]>({ queryKey: ['colab-com', id], queryFn: async () => (await api.get(`/colaboradores/${id}/comissoes`)).data.data })

// ---- RH complementar (C5): exames (ASO), turnos, ponto ----
export const useExames = (id: number) => useQuery<any[]>({ queryKey: ['colab-exa', id], queryFn: async () => (await api.get(`/colaboradores/${id}/exames`)).data.data })
export function useAddExame(id: number) {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (d: Record<string, unknown>) => (await api.post(`/colaboradores/${id}/exames`, d)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['colab-exa', id] }) })
}
export const useTurnos = (id: number) => useQuery<any[]>({ queryKey: ['colab-tur', id], queryFn: async () => (await api.get(`/colaboradores/${id}/turnos`)).data.data })
export function useAddTurno(id: number) {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (d: Record<string, unknown>) => (await api.post(`/colaboradores/${id}/turnos`, d)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['colab-tur', id] }) })
}
export const usePontos = (id: number) => useQuery<any[]>({ queryKey: ['colab-pon', id], queryFn: async () => (await api.get(`/colaboradores/${id}/pontos`)).data.data })
export function useAddPonto(id: number) {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (d: Record<string, unknown>) => (await api.post(`/colaboradores/${id}/pontos`, d)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['colab-pon', id] }) })
}
