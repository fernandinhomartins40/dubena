import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

/** Pagamentos (C4): cartões (NSU) e Gás do Povo. */

// ---- Cartões ----
export interface CartaoTransacao {
  id: number; bandeira: string | null; tipo: string; nsu: string | null; parcelas: number
  valor_bruto: string; taxa_percentual: string; valor_liquido: string; situacao: string
}
export const useCartoes = () =>
  useQuery<CartaoTransacao[]>({ queryKey: ['cartoes'], queryFn: async () => (await api.get('/cartoes')).data.data })
export function useRegistrarCartao() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (data: Record<string, unknown>) => (await api.post('/cartoes', data)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['cartoes'] }),
  })
}

// ---- Gás do Povo ----
export interface Beneficio {
  id: number; cliente_id: number | null; nis: string | null; competencia: string
  valor: string; situacao: string; pedido_id: number | null
}
export const useBeneficios = () =>
  useQuery<Beneficio[]>({ queryKey: ['gasdopovo'], queryFn: async () => (await api.get('/gasdopovo')).data.data })
export function useRegistrarBeneficio() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (data: Record<string, unknown>) => (await api.post('/gasdopovo', data)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['gasdopovo'] }),
  })
}
export function useSacarBeneficio() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: Record<string, unknown> }) => (await api.post(`/gasdopovo/${id}/sacar`, data)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['gasdopovo'] }),
  })
}
