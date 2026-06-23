import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

interface Paginated<T> { data: T[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }

// ---- RH / Colaboradores ----
export interface Colaborador { id: number; nome: string; cpf: string | null; dataadmissao: string | null; datadesligamento: string | null; cargo: string | null }
export function useColaboradores(q: string, page: number) {
  return useQuery<Paginated<Colaborador>>({ queryKey: ['colab', q, page], queryFn: async () => (await api.get('/colaboradores', { params: { q, page } })).data, placeholderData: (p) => p })
}
export function useColaborador(id: number | null) {
  return useQuery({ queryKey: ['colab', id], queryFn: async () => (await api.get(`/colaboradores/${id}`)).data.data, enabled: id !== null })
}
export function useSalvarColaborador() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, data }: { id: number | null; data: Record<string, unknown> }) => id ? (await api.put(`/colaboradores/${id}`, data)).data.data : (await api.post('/colaboradores', data)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['colab'] }),
  })
}
export function useExcluirColaborador() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (id: number) => (await api.delete(`/colaboradores/${id}`)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['colab'] }) })
}
export const useFamilia = (id: number) => useQuery<any[]>({ queryKey: ['colab-fam', id], queryFn: async () => (await api.get(`/colaboradores/${id}/familia`)).data.data })
export function useAddFamilia(id: number) {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (d: Record<string, unknown>) => (await api.post(`/colaboradores/${id}/familia`, d)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['colab-fam', id] }) })
}
export function useDelFamilia(id: number) {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (famId: number) => (await api.delete(`/colaboradores/${id}/familia/${famId}`)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['colab-fam', id] }) })
}
export const useRecessos = (id: number) => useQuery<any[]>({ queryKey: ['colab-rec', id], queryFn: async () => (await api.get(`/colaboradores/${id}/recessos`)).data.data })
export const useComissoes = (id: number) => useQuery<any[]>({ queryKey: ['colab-com', id], queryFn: async () => (await api.get(`/colaboradores/${id}/comissoes`)).data.data })

// ---- RH complementar (C5): exames (ASO), turnos, ponto ----
export const useExames = (id: number) => useQuery<any[]>({ queryKey: ['colab-exa', id], queryFn: async () => (await api.get(`/colaboradores/${id}/exames`)).data.data })
export function useAddExame(id: number) {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (d: Record<string, unknown>) => (await api.post(`/colaboradores/${id}/exames`, d)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['colab-exa', id] }) })
}
export const useTurnos = (id: number) => useQuery<any[]>({ queryKey: ['colab-tur', id], queryFn: async () => (await api.get(`/colaboradores/${id}/turnos`)).data.data })
export function useAddTurno(id: number) {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (d: Record<string, unknown>) => (await api.post(`/colaboradores/${id}/turnos`, d)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['colab-tur', id] }) })
}
export const usePontos = (id: number) => useQuery<any[]>({ queryKey: ['colab-pon', id], queryFn: async () => (await api.get(`/colaboradores/${id}/pontos`)).data.data })
export function useAddPonto(id: number) {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (d: Record<string, unknown>) => (await api.post(`/colaboradores/${id}/pontos`, d)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['colab-pon', id] }) })
}

// ---- Frota / Veículos ----
export interface Veiculo { id: number; placa: string; descricao: string; kmatual: number; veiculotipo_id: number; tipocombustivel_id: number }
export const useVeiculos = (q: string) => useQuery<Veiculo[]>({ queryKey: ['veic', q], queryFn: async () => (await api.get('/veiculos', { params: { q } })).data.data })
export function useVeiculo(id: number | null) {
  return useQuery({ queryKey: ['veic', id], queryFn: async () => (await api.get(`/veiculos/${id}`)).data.data, enabled: id !== null })
}
export function useSalvarVeiculo() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, data }: { id: number | null; data: Record<string, unknown> }) => id ? (await api.put(`/veiculos/${id}`, data)).data.data : (await api.post('/veiculos', data)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['veic'] }),
  })
}
export function useExcluirVeiculo() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (id: number) => (await api.delete(`/veiculos/${id}`)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['veic'] }) })
}
export const useAbastecimentos = (id: number) => useQuery<any[]>({ queryKey: ['veic-ab', id], queryFn: async () => (await api.get(`/veiculos/${id}/abastecimentos`)).data.data })
export const useTrocasOleo = (id: number) => useQuery<any[]>({ queryKey: ['veic-ol', id], queryFn: async () => (await api.get(`/veiculos/${id}/trocas-oleo`)).data.data })
export const usePneus = (id: number) => useQuery<any[]>({ queryKey: ['veic-pn', id], queryFn: async () => (await api.get(`/veiculos/${id}/pneus`)).data.data })

// ---- Vale-Gás ----
export const useValeGas = (q: string) => useQuery<any[]>({ queryKey: ['valegas', q], queryFn: async () => (await api.get('/vale-gas', { params: { q } })).data.data })
export const useValeGasSituacoes = () => useQuery<any[]>({ queryKey: ['valegas-sit'], queryFn: async () => (await api.get('/vale-gas/situacoes')).data.data })
export function useBaixarValeGas() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (d: { codigo: string; situacao_id: number }) => (await api.post('/vale-gas/baixar', d)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['valegas'] }) })
}

// ---- Satélites status ----
export const useRelatorios = () => useQuery<any[]>({ queryKey: ['sat-rel'], queryFn: async () => (await api.get('/satelites/relatorios')).data.data })
export const useMonitoramento = () => useQuery({ queryKey: ['sat-mon'], queryFn: async () => (await api.get('/satelites/monitoramento')).data.data })
export const useIntegracoes = () => useQuery({ queryKey: ['sat-int'], queryFn: async () => (await api.get('/satelites/integracoes')).data.data })
