import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { abrirPdf } from '@/lib/pdf'

/** Comodatos (empréstimo de vasilhame) — domínio do ERP (N8). */
export interface Comodato {
  id: number; cliente_id: number | null; produto_id: number | null
  quantidade: string; quantidade_devolvida: string; situacao: string; data_emprestimo: string
  data_devolucao?: string | null
  cliente?: { id: number; nome: string }; produto?: { id: number; descricao: string }
}

/** Uma linha do extrato: empréstimo, devolução ou estorno. */
export interface ComodatoMovimento {
  id: number; tipo: 'EMPRESTIMO' | 'DEVOLUCAO' | 'ESTORNO'
  quantidade: string; saldo_apos: string; data: string
  estorna_id: number | null; estornado: boolean
  observacao: string | null
  usuario?: { id: number; name: string } | null
}

/** Uma versão emitida do contrato — os números são os daquele momento. */
export interface ComodatoContrato {
  id: number; versao: number; motivo: string
  quantidade_contratada: string; quantidade_devolvida: string; quantidade_em_posse: string
  assinado_em: string | null; created_at: string
}

export interface ComodatoDetalhe {
  comodato: Comodato
  em_posse: number
  movimentos: ComodatoMovimento[]
  contratos: ComodatoContrato[]
}

export const useComodatos = () =>
  useQuery<Comodato[]>({ queryKey: ['comodatos'], queryFn: async () => (await api.get('/comodatos')).data.data })

/** Extrato + versões do contrato. Só busca com a gaveta aberta. */
export const useComodato = (id: number | null) =>
  useQuery<ComodatoDetalhe>({
    queryKey: ['comodatos', id],
    queryFn: async () => (await api.get(`/comodatos/${id}`)).data.data,
    enabled: id !== null,
  })

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
    mutationFn: async (v: { id: number; quantidade: number; data?: string; observacao?: string }) =>
      (await api.post(`/comodatos/${v.id}/devolver`, {
        quantidade: v.quantidade, data: v.data, observacao: v.observacao,
      })).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['comodatos'] }),
  })
}

/** Desfaz uma devolução lançada errada — o saldo volta e o estoque baixa de novo. */
export function useEstornarDevolucao() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (v: { id: number; movimento: number; observacao?: string }) =>
      (await api.post(`/comodatos/${v.id}/movimentos/${v.movimento}/estornar`, {
        observacao: v.observacao,
      })).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['comodatos'] }),
  })
}

/** Versão nova do contrato sem mexer em saldo (dados do cliente mudaram). */
export function useReemitirContrato() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => (await api.post(`/comodatos/${id}/reemitir`)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['comodatos'] }),
  })
}

/** Registra que a via assinada voltou. */
export function useMarcarAssinado() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (v: { id: number; contrato: number }) =>
      (await api.post(`/comodatos/${v.id}/contratos/${v.contrato}/assinado`)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['comodatos'] }),
  })
}

/**
 * Abre o contrato numa aba para colher a assinatura.
 *
 * Sem `versao`, sai a vigente. Com, sai aquela versão como foi emitida — é a
 * segunda via do papel que o cliente assinou antes da devolução parcial.
 */
export const abrirContratoComodato = (id: number, versao?: number) =>
  abrirPdf(`/comodatos/${id}/contrato${versao !== undefined ? `?versao=${versao}` : ''}`)

/** Recibo da devolução: a prova, para o cliente, de que ele entregou. */
export const abrirReciboDevolucao = (id: number, movimento: number) =>
  abrirPdf(`/comodatos/${id}/movimentos/${movimento}/recibo`)
