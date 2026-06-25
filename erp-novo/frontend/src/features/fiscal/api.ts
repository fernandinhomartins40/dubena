import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

// Malha fiscal (genérico por tipo)
export interface MalhaRow { id: number; descricao: string; codigo?: string }
export function useMalha(tipo: string) {
  return useQuery<MalhaRow[]>({ queryKey: ['fiscal-malha', tipo], queryFn: async () => (await api.get(`/fiscal/malha/${tipo}`)).data.data })
}
export function useSalvarMalha(tipo: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...d }: { id?: number } & Record<string, unknown>) => id ? (await api.put(`/fiscal/malha/${tipo}/${id}`, d)).data : (await api.post(`/fiscal/malha/${tipo}`, d)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['fiscal-malha', tipo] }),
  })
}
export function useExcluirMalha(tipo: string) {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (id: number) => (await api.delete(`/fiscal/malha/${tipo}/${id}`)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['fiscal-malha', tipo] }) })
}

// Operações fiscais
export interface OperacaoRow { id: number; descricao: string; descricaofiscal: string | null; cfop: string | null; movimentaestoque: number; movimentafinanceiro: number }
export const useOperacoes = () => useQuery<OperacaoRow[]>({ queryKey: ['fiscal-operacoes'], queryFn: async () => (await api.get('/fiscal/operacoes')).data.data })
export function useSalvarOperacao() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...d }: { id?: number } & Record<string, unknown>) => id ? (await api.put(`/fiscal/operacoes/${id}`, d)).data : (await api.post('/fiscal/operacoes', d)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['fiscal-operacoes'] }),
  })
}
export function useExcluirOperacao() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (id: number) => (await api.delete(`/fiscal/operacoes/${id}`)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['fiscal-operacoes'] }) })
}

// NF-e
export interface NfeRow { id: number; nfmodelo: string | null; nfserie: string | null; nfnumero: string | null; chaveacesso: string | null; datahoraemissao: string | null; nfsituacao_id: number | null }
export const useNfe = (q: string) => useQuery<NfeRow[]>({ queryKey: ['fiscal-nfe', q], queryFn: async () => (await api.get('/fiscal/nfe', { params: { q } })).data.data })
export function useTransmitirNfe() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (id: number) => (await api.post(`/fiscal/nfe/${id}/transmitir`)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['fiscal-nfe'] }) })
}
export function useCancelarNfe() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async ({ id, justificativa }: { id: number; justificativa: string }) => (await api.post(`/fiscal/nfe/${id}/cancelar`, { justificativa })).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['fiscal-nfe'] }) })
}

// SPED
export function useSpedPreview(inicio: string, fim: string, enabled: boolean) {
  return useQuery({ queryKey: ['fiscal-sped', inicio, fim], queryFn: async () => (await api.get('/fiscal/sped', { params: { inicio, fim } })).data.data, enabled })
}

// NF de Entrada (F06)
export interface NfEntradaRow {
  id: number; numero: string | null; serie: string | null; emitente_nome: string | null
  data_emissao: string | null; valor_total: number; situacao: string; itens_count?: number
}
export function useNfEntrada() {
  return useQuery<{ data: NfEntradaRow[]; meta: any }>({
    queryKey: ['nf-entrada'],
    queryFn: async () => (await api.get('/fiscal/nf-entrada')).data,
  })
}
export function useImportarNfEntrada() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (xml: string) => (await api.post('/fiscal/nf-entrada/importar', { xml })).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['nf-entrada'] }),
  })
}
export function useProcessarNfEntrada() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, setor_id }: { id: number; setor_id: number }) =>
      (await api.post(`/fiscal/nf-entrada/${id}/processar`, { setor_id })).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['nf-entrada'] }),
  })
}
