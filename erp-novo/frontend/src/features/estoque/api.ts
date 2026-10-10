import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

/**
 * Saldo de estoque como a API entrega — nomes do schema NOVO
 * (`quantidade_minima`), não os do ERP antigo (`quantidademinima`). Com o nome
 * errado a coluna vinha `undefined` e a tela mostrava "—" em todos os mínimos.
 */
export interface SaldoRow {
  id: number
  setor_id: number
  produto_id: number
  quantidade: number
  quantidade_minima: number | null
  quantidade_maxima: number | null
  custo_medio?: number | null
  setor: string
  produto: string
}

/**
 * As linhas abaixo são tipadas de propósito. Estas telas nasceram com os nomes
 * do ERP antigo (`datahora`, `datacompetencia`, `quantidadefisica`,
 * `datahorafechamento`) e `any` em tudo: o envio tomava 422 e a lista mostrava
 * colunas vazias, sem o compilador acusar nada. Com o tipo, o nome errado não
 * compila.
 */
interface Rotulado { id: number; descricao: string }

export interface RequisicaoRow {
  id: number
  setor_origem_id: number | null
  setor_destino_id: number
  produto_id: number
  quantidade: string | number
  situacao: 'pendente' | 'atendida' | 'cancelada'
  observacao: string | null
  created_at: string
  produto?: Rotulado | null
  setor_origem?: Rotulado | null
  setor_destino?: Rotulado | null
}

export interface InventarioRow {
  id: number
  setor_id: number
  data: string
  situacao: 'aberto' | 'efetivado'
  setor?: Rotulado | null
  itens: Array<{
    id: number
    produto_id: number
    quantidade_contada: string | number
    quantidade_sistema: string | number | null
    produto?: Rotulado | null
  }>
}

export interface FechamentoRow {
  id: number
  setor_id: number
  produto_id: number
  data_fechamento: string
  saldo_inicial: string | number
  saldo_final: string | number
  /** `true` = reaberto: deixou de travar os movimentos do par. */
  aberto: boolean
  setor?: Rotulado | null
  produto?: Rotulado | null
}

export function useSaldos(setorId: number | null, q: string) {
  return useQuery<SaldoRow[]>({
    queryKey: ['estoque-saldos', setorId, q],
    queryFn: async () => (await api.get('/estoque/saldos', { params: { setor_id: setorId, q } })).data.data,
  })
}

function useLista<T>(rota: string) {
  return useQuery<T[]>({ queryKey: ['estoque', rota], queryFn: async () => (await api.get(`/estoque/${rota}`)).data.data })
}
export const useTransferencias = () => useLista<any>('transferencias')
export const useRequisicoes = () => useLista<RequisicaoRow>('requisicoes')
export const useFisicos = () => useLista<InventarioRow>('fisico')
export const useFechamentos = () => useLista<FechamentoRow>('fechamentos')

/** Invalida uma lista do módulo e os saldos, que toda movimentação altera. */
function useAtualizar(lista?: string) {
  const qc = useQueryClient()
  return () => {
    if (lista) void qc.invalidateQueries({ queryKey: ['estoque', lista] })
    void qc.invalidateQueries({ queryKey: ['estoque-saldos'] })
  }
}

export interface LancamentoManual {
  /** Gerada quando o formulário abre e reaproveitada em todo reenvio. */
  chave: string
  movimentacao: 'ENTRADA' | 'SAIDA'
  setor_id: number
  produto_id: number
  quantidade: number
  motivo: string
}

/**
 * Lançamento manual (aba Acerto): entrada ou saída avulsa, com motivo.
 *
 * Vai para `/estoque/entrada` ou `/estoque/saida`, não para `/estoque/acerto` —
 * este último ajusta o saldo para uma quantidade CONTADA, que é o que a aba
 * Físico faz. A tela mandava movimentação/quantidade para ele e tomava 422.
 */
export const useLancamentoManual = () => {
  const atualizar = useAtualizar()
  return useMutation({
    mutationFn: async ({ chave, movimentacao, ...data }: LancamentoManual) =>
      (await api.post(movimentacao === 'ENTRADA' ? '/estoque/entrada' : '/estoque/saida', data, { headers: { 'Idempotency-Key': chave } })).data,
    onSuccess: atualizar,
  })
}

