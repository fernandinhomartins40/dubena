import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

export interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export interface Cidade { id: number; descricao: string; uf: string; cod_ibge: number; grupo_id: number | null }
export interface Bairro { id: number; descricao: string; cidade_id: number; cidade: string | null }
export interface Rua {
  id: number
  descricao: string
  cidade_id: number
  cidade: string | null
  /** Vínculo que o legado tinha e o schema novo havia perdido; preenchido pela importação. */
  bairro_id: number | null
  bairro: { id: number; descricao: string } | null
  cep: string | null
  ativo: number
}
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

// ── Catálogo oficial do IBGE ─────────────────────────────────────────────────
// Cidade nova passa a ser SELECIONADA daqui. Digitar o código à mão produziu,
// na base real, código inventado, zerado e o código de outra cidade — e ele vai
// para o XML da NF-e, onde errado significa rejeição da SEFAZ.

export interface MunicipioIbge { cod_ibge: number; nome: string; uf: string }

export function useMunicipiosIbge(q: string, uf: string) {
  return useQuery<{ data: MunicipioIbge[] }>({
    queryKey: ['municipios-ibge', q, uf],
    queryFn: async () => (await api.get('/municipios-ibge', { params: { q, uf } })).data,
    // Só busca com 2+ letras: a lista nacional tem 5.570 municípios.
    enabled: q.trim().length >= 2 || uf !== '',
  })
}

export function useAdotarMunicipio() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (cod_ibge: number) => (await api.post('/municipios-ibge/adotar', { cod_ibge })).data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['geo-cidades'] })
      qc.invalidateQueries({ queryKey: ['ibge-conciliacao'] })
    },
  })
}

export interface Divergencia {
  cidade_id: number
  cidade: string
  uf: string
  cod_ibge_atual: number | null
  cod_ibge_correto: number | null
  nome_oficial: string | null
  criterio: 'nome' | 'codigo_uf_divergente' | 'sem_correspondencia'
}

export function useConciliacaoIbge() {
  return useQuery<{ data: Divergencia[] }>({
    queryKey: ['ibge-conciliacao'],
    queryFn: async () => (await api.get('/municipios-ibge/conciliacao')).data,
  })
}

export function useAplicarConciliacao() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => (await api.post('/municipios-ibge/conciliacao/aplicar')).data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['ibge-conciliacao'] })
      qc.invalidateQueries({ queryKey: ['geo-cidades'] })
    },
  })
}

// ── Importação de logradouros (base de CEP) ──────────────────────────────────

export interface Importacao {
  id: number
  cidade_id: number
  cidade: string | null
  uf: string | null
  situacao: 'processando' | 'concluida' | 'falhou'
  ruas_criadas: number
  bairros_criados: number
  ruas_atualizadas: number
  consultas: number
  /** Ramos que continuaram no teto da fonte: a importação pode estar incompleta. */
  termos_truncados: number
  erro: string | null
  criado_em: string
}

export function useImportacoes() {
  const { data } = useQuery<{ data: Importacao[] }>({
    queryKey: ['logradouro-importacoes'],
    queryFn: async () => (await api.get('/logradouros/importacoes')).data,
    // Enquanto houver importação em curso, acompanha sozinho: a varredura leva
    // minutos e o operador não deveria ter que recarregar a página.
    refetchInterval: (query) =>
      query.state.data?.data.some((i) => i.situacao === 'processando') ? 5000 : false,
  })
  return data?.data ?? []
}

export function useImportarLogradouros() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (cidade_id: number) => (await api.post('/logradouros/importacoes', { cidade_id })).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['logradouro-importacoes'] }),
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
