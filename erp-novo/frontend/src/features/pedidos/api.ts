import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

interface Paginated<T> { data: T[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }

export interface PedidoListItem {
  id: number; datahora: string | null; valorvenda: number; pedidosituacao_id: number
  cliente: string | null; situacao: string | null; fechadoconcluido: number; fechadocancelado: number
}
export type EfeitoPedido = 'PENDENTE' | 'CONCLUIDO' | 'CANCELADO'

export interface KanbanColuna {
  situacao_id: number; descricao: string; efeito: EfeitoPedido; cor: string | null; total: number; valor: number
  pedidos: { id: number; valorvenda: number; datahora: string | null; cliente: string | null }[]
}

export interface PedidoSituacao {
  id: number; descricao: string; efeito: EfeitoPedido; cor: string | null; ordem: number; ativo: boolean
}
export interface SituacaoForm {
  id?: number; descricao: string; efeito: EfeitoPedido; cor?: string | null
}

export function usePedidos(situacaoId: number, q: string, page: number) {
  return useQuery<Paginated<PedidoListItem>>({
    queryKey: ['pedidos', situacaoId, q, page],
    queryFn: async () => (await api.get('/pedidos', { params: { situacao_id: situacaoId, q, page } })).data,
    placeholderData: (p) => p,
  })
}
export const usePedidosKanban = () => useQuery<KanbanColuna[]>({ queryKey: ['pedidos-kanban'], queryFn: async () => (await api.get('/pedidos/kanban')).data.data })
export const usePedidoSituacoes = () => useQuery<PedidoSituacao[]>({ queryKey: ['pedido-situacoes'], queryFn: async () => (await api.get('/pedidos/situacoes')).data.data })

/** CRUD das colunas (situações) do Kanban — invalida kanban + situações. */
export function useSalvarSituacao() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...data }: SituacaoForm) =>
      (id ? api.put(`/pedidos/situacoes/${id}`, data) : api.post('/pedidos/situacoes', data)).then((r) => (r as any).data.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['pedidos-kanban'] })
      qc.invalidateQueries({ queryKey: ['pedido-situacoes'] })
    },
  })
}
export function useExcluirSituacao() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => (await api.delete(`/pedidos/situacoes/${id}`)).data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['pedidos-kanban'] })
      qc.invalidateQueries({ queryKey: ['pedido-situacoes'] })
    },
  })
}

/** Persiste a nova ordem das colunas (lista de ids na ordem desejada). */
export function useReordenarSituacoes() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (ordem: number[]) => (await api.put('/pedidos/situacoes/reordenar', { ordem })).data,
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['pedidos-kanban'] }); qc.invalidateQueries({ queryKey: ['pedido-situacoes'] }) },
  })
}

/** Move um pedido para outra situação (máquina de estados: pode baixar/estornar estoque). */
export function useMudarSituacaoPedido() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ pedidoId, situacaoId }: { pedidoId: number; situacaoId: number }) =>
      (await api.put(`/pedidos/${pedidoId}/situacao`, { pedidosituacao_id: situacaoId })).data.data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['pedidos-kanban'] })
      qc.invalidateQueries({ queryKey: ['pedidos'] })
    },
  })
}
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

// Bina no atendimento (T4.4) — condicionado à decisão do dono.
export interface ChamadaEntrante {
  id: number
  telefone: string
  telefone_formatado: string
  ramal: string | null
  cliente_id: number | null
  cliente: string | null
  candidatos: { id: number; nome: string; telefone: string; endereco: string }[]
  recebida_em: string | null
}

/** Fila de chamadas, com polling — a SPA ainda não assina canais do Echo. */
export function useFilaChamadas(intervaloMs = 5000) {
  return useQuery<ChamadaEntrante[]>({
    queryKey: ['telefonia-fila'],
    queryFn: async () => (await api.get('/telefonia/fila')).data.data,
    refetchInterval: intervaloMs,
    // Sem chamada não há o que mostrar; falhar em silêncio é melhor que um
    // toast de erro piscando a cada 5s se a integração não estiver ligada.
    retry: false,
  })
}

export function useAtenderChamada() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, cliente_id }: { id: number; cliente_id?: number }) =>
      (await api.post(`/telefonia/chamadas/${id}/atender`, { cliente_id })).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['telefonia-fila'] }),
  })
}

export function useRejeitarChamada() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, motivo }: { id: number; motivo?: string }) =>
      (await api.post(`/telefonia/chamadas/${id}/rejeitar`, { motivo })).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['telefonia-fila'] }),
  })
}
