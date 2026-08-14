import axios from 'axios'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

/**
 * Camada de API do SuperAdmin (P4) — ISOLADA do tenant.
 *
 * O painel da plataforma usa o guard `platform` (token Sanctum próprio, sobre
 * `platform_admins`), separado do login do tenant. Por isso:
 *  - baseURL própria em `/api/superadmin` (não `/api/admin`);
 *  - token guardado numa chave PRÓPRIA (não vaza com o token do ERP de tenant);
 *  - nenhuma resolução de tenant — o SuperAdmin é cross-tenant, tudo auditado no backend.
 */

// '/novo/app/' → '/novo' ; '/' → '' (dev) — mesmo cálculo do lib/api.ts
const PREFIX = import.meta.env.BASE_URL.replace(/\/app\/?$/, '').replace(/\/$/, '')
const TOKEN_KEY = 'superadmin_token'

export function setSaToken(token: string | null): void {
  if (token) localStorage.setItem(TOKEN_KEY, token)
  else localStorage.removeItem(TOKEN_KEY)
}
export function getSaToken(): string | null {
  return localStorage.getItem(TOKEN_KEY)
}

/**
 * O backend usa `statefulApi()` do Sanctum: como o painel roda no MESMO domínio
 * da SPA, o navegador anexa os cookies de sessão/XSRF automaticamente e o Sanctum
 * trata a requisição como stateful → exige CSRF (senão 419). Por isso o cliente
 * envia credenciais + reflete o XSRF-TOKEN no header (igual ao lib/api.ts do tenant).
 * O guard real continua sendo o Bearer token da plataforma.
 */
export const saApi = axios.create({
  baseURL: `${PREFIX}/api/superadmin`,
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: { Accept: 'application/json' },
})

/** Garante o cookie CSRF (Sanctum) antes de um POST stateful (login/escrita). */
export async function ensureSaCsrf(): Promise<void> {
  await axios.get(`${PREFIX}/sanctum/csrf-cookie`, { withCredentials: true })
}

