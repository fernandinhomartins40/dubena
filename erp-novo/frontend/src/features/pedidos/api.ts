import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

interface Paginated<T> { data: T[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }

export interface PedidoListItem {
  id: number; datahora: string | null; valorvenda: number; pedidosituacao_id: number
  cliente: string | null; situacao: string | null; fechadoconcluido: number; fechadocancelado: number
}
export interface KanbanColuna {
  situacao_id: number; descricao: string; total: number; valor: number
  pedidos: { id: number; valorvenda: number; datahora: string | null; cliente: string | null }[]
}

export function usePedidos(situacaoId: number, q: string, page: number) {
  return useQuery<Paginated<PedidoListItem>>({
    queryKey: ['pedidos', situacaoId, q, page],
    queryFn: async () => (await api.get('/pedidos', { params: { situacao_id: situacaoId, q, page } })).data,
    placeholderData: (p) => p,
  })
}
export const usePedidosKanban = () => useQuery<KanbanColuna[]>({ queryKey: ['pedidos-kanban'], queryFn: async () => (await api.get('/pedidos/kanban')).data.data })
export const usePedidoSituacoes = () => useQuery<any[]>({ queryKey: ['pedido-situacoes'], queryFn: async () => (await api.get('/pedidos/situacoes')).data.data })
export function usePedido(id: number | null) {
  return useQuery({ queryKey: ['pedido', id], queryFn: async () => (await api.get(`/pedidos/${id}`)).data.data, enabled: id !== null })
}
export function useCriarPedido() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (data: Record<string, unknown>) => (await api.post('/pedidos', data)).data.data,
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['pedidos'] }); qc.invalidateQueries({ queryKey: ['pedidos-kanban'] }) },
  })
}

/** Emite NFC-e/NF-e a partir do pedido concluído (F03). */
export function useEmitirNfce(pedidoId: number | null) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (modelo: '55' | '65' = '65') =>
      (await api.post(`/pedidos/${pedidoId}/emitir-nfce`, { modelo })).data.data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['pedido', pedidoId] })
      qc.invalidateQueries({ queryKey: ['pedidos'] })
    },
  })
}
