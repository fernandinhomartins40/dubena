import { createContext, useContext, type ReactNode } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api, ensureCsrf } from './api'

export interface AuthUser {
  id: number
  name: string
  email: string
  empresa_id: number | null
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

export function AuthProvider({ children }: { children: ReactNode }) {
  const qc = useQueryClient()

  const { data: user, isLoading } = useQuery<AuthUser | null>({
    queryKey: ['me'],
    queryFn: async () => {
      try {
        const { data } = await api.get<AuthUser>('/me')
        return data
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
      const { data } = await api.post<AuthUser>('/login', { email, password })
      return data
    },
    onSuccess: (data) => qc.setQueryData(['me'], data),
  })

  const logoutMut = useMutation({
    mutationFn: async () => { await api.post('/logout') },
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
