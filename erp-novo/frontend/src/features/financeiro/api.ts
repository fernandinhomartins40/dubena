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

// Cheques
export const useChequesEmitidos = (q: string) => useQuery<any[]>({ queryKey: ['cheques-emitidos', q], queryFn: async () => (await api.get('/cheques/emitidos', { params: { q } })).data.data })
export const useChequesRecebidos = (q: string) => useQuery<any[]>({ queryKey: ['cheques-recebidos', q], queryFn: async () => (await api.get('/cheques/recebidos', { params: { q } })).data.data })
// Escrita usa a rota CANÔNICA /cheques (API-2): /cheques/recebidos é só a LISTAGEM
// dos recebidos; os aliases de escrita foram removidos do backend.
export function useSalvarChequeRecebido() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...d }: { id?: number } & Record<string, unknown>) => id ? (await api.put(`/cheques/${id}`, d)).data : (await api.post('/cheques', { ...d, especie: 'R' })).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['cheques-recebidos'] }),
  })
}
export function useExcluirChequeRecebido() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: async (id: number) => (await api.delete(`/cheques/${id}`)).data, onSuccess: () => qc.invalidateQueries({ queryKey: ['cheques-recebidos'] }) })
}
/** Transição de situação do cheque (depósito/compensação/devolução) — F07. */
export function useMudarSituacaoCheque() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, situacao, conta_id }: { id: number; situacao: string; conta_id?: number | null }) =>
      (await api.put(`/cheques/${id}/situacao`, { situacao, conta_id })).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['cheques-recebidos'] }),
  })
}

/** Reparcela o saldo em aberto de um título (F07). */
export function useReparcelarLancamento() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, num_parcelas }: { id: number; num_parcelas: number }) =>
      (await api.post(`/financeiro/lancamentos/${id}/reparcelar`, { num_parcelas })).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['fin-lancamentos'] }),
  })
}

// Boletos / PIX
export const useBoletos = (status: string, q: string) => useQuery<any[]>({ queryKey: ['boletos', status, q], queryFn: async () => (await api.get('/boletos', { params: { status, q } })).data.data })
export const useResumoBoletos = () => useQuery({ queryKey: ['boletos-resumo'], queryFn: async () => (await api.get('/boletos/resumo')).data.data })
export const usePixStatus = () => useQuery({ queryKey: ['pix-status'], queryFn: async () => (await api.get('/pix/config')).data.data })

// DRE / Conciliação
export function useDRE(inicio: string, fim: string, enabled: boolean) {
  return useQuery({ queryKey: ['fin-dre', inicio, fim], queryFn: async () => (await api.get('/financeiro/dre', { params: { inicio, fim } })).data.data, enabled })
}
export function useConciliacao(contaId: number | null, inicio: string, fim: string, enabled: boolean) {
  return useQuery({ queryKey: ['fin-conciliacao', contaId, inicio, fim], queryFn: async () => (await api.get('/financeiro/conciliacao', { params: { conta_id: contaId, inicio, fim } })).data.data, enabled })
}

// Regras de classificação do extrato (T4.2)
export interface ExtratoRegra {
  id: number; conta_id: number; descricao: string; acao: string
  condicaopagamento_id: number | null; contamovimentotipo_id: number | null
  plano_conta_id: number | null; centro_custo_id: number | null
  cliente_id: number | null; conta_origem_id: number | null
  ativo: boolean; prioridade: number
}
export function useExtratoRegras(contaId: number | null) {
  return useQuery<{ data: ExtratoRegra[]; acoes: { valor: string; rotulo: string }[] }>({
    queryKey: ['fin-extrato-regras', contaId],
    queryFn: async () => (await api.get(`/financeiro/contas/${contaId}/extrato-regras`)).data,
    enabled: contaId !== null,
  })
}
export function useSalvarExtratoRegra(contaId: number | null) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...d }: { id?: number } & Record<string, unknown>) =>
      id ? (await api.put(`/financeiro/contas/${contaId}/extrato-regras/${id}`, d)).data
         : (await api.post(`/financeiro/contas/${contaId}/extrato-regras`, d)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['fin-extrato-regras', contaId] }),
  })
}
export function useExcluirExtratoRegra(contaId: number | null) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => (await api.delete(`/financeiro/contas/${contaId}/extrato-regras/${id}`)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['fin-extrato-regras', contaId] }),
  })
}

// Fechamento de malote (T4.3) — acerto de valores do entregador.
export interface MalotePedido {
  pedido_id: number
  cliente: string
  situacao: string
  valor: number
  condicao: string
  parcelas_abertas: number
  valor_a_baixar: number
  ja_baixado: boolean
}
export interface MaloteConferencia {
  pedidos: MalotePedido[]
  por_condicao: { condicao_id: number; condicao: string; pedidos: number; valor: number }[]
  totais: { pedidos: number; valor_total: number; valor_a_baixar: number }
}

export function useConferenciaMalote(
  filtros: { inicio: string; fim: string; setor_id: number | null; entregador_user_id: number | null },
  enabled: boolean,
) {
  return useQuery<MaloteConferencia>({
    queryKey: ['malote-conferencia', filtros],
    queryFn: async () => (await api.get('/malotes/conferencia', { params: filtros })).data.data,
    enabled,
  })
}

export function useFecharMalote() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (d: { pedidos: number[]; conta_id?: number }) =>
      (await api.post('/malotes/fechar', d)).data.data as
        { baixadas: number; valor: number; conta_id: number; ignorados: number[] },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['malote-conferencia'] })
      // A baixa mexe no caixa e nos lançamentos: sem invalidar, a tela de
      // lançamentos continuaria mostrando as parcelas como em aberto.
      qc.invalidateQueries({ queryKey: ['fin-lancamentos'] })
      qc.invalidateQueries({ queryKey: ['caixa'] })
    },
  })
}
