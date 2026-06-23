import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

/** Gestão (C11): cupons SAT/CFe, MCMM, documentos, bens. */

// ---- Cupons fiscais (SAT/CFe) ----
export interface CupomItem { id: number; produto_id: number | null; quantidade: string; valor_unitario: string; valor_total: string }
export interface Cupom { id: number; numero: string | null; valor_total: string; situacao: string; emitido_em: string | null; itens: CupomItem[] }
export const useCupons = () =>
  useQuery<Cupom[]>({ queryKey: ['cupons'], queryFn: async () => (await api.get('/cupons-fiscais')).data.data })
export function useCriarCupom() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (data: Record<string, unknown>) => (await api.post('/cupons-fiscais', data)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['cupons'] }),
  })
}
export function useEmitirCupom() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => (await api.post(`/cupons-fiscais/${id}/emitir`)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['cupons'] }),
  })
}

// ---- MCMM ----
export interface Mcmm { id: number; data: string; descricao: string; tipo: string; quantidade: string; produto_id: number | null }
export const useMcmms = () =>
  useQuery<Mcmm[]>({ queryKey: ['mcmm'], queryFn: async () => (await api.get('/mcmm')).data.data })
export function useSalvarMcmm() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, data }: { id: number | null; data: Record<string, unknown> }) =>
      id ? (await api.put(`/mcmm/${id}`, data)).data.data : (await api.post('/mcmm', data)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['mcmm'] }),
  })
}
export function useExcluirMcmm() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (id: number) => (await api.delete(`/mcmm/${id}`)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['mcmm'] }) })
}

// ---- Documentos ----
export interface Documento { id: number; tipo: string | null; descricao: string; numero: string | null; emissao: string | null; validade: string | null }
export const useDocumentos = () =>
  useQuery<Documento[]>({ queryKey: ['documentos'], queryFn: async () => (await api.get('/documentos')).data.data })
export function useSalvarDocumento() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, data }: { id: number | null; data: Record<string, unknown> }) =>
      id ? (await api.put(`/documentos/${id}`, data)).data.data : (await api.post('/documentos', data)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['documentos'] }),
  })
}
export function useExcluirDocumento() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (id: number) => (await api.delete(`/documentos/${id}`)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['documentos'] }) })
}

// ---- Bens (com depreciação) ----
export interface BemDepreciacao { depreciacao_anual: number; depreciacao_acumulada: number; valor_contabil: number; anos: number }
export interface Bem {
  id: number; descricao: string; data_aquisicao: string | null; valor_aquisicao: string
  taxa_depreciacao_anual: string; valor_residual: string; ativo: boolean; depreciacao: BemDepreciacao
}
export const useBens = () =>
  useQuery<Bem[]>({ queryKey: ['bens'], queryFn: async () => (await api.get('/bens')).data.data })
export function useSalvarBem() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, data }: { id: number | null; data: Record<string, unknown> }) =>
      id ? (await api.put(`/bens/${id}`, data)).data.data : (await api.post('/bens', data)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['bens'] }),
  })
}
export function useExcluirBem() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (id: number) => (await api.delete(`/bens/${id}`)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['bens'] }) })
}
