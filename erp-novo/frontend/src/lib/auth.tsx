import { createContext, useContext, type ReactNode } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { apiPrefix, ensureCsrf, setToken, getToken } from './api'

export interface AuthUser {
  id: number
  name: string
  email: string
  empresa_id: number | null
  grupo_id: number | null
  is_support: boolean
  roles: string[]
  permissions: string[]
}

interface AuthContextValue {
  user: AuthUser | null
  loading: boolean
  login: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
  can: (permission: string) => boolean
  refresh: () => Promise<void>
}

const AuthContext = createContext<AuthContextValue | null>(null)

// Endpoints de auth ficam na RAIZ da API (/api), não sob /api/admin.
const authUrl = (path: string) => `${apiPrefix}/api${path}`

/** Normaliza o /me do backend novo ({user,tenant}) para o shape da SPA. */
function normalizarMe(data: any): AuthUser {
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
    staleTime: 5 * 60 * 1000,
  })

  const loginMut = useMutation({
    mutationFn: async ({ email, password }: { email: string; password: string }) => {
      await ensureCsrf()
      const { data } = await axios.post(authUrl('/login'), { email, password }, {
        withCredentials: true,
        headers: { Accept: 'application/json' },
      })
      // Guarda o token (modo Bearer); o cookie de sessão também foi setado.
      if (data.token) setToken(data.token)
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
    onSuccess: () => qc.setQueryData(['me'], null),
  })

  const value: AuthContextValue = {
    user: user ?? null,
    loading: isLoading,
    login: async (email, password) => { await loginMut.mutateAsync({ email, password }) },
    logout: async () => { await logoutMut.mutateAsync() },
    can: (permission) => {
      if (!user) return false
      if (user.is_support) return true
      return user.permissions.includes(permission)
    },
    refresh: async () => { await qc.invalidateQueries({ queryKey: ['me'] }) },
  }

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth deve ser usado dentro de <AuthProvider>')
  return ctx
}
