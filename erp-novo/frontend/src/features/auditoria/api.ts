import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'

/** Uma alteração de campo dentro de uma ação, já com rótulo legível. */
export interface Alteracao {
  campo: string
  rotulo: string
  de: string
  para: string
}

/** Uma linha da trilha — uma ação de uma pessoa sobre um registro. */
export interface AcaoTrilha {
  id: number
  entidade: string
  entidade_rotulo: string
  entidade_id: number | null
  /** Nome do registro NO MOMENTO da ação (não o nome atual). */
  alvo: string | null
  acao: string
  acao_rotulo: string
  /** Decisão (desativar, estornar, aprovar) — a tela destaca estas. */
  sensivel: boolean
  motivo: string | null
  /** Nulo em ação de sistema (ETL, cron): a tela mostra "Sistema". */
  autor: string | null
  autor_id: number | null
  ip: string | null
  criado_em: string
  alteracoes: Alteracao[]
}

export interface FiltrosTrilha {
  entidade?: string
  acao?: string
  user_id?: number
  inicio?: string
  fim?: string
  apenas_sensiveis?: boolean
  cliente_id?: number
}

interface Paginado<T> {
  data: T[]
  meta: { current_page: number; last_page: number; per_page?: number; total: number }
}

export function useTrilha(filtros: FiltrosTrilha, page: number) {
  return useQuery<Paginado<AcaoTrilha>>({
    queryKey: ['auditoria-trilha', filtros, page],
    queryFn: async () => (await api.get('/auditoria/trilha', { params: { ...filtros, page } })).data,
    placeholderData: (prev) => prev,
  })
}

export interface ResumoAcao {
  acao: string
  rotulo: string
  total: number
  ultima: string | null
  sensivel: boolean
}

interface TrilhaRegistro extends Paginado<AcaoTrilha> {
  resumo: ResumoAcao[]
  entidade_rotulo: string
}

/** Trilha de UM registro (ex.: tudo que aconteceu com o cliente 50218). */
export function useTrilhaRegistro(entidade: string | null, id: number | null) {
  return useQuery<TrilhaRegistro>({
    queryKey: ['auditoria-registro', entidade, id],
    queryFn: async () => (await api.get(`/auditoria/registro/${entidade}/${id}`)).data,
    enabled: !!entidade && id !== null,
  })
}

export interface OpcoesTrilha {
  entidades: { valor: string; rotulo: string }[]
  acoes: { valor: string; rotulo: string; sensivel: boolean }[]
  autores: { valor: number; rotulo: string }[]
}

/** Só o que REALMENTE aparece na trilha — filtro que não devolve nada é ruído. */
export function useOpcoesTrilha() {
  return useQuery<OpcoesTrilha>({
    queryKey: ['auditoria-opcoes'],
    queryFn: async () => (await api.get('/auditoria/opcoes')).data.data,
  })
}

export interface ClienteBusca {
  id: number
  nome: string
  documento: string | null
  ativo: boolean
}

/**
 * Busca de cliente para o filtro. Alcança TAMBÉM o desativado — que é sobre
 * quem mais se pergunta "quem desativou e por quê".
 */
export function useBuscarClientes(q: string) {
  return useQuery<ClienteBusca[]>({
    queryKey: ['auditoria-clientes', q],
    queryFn: async () => (await api.get('/auditoria/clientes', { params: { q } })).data.data,
    enabled: q.trim().length >= 2,
  })
}
