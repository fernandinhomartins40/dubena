import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

/** Um dos dois lados do par suspeito. */
export interface CadastroRevisao {
  id: number
  nome: string
  documento: string | null
  email: string | null
  endereco: string | null
  telefones: string[]
  ativo: boolean
  criado_em: string | null
}

export interface RevisaoItem {
  id: number
  escore: number
  confianca: 'alta' | 'media' | 'baixa'
  /** O que casou — é o que permite decidir sem conferir campo a campo. */
  motivos: string[]
  origem: string | null
  situacao: 'pendente' | 'consolidado' | 'descartado'
  criado_em: string | null
  decidido_por: string | null
  decidido_em: string | null
  observacao: string | null
  cliente: CadastroRevisao | null
  candidato: CadastroRevisao | null
}

interface Paginado {
  data: RevisaoItem[]
  meta: { current_page: number; last_page: number; total: number; pendentes: number }
}

export function useRevisoes(situacao: string, page: number) {
  return useQuery<Paginado>({
    queryKey: ['cliente-revisoes', situacao, page],
    queryFn: async () => (await api.get('/clientes/revisoes', { params: { situacao, page } })).data,
    placeholderData: (prev) => prev,
  })
}

/** Consolida o par. `principal_id` escolhe quem sobrevive (default: o mais antigo). */
export function useConsolidarRevisao() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, principalId }: { id: number; principalId?: number }) =>
      (await api.post(`/clientes/revisoes/${id}/consolidar`, { principal_id: principalId })).data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['cliente-revisoes'] })
      qc.invalidateQueries({ queryKey: ['clientes'] })
    },
  })
}

/** São pessoas diferentes: fecha o par sem tocar nos cadastros. */
export function useDescartarRevisao() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, observacao }: { id: number; observacao?: string }) =>
      (await api.post(`/clientes/revisoes/${id}/descartar`, { observacao })).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['cliente-revisoes'] }),
  })
}

/** Sugestões de "quem pode ser esta pessoa", para a tela de cadastro. */
export interface Sugestao {
  cliente_id: number
  nome: string
  documento: string | null
  ativo: boolean
  escore: number
  confianca: 'alta' | 'media' | 'baixa'
  motivos: string[]
}

export function useSugestoesCliente(params: {
  nome?: string; telefone?: string; cpf?: string; cnpj?: string
}, habilitado: boolean) {
  return useQuery<Sugestao[]>({
    queryKey: ['cliente-sugestoes', params],
    queryFn: async () => (await api.get('/clientes/sugestoes', { params })).data.data,
    enabled: habilitado,
    // A sugestão acompanha o que o operador digita: sem isto, o resultado
    // antigo pisca na tela enquanto o novo carrega.
    placeholderData: undefined,
  })
}
