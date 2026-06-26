import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

/** Satélites adicionais: comodatos, convênios, monitora (GPS). */

// ---- Comodatos ----
export interface Comodato {
  id: number; cliente_id: number | null; produto_id: number | null
  quantidade: string; quantidade_devolvida: string; situacao: string; data_emprestimo: string
  cliente?: { id: number; nome: string }; produto?: { id: number; descricao: string }
}
export const useComodatos = () =>
  useQuery<Comodato[]>({ queryKey: ['comodatos'], queryFn: async () => (await api.get('/comodatos')).data.data })
export function useCriarComodato() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (data: Record<string, unknown>) => (await api.post('/comodatos', data)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['comodatos'] }),
  })
}
export function useDevolverComodato() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, quantidade }: { id: number; quantidade: number }) => (await api.post(`/comodatos/${id}/devolver`, { quantidade })).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['comodatos'] }),
  })
}

// ---- Convênios ----
export interface Convenio {
  id: number; cliente_id: number | null; descricao: string; dia_fechamento: number | null; dia_vencimento: number | null; ativo: boolean
  cliente?: { id: number; nome: string }
}
export const useConvenios = () =>
  useQuery<Convenio[]>({ queryKey: ['convenios'], queryFn: async () => (await api.get('/convenios')).data.data })
export function useCriarConvenio() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (data: Record<string, unknown>) => (await api.post('/convenios', data)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['convenios'] }),
  })
}
export function useFecharConvenio() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => (await api.post(`/convenios/${id}/fechar`)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['convenios'] }),
  })
}

// ---- Monitora (GPS) ----
export interface UltimaPosicao {
  veiculo_id: number; placa: string | null; latitude: number; longitude: number
  velocidade: number; ignicao: boolean; registrado_em: string | null
}
export const useUltimasPosicoes = () =>
  useQuery<UltimaPosicao[]>({ queryKey: ['monitora-posicoes'], queryFn: async () => (await api.get('/monitora/ultimas-posicoes')).data.data, refetchInterval: 30000 })

// ---- Cercas (geofencing poligonal) ----
export interface CercaPonto { latitude: number | string; longitude: number | string; ordem?: number }
export interface Cerca {
  id: number; descricao: string; cor: string | null; setor_id: number | null; ativo: boolean
  pontos: CercaPonto[]
}
export interface CercaForm {
  id?: number; descricao: string; cor?: string | null; setor_id?: number | null; ativo?: boolean
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

/** Chave do Google Maps (config global) — necessária para o mapa de cercas. */
export const useGoogleMapsKey = () =>
  useQuery<string | null>({ queryKey: ['google-maps-key'], staleTime: Infinity, queryFn: async () => (await api.get('/config-global')).data?.google_maps_key ?? null })
