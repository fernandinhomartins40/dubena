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
import { ClienteConfigPage } from '@/features/cadastros/ClienteConfigPage'
import { FinanceiroConfigPage } from '@/features/cadastros/FinanceiroConfigPage'
import { ColaboradorConfigPage } from '@/features/cadastros/ColaboradorConfigPage'
import { EstoquePage } from '@/features/estoque/EstoquePage'
import { FinanceiroPage } from '@/features/financeiro/FinanceiroPage'
import { FiscalPage } from '@/features/fiscal/FiscalPage'
import { ColaboradoresListPage, ColaboradorFormPage } from '@/features/satelites/ColaboradoresPage'
import { VeiculosListPage, VeiculoFormPage } from '@/features/satelites/VeiculosPage'
import { ValeGasPage } from '@/features/satelites/ValeGasPage'
import { SatelitesPage } from '@/features/satelites/SatelitesPage'
import { PedidosPage } from '@/features/pedidos/PedidosPage'
import { PosVendaPage } from '@/features/crm/PosVendaPage'
import { PromocaoPage } from '@/features/crm/PromocaoPage'
import { SorteioPage } from '@/features/crm/SorteioPage'
import { MetaPage } from '@/features/crm/MetaPage'
import { ChecklistPage } from '@/features/crm/ChecklistPage'
import { CupomPage } from '@/features/gestao/CupomPage'
import { McmmPage } from '@/features/gestao/McmmPage'
import { DocumentoPage } from '@/features/gestao/DocumentoPage'
import { BemPage } from '@/features/gestao/BemPage'
import { CartaoPage } from '@/features/pagamentos/CartaoPage'
import { GasDoPovoPage } from '@/features/pagamentos/GasDoPovoPage'
import { ComodatoPage } from '@/features/satelites/ComodatoPage'
import { ConvenioPage } from '@/features/satelites/ConvenioPage'
import { MonitoraPage } from '@/features/satelites/MonitoraPage'
import { RelatoriosPage } from '@/features/relatorios/RelatoriosPage'
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
      <Route path="/clientes/configuracoes" element={<Protected><ClienteConfigPage /></Protected>} />
      <Route path="/clientes/novo" element={<Protected><ClienteFormPage /></Protected>} />
      <Route path="/clientes/:id" element={<Protected><ClienteFormPage /></Protected>} />
      <Route path="/financeiro/configuracoes" element={<Protected><FinanceiroConfigPage /></Protected>} />
      <Route path="/estoque" element={<Protected><EstoquePage /></Protected>} />
      <Route path="/financeiro" element={<Protected><FinanceiroPage /></Protected>} />
      <Route path="/fiscal" element={<Protected><FiscalPage /></Protected>} />
      <Route path="/colaboradores" element={<Protected><ColaboradoresListPage /></Protected>} />
      <Route path="/colaboradores/configuracoes" element={<Protected><ColaboradorConfigPage /></Protected>} />
      <Route path="/colaboradores/novo" element={<Protected><ColaboradorFormPage /></Protected>} />
      <Route path="/colaboradores/:id" element={<Protected><ColaboradorFormPage /></Protected>} />
      <Route path="/veiculos" element={<Protected><VeiculosListPage /></Protected>} />
      <Route path="/veiculos/novo" element={<Protected><VeiculoFormPage /></Protected>} />
      <Route path="/veiculos/:id" element={<Protected><VeiculoFormPage /></Protected>} />
      <Route path="/vale-gas" element={<Protected><ValeGasPage /></Protected>} />
      <Route path="/relatorios" element={<Protected><RelatoriosPage /></Protected>} />
      <Route path="/satelites" element={<Protected><SatelitesPage /></Protected>} />
      <Route path="/pedidos" element={<Protected><PedidosPage /></Protected>} />

      {/* CRM (C10) */}
      <Route path="/pos-venda" element={<Protected><PosVendaPage /></Protected>} />
      <Route path="/promocoes" element={<Protected><PromocaoPage /></Protected>} />
      <Route path="/sorteios" element={<Protected><SorteioPage /></Protected>} />
      <Route path="/metas" element={<Protected><MetaPage /></Protected>} />
      <Route path="/checklists" element={<Protected><ChecklistPage /></Protected>} />

      {/* Gestão (C11) */}
      <Route path="/cupons-fiscais" element={<Protected><CupomPage /></Protected>} />
      <Route path="/mcmm" element={<Protected><McmmPage /></Protected>} />
      <Route path="/documentos" element={<Protected><DocumentoPage /></Protected>} />
      <Route path="/bens" element={<Protected><BemPage /></Protected>} />

      {/* Pagamentos (C4) + satélites */}
      <Route path="/cartoes" element={<Protected><CartaoPage /></Protected>} />
      <Route path="/gas-do-povo" element={<Protected><GasDoPovoPage /></Protected>} />
      <Route path="/comodatos" element={<Protected><ComodatoPage /></Protected>} />
      <Route path="/convenios" element={<Protected><ConvenioPage /></Protected>} />
      <Route path="/monitora" element={<Protected><MonitoraPage /></Protected>} />
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
      <BrowserRouter basename={import.meta.env.BASE_URL.replace(/\/$/, '')}>
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
