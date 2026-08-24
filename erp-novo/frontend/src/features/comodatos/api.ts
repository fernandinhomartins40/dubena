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

/** A foto do giro de um cliente com comodato. */
export interface Avaliacao {
  id: number; cliente_id: number; referencia: string
  em_posse: string; comprado_janela: string; dias_janela: number
  pedidos_janela: number; giro: string
  baseline_giro: string | null; variacao: string | null
  dias_sem_compra: number | null
  classificacao: 'OK' | 'ATENCAO' | 'CRITICO'
  motivo: string | null
  cliente?: { id: number; nome: string }
}

export interface VigilanciaConfig {
  dias_janela: number; giro_minimo: number; giro_critico: number
  queda_atencao: number; queda_critica: number
  dias_sem_compra_alerta: number; posse_minima_vigiada: number
  dias_aviso_vencimento: number; ativo: boolean
}

/** Painel de giro: lê a última avaliação de cada cliente (o cálculo é do cron). */
export const useVigilancia = (classificacao?: string) =>
  useQuery<{ data: Avaliacao[]; config: VigilanciaConfig }>({
    queryKey: ['comodatos', 'vigilancia', classificacao],
    queryFn: async () =>
      (await api.get('/comodatos/vigilancia', { params: { classificacao } })).data,
  })

/** Série histórica de um cliente — mostra a evolução, não só o retrato. */
export const useHistoricoVigilancia = (clienteId: number | null) =>
  useQuery<Avaliacao[]>({
    queryKey: ['comodatos', 'vigilancia', 'historico', clienteId],
    queryFn: async () => (await api.get(`/comodatos/vigilancia/${clienteId}`)).data.data,
    enabled: clienteId !== null,
  })

export function useSalvarConfigVigilancia() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (c: VigilanciaConfig) => (await api.put('/comodatos/config', c)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['comodatos', 'vigilancia'] }),
  })
}

/** Um vasilhame e o produto que o enche — o par que sustenta a vigilância. */
export interface Vinculo {
  id: number; descricao: string; capacidade: string | null
  produto_retornavel_id: number | null
  sugeridos: number[]
  em_comodato: number
}

export interface ConteudoDisponivel {
  id: number; descricao: string; capacidade: string | null
}

export const useVinculos = () =>
  useQuery<{ data: Vinculo[]; conteudos: ConteudoDisponivel[] }>({
    queryKey: ['comodatos', 'vinculos'],
    queryFn: async () => (await api.get('/comodatos/vinculos')).data,
  })

export function useSalvarVinculo() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (v: { id: number; produto_retornavel_id: number | null }) =>
      (await api.put(`/comodatos/vinculos/${v.id}`, {
        produto_retornavel_id: v.produto_retornavel_id,
      })).data.data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['comodatos', 'vinculos'] })
      // A régua mudou de base: a vigilância precisa ser relida.
      qc.invalidateQueries({ queryKey: ['comodatos', 'vigilancia'] })
    },
  })
}

/** Acrescenta vasilhames ao MESMO comodato e reemite o contrato. */
export function useAcrescentarComodato() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (v: { id: number; quantidade: number; observacao?: string }) =>
      (await api.post(`/comodatos/${v.id}/acrescentar`, {
        quantidade: v.quantidade, observacao: v.observacao,
      })).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['comodatos'] }),
  })
}

/** Renova o vencimento e emite a versão nova do contrato. */
export function useRenovarComodato() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (v: {
      id: number; data_vencimento: string
      nome_representante?: string; cpf_representante?: string
    }) => (await api.post(`/comodatos/${v.id}/renovar`, v)).data.data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['comodatos'] })
      qc.invalidateQueries({ queryKey: ['alertas'] })
    },
  })
}
