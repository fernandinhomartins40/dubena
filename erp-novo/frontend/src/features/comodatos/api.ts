import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { abrirPdf } from '@/lib/pdf'

/** Comodatos (empréstimo de vasilhame) — domínio do ERP (N8). */
export interface Comodato {
  id: number; cliente_id: number | null; produto_id: number | null
  quantidade: string; quantidade_devolvida: string; situacao: string; data_emprestimo: string
  cliente?: { id: number; nome: string }; produto?: { id: number; descricao: string }
}
export const useComodatos = () =>
  useQuery<Comodato[]>({ queryKey: ['comodatos'], queryFn: async () => (await api.get('/comodatos')).data.data })
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
    mutationFn: async ({ id, quantidade }: { id: number; quantidade: number }) => (await api.post(`/comodatos/${id}/devolver`, { quantidade })).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['comodatos'] }),
  })
}

/** Abre o contrato de comodato numa aba para colher a assinatura. */
export const abrirContratoComodato = (id: number) => abrirPdf(`/comodatos/${id}/contrato`)
