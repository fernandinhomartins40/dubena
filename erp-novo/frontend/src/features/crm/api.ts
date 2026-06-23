import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

/** CRM (C10): pós-venda, promoções, sorteios, metas, checklists. */

// ---- Pós-venda ----
export interface PosVenda {
  id: number; cliente_id: number | null; pedido_id: number | null
  data: string; nota: number | null; canal: string | null; observacao: string | null; situacao: string
}
export const usePosVendas = () =>
  useQuery<PosVenda[]>({ queryKey: ['pos-vendas'], queryFn: async () => (await api.get('/pos-vendas')).data.data })
export function useSalvarPosVenda() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, data }: { id: number | null; data: Record<string, unknown> }) =>
      id ? (await api.put(`/pos-vendas/${id}`, data)).data.data : (await api.post('/pos-vendas', data)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['pos-vendas'] }),
  })
}
export function useExcluirPosVenda() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (id: number) => (await api.delete(`/pos-vendas/${id}`)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['pos-vendas'] }) })
}

// ---- Promoções ----
export interface Promocao {
  id: number; descricao: string; inicio: string; fim: string; desconto_percentual: string | null; ativo: boolean
}
export const usePromocoes = () =>
  useQuery<Promocao[]>({ queryKey: ['promocoes'], queryFn: async () => (await api.get('/promocoes')).data.data })
export function useSalvarPromocao() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, data }: { id: number | null; data: Record<string, unknown> }) =>
      id ? (await api.put(`/promocoes/${id}`, data)).data.data : (await api.post('/promocoes', data)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['promocoes'] }),
  })
}
export function useExcluirPromocao() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (id: number) => (await api.delete(`/promocoes/${id}`)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['promocoes'] }) })
}

// ---- Sorteios ----
export interface Sorteio {
  id: number; descricao: string; data_sorteio: string | null; situacao: string; numero_sorteado: string | null; numeros_count: number
}
export const useSorteios = () =>
  useQuery<Sorteio[]>({ queryKey: ['sorteios'], queryFn: async () => (await api.get('/sorteios')).data.data })
export function useSalvarSorteio() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, data }: { id: number | null; data: Record<string, unknown> }) =>
      id ? (await api.put(`/sorteios/${id}`, data)).data.data : (await api.post('/sorteios', data)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['sorteios'] }),
  })
}
export function useAddNumeroSorteio() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: Record<string, unknown> }) => (await api.post(`/sorteios/${id}/numeros`, data)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['sorteios'] }),
  })
}
export function useSortear() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => (await api.post(`/sorteios/${id}/sortear`)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['sorteios'] }),
  })
}

// ---- Metas ----
export interface Meta {
  id: number; colaborador_id: number | null; competencia: string; meta_valor: string; realizado_valor: string
}
export const useMetas = (competencia?: string) =>
  useQuery<Meta[]>({ queryKey: ['metas', competencia], queryFn: async () => (await api.get('/metas', { params: { competencia } })).data.data })
export function useSalvarMeta() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, data }: { id: number | null; data: Record<string, unknown> }) =>
      id ? (await api.put(`/metas/${id}`, data)).data.data : (await api.post('/metas', data)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['metas'] }),
  })
}
export function useExcluirMeta() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (id: number) => (await api.delete(`/metas/${id}`)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['metas'] }) })
}

// ---- Checklists ----
export interface ChecklistPergunta { id: number; pergunta: string; ordem: number }
export interface Checklist { id: number; descricao: string; ativo: boolean; perguntas: ChecklistPergunta[] }
export const useChecklists = () =>
  useQuery<Checklist[]>({ queryKey: ['checklists'], queryFn: async () => (await api.get('/checklists')).data.data })
export function useSalvarChecklist() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, data }: { id: number | null; data: Record<string, unknown> }) =>
      id ? (await api.put(`/checklists/${id}`, data)).data.data : (await api.post('/checklists', data)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['checklists'] }),
  })
}
export function useExcluirChecklist() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (id: number) => (await api.delete(`/checklists/${id}`)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['checklists'] }) })
}
