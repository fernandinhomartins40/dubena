import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

export interface ProdutoListItem {
  id: number
  descricao: string
  preco_venda: number | string | null
  ativo: number | null
  nfe_permite: number | null
  produtoclasse_id: number
  classe: string | null
}

export interface OrigemCombustivel {
  id?: number
  ind_import: number
  cuf_orig: number
  p_orig: number
  uf?: string | null
}

/**
 * Papel do produto no ciclo de CUSTÓDIA (F3-02).
 *
 * A vigilância de comodato precisa distinguir o casco que se empresta do gás
 * que se vende dentro dele. Antes isso era lido da descrição (`VASILHA`,
 * `CASCO`, `GLP`…), o que só funciona para quem escreveu essas palavras.
 */
export type TipoProduto = 'INDEFINIDO' | 'RECIPIENTE' | 'CONTEUDO' | 'MERCADORIA'

export interface ProdutoForm {
  // Dados
  descricao: string
  tipo?: TipoProduto
  /** Rótulo da grade comercial ("P13"), não medida — o par casco↔gás é por igualdade exata. */
  capacidade?: string | null
  produtoclasse_id: number | null
  unidademedida_id: number | null
  vasilhame_retornavel: boolean
  produto_retornavel_id?: number | null
  ativo: boolean
  envia_app_nf?: boolean
  dias_giro?: number | null
  observacao?: string | null
  // Preços
  preco_venda?: number | string | null
  preco_venda_minimo?: number | string | null
  custo_medio?: number | string | null
  custo_frete?: number | string | null
  preco_gasdopovo?: number | string | null
  peso_liquido?: number | string | null
  peso_bruto?: number | string | null
  // Fiscal / NF-e
  nfe_permite?: boolean
  sped?: boolean
  nfe_tipo_item?: number | null
  grupo_fiscal_id?: number | null
  nfipi_id?: number | null
  nfe_aliq_ipi?: number | string | null
  nfe_bc_ipi?: number | string | null
  nfe_cod_enquadramento_ipi?: number | null
  nfe_ex_tipi?: string | null
  nfe_descricao_fiscal?: string | null
  nfe_nat_rec?: string | null
  ean?: string | null
  ean_trib?: string | null
  ncm?: string | null
  nfe_cest?: string | null
  especie?: string | null
  marca?: string | null
  nfe_cod_gen?: number | null
  nfe_cod_lst?: number | null
  // GLP
  tipo_glp?: number | null
  ressarcimentoproduto_id?: number | null
  nfe_cprod_anp?: string | null
  nfe_desc_anp?: string | null
  nfe_qbc_prod?: number | string | null
  nfe_valiq_prod?: number | string | null
  nfe_vcide?: number | string | null
  pgni?: number | string | null
  pgnn?: number | string | null
  pglp?: number | string | null
  // Origens (sub-recurso)
  origens?: OrigemCombustivel[]
}

interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export function useProdutos(q: string, page: number) {
  return useQuery<Paginated<ProdutoListItem>>({
    queryKey: ['produtos', q, page],
    queryFn: async () => (await api.get('/produtos', { params: { q, page } })).data,
    placeholderData: (prev) => prev,
  })
}

export function useProduto(id: number | null) {
  return useQuery({
    queryKey: ['produto', id],
    queryFn: async () => (await api.get(`/produtos/${id}`)).data.data,
    enabled: id !== null,
  })
}

export function useSalvarProduto() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, data }: { id: number | null; data: ProdutoForm }) => {
      if (id) return (await api.put(`/produtos/${id}`, data)).data.data
      return (await api.post('/produtos', data)).data.data
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['produtos'] }),
  })
}

export interface EstoqueProduto {
  dias_giro: number
  total: number
  setores: { setor: string; quantidade: number; minima: number; maxima: number }[]
}

export function useEstoqueProduto(id: number | null) {
  return useQuery<EstoqueProduto>({
    queryKey: ['produto-estoque', id],
    queryFn: async () => (await api.get(`/produtos/${id}/estoque`)).data.data,
    enabled: id !== null,
  })
}

export function useExcluirProduto() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => (await api.delete(`/produtos/${id}`)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['produtos'] }),
  })
}

// ---- Configurações (Classes / Unidades) ----
export interface ClasseItem { id: number; descricao: string; tipo: string | null; ativo: number }
export interface UnidadeItem { id: number; descricao: string; sigla: string | null; ativo: number }

export function useClasses() {
  return useQuery<ClasseItem[]>({ queryKey: ['produto-classes-crud'], queryFn: async () => (await api.get('/produto-config/classes')).data.data })
}
export function useUnidades() {
  return useQuery<UnidadeItem[]>({ queryKey: ['produto-unidades-crud'], queryFn: async () => (await api.get('/produto-config/unidades')).data.data })
}
export function useSalvarClasse() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...data }: { id?: number; descricao: string; tipo?: string; ativo?: boolean }) =>
      id ? (await api.put(`/produto-config/classes/${id}`, data)).data : (await api.post('/produto-config/classes', data)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['produto-classes-crud'] }),
  })
}
export function useExcluirClasse() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (id: number) => (await api.delete(`/produto-config/classes/${id}`)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['produto-classes-crud'] }) })
}
export function useSalvarUnidade() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...data }: { id?: number; descricao: string; sigla?: string; ativo?: boolean }) =>
      id ? (await api.put(`/produto-config/unidades/${id}`, data)).data : (await api.post('/produto-config/unidades', data)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['produto-unidades-crud'] }),
  })
}
export function useExcluirUnidade() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (id: number) => (await api.delete(`/produto-config/unidades/${id}`)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['produto-unidades-crud'] }) })
}

// ---- Atualizar preços em massa ----
export interface PrecoPreviewItem { id: number; descricao: string; atual: number; novo: number }
export interface PrecoParams { tipo: 'percentual' | 'fixo'; valor: number; classe_id?: number | null }

export function usePrecosPreview() {
  return useMutation({
    mutationFn: async (p: PrecoParams) => (await api.get('/produtos-precos/preview', { params: p })).data.data as PrecoPreviewItem[],
  })
}
export function usePrecosAplicar() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (p: PrecoParams) => (await api.put('/produtos-precos/aplicar', p)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['produtos'] }),
  })
}
