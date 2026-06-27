import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

/** Monitora (GPS) — único satélite real desta pasta (posições + cercas). */

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

/** Chave do Google Maps (config global) — necessária para o mapa de cercas.
 *  O endpoint embrulha em { data: {...} }: o valor vem em response.data.data. */
export const useGoogleMapsKey = () =>
  useQuery<string | null>({ queryKey: ['google-maps-key'], staleTime: Infinity, queryFn: async () => (await api.get('/config-global')).data?.data?.google_maps_key ?? null })
