import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

/**
 * Mercadoria em poder do franqueado (F5).
 *
 * Antes disto o sistema não sabia o que estava na rua: o que a Central de
 * Logística chama de "carga" é número de pedidos atribuídos, não botijão. Aqui é
 * estoque físico — a base do acerto no fim do turno.
 *
 * Dois modos, fixos por pessoa: `consignacao` (mercadoria da empresa em poder
 * dele, com devolução do que sobrou) e `compra` (a mercadoria é dele, e não
 * retorna).
 */

export interface ItemEmPoder {
  produto_id: number
  produto: string
  quantidade: number
}

export interface EstoqueFranqueado {
  colaborador: { id: number; nome: string }
  modo_estoque: 'consignacao' | 'compra' | null
  itens: ItemEmPoder[]
}

export interface ItemMovimento {
  produto_id: number
  quantidade: number
}

export function useEstoqueFranqueado(colaboradorId: number | null) {
  return useQuery<EstoqueFranqueado>({
    queryKey: ['franqueado-estoque', colaboradorId],
    queryFn: async () => (await api.get(`/franqueados/${colaboradorId}/estoque`)).data.data,
    enabled: colaboradorId !== null,
  })
}

function useInvalidarEstoque() {
  const qc = useQueryClient()
  return () => qc.invalidateQueries({ queryKey: ['franqueado-estoque'] })
}

export function useCarregar() {
  const invalidar = useInvalidarEstoque()
  return useMutation({
    mutationFn: async (v: { colaboradorId: number; setor_origem_id: number; itens: ItemMovimento[] }) =>
      (await api.post(`/franqueados/${v.colaboradorId}/carga`, {
        setor_origem_id: v.setor_origem_id, itens: v.itens,
      })).data.data,
    onSuccess: invalidar,
  })
}

/** Só faz sentido na consignação — o backend recusa (422) no modo compra. */
export function useDevolver() {
  const invalidar = useInvalidarEstoque()
  return useMutation({
    mutationFn: async (v: { colaboradorId: number; setor_origem_id: number; itens: ItemMovimento[] }) =>
      (await api.post(`/franqueados/${v.colaboradorId}/devolucao`, {
        setor_origem_id: v.setor_origem_id, itens: v.itens,
      })).data.data,
    onSuccess: invalidar,
  })
}
