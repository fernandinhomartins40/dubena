import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

/**
 * Central de Vendas (F3) — a fila de solicitações do campo.
 *
 * Irmã da Central de Logística (`features/central`), não extensão: aquela decide
 * QUEM leva o pedido; esta decide SE vende e POR QUANTO.
 *
 * Como na de logística, a lista usa polling curto enquanto o Reverb não está
 * ligado em produção. O backend já emite `venda.solicitacao` no canal
 * `empresa.{id}.central` — quando o tempo real subir, basta assinar e invalidar.
 */

export interface SolicitacaoItem {
  produto_id: number
  quantidade: number
  preco_unitario: number
}

export interface Solicitacao {
  id: number
  cliente: { id: number; nome: string | null } | null
  solicitante: { id: number; name: string } | null
  itens: SolicitacaoItem[]
  desconto_solicitado: number
  desconto_aprovado: number | null
  justificativa: string | null
  observacao: string | null
  situacao: 'pendente' | 'aprovada' | 'recusada' | 'cancelada'
  motivo_decisao: string | null
  pedido: { id: number } | null
  created_at: string
}

/** Quanto do desconto pedido já caberia na alçada de quem pediu. */
export interface AnaliseAlcada {
  teto_do_solicitante: number
  desconto_solicitado: number
  excede_em: number
}

export function useSolicitacoes(situacao: string = 'pendente') {
  return useQuery<Solicitacao[]>({
    queryKey: ['central-vendas-fila', situacao],
    queryFn: async () => (await api.get('/central-vendas/solicitacoes', { params: { situacao } })).data.data,
    refetchInterval: 15000,
  })
}

export function useSolicitacao(id: number | null) {
  return useQuery<{ data: Solicitacao; alcada: AnaliseAlcada }>({
    queryKey: ['central-vendas-solicitacao', id],
    queryFn: async () => (await api.get(`/central-vendas/solicitacoes/${id}`)).data,
    enabled: id !== null,
  })
}

function useInvalidarVendas() {
  const qc = useQueryClient()
  return () => {
    qc.invalidateQueries({ queryKey: ['central-vendas-fila'] })
    qc.invalidateQueries({ queryKey: ['central-vendas-solicitacao'] })
  }
}

/** `desconto_aprovado` omitido = aprova o que foi pedido; menor = contraproposta. */
export function useAprovar() {
  const invalidar = useInvalidarVendas()
  return useMutation({
    mutationFn: async (v: { id: number; desconto_aprovado?: number | null; motivo?: string }) =>
      (await api.post(`/central-vendas/solicitacoes/${v.id}/aprovar`, {
        desconto_aprovado: v.desconto_aprovado ?? null, motivo: v.motivo ?? null,
      })).data.data,
    onSuccess: invalidar,
  })
}

export function useRecusar() {
  const invalidar = useInvalidarVendas()
  return useMutation({
    mutationFn: async (v: { id: number; motivo?: string }) =>
      (await api.post(`/central-vendas/solicitacoes/${v.id}/recusar`, { motivo: v.motivo ?? null })).data.data,
    onSuccess: invalidar,
  })
}

export function useFaturar() {
  const invalidar = useInvalidarVendas()
  return useMutation({
    mutationFn: async (v: { id: number }) =>
      (await api.post(`/central-vendas/solicitacoes/${v.id}/faturar`)).data.data,
    onSuccess: invalidar,
  })
}
