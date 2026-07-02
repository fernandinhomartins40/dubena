import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

/** Missões de campo (L7/L9) — moldes + auditoria das execuções. */

export interface Missao {
  id: number
  tipo: string
  titulo: string
  descricao: string | null
  meta_visitas: number | null
  exige_foto: boolean
  ativo: boolean
  atribuicoes_count?: number
}

export interface Atribuicao {
  id: number
  status: string
  automatica: boolean
  missao: string | null
  tipo: string | null
  entregador: string | null
  visitas: number
  iniciada_em: string | null
  concluida_em: string | null
  adiamento: { motivo: string; detalhe: string | null; em: string | null; aprovacao: string | null } | null
  auditoria: { resultado: string; sancao: string | null; observacao: string | null; em: string | null } | null
}

export interface AtribuicaoDetalhe {
  id: number
  status: string
  missao: { titulo: string | null; tipo: string | null }
  entregador: string | null
  metricas: {
    visitas_total: number
    vendas: number
    interessados: number
    distancia_km: number
    duracao_min: number | null
    pontos_trilha: number
    por_status: Record<string, number>
  }
  visitas: {
    id: number
    status: string
    cliente: string | null
    pedido_id: number | null
    lat: number | null
    lng: number | null
    em: string | null
    observacao: string | null
    evidencias: { id: number; tipo: string }[]
  }[]
  trilha: { lat: number; lng: number; em: string | null }[]
}

export const TIPOS_MISSAO = [
  { valor: 'panfletagem', label: 'Panfletagem' },
  { valor: 'visita_comercial', label: 'Visita comercial' },
  { valor: 'divulgacao_valegas', label: 'Divulgação Vale Gás' },
  { valor: 'prospeccao', label: 'Prospecção' },
  { valor: 'acao_promocional', label: 'Ação promocional' },
  { valor: 'campanha_bairro', label: 'Campanha de bairro' },
]

export const useMissoes = () =>
  useQuery<Missao[]>({ queryKey: ['missoes'], queryFn: async () => (await api.get('/missoes')).data.data })

export const useAtribuicoes = (status?: string) =>
  useQuery<Atribuicao[]>({
    queryKey: ['missoes-atribuicoes', status ?? 'todas'],
    queryFn: async () => (await api.get('/missoes/atribuicoes', { params: status ? { status } : {} })).data.data,
    refetchInterval: 15000,
  })

export const useAtribuicaoDetalhe = (id: number | null) =>
  useQuery<AtribuicaoDetalhe>({
    queryKey: ['missoes-atribuicao', id],
    queryFn: async () => (await api.get(`/missoes/atribuicoes/${id}`)).data.data,
    enabled: id !== null,
  })

export function useSalvarMissao() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...data }: Partial<Missao> & { janela_inicio?: string | null; janela_fim?: string | null }) =>
      (id ? api.put(`/missoes/${id}`, data) : api.post('/missoes', data)).then((r) => (r as any).data.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['missoes'] }),
  })
}

export function useAtribuirMissao() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (v: { missaoId: number; entregador_user_id: number }) =>
      (await api.post(`/missoes/${v.missaoId}/atribuir`, { entregador_user_id: v.entregador_user_id })).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['missoes-atribuicoes'] }),
  })
}

export function useAuditar() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (v: { id: number; resultado: 'aprovada' | 'reprovada' | 'revisao'; sancao?: string; observacao?: string }) =>
      (await api.post(`/missoes/atribuicoes/${v.id}/auditar`, {
        resultado: v.resultado, sancao: v.sancao ?? 'nenhuma', observacao: v.observacao ?? null,
      })).data.data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['missoes-atribuicoes'] })
      qc.invalidateQueries({ queryKey: ['missoes-atribuicao'] })
    },
  })
}

export function useDecidirAdiamento() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (v: { id: number; decisao: 'aprovado' | 'reprovado' }) =>
      (await api.post(`/missoes/atribuicoes/${v.id}/adiamento`, { decisao: v.decisao })).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['missoes-atribuicoes'] }),
  })
}

/** URL absoluta da evidência (streaming autenticado via axios baseURL + token no header não funciona em <img>; usa endpoint com fetch). */
export async function carregarEvidencia(id: number): Promise<string> {
  const resp = await api.get(`/missoes/evidencias/${id}`, { responseType: 'blob' })
  return URL.createObjectURL(resp.data)
}
