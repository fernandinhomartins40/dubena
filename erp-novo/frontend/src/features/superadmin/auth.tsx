import { createContext, useContext, type ReactNode } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { saLogin, saLogout, saMe, getSaToken, type SaAdmin } from './api'

/**
 * Contexto de auth do SuperAdmin (P4) — ISOLADO do AuthProvider de tenant.
 * Token próprio (`superadmin_token`), guard `platform` no backend. Não tem nada
 * a ver com o usuário/empresa do ERP — é o operador da plataforma.
 */
interface SaAuthValue {
  admin: SaAdmin | null
  loading: boolean
  login: (email: string, password: string, otp?: string) => Promise<void>
  logout: () => Promise<void>
}

const Ctx = createContext<SaAuthValue | null>(null)

export function SaAuthProvider({ children }: { children: ReactNode }) {
  const qc = useQueryClient()

  const { data: admin, isLoading } = useQuery<SaAdmin | null>({
    queryKey: ['sa', 'me'],
    queryFn: async () => {
      if (!getSaToken()) return null
      try {
        return await saMe()
      } catch {
        return null // 401 → não logado
      }
    },
    retry: false,
    staleTime: 5 * 60 * 1000,
  })

  const value: SaAuthValue = {
    admin: admin ?? null,
    loading: isLoading,
    login: async (email, password, otp) => {
      const a = await saLogin(email, password, otp)
      qc.setQueryData(['sa', 'me'], a)
    },
    /**
     * F9-03 — o logout do SuperAdmin apaga TODO o cache `sa`, não só o `me`.
     *
     * Aqui o risco é maior que no lado do tenant: o SuperAdmin enxerga **todas
     * as revendas** — planos, limites, consumo, dados de empresas concorrentes.
     * Deixar isso em memória depois do logout significa que quem entrar em
     * seguida na mesma máquina, inclusive um usuário comum de uma revenda, tem
     * dado de plataforma no cache do navegador.
     *
     * `removeQueries` com o prefixo `['sa']` e não `clear()`: a aplicação usa um
     * QueryClient só, e limpar tudo derrubaria também a sessão de tenant que
     * pode estar aberta na mesma aba. O prefixo é o que separa os dois mundos
     * hoje — e é por isso que toda query de plataforma precisa carregá-lo.
     */
    logout: async () => {
      await saLogout()
      await qc.cancelQueries({ queryKey: ['sa'] })
      qc.removeQueries({ queryKey: ['sa'] })
    },
  }

  return <Ctx.Provider value={value}>{children}</Ctx.Provider>
}

export function useSaAuth(): SaAuthValue {
  const ctx = useContext(Ctx)
  if (!ctx) throw new Error('useSaAuth deve ser usado dentro de <SaAuthProvider>')
  return ctx
}
