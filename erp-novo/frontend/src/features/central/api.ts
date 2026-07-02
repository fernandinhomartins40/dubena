import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

/**
 * Central de Logística (L1/L3). Enquanto o Reverb não está ligado em produção, as
 * listas usam polling curto do react-query (refetchInterval) — a UI é "quase ao
 * vivo" sem WebSocket. Quando o tempo real for ligado (F2), basta assinar o canal
 * `empresa.{id}.central` e invalidar estas queries nos eventos.
 */

export interface FilaPedido {
  id: number
  cliente: string | null
  endereco: string
  lat: number | null
  lng: number | null
  valor_venda: number
  urgente: boolean
  datahora: string | null
  situacao: string | null
  entregador: { id: number; nome: string | null } | null
}

export interface EntregadorStatus {
  entregador_user_id: number
  nome: string | null
  em_servico: boolean
  jornada_id: number
  veiculo: { id: number; placa: string; descricao: string | null } | null
  posicao: { lat: number; lng: number; em: string | null } | null
  carga: number
  bloqueado: boolean
}

export interface Sugestao {
  entregador_user_id: number
  nome: string | null
  veiculo_id: number | null
  distancia_km: number | null
  carga: number
  score: number
  elegivel: boolean
}

export interface LogisticaConfig {
  modo: 'sugerir' | 'auto'
  peso_distancia: number
  peso_carga: number
  raio_maximo_km: number | null
  teto_carga: number | null
  /** Minutos sem entrega para o motor de MISSÕES agir (L7). */
  ociosidade_min: number
}

const POLL = 8000 // 8s — "quase ao vivo" sem WebSocket

export const useFila = (incluirAtribuidos = false) =>
  useQuery<FilaPedido[]>({
    queryKey: ['central-fila', incluirAtribuidos],
    queryFn: async () => (await api.get('/central/fila', { params: { incluir_atribuidos: incluirAtribuidos ? 1 : 0 } })).data.data,
    refetchInterval: POLL,
  })

export const useEntregadores = () =>
  useQuery<EntregadorStatus[]>({
    queryKey: ['central-entregadores'],
    queryFn: async () => (await api.get('/central/entregadores')).data.data,
    refetchInterval: POLL,
  })

export const useSugestoes = (pedidoId: number | null) =>
  useQuery<Sugestao[]>({
    queryKey: ['central-sugestoes', pedidoId],
    queryFn: async () => (await api.get(`/central/pedidos/${pedidoId}/sugestoes`)).data.data,
    enabled: pedidoId !== null,
  })

export const useConfig = () =>
  useQuery<LogisticaConfig>({
    queryKey: ['central-config'],
    queryFn: async () => (await api.get('/central/config')).data.data,
  })

function useInvalidarCentral() {
  const qc = useQueryClient()
  return () => {
    qc.invalidateQueries({ queryKey: ['central-fila'] })
    qc.invalidateQueries({ queryKey: ['central-entregadores'] })
  }
}

export function useAtribuir() {
  const invalidar = useInvalidarCentral()
  return useMutation({
    mutationFn: async (v: { pedidoId: number; entregador_user_id: number; veiculo_id?: number | null; motivo?: string }) =>
      (await api.post(`/central/pedidos/${v.pedidoId}/atribuir`, {
        entregador_user_id: v.entregador_user_id, veiculo_id: v.veiculo_id ?? null, motivo: v.motivo ?? null,
      })).data.data,
    onSuccess: invalidar,
  })
}

export function useRedistribuir() {
  const invalidar = useInvalidarCentral()
  return useMutation({
    mutationFn: async (v: { pedidoId: number; entregador_user_id: number; motivo?: string }) =>
      (await api.post(`/central/pedidos/${v.pedidoId}/redistribuir`, { entregador_user_id: v.entregador_user_id, motivo: v.motivo ?? null })).data.data,
    onSuccess: invalidar,
  })
}

export function usePriorizar() {
  const invalidar = useInvalidarCentral()
  return useMutation({
    mutationFn: async (v: { pedidoId: number; urgente: boolean }) =>
      (await api.post(`/central/pedidos/${v.pedidoId}/priorizar`, { urgente: v.urgente })).data.data,
    onSuccess: invalidar,
  })
}

export function useBloquear() {
  const invalidar = useInvalidarCentral()
  return useMutation({
    mutationFn: async (v: { entregadorId: number; motivo?: string; ate?: string | null }) =>
      (await api.post(`/central/entregadores/${v.entregadorId}/bloquear`, { motivo: v.motivo ?? null, ate: v.ate ?? null })).data,
    onSuccess: invalidar,
  })
}

export function useDesbloquear() {
  const invalidar = useInvalidarCentral()
  return useMutation({
    mutationFn: async (entregadorId: number) => (await api.delete(`/central/entregadores/${entregadorId}/bloquear`)).data,
    onSuccess: invalidar,
  })
}

export function useSalvarConfig() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (v: Partial<LogisticaConfig>) => (await api.put('/central/config', v)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['central-config'] }),
  })
}
