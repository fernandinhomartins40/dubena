import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { abrirPdf } from '@/lib/pdf'

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
/**
 * Nota fiscal como a API entrega.
 *
 * Os nomes são os do schema NOVO (`serie`, `numero`, `chave`, `situacao`), não
 * os do ERP antigo (`nfserie`, `nfnumero`, `chaveacesso`, `nfsituacao_id`).
 * Enquanto a tela lia os nomes legados, cada campo vinha `undefined` e as 241
 * mil notas apareciam como linhas vazias com "/" e travessões.
 */
export interface NfeRow {
  id: number
  modelo: string | null
  serie: number | string | null
  numero: number | string | null
  chave: string | null
  protocolo: string | null
  situacao: 'RASCUNHO' | 'EMITIDA' | 'AUTORIZADA' | 'REJEITADA' | 'DENEGADA' | 'CANCELADA' | null
  motivo_rejeicao: string | null
  valor_total: string | number | null
  emitida_em: string | null
  cliente?: { id: number; nome: string } | null
}
export interface PaginaNfe {
  data: NfeRow[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

/**
 * Notas fiscais — PAGINADO.
 *
 * A listagem tinha teto fixo de 200 no backend, sem informar o total: com 241
 * mil notas migradas, a tela mostrava as mais recentes e parecia truncada sem
 * explicação. Agora vem `meta.total` e o usuário navega/filtra por período.
 */
export const useNfe = (q: string, page = 1, filtros?: { inicio?: string; fim?: string; situacao?: string }) =>
  useQuery<PaginaNfe>({
    queryKey: ['fiscal-nfe', q, page, filtros?.inicio, filtros?.fim, filtros?.situacao],
    queryFn: async () => (await api.get('/fiscal/nfe', {
      params: { q, page, ...filtros },
    })).data,
    placeholderData: (anterior) => anterior,   // evita piscar ao trocar de página
  })
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

/**
 * Abre o DANFE numa aba para impressão.
 *
 * Abre em vez de baixar porque o DANFE existe para virar papel: o operador
 * imprime e a folha segue com a carga. Vai por blob, e não por `window.open`
 * na URL, porque o Bearer token viaja no header — um link direto chegaria
 * sem autenticação.
 */
export const abrirDanfe = (id: number) => abrirPdf(`/notas/${id}/danfe`)