saApi.interceptors.request.use((config) => {
  const token = getSaToken()
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

// 401 → token inválido/expirado: limpa para o guard de rota mandar ao login.
saApi.interceptors.response.use(
  (r) => r,
  (error) => {
    if (error?.response?.status === 401) setSaToken(null)
    return Promise.reject(error)
  },
)

// ──────────────────────────── Tipos ────────────────────────────

export interface SaAdmin {
  id: number
  nome: string
  email: string
  twofa_habilitado?: boolean
}

export interface SaDashboard {
  empresas_total: number
  empresas_ativas: number
  assinaturas_ativas: number
  assinaturas_inadimplentes: number
  planos: number
  por_plano: Record<string, number>
}

export interface SaEmpresa {
  id: number
  razao_social: string
  nome_fantasia: string | null
  cnpj: string | null
  grupo: string | null
  ativo: boolean
  plano: string | null
  status_assinatura: string | null
}

export interface SaPlano {
  id: number
  nome: string
  slug: string
  descricao: string | null
  preco_mensal: string | number | null
  ativo: boolean
  recursos?: string[]
}

export interface SaCidade {
  id: number
  nome: string
  uf: string
  cod_ibge: number | null
  centro_lat: string | number | null
  centro_lng: string | number | null
  ativo: boolean
}

/** recursosEfetivos do backend = lista plana das CHAVES habilitadas para a empresa. */
export type SaRecursosEfetivos = string[]

export interface SaAuditoria {
  id: number
  acao: string
  empresa_id: number | null
  entidade: string | null
  entidade_id: number | null
  ip: string | null
  criado_em: string
  admin: string | null
}

// ──────────────────────────── Auth ────────────────────────────

/** Login da plataforma. 423 = 2FA exigido (tratado na tela). */
export async function saLogin(email: string, password: string, otp?: string): Promise<SaAdmin> {
  // Garante o cookie CSRF antes do POST (Sanctum stateful no mesmo domínio).
  try { await ensureSaCsrf() } catch { /* sem cookie → tenta mesmo assim (Bearer) */ }
  const { data } = await saApi.post('/login', { email, password, ...(otp ? { otp } : {}) })
  setSaToken(data.token)
  return data.admin
}
export async function saLogout(): Promise<void> {
  try { await saApi.post('/logout') } finally { setSaToken(null) }
}
export async function saMe(): Promise<SaAdmin> {
  const { data } = await saApi.get('/me')
  return data.admin
}

// ──────────────────────────── Hooks ────────────────────────────

export const useSaDashboard = () =>
  useQuery<SaDashboard>({ queryKey: ['sa', 'dashboard'], queryFn: async () => (await saApi.get('/dashboard')).data.data })

export const useSaEmpresas = (q?: string) =>
  useQuery<SaEmpresa[]>({
    queryKey: ['sa', 'empresas', q ?? ''],
    queryFn: async () => (await saApi.get('/empresas', { params: q ? { q } : {} })).data.data,
  })

export function useSaEmpresaAcoes() {
  const qc = useQueryClient()
  const inval = () => qc.invalidateQueries({ queryKey: ['sa', 'empresas'] })
  return {
    suspender: useMutation({ mutationFn: async (id: number) => (await saApi.post(`/empresas/${id}/suspender`)).data, onSuccess: inval }),
    reativar: useMutation({ mutationFn: async (id: number) => (await saApi.post(`/empresas/${id}/reativar`)).data, onSuccess: inval }),
    definirAssinatura: useMutation({
      mutationFn: async ({ id, ...body }: { id: number; plano_id: number; status?: string; trial_ate?: string | null }) =>
        (await saApi.put(`/empresas/${id}/assinatura`, body)).data,
      onSuccess: inval,
    }),
    alterarStatus: useMutation({
      mutationFn: async ({ id, status }: { id: number; status: string }) =>
        (await saApi.put(`/empresas/${id}/assinatura/status`, { status })).data,
      onSuccess: inval,
    }),
  }
}

export const useSaRecursos = (empresaId: number | null) =>
  useQuery<SaRecursosEfetivos>({
    queryKey: ['sa', 'recursos', empresaId],
    enabled: empresaId != null,
    queryFn: async () => (await saApi.get(`/empresas/${empresaId}/recursos`)).data.data,
  })

export function useSaOverride() {
  const qc = useQueryClient()
  const inval = (empresaId: number) => qc.invalidateQueries({ queryKey: ['sa', 'recursos', empresaId] })
  return {
    set: useMutation({
      mutationFn: async ({ empresaId, chave, habilitado }: { empresaId: number; chave: string; habilitado: boolean }) =>
        (await saApi.put(`/empresas/${empresaId}/override`, { recurso_chave: chave, habilitado })).data,
      onSuccess: (_d, v) => inval(v.empresaId),
    }),
    remover: useMutation({
      mutationFn: async ({ empresaId, chave }: { empresaId: number; chave: string }) =>
        (await saApi.delete(`/empresas/${empresaId}/override/${chave}`)).data,
      onSuccess: (_d, v) => inval(v.empresaId),
    }),
  }
}

export interface SaPlanosResposta {
  planos: SaPlano[]
  catalogo: { chave: string; descricao: string }[]
}
export const useSaPlanos = () =>
  useQuery<SaPlanosResposta>({
    queryKey: ['sa', 'planos'],
    queryFn: async () => {
      const { data } = await saApi.get('/planos')
      return { planos: data.data, catalogo: data.catalogo_recursos ?? [] }
    },
  })

export function useSaSalvarPlano() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, data }: { id: number | null; data: Record<string, unknown> }) =>
      id ? (await saApi.put(`/planos/${id}`, data)).data.data : (await saApi.post('/planos', data)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['sa', 'planos'] }),
  })
}

export const useSaCidades = () =>
  useQuery<SaCidade[]>({ queryKey: ['sa', 'cidades'], queryFn: async () => (await saApi.get('/cidades')).data.data })

export function useSaCidadeAcoes() {
  const qc = useQueryClient()
  const inval = () => qc.invalidateQueries({ queryKey: ['sa', 'cidades'] })
  return {
    salvar: useMutation({
      mutationFn: async ({ id, data }: { id: number | null; data: Record<string, unknown> }) =>
        id ? (await saApi.put(`/cidades/${id}`, data)).data.data : (await saApi.post('/cidades', data)).data.data,
      onSuccess: inval,
    }),
    excluir: useMutation({ mutationFn: async (id: number) => (await saApi.delete(`/cidades/${id}`)).data, onSuccess: inval }),
  }
}

export const useSaAuditoria = () =>
  useQuery<SaAuditoria[]>({ queryKey: ['sa', 'auditoria'], queryFn: async () => (await saApi.get('/auditoria')).data.data })

