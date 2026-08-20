import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

/** Monitora (GPS) — único satélite real desta pasta (posições + cercas). */

// ---- Monitora (GPS) ----
export interface UltimaPosicao {
  veiculo_id: number; placa: string | null; latitude: number; longitude: number
  velocidade: number; ignicao: boolean; registrado_em: string | null
  /** Apelido do veículo no rastreador ("Caminhão Volks"). */
  descricao: string | null
  motorista: string | null
  /** Descrição do tipo ("CAMINHÃO") e o rótulo curto que escolhe o ícone. */
  tipo: string | null
  icone: string | null
  /** Azimute em graus — gira o ícone no sentido da viagem. */
  direcao: number | null
  velocidade_maxima: number | null
  /** Apurado no backend: velocidade acima da máxima do tipo. */
  excesso: boolean
}
export const useUltimasPosicoes = () =>
  useQuery<UltimaPosicao[]>({ queryKey: ['monitora-posicoes'], queryFn: async () => (await api.get('/monitora/ultimas-posicoes')).data.data, refetchInterval: 30000 })

// ---- Veículos + tipos (F1) ----
export interface VeiculoTipo { id: number; descricao: string; icone: string | null; velocidade_maxima: number | null; ativo: boolean }
export interface Veiculo {
  id: number; placa: string; descricao: string | null; tipo_id: number | null
  motorista: string | null; km_atual: number | null; imei: string | null; deviceid: string | null; ativo: boolean
  tipo?: VeiculoTipo | null
}
export const useVeiculos = () =>
  useQuery<Veiculo[]>({ queryKey: ['monitora-veiculos'], queryFn: async () => (await api.get('/monitora/veiculos')).data.data })
export const useTipos = () =>
  useQuery<VeiculoTipo[]>({ queryKey: ['monitora-tipos'], queryFn: async () => (await api.get('/monitora/tipos')).data.data })

export function useSalvarVeiculo() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...data }: Partial<Veiculo> & { placa: string }) =>
      (id ? api.put(`/monitora/veiculos/${id}`, data) : api.post('/monitora/veiculos', data)).then((r) => (r as any).data.data),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['monitora-veiculos'] }); qc.invalidateQueries({ queryKey: ['monitora-posicoes'] }) },
  })
}
export function useCriarTipo() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (data: { descricao: string; icone?: string | null; velocidade_maxima?: number | null }) => (await api.post('/monitora/tipos', data)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['monitora-tipos'] }),
  })
}

// ---- Histórico / Rota (replay) + Relatório de eventos (F2) ----
export interface PosicaoHistorico { latitude: number; longitude: number; velocidade: number; direcao: number | null; ignicao: boolean; registrado_em: string | null }
export function useHistorico(veiculoId: number | null, de: string, ate: string) {
  return useQuery<PosicaoHistorico[]>({
    queryKey: ['monitora-historico', veiculoId, de, ate],
    enabled: !!veiculoId && !!de && !!ate,
    queryFn: async () => (await api.get(`/monitora/veiculos/${veiculoId}/historico`, { params: { de, ate, limite: 5000 } })).data.data,
  })
}

/**
 * Período com histórico de posições de um veículo.
 *
 * A tela abre em "hoje"; quando não há posição hoje, isto permite dizer até
 * quando existe dado em vez de mostrar um mapa vazio sem explicação.
 */
export interface PeriodoDisponivel { inicio: string | null; fim: string | null; total: number }
export function usePeriodoDisponivel(veiculoId: number | null) {
  return useQuery<PeriodoDisponivel>({
    queryKey: ['monitora-periodo', veiculoId],
    enabled: !!veiculoId,
    queryFn: async () => (await api.get(`/monitora/veiculos/${veiculoId}/periodo`)).data.data,
  })
}

export interface EventosVeiculo {
  veiculo: { id: number; placa: string; descricao: string | null; tipo: string | null; velocidade_maxima: number | null }
  paradas: { inicio: string; fim: string; duracao_min: number; latitude: number; longitude: number }[]
  excessos: { registrado_em: string; velocidade: number; latitude: number; longitude: number }[]
  resumo: { total_paradas: number; total_excessos: number; posicoes: number }
}
export function useEventos(veiculoId: number | null, de: string, ate: string) {
  return useQuery<EventosVeiculo>({
    queryKey: ['monitora-eventos', veiculoId, de, ate],
    enabled: !!veiculoId && !!de && !!ate,
    queryFn: async () => (await api.get(`/monitora/veiculos/${veiculoId}/eventos`, { params: { de, ate } })).data.data,
  })
}

/** Baixa o relatório de eventos (csv|pdf) disparando o download no browser. */
export async function baixarEventos(veiculoId: number, de: string, ate: string, formato: 'csv' | 'pdf'): Promise<void> {
  const resp = await api.get(`/monitora/veiculos/${veiculoId}/eventos`, { params: { de, ate, formato }, responseType: 'blob' })
  const url = URL.createObjectURL(resp.data as Blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `eventos.${formato}`
  document.body.appendChild(a); a.click(); a.remove()
  URL.revokeObjectURL(url)
}

// ---- Cercas (geofencing poligonal) ----
export interface CercaPonto { latitude: number | string; longitude: number | string; ordem?: number }
export interface Cerca {
  id: number; descricao: string; cor: string | null; setor_id: number | null; ativo: boolean
  /** Município a que a cerca pertence — a tela agrupa por ele. */
  cidade_id: number | null; cidade: string | null; uf: string | null
  pontos: CercaPonto[]
}
export interface CercaForm {
  id?: number; descricao: string; cor?: string | null; setor_id?: number | null; ativo?: boolean
  cidade_id?: number | null
  pontos: { latitude: number; longitude: number }[]
}
export const useCercas = () =>
  useQuery<Cerca[]>({ queryKey: ['monitora-cercas'], queryFn: async () => (await api.get('/monitora/cercas')).data.data })

export function useSalvarCerca() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...data }: CercaForm) =>
      (id ? api.put(`/monitora/cercas/${id}`, data) : api.post('/monitora/cercas', data)).then((r) => (r as any).data.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['monitora-cercas'] }),
  })
}
export function useExcluirCerca() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => (await api.delete(`/monitora/cercas/${id}`)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['monitora-cercas'] }),
  })
}

/** Chave do Google Maps (config global) — necessária para o mapa de cercas.
 *  O endpoint embrulha em { data: {...} }: o valor vem em response.data.data. */
export const useGoogleMapsKey = () =>
  useQuery<string | null>({ queryKey: ['google-maps-key'], staleTime: Infinity, queryFn: async () => (await api.get('/config-global')).data?.data?.google_maps_key ?? null })
