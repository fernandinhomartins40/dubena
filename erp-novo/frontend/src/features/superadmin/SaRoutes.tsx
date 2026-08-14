import React, { Suspense } from 'react'
import { Routes, Route, Navigate } from 'react-router-dom'
import { SaAuthProvider, useSaAuth } from './auth'
import { SaLayout } from './SaLayout'

/**
 * Bloco de rotas do SuperAdmin (P4) — ISOLADO da SPA de tenant.
 * Tem seu próprio provider de auth (token da plataforma) e layout. Montado por
 * main.tsx quando a URL começa com /superadmin, SEM o AuthProvider de tenant.
 */
const lazyNamed = <T extends Record<string, React.ComponentType<any>>>(
  factory: () => Promise<T>,
  nome: keyof T,
) => React.lazy(() => factory().then((m) => ({ default: m[nome] })))

const SaLoginPage = lazyNamed(() => import('./SaLoginPage'), 'SaLoginPage')
const SaDashboardPage = lazyNamed(() => import('./SaDashboardPage'), 'SaDashboardPage')
const SaEmpresasPage = lazyNamed(() => import('./SaEmpresasPage'), 'SaEmpresasPage')
const SaPlanosPage = lazyNamed(() => import('./SaPlanosPage'), 'SaPlanosPage')
const SaCidadesPage = lazyNamed(() => import('./SaCidadesPage'), 'SaCidadesPage')
const SaAuditoriaPage = lazyNamed(() => import('./SaAuditoriaPage'), 'SaAuditoriaPage')
const SaMigracaoPage = lazyNamed(() => import('./SaMigracaoPage'), 'SaMigracaoPage')

const Splash = () => <div className="grid min-h-screen place-items-center text-muted-foreground">Carregando…</div>

/** Exige admin de plataforma autenticado; envolve no SaLayout. */
function Protegido({ children }: { children: React.ReactNode }) {
  const { admin, loading } = useSaAuth()
  if (loading) return <Splash />
  if (!admin) return <Navigate to="/superadmin/login" replace />
  return <SaLayout>{children}</SaLayout>
}

const p = (el: React.ReactNode) => (
  <Protegido><Suspense fallback={<Splash />}>{el}</Suspense></Protegido>
)

export function SaRoutes() {
  return (
    <SaAuthProvider>
      <Routes>
        <Route path="/superadmin/login" element={<Suspense fallback={<Splash />}><SaLoginPage /></Suspense>} />
        <Route path="/superadmin" element={p(<SaDashboardPage />)} />
        <Route path="/superadmin/empresas" element={p(<SaEmpresasPage />)} />
        <Route path="/superadmin/planos" element={p(<SaPlanosPage />)} />
        <Route path="/superadmin/cidades" element={p(<SaCidadesPage />)} />
        <Route path="/superadmin/migracoes" element={p(<SaMigracaoPage />)} />
        <Route path="/superadmin/auditoria" element={p(<SaAuditoriaPage />)} />
        <Route path="*" element={<Navigate to="/superadmin" replace />} />
      </Routes>
    </SaAuthProvider>
  )
}
