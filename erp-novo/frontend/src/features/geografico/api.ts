import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

export interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export interface Cidade { id: number; descricao: string; uf: string; cod_ibge: number; grupo_id: number | null }
export interface Bairro { id: number; descricao: string; cidade_id: number; cidade: string | null }
export interface Rua { id: number; descricao: string; cidade_id: number; cidade: string | null; cep: string | null; ativo: number }
export interface Regiao { id: number; descricao: string; ativo: number }

/** Hook genérico de lista paginada para uma entidade geográfica. */
function useLista<T>(entidade: string, params: Record<string, unknown>) {
  return useQuery<Paginated<T>>({
    queryKey: [`geo-${entidade}`, params],
    queryFn: async () => (await api.get(`/geo/${entidade}`, { params })).data,
    placeholderData: (prev) => prev,
  })
}

export const useCidades = (q: string, uf: string, page: number) => useLista<Cidade>('cidades', { q, uf, page })
export const useBairros = (q: string, cidade_id: number | null, page: number) => useLista<Bairro>('bairros', { q, cidade_id, page })
export const useRuas = (q: string, cidade_id: number | null, page: number) => useLista<Rua>('ruas', { q, cidade_id, page })
export const useRegioes = (q: string, page: number) => useLista<Regiao>('regioes', { q, page })

/** Mutação genérica de salvar (cria/edita) para uma entidade. */
export function useSalvarGeo(entidade: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...data }: { id?: number } & Record<string, unknown>) =>
      id ? (await api.put(`/geo/${entidade}/${id}`, data)).data : (await api.post(`/geo/${entidade}`, data)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: [`geo-${entidade}`] }),
  })
}

export function useExcluirGeo(entidade: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => (await api.delete(`/geo/${entidade}/${id}`)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: [`geo-${entidade}`] }),
  })
}