// ── Migração de sistemas antigos ──────────────────────────────────────────────

export type SaMigracaoStatus =
  | 'pendente' | 'diagnosticando' | 'aguardando_mapeamento'
  | 'migrando' | 'concluida' | 'falhou'

export interface SaMigracao {
  id: number
  descricao: string
  origem_tipo: string
  status: SaMigracaoStatus
  progresso: number
  etapa_atual: string | null
  erro?: string | null
  diagnostico?: SaDiagnostico | null
  mapa_empresas?: SaMapaEmpresa[] | null
  resultado?: Record<string, SaResultadoEtapa> | null
  descartes_count?: number
  iniciada_em: string | null
  concluida_em: string | null
  created_at: string
}

export interface SaEmpresaOrigem {
  id_origem: number
  nome: string
  cnpj: string | null
  tenant_sugerido: number | null
  acao_sugerida: 'mapear' | 'criar'
}

export interface SaDiagnostico {
  tabelas_encontradas: number
  contagens: Record<string, number>
  empresas: SaEmpresaOrigem[]
  alertas: { tipo: string; mensagem: string }[]
}

export interface SaResultadoEtapa {
  lidos: number
  gravados: number
  pulados: number
  avisos?: string[]
  erro?: string
}

export interface SaMapaEmpresa {
  id_origem: number
  acao: 'mapear' | 'criar' | 'ignorar'
  empresa_id?: number | null
}

export const useSaMigracoes = () =>
  useQuery<SaMigracao[]>({
    queryKey: ['sa', 'migracoes'],
    queryFn: async () => (await saApi.get('/migracoes')).data.data,
    // Enquanto houver carga rodando, a lista se atualiza sozinha.
    refetchInterval: (q) =>
      (q.state.data ?? []).some((m) => m.status === 'migrando' || m.status === 'diagnosticando')
        ? 3000
        : false,
  })

export const useSaMigracao = (id: number | null) =>
  useQuery<SaMigracao>({
    queryKey: ['sa', 'migracao', id],
    enabled: id != null,
    queryFn: async () => (await saApi.get(`/migracoes/${id}`)).data.data,
    refetchInterval: (q) => {
      const s = q.state.data?.status
      return s === 'migrando' || s === 'diagnosticando' ? 2000 : false
    },
  })

export function useSaMigracaoAcoes() {
  const qc = useQueryClient()
  const inval = (id?: number) => {
    qc.invalidateQueries({ queryKey: ['sa', 'migracoes'] })
    if (id != null) qc.invalidateQueries({ queryKey: ['sa', 'migracao', id] })
  }

  return {
    criar: useMutation({
      mutationFn: async (data: Record<string, unknown>) =>
        (await saApi.post('/migracoes', data)).data.data as SaMigracao,
      onSuccess: () => inval(),
    }),
    conectar: useMutation({
      mutationFn: async (id: number) => (await saApi.post(`/migracoes/${id}/conectar`)).data.data,
    }),
    diagnosticar: useMutation({
      mutationFn: async (id: number) =>
        (await saApi.post(`/migracoes/${id}/diagnosticar`)).data.data as SaDiagnostico,
      onSuccess: (_d, id) => inval(id),
    }),
    salvarMapa: useMutation({
      mutationFn: async ({ id, mapa }: { id: number; mapa: SaMapaEmpresa[] }) =>
        (await saApi.put(`/migracoes/${id}/mapeamento`, { mapa })).data.data,
      onSuccess: (_d, v) => inval(v.id),
    }),
    simular: useMutation({
      mutationFn: async (id: number) =>
        (await saApi.post(`/migracoes/${id}/simular`)).data.data as Record<string, SaResultadoEtapa>,
    }),
    executar: useMutation({
      mutationFn: async (id: number) => (await saApi.post(`/migracoes/${id}/executar`)).data,
      onSuccess: (_d, id) => inval(id),
    }),
    validar: useMutation({
      mutationFn: async (id: number) =>
        (await saApi.get(`/migracoes/${id}/validar`)).data.data as {
          migrador: string; invariante: string; ok: boolean; resumo: string
        }[],
    }),
  }
}

/** URL do CSV de descartes (o que não entrou, com o dado de origem). */
export const saDescartesCsvUrl = (id: number) =>
  `${PREFIX}/api/superadmin/migracoes/${id}/descartes.csv`
