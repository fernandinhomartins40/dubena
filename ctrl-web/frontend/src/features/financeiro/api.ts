import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

interface Paginated<T> { data: T[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }

export interface Lancamento {
  id: number; numero: number; datavencimento: string; valor: number; valorefetivado: number
  baixado: number; datahorabaixa: string | null; pagarreceber: string
  documento: string | null; descricao: string | null; cliente: string | null
}

export function useLancamentos(pagarreceber: string, status: string, q: string, page: number) {
  return useQuery<Paginated<Lancamento>>({
    queryKey: ['fin-lancamentos', pagarreceber, status, q, page],
    queryFn: async () => (await api.get('/financeiro/lancamentos', { params: { pagarreceber, status, q, page } })).data,
    placeholderData: (p) => p,
  })
}
export function useResumoFinanceiro() {
  return useQuery({ queryKey: ['fin-resumo'], queryFn: async () => (await api.get('/financeiro/lancamentos/resumo')).data.data })
}
export function useCriarLancamento() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (data: Record<string, unknown>) => (await api.post('/financeiro/lancamentos', data)).data,
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['fin-lancamentos'] }); qc.invalidateQueries({ queryKey: ['fin-resumo'] }) },
  })
}

// Plano / Centro
export interface PlanoConta { id: number; codigo: string | null; descricao: string; pagarreceber: string; nivel: number }
export interface CentroCusto { id: number; codigo: string | null; descricao: string; nivel: number; ativo: number }
export const usePlanosConta = () => useQuery<PlanoConta[]>({ queryKey: ['fin-planos'], queryFn: async () => (await api.get('/financeiro/planos-conta')).data.data })
export const useCentrosCusto = () => useQuery<CentroCusto[]>({ queryKey: ['fin-centros'], queryFn: async () => (await api.get('/financeiro/centros-custo')).data.data })

export function useSalvarPlanoConta() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...d }: { id?: number } & Record<string, unknown>) => id ? (await api.put(`/financeiro/planos-conta/${id}`, d)).data : (await api.post('/financeiro/planos-conta', d)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['fin-planos'] }),
  })
}
export function useExcluirPlanoConta() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (id: number) => (await api.delete(`/financeiro/planos-conta/${id}`)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['fin-planos'] }) })
}
export function useSalvarCentroCusto() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...d }: { id?: number } & Record<string, unknown>) => id ? (await api.put(`/financeiro/centros-custo/${id}`, d)).data : (await api.post('/financeiro/centros-custo', d)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['fin-centros'] }),
  })
}
export function useExcluirCentroCusto() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (id: number) => (await api.delete(`/financeiro/centros-custo/${id}`)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['fin-centros'] }) })
}

// Caixa
export interface ContaCaixa { id: number; descricao: string; saldoatual: number; fechado: number }
export const useContasCaixa = () => useQuery<ContaCaixa[]>({ queryKey: ['caixa-contas'], queryFn: async () => (await api.get('/caixa/contas')).data.data })
export function useMovimentosCaixa(contaId: number | null) {
  return useQuery({
    queryKey: ['caixa-movimentos', contaId],
    queryFn: async () => (await api.get(`/caixa/${contaId}/movimentos`)).data,
    enabled: contaId !== null,
  })
}
export function useAbrirCaixa() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ contaId, datahoraabertura }: { contaId: number; datahoraabertura: string }) => (await api.post(`/caixa/${contaId}/abrir`, { datahoraabertura })).data,
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['caixa-contas'] }); qc.invalidateQueries({ queryKey: ['caixa-movimentos'] }) },
  })
}
export function useFecharCaixa() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ contaId, datahorafechamento }: { contaId: number; datahorafechamento: string }) => (await api.post(`/caixa/${contaId}/fechar`, { datahorafechamento })).data,
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['caixa-contas'] }); qc.invalidateQueries({ queryKey: ['caixa-movimentos'] }) },
  })
}
