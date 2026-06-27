import React, { Suspense } from 'react'
import { Routes, Route, Navigate } from 'react-router-dom'
import { useAuth } from '@/lib/auth'
import { AppShell } from '@/layouts/AppShell'

/**
 * Roteamento da SPA (F17.R2) — code-splitting por rota com React.lazy + Suspense,
 * organizado por DOMÍNIO. Cada página vira um chunk próprio (bundle inicial menor).
 * Páginas usam export NOMEADO → adaptamos para { default } no lazy.
 */
const lazyNamed = <T extends Record<string, React.ComponentType<any>>>(
  factory: () => Promise<T>,
  nome: keyof T,
) => React.lazy(() => factory().then((m) => ({ default: m[nome] })))

// Auth / Geral
const LoginPage = lazyNamed(() => import('@/features/auth/LoginPage'), 'LoginPage')
const DashboardPage = lazyNamed(() => import('@/features/dashboard/DashboardPage'), 'DashboardPage')
const RelatoriosPage = lazyNamed(() => import('@/features/relatorios/RelatoriosPage'), 'RelatoriosPage')

// Cadastros
const ClientesListPage = lazyNamed(() => import('@/features/clientes/ClientesListPage'), 'ClientesListPage')
const ClienteFormPage = lazyNamed(() => import('@/features/clientes/ClienteFormPage'), 'ClienteFormPage')
const ProdutosListPage = lazyNamed(() => import('@/features/produtos/ProdutosListPage'), 'ProdutosListPage')
const ProdutoFormPage = lazyNamed(() => import('@/features/produtos/ProdutoFormPage'), 'ProdutoFormPage')
const ProdutoConfigPage = lazyNamed(() => import('@/features/produtos/ProdutoConfigPage'), 'ProdutoConfigPage')
const ProdutoPrecosPage = lazyNamed(() => import('@/features/produtos/ProdutoPrecosPage'), 'ProdutoPrecosPage')
const GeograficoPage = lazyNamed(() => import('@/features/geografico/GeograficoPage'), 'GeograficoPage')

// Operações
const PedidosPage = lazyNamed(() => import('@/features/pedidos/PedidosPage'), 'PedidosPage')
const EstoquePage = lazyNamed(() => import('@/features/estoque/EstoquePage'), 'EstoquePage')
const FiscalPage = lazyNamed(() => import('@/features/fiscal/FiscalPage'), 'FiscalPage')
const CupomPage = lazyNamed(() => import('@/features/gestao/CupomPage'), 'CupomPage')
const ValeGasPage = lazyNamed(() => import('@/features/satelites/ValeGasPage'), 'ValeGasPage')
const ComodatoPage = lazyNamed(() => import('@/features/satelites/ComodatoPage'), 'ComodatoPage')

// Financeiro
const FinanceiroPage = lazyNamed(() => import('@/features/financeiro/FinanceiroPage'), 'FinanceiroPage')
const CartaoPage = lazyNamed(() => import('@/features/pagamentos/CartaoPage'), 'CartaoPage')
const GasDoPovoPage = lazyNamed(() => import('@/features/pagamentos/GasDoPovoPage'), 'GasDoPovoPage')
const ConvenioPage = lazyNamed(() => import('@/features/satelites/ConvenioPage'), 'ConvenioPage')

// CRM
const PosVendaPage = lazyNamed(() => import('@/features/crm/PosVendaPage'), 'PosVendaPage')
const PromocaoPage = lazyNamed(() => import('@/features/crm/PromocaoPage'), 'PromocaoPage')
const SorteioPage = lazyNamed(() => import('@/features/crm/SorteioPage'), 'SorteioPage')
const MetaPage = lazyNamed(() => import('@/features/crm/MetaPage'), 'MetaPage')
const ChecklistPage = lazyNamed(() => import('@/features/crm/ChecklistPage'), 'ChecklistPage')

// Gestão
const McmmPage = lazyNamed(() => import('@/features/gestao/McmmPage'), 'McmmPage')
const DocumentoPage = lazyNamed(() => import('@/features/gestao/DocumentoPage'), 'DocumentoPage')
const BemPage = lazyNamed(() => import('@/features/gestao/BemPage'), 'BemPage')

// RH & Frota
const ColaboradoresListPage = lazyNamed(() => import('@/features/rh/ColaboradoresPage'), 'ColaboradoresListPage')
const ColaboradorFormPage = lazyNamed(() => import('@/features/rh/ColaboradoresPage'), 'ColaboradorFormPage')
const VeiculosListPage = lazyNamed(() => import('@/features/frota/VeiculosPage'), 'VeiculosListPage')
const VeiculoFormPage = lazyNamed(() => import('@/features/frota/VeiculosPage'), 'VeiculoFormPage')
const MonitoraPage = lazyNamed(() => import('@/features/satelites/MonitoraPage'), 'MonitoraPage')
const SatelitesPage = lazyNamed(() => import('@/features/satelites/SatelitesPage'), 'SatelitesPage')

