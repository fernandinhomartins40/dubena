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

// ── Inconsistências (T4.1) ───────────────────────────────────────────────────
// Prováveis ruas/bairros duplicados, detectados por similaridade de nome.
// A tela só é útil com a AÇÃO de ignorar: sem ela os mesmos falsos positivos
// reaparecem a cada consulta e a fila nunca esvazia.

export interface ParInconsistente {
  tipo: 'rua' | 'bairro'
  cidade_id: number
  a: { id: number; descricao: string }
  b: { id: number; descricao: string }
  similaridade: number
}

export type TipoInconsistencia = 'todas' | 'ruas' | 'bairros'

export function useInconsistencias(tipo: TipoInconsistencia) {
  return useQuery<{ data: ParInconsistente[] }>({
    queryKey: ['geo-inconsistencias', tipo],
    queryFn: async () => (await api.get('/cadastros/inconsistencias', { params: { tipo } })).data,
  })
}

export function useIgnorarPar() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (par: { tipo: 'rua' | 'bairro'; item_id: number; item_ignorado_id: number; motivo?: string }) =>
      (await api.post('/cadastros/inconsistencias/ignorar', par)).data,
    // Invalida a lista inteira: o par sai da fila imediatamente.
    onSuccess: () => qc.invalidateQueries({ queryKey: ['geo-inconsistencias'] }),
  })
}
