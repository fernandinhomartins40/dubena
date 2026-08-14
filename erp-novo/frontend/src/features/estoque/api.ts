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
export const useRequisicoes = () => useLista<any>('requisicoes')
export const useInventarios = () => useLista<any>('inventarios')
export const useFisicos = () => useLista<any>('fisico')
export const useFechamentos = () => useLista<any>('fechamentos')

function usePost(rota: string, invalidate: string[]) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (data: Record<string, unknown>) => (await api.post(`/estoque/${rota}`, data)).data,
    onSuccess: () => invalidate.forEach((k) => qc.invalidateQueries({ queryKey: ['estoque', k] }).catch?.(() => {})),
  })
}

export const useAcerto = () => {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (data: Record<string, unknown>) => (await api.post('/estoque/acerto', data)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['estoque-saldos'] }),
  })
}
export const useCriarTransferencia = () => {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (data: Record<string, unknown>) => (await api.post('/estoque/transferencias', data)).data,
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['estoque', 'transferencias'] }); qc.invalidateQueries({ queryKey: ['estoque-saldos'] }) },
  })
}
export const useCriarRequisicao = () => {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (data: Record<string, unknown>) => (await api.post('/estoque/requisicoes', data)).data,
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['estoque', 'requisicoes'] }); qc.invalidateQueries({ queryKey: ['estoque-saldos'] }) },
  })
}
export const useCriarInventario = () => usePost('inventarios', ['inventarios'])
export const useCriarFisico = () => usePost('fisico', ['fisico'])
export const useFechar = () => usePost('fechamentos', ['fechamentos'])

export const useEfetivarFisico = () => {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => (await api.post(`/estoque/fisico/${id}/efetivar`)).data,
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['estoque', 'fisico'] }); qc.invalidateQueries({ queryKey: ['estoque-saldos'] }) },
  })
}
export const useAbrirFechamento = () => {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (data: Record<string, unknown>) => (await api.post('/estoque/fechamentos/abrir', data)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['estoque', 'fechamentos'] }),
  })
}
