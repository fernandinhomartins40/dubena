import React from 'react'
import ReactDOM from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { Toaster } from 'sonner'
import { useLocation } from 'react-router-dom'
import { TooltipProvider } from '@/components/ui'
import { AuthProvider } from '@/lib/auth'
import { AppRoutes } from '@/routes'
import { SaRoutes } from '@/features/superadmin/SaRoutes'
import { ErrorBoundary } from '@/components/ErrorBoundary'
import './index.css'

const queryClient = new QueryClient({
  defaultOptions: { queries: { refetchOnWindowFocus: false } },
})

const basename = import.meta.env.BASE_URL.replace(/\/$/, '')

/**
 * O SuperAdmin (P4) é uma área ISOLADA: roteia /superadmin/* com seu próprio
 * provider de auth (token da plataforma), SEM montar o AuthProvider de tenant
 * (que dispara /me do ERP). A divisão é por pathname (considerando o basename,
 * ex.: /novo) — os dois mundos não compartilham sessão.
 */
function Raiz() {
  const location = useLocation()
  const path = window.location.pathname.replace(basename, '') || '/'
  const isSuperAdmin = path.startsWith('/superadmin')

  return (
    <TooltipProvider delayDuration={200}>
      {/* ErrorBoundary (FE-1): um erro de render numa página não derruba a SPA.
          resetKey = rota atual → ao navegar, o boundary se reseta e tenta a nova tela. */}
      <ErrorBoundary resetKey={location.pathname}>
        {isSuperAdmin ? (
          <SaRoutes />
        ) : (
          <AuthProvider>
            <AppRoutes />
          </AuthProvider>
        )}
      </ErrorBoundary>
      <Toaster richColors closeButton position="top-right" />
    </TooltipProvider>
  )
}

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <QueryClientProvider client={queryClient}>
      <BrowserRouter basename={basename}>
        <Raiz />
      </BrowserRouter>
    </QueryClientProvider>
  </React.StrictMode>,
)
