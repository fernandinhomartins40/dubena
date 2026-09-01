import { createContext, useContext, useEffect, type ReactNode } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { apiPrefix, ensureCsrf, setToken, getToken, TOKEN_KEY } from './api'
import { can as canFn, canField as canFieldFn, hasFeature as hasFeatureFn } from './rbac'

export interface AuthUser {
  id: number
  name: string
  email: string
  empresa_id: number | null
  grupo_id: number | null
  is_support: boolean
  roles: string[]
  permissions: string[]
  /** Recursos CONTRATADOS pela empresa (F2-03). Ausente em backend antigo. */
  features?: string[]
}

interface AuthContextValue {
  user: AuthUser | null
  loading: boolean
  login: (email: string, password: string, manterConectado?: boolean, otp?: string) => Promise<void>
  logout: () => Promise<void>
  can: (permission: string) => boolean
  /** Field-level (A7): pode ver/editar um campo controlado? `can('modulo.campo.{nome}.{acao}')`. */
  canField: (modulo: string, campo: string, acao: 'view' | 'edit') => boolean
  /** O modulo esta contratado no plano da empresa? (F2-03) */
  hasFeature: (feature: string) => boolean
  refresh: () => Promise<void>
}

const AuthContext = createContext<AuthContextValue | null>(null)

// Endpoints de auth ficam na RAIZ da API (/api), não sob /api/admin.
const authUrl = (path: string) => `${apiPrefix}/api${path}`

/** Lê o cookie XSRF-TOKEN (setado por /sanctum/csrf-cookie) e url-decodifica. */
function lerXsrf(): string | null {
  const m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)
  return m ? decodeURIComponent(m[1]) : null
}

