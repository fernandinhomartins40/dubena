import React from 'react'
import ReactDOM from 'react-dom/client'
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { Toaster } from 'sonner'
import { TooltipProvider } from '@/components/ui'
import { AuthProvider, useAuth } from '@/lib/auth'
import { AppShell } from '@/layouts/AppShell'
import { LoginPage } from '@/features/auth/LoginPage'
import { DashboardPage } from '@/features/dashboard/DashboardPage'
import { ClientesListPage } from '@/features/clientes/ClientesListPage'
import { ClienteFormPage } from '@/features/clientes/ClienteFormPage'
import { ProdutosListPage } from '@/features/produtos/ProdutosListPage'
import { ProdutoFormPage } from '@/features/produtos/ProdutoFormPage'
import { ProdutoConfigPage } from '@/features/produtos/ProdutoConfigPage'
import { ProdutoPrecosPage } from '@/features/produtos/ProdutoPrecosPage'
import { GeograficoPage } from '@/features/geografico/GeograficoPage'
import { EmpresasListPage } from '@/features/empresas/EmpresasListPage'
import { EmpresaFormPage } from '@/features/empresas/EmpresaFormPage'
import './index.css'

const queryClient = new QueryClient({
  defaultOptions: { queries: { refetchOnWindowFocus: false } },
})

/** Guarda: exige usuário autenticado; senão manda p/ /login. */
function Protected({ children }: { children: React.ReactNode }) {
  const { user, loading } = useAuth()
  if (loading) {
    return <div className="h-full grid place-items-center text-muted-foreground">Carregando…</div>
  }
  if (!user) return <Navigate to="/login" replace />
  return <AppShell>{children}</AppShell>
}

function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/" element={<Protected><DashboardPage /></Protected>} />
      <Route path="/clientes" element={<Protected><ClientesListPage /></Protected>} />
      <Route path="/clientes/novo" element={<Protected><ClienteFormPage /></Protected>} />
      <Route path="/clientes/:id" element={<Protected><ClienteFormPage /></Protected>} />
      <Route path="/produtos" element={<Protected><ProdutosListPage /></Protected>} />
      <Route path="/produtos/configuracoes" element={<Protected><ProdutoConfigPage /></Protected>} />
      <Route path="/produtos/precos" element={<Protected><ProdutoPrecosPage /></Protected>} />
      <Route path="/produtos/novo" element={<Protected><ProdutoFormPage /></Protected>} />
      <Route path="/produtos/:id" element={<Protected><ProdutoFormPage /></Protected>} />
      <Route path="/geografico" element={<Protected><GeograficoPage /></Protected>} />
      <Route path="/empresas" element={<Protected><EmpresasListPage /></Protected>} />
      <Route path="/empresas/novo" element={<Protected><EmpresaFormPage /></Protected>} />
      <Route path="/empresas/:id" element={<Protected><EmpresaFormPage /></Protected>} />
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <QueryClientProvider client={queryClient}>
      <BrowserRouter basename="/app">
        <TooltipProvider delayDuration={200}>
          <AuthProvider>
            <App />
          </AuthProvider>
          <Toaster richColors closeButton position="top-right" />
        </TooltipProvider>
      </BrowserRouter>
    </QueryClientProvider>
  </React.StrictMode>,
)