// Administração
const EmpresasListPage = lazyNamed(() => import('@/features/empresas/EmpresasListPage'), 'EmpresasListPage')
const EmpresaFormPage = lazyNamed(() => import('@/features/empresas/EmpresaFormPage'), 'EmpresaFormPage')
const ConfiguracoesPage = lazyNamed(() => import('@/features/configuracoes/ConfiguracoesPage'), 'ConfiguracoesPage')
const AcessosPage = lazyNamed(() => import('@/features/acessos/AcessosPage'), 'AcessosPage')
const SegurancaPage = lazyNamed(() => import('@/features/seguranca/SegurancaPage'), 'SegurancaPage')

const Splash = () => <div className="h-full grid place-items-center text-muted-foreground">Carregando…</div>

/** Guarda: exige autenticação; envolve a página no AppShell. */
function Protegido({ children }: { children: React.ReactNode }) {
  const { user, loading } = useAuth()
  if (loading) return <Splash />
  if (!user) return <Navigate to="/login" replace />
  return <AppShell>{children}</AppShell>
}

/** Helper: rota protegida + lazy (com Suspense). */
const p = (el: React.ReactNode) => (
  <Protegido><Suspense fallback={<Splash />}>{el}</Suspense></Protegido>
)

export function AppRoutes() {
  return (
    <Routes>
      <Route path="/login" element={<Suspense fallback={<Splash />}><LoginPage /></Suspense>} />

      {/* Geral */}
      <Route path="/" element={p(<DashboardPage />)} />
      <Route path="/relatorios" element={p(<RelatoriosPage />)} />
      <Route path="/satelites" element={p(<SatelitesPage />)} />

      {/* Cadastros */}
      <Route path="/clientes" element={p(<ClientesListPage />)} />
      <Route path="/clientes/configuracoes" element={<Navigate to="/configuracoes?tab=clientes" replace />} />
      <Route path="/clientes/novo" element={p(<ClienteFormPage />)} />
      <Route path="/clientes/:id" element={p(<ClienteFormPage />)} />
      <Route path="/produtos" element={p(<ProdutosListPage />)} />
      <Route path="/produtos/configuracoes" element={p(<ProdutoConfigPage />)} />
      <Route path="/produtos/precos" element={p(<ProdutoPrecosPage />)} />
      <Route path="/produtos/novo" element={p(<ProdutoFormPage />)} />
      <Route path="/produtos/:id" element={p(<ProdutoFormPage />)} />
      <Route path="/geografico" element={p(<GeograficoPage />)} />

      {/* Operações */}
      <Route path="/pedidos" element={p(<PedidosPage />)} />
      <Route path="/estoque" element={p(<EstoquePage />)} />
      <Route path="/fiscal" element={p(<FiscalPage />)} />
      <Route path="/cupons-fiscais" element={p(<CupomPage />)} />
      <Route path="/vale-gas" element={p(<ValeGasPage />)} />
      <Route path="/comodatos" element={p(<ComodatoPage />)} />

      {/* Financeiro */}
      <Route path="/financeiro" element={p(<FinanceiroPage />)} />
      <Route path="/financeiro/configuracoes" element={<Navigate to="/configuracoes?tab=financeiro" replace />} />
      <Route path="/cartoes" element={p(<CartaoPage />)} />
      <Route path="/gas-do-povo" element={p(<GasDoPovoPage />)} />
      <Route path="/convenios" element={p(<ConvenioPage />)} />

      {/* CRM */}
      <Route path="/pos-venda" element={p(<PosVendaPage />)} />
      <Route path="/promocoes" element={p(<PromocaoPage />)} />
      <Route path="/sorteios" element={p(<SorteioPage />)} />
      <Route path="/metas" element={p(<MetaPage />)} />
      <Route path="/checklists" element={p(<ChecklistPage />)} />

      {/* Gestão */}
      <Route path="/mcmm" element={p(<McmmPage />)} />
      <Route path="/documentos" element={p(<DocumentoPage />)} />
      <Route path="/bens" element={p(<BemPage />)} />

      {/* RH & Frota */}
      <Route path="/colaboradores" element={p(<ColaboradoresListPage />)} />
      <Route path="/colaboradores/configuracoes" element={<Navigate to="/configuracoes?tab=colaboradores" replace />} />
      <Route path="/colaboradores/novo" element={p(<ColaboradorFormPage />)} />
      <Route path="/colaboradores/:id" element={p(<ColaboradorFormPage />)} />
      <Route path="/veiculos" element={p(<VeiculosListPage />)} />
      <Route path="/veiculos/novo" element={p(<VeiculoFormPage />)} />
      <Route path="/veiculos/:id" element={p(<VeiculoFormPage />)} />
      <Route path="/monitora" element={p(<MonitoraPage />)} />

      {/* Administração */}
      <Route path="/empresas" element={p(<EmpresasListPage />)} />
      <Route path="/empresas/novo" element={p(<EmpresaFormPage />)} />
      <Route path="/empresas/:id" element={p(<EmpresaFormPage />)} />
      <Route path="/configuracoes" element={p(<ConfiguracoesPage />)} />
      <Route path="/acessos" element={p(<AcessosPage />)} />
      <Route path="/seguranca" element={p(<SegurancaPage />)} />

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