/** Normaliza o /me do backend novo ({user,tenant}) para o shape da SPA. */
export function normalizarMe(data: any): AuthUser {
  const u = data.user ?? data
  const t = data.tenant ?? {}
  return {
    id: u.id,
    name: u.name,
    email: u.email,
    empresa_id: t.empresa_id ?? u.empresa_id ?? null,
    grupo_id: t.grupo_id ?? u.grupo_id ?? null,
    is_support: Boolean(u.support ?? u.is_support ?? false),
    roles: u.roles ?? [],
    permissions: u.permissions ?? [],
  }
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const qc = useQueryClient()

  const { data: user, isLoading } = useQuery<AuthUser | null>({
    queryKey: ['me'],
    queryFn: async () => {
      try {
        const { data } = await axios.get(authUrl('/me'), {
          withCredentials: true,
          headers: {
            Accept: 'application/json',
            ...(getToken() ? { Authorization: `Bearer ${getToken()}` } : {}),
          },
        })
        return normalizarMe(data)
      } catch {
        return null // 401 → não logado
      }
    },
    retry: false,
    // FE-2: RBAC/permissões vivem no /me. Um staleTime de 5 min mantinha a UI
    // liberada por até 5 min após uma revogação de papel. Reduzido para 60s e
    // revalidando ao focar a janela — o backend segue sendo a autoridade (isto é
    // só a rapidez com que a UX reflete a mudança). refresh() força na hora.
    staleTime: 60 * 1000,
    refetchOnWindowFocus: true,
  })

  /**
   * F9-08 — duas abas.
   *
   * O token vive em `localStorage`, que é **compartilhado entre abas do mesmo
   * navegador**. Sem escutar o evento `storage`, cada aba fica com uma visão
   * própria da identidade, e as duas divergem em silêncio:
   *
   *  - **logout na aba A.** A aba B não sabe: continua exibindo a carteira, o
   *    financeiro e os pedidos da sessão encerrada, do cache em memória. O
   *    operador acha que está logado; a próxima requisição vai sem token e falha
   *    de um jeito que não explica nada;
   *  - **login de OUTRA pessoa na aba A.** É o caso grave num SaaS: a aba B
   *    passa a mandar o token da revenda B, mas a tela continua renderizando o
   *    dado da revenda A que já estava em cache. Dado de um concorrente na tela,
   *    credencial de outro nas requisições.
   *
   * O tratamento é o mesmo do logout, e pela mesma razão de ordem:
   * `cancelQueries` antes de `clear`, senão uma requisição em voo — disparada
   * com a identidade velha — chega depois da limpeza e repovoa o cache já sob a
   * identidade nova.
   *
   * `storage` só dispara nas OUTRAS abas, nunca na que escreveu. É exatamente o
   * que se quer: quem fez a ação já tratou o próprio cache.
   */
  useEffect(() => {
    const aoMudarStorage = (evento: StorageEvent) => {
      // `key === null` é `localStorage.clear()` — trata como troca de
      // identidade, porque o token pode ter ido junto.
      if (evento.key !== null && evento.key !== TOKEN_KEY) {
        return
      }

      void (async () => {
        await qc.cancelQueries()
        qc.clear()
      })()
    }

    window.addEventListener('storage', aoMudarStorage)

    return () => window.removeEventListener('storage', aoMudarStorage)
  }, [qc])

  const loginMut = useMutation({
    mutationFn: async ({ email, password, otp, manterConectado = true }: { email: string; password: string; otp?: string; manterConectado?: boolean }) => {
      // Tenta o fluxo cookie (csrf). Se o cookie falhar, o token Bearer (abaixo)
      // ainda autentica — login robusto a problemas de CSRF cross-path.
      try { await ensureCsrf() } catch { /* segue: usaremos o token */ }

      const { data } = await axios.post(authUrl('/login'), { email, password, ...(otp ? { otp } : {}) }, {
        withCredentials: true,
        headers: {
          Accept: 'application/json',
          // XSRF lido do cookie e enviado manualmente (axios global não injeta).
          ...(lerXsrf() ? { 'X-XSRF-TOKEN': lerXsrf() } : {}),
        },
      })
      // Guarda o token (modo Bearer). manterConectado=true → localStorage (persiste);
      // false → sessionStorage (cai ao fechar o navegador).
      if (data.token) setToken(data.token, manterConectado)
      return normalizarMe(data)
    },
    onSuccess: (data) => qc.setQueryData(['me'], data),
  })

  const logoutMut = useMutation({
    mutationFn: async () => {
      try {
        await axios.post(authUrl('/logout'), {}, {
          withCredentials: true,
          headers: {
            Accept: 'application/json',
            ...(getToken() ? { Authorization: `Bearer ${getToken()}` } : {}),
          },
        })
      } finally {
        setToken(null)
      }
    },
    /**
     * F9-03 — o cache inteiro morre no logout, não só o `['me']`.
     *
     * Antes só `['me']` era zerado, e o resto ficava: clientes, pedidos,
     * financeiro da sessão anterior seguiam em memória. Numa revenda só isso
     * passava despercebido — o próximo login era a mesma pessoa.
     *
     * Num SaaS é vazamento entre concorrentes: A sai, B entra na mesma máquina,
     * e vê a carteira de A na tela até o refetch chegar. As telas renderizam
     * dado cacheado imediatamente, por desenho do React Query.
     *
     * A ordem importa. `cancelQueries` primeiro: uma requisição EM VOO,
     * disparada com o token de A, chega depois do `clear()` e repovoa o cache
     * já sob a sessão de B. Cancelar antes de limpar fecha essa janela.
     */
    /**
     * `onSettled`, e não `onSuccess`.
     *
     * O `setToken(null)` está num `finally` — some sempre, dê certo ou não o
     * POST. Mas o `clear()` estava em `onSuccess`, que **só roda quando a
     * requisição tem êxito**.
     *
     * O buraco aparece quando o operador clica em "Sair" com a rede caindo ou o
     * backend fora: o token é apagado e a **carteira, os pedidos e o financeiro
     * continuam na tela**. Ele acha que saiu — é o mesmo vazamento que a F9-03
     * fechou, pela porta que ficou aberta.
     *
     * Falha de rede não pode manter dado de uma sessão encerrada na tela: a
     * decisão de sair é do operador, não do servidor.
     */
    onSettled: async () => {
      await qc.cancelQueries()
      qc.clear()
    },
  })

  const value: AuthContextValue = {
    user: user ?? null,
    loading: isLoading,
    login: async (email, password, manterConectado = true, otp) => { await loginMut.mutateAsync({ email, password, otp, manterConectado }) },
    logout: async () => { await logoutMut.mutateAsync() },
    can: (permission) => canFn(user ?? null, permission),
    canField: (modulo, campo, acao) => canFieldFn(user ?? null, modulo, campo, acao),
    hasFeature: (feature) => hasFeatureFn(user ?? null, feature),
    refresh: async () => { await qc.invalidateQueries({ queryKey: ['me'] }) },
  }

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth deve ser usado dentro de <AuthProvider>')
  return ctx
}
