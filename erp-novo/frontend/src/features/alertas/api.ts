import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

/** Um item da central de alertas — a fila de averiguação da equipe. */
export interface Alerta {
  id: number
  origem: string
  severidade: 'ALTA' | 'MEDIA' | 'BAIXA'
  titulo: string
  descricao: string | null
  situacao: 'ABERTO' | 'EM_ANALISE' | 'RESOLVIDO' | 'IGNORADO'
  cliente_id: number | null
  comodato_id: number | null
  dados: Record<string, any> | null
  ocorrencias: number
  ultima_ocorrencia: string | null
  resolucao: string | null
  created_at: string
  cliente?: { id: number; nome: string } | null
  responsavel?: { id: number; name: string } | null
}

export interface ResumoAlertas {
  abertos: number; em_analise: number
  alta: number; media: number; baixa: number
}

export interface Filtros {
  origem?: string
  severidade?: string
  situacao?: string
}

export function useAlertas(filtros: Filtros = {}) {
  return useQuery<{ data: Alerta[]; resumo: ResumoAlertas }>({
    queryKey: ['alertas', filtros],
    queryFn: async () => (await api.get('/alertas', { params: filtros })).data,
  })
}

export function useAssumirAlerta() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => (await api.post(`/alertas/${id}/assumir`)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['alertas'] }),
  })
}

export function useEncerrarAlerta() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (v: { id: number; situacao: 'RESOLVIDO' | 'IGNORADO'; resolucao: string }) =>
      (await api.post(`/alertas/${v.id}/encerrar`, {
        situacao: v.situacao, resolucao: v.resolucao,
      })).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['alertas'] }),
  })
}
