import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

export interface ProdutoListItem {
  id: number
  descricao: string
  precovenda: number | string | null
  ativo: number | null
  nfepermite: number | null
  produtoclasse_id: number
  classe: string | null
}

export interface OrigemCombustivel {
  id?: number
  indimport: number
  cuforig: number
  porig: number
  uf?: string | null
}

export interface ProdutoForm {
  // Dados
  descricao: string
  produtoclasse_id: number | null
  unidademedida_id: number | null
  vasilhameretornavel: boolean
  produtoretornavel_id?: number | null
  ativo: boolean
  enviaappnf?: boolean
  diasgiro?: number | null
  observacao?: string | null
  // Preços
  precovenda?: number | string | null
  precovendaminimo?: number | string | null
  customedio?: number | string | null
  custofrete?: number | string | null
  precogasdopovo?: number | string | null
  pesoliquido?: number | string | null
  pesobruto?: number | string | null
  // Fiscal / NF-e
  nfepermite?: boolean
  sped?: boolean
  nfetipoitem?: number | null
  nfgrupofiscal_id?: number | null
  nfipi_id?: number | null
  nfealiqipi?: number | string | null
  nfebcipi?: number | string | null
  nfecodenquadramentoipi?: number | null
  nfeextipi?: string | null
  nfedescricaofiscal?: string | null
  nfenatrec?: string | null
  ean?: string | null
  eantrib?: string | null
  ncm?: string | null
  nfcest?: string | null
  especie?: string | null
  marca?: string | null
  nfecodgen?: number | null
  nfecodlst?: number | null
  // GLP
  tipo_glp?: number | null
  ressarcimentoproduto_id?: number | null
  nfecprodanp?: string | null
  nfedescanp?: string | null
  nfeqbcprod?: number | string | null
  nfevaliqprod?: number | string | null
  nfevcide?: number | string | null
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

export function useExcluirProduto() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => (await api.delete(`/produtos/${id}`)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['produtos'] }),
  })
}
