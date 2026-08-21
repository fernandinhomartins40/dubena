import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

/**
 * Alçadas de desconto (F2).
 *
 * **Por que esta tela é indispensável.** A verificação é fail-closed: sem regra
 * cadastrada, o teto é ZERO e ninguém concede desconto nenhum. Enquanto não
 * houver por onde cadastrar, o sistema fica *mais* travado que antes — o oposto
 * do que a fase pretende.
 */

export interface Alcada {
  id: number
  role_id: number | null
  colaborador_id: number | null
  produto_id: number | null
  produto: string | null
  setor_id: number | null
  condicaopagamento_id: number | null
  percentual_max: number
  valor_max: number | null
  base_calculo: 'tabela' | 'praticado'
  permite_solicitar: boolean
  data_inicio: string | null
  data_fim: string | null
  ativo: boolean
  /** Quão específica é a regra — desempata quando várias batem no mesmo item. */
  especificidade: number
}

export type AlcadaForm = Partial<Omit<Alcada, 'id' | 'produto' | 'especificidade'>> & {
  percentual_max: number
}

export function useAlcadas() {
  return useQuery<Alcada[]>({
    queryKey: ['alcadas'],
    queryFn: async () => (await api.get('/alcadas')).data.data,
  })
}

function useInvalidarAlcadas() {
  const qc = useQueryClient()
  return () => qc.invalidateQueries({ queryKey: ['alcadas'] })
}

export function useSalvarAlcada() {
  const invalidar = useInvalidarAlcadas()
  return useMutation({
    mutationFn: async (v: { id?: number } & AlcadaForm) => {
      const { id, ...dados } = v
      const r = id ? await api.put(`/alcadas/${id}`, dados) : await api.post('/alcadas', dados)
      return r.data.data
    },
    onSuccess: invalidar,
  })
}

export function useExcluirAlcada() {
  const invalidar = useInvalidarAlcadas()
  return useMutation({
    mutationFn: async (id: number) => (await api.delete(`/alcadas/${id}`)).data.data,
    onSuccess: invalidar,
  })
}
