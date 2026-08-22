import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

/** Critério de cobrança — a ordem de precedência vive no backend. */
export type CriterioTaxa = 'valor_pedido' | 'bairro' | 'distancia' | 'cidade' | 'padrao'

export interface RegraTaxa {
  id: number
  descricao: string
  criterio: CriterioTaxa
  bairro_id: number | null
  bairro: string | null
  cidade_id: number | null
  cidade: string | null
  /** Faixa dos critérios numéricos: km (distância) ou R$ (valor do pedido). */
  faixa_de: number | null
  faixa_ate: number | null
  valor: number
  isenta: boolean
  custo_estimado: number | null
  /** Cobrado menos custo — a resposta para "esta entrega dá lucro?". */
  margem: number | null
  prioridade: number
  ativo: boolean
}

export type RegraTaxaForm = Omit<RegraTaxa, 'id' | 'bairro' | 'cidade' | 'margem'>

export function useTaxasEntrega() {
  return useQuery<RegraTaxa[]>({
    queryKey: ['taxas-entrega'],
    queryFn: async () => (await api.get('/taxas-entrega')).data.data,
  })
}

export function useSalvarTaxa() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, data }: { id: number | null; data: Partial<RegraTaxaForm> }) =>
      id
        ? (await api.put(`/taxas-entrega/${id}`, data)).data
        : (await api.post('/taxas-entrega', data)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['taxas-entrega'] }),
  })
}

export function useExcluirTaxa() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => (await api.delete(`/taxas-entrega/${id}`)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['taxas-entrega'] }),
  })
}

export interface SimulacaoTaxa {
  valor: number
  custo: number | null
  margem: number | null
  isenta: boolean
  regra_id: number | null
  regra: string | null
}

/**
 * Confere a configuração antes de vender: sem isto, o primeiro teste de uma
 * tabela de preços seria um pedido real.
 */
export function useSimularTaxa(clienteId: number | null, valorPedido: number) {
  return useQuery<SimulacaoTaxa>({
    queryKey: ['taxa-simulacao', clienteId, valorPedido],
    queryFn: async () =>
      (await api.get('/taxas-entrega/simular', {
        params: { cliente_id: clienteId, valor_pedido: valorPedido },
      })).data.data,
    enabled: clienteId !== null,
  })
}