export const useCriarTransferencia = () => {
  const atualizar = useAtualizar('transferencias')
  return useMutation({
    // `chave` é gerada quando o formulário ABRE e reaproveitada em todo reenvio:
    // se a rede cair depois que o servidor gravou, repetir não move a
    // mercadoria de novo.
    mutationFn: async ({ chave, ...data }: Record<string, unknown> & { chave: string }) =>
      (await api.post('/estoque/transferencias', data, { headers: { 'Idempotency-Key': chave } })).data,
    onSuccess: atualizar,
  })
}

export interface NovaRequisicao {
  setor_origem_id: number | null
  setor_destino_id: number
  produto_id: number
  quantidade: number
  observacao: string | null
  atender: boolean
}
export const useCriarRequisicao = () => {
  const atualizar = useAtualizar('requisicoes')
  return useMutation({
    mutationFn: async (data: NovaRequisicao) => (await api.post('/estoque/requisicoes', data)).data,
    onSuccess: atualizar,
  })
}
export const useAtenderRequisicao = () => {
  const atualizar = useAtualizar('requisicoes')
  return useMutation({
    mutationFn: async ({ id, setor_origem_id }: { id: number; setor_origem_id: number | null }) =>
      (await api.post(`/estoque/requisicoes/${id}/atender`, { setor_origem_id })).data,
    onSuccess: atualizar,
  })
}

export interface NovoFisico {
  setor_id: number
  data: string
  itens: Array<{ produto_id: number; quantidade_contada: number }>
}
export const useCriarFisico = () => {
  const atualizar = useAtualizar('fisico')
  return useMutation({
    mutationFn: async (data: NovoFisico) => (await api.post('/estoque/fisico', data)).data,
    onSuccess: atualizar,
  })
}
export const useEfetivarFisico = () => {
  const atualizar = useAtualizar('fisico')
  return useMutation({
    mutationFn: async (id: number) => (await api.post(`/estoque/fisico/${id}/efetivar`)).data,
    onSuccess: atualizar,
  })
}

export const useFechar = () => {
  const atualizar = useAtualizar('fechamentos')
  return useMutation({
    mutationFn: async (data: { setor_id: number; produto_id: number; data_fechamento: string }) =>
      (await api.post('/estoque/fechamentos', data)).data,
    onSuccess: atualizar,
  })
}

/** Reabre um fechamento: ele deixa de travar os movimentos do par. O motivo vai para a trilha. */
export const useReabrirFechamento = () => {
  const atualizar = useAtualizar('fechamentos')
  return useMutation({
    mutationFn: async ({ id, motivo }: { id: number; motivo: string }) =>
      (await api.post(`/estoque/fechamentos/${id}/reabrir`, { motivo })).data,
    onSuccess: atualizar,
  })
}

// ---- Inventário fiscal (SPED Fiscal, Bloco H) ----
// O estoque DECLARADO ao fisco. Não é a contagem física: não tem setor, tem
// valor, e gravar não mexe em saldo nenhum.
export interface InventarioFiscalRow {
  id: number
  mes_entrega: string
  data_inventario: string
  motivo: string
  valor_total: string | number
  itens: Array<{ id: number; produto_id: number; descricao_snapshot: string | null; quantidade: string | number; valor_unitario: string | number }>
}
export interface SugestaoInventarioFiscal { produto_id: number; descricao: string; quantidade: number; valor_unitario: number | null }
export interface NovoInventarioFiscal {
  mes_entrega: string
  data_inventario: string
  itens: Array<{ produto_id: number; quantidade: number; valor_unitario: number }>
}

export const useInventariosFiscais = () =>
  useQuery<InventarioFiscalRow[]>({ queryKey: ['inventarios-fiscais'], queryFn: async () => (await api.get('/fiscal/inventarios')).data.data })

/** Saldo e custo médio de AGORA — ponto de partida, a conferir. Só busca quando pedido. */
export async function buscarSugestaoInventarioFiscal(): Promise<SugestaoInventarioFiscal[]> {
  return (await api.get('/fiscal/inventarios/sugestao')).data.data
}

export const useCriarInventarioFiscal = () => {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (data: NovoInventarioFiscal) => (await api.post('/fiscal/inventarios', data)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['inventarios-fiscais'] }),
  })
}
export const useExcluirInventarioFiscal = () => {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, motivo }: { id: number; motivo: string }) =>
      (await api.delete(`/fiscal/inventarios/${id}`, { data: { motivo } })).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['inventarios-fiscais'] }),
  })
}
