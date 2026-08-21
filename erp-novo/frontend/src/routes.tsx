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
const CentralPage = lazyNamed(() => import('@/features/central/CentralPage'), 'CentralPage')
const CentralVendasPage = lazyNamed(() => import('@/features/central-vendas/CentralVendasPage'), 'CentralVendasPage')
const AlcadasPage = lazyNamed(() => import('@/features/central-vendas/AlcadasPage'), 'AlcadasPage')
const MissoesPage = lazyNamed(() => import('@/features/missoes/MissoesPage'), 'MissoesPage')
const EstoquePage = lazyNamed(() => import('@/features/estoque/EstoquePage'), 'EstoquePage')
const FiscalPage = lazyNamed(() => import('@/features/fiscal/FiscalPage'), 'FiscalPage')
const CupomPage = lazyNamed(() => import('@/features/gestao/CupomPage'), 'CupomPage')
const ValeGasPage = lazyNamed(() => import('@/features/valegas/ValeGasPage'), 'ValeGasPage')
const ComodatoPage = lazyNamed(() => import('@/features/comodatos/ComodatoPage'), 'ComodatoPage')

// Financeiro
const FinanceiroPage = lazyNamed(() => import('@/features/financeiro/FinanceiroPage'), 'FinanceiroPage')
const CartaoPage = lazyNamed(() => import('@/features/pagamentos/CartaoPage'), 'CartaoPage')
const GasDoPovoPage = lazyNamed(() => import('@/features/pagamentos/GasDoPovoPage'), 'GasDoPovoPage')
const ConvenioPage = lazyNamed(() => import('@/features/convenios/ConvenioPage'), 'ConvenioPage')

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
const SemAcessoPage = lazyNamed(() => import('@/features/auth/SemAcessoPage'), 'SemAcessoPage')

const Splash = () => <div className="h-full grid place-items-center text-muted-foreground">Carregando…</div>

/** Guarda: exige autenticação; envolve a página no AppShell. */
function Protegido({ children }: { children: React.ReactNode }) {
  const { user, loading } = useAuth()
  if (loading) return <Splash />
  if (!user) return <Navigate to="/login" replace />
  return <AppShell>{children}</AppShell>
}

/**
 * Guarda de PERMISSÃO por rota (níveis de acesso em toda a app): exige `auth`
 * (Protegido) e a permissão dada; sem ela, mostra a tela "Sem acesso" (403) —
 * não redireciona, para o usuário entender. `permission` ausente = só auth
 * (ex.: Dashboard, Segurança — abertos a qualquer usuário logado).
 */
function RequirePermission({ permission, children }: { permission?: string; children: React.ReactNode }) {
  const { loading, can } = useAuth()
  if (loading) return <Splash />
  if (permission && !can(permission)) {
    return <Suspense fallback={<Splash />}><SemAcessoPage /></Suspense>
  }
  return <>{children}</>
}

/**
 * Helper: rota protegida (auth) + guarda de permissão + lazy (Suspense).
 * Passe a permissão exigida; omita só para páginas abertas a qualquer logado.
 */
const p = (el: React.ReactNode, permission?: string) => (
  <Protegido>
    <RequirePermission permission={permission}>
      <Suspense fallback={<Splash />}>{el}</Suspense>
    </RequirePermission>
  </Protegido>
)

export function AppRoutes() {
  return (
    <Routes>
      <Route path="/login" element={<Suspense fallback={<Splash />}><LoginPage /></Suspense>} />

      {/* Geral — Dashboard e Satélites são abertos a qualquer logado (sem permissão) */}
      <Route path="/" element={p(<DashboardPage />)} />
      <Route path="/relatorios" element={p(<RelatoriosPage />, 'relatorio.view')} />
      <Route path="/satelites" element={p(<SatelitesPage />)} />

      {/* Cadastros */}
      <Route path="/clientes" element={p(<ClientesListPage />, 'cliente.view')} />
      <Route path="/clientes/configuracoes" element={<Navigate to="/configuracoes?tab=clientes" replace />} />
      <Route path="/clientes/novo" element={p(<ClienteFormPage />, 'cliente.view')} />
      <Route path="/clientes/:id" element={p(<ClienteFormPage />, 'cliente.view')} />
      <Route path="/produtos" element={p(<ProdutosListPage />, 'produto.view')} />
      <Route path="/produtos/configuracoes" element={p(<ProdutoConfigPage />, 'produto.view')} />
      <Route path="/produtos/precos" element={p(<ProdutoPrecosPage />, 'produto.view')} />
      <Route path="/produtos/novo" element={p(<ProdutoFormPage />, 'produto.view')} />
      <Route path="/produtos/:id" element={p(<ProdutoFormPage />, 'produto.view')} />
      <Route path="/geografico" element={p(<GeograficoPage />, 'cliente.view')} />

      {/* Operações */}
      <Route path="/pedidos" element={p(<PedidosPage />, 'pedido.view')} />
      <Route path="/central" element={p(<CentralPage />, 'logistica.view')} />
      <Route path="/central-vendas" element={p(<CentralVendasPage />, 'venda.view')} />
      <Route path="/alcadas" element={p(<AlcadasPage />, 'venda.alcada')} />
      <Route path="/missoes" element={p(<MissoesPage />, 'missao.view')} />
      <Route path="/estoque" element={p(<EstoquePage />, 'estoque.view')} />
      <Route path="/fiscal" element={p(<FiscalPage />, 'fiscal.view')} />
      <Route path="/cupons-fiscais" element={p(<CupomPage />, 'cupomfiscal.view')} />
      <Route path="/vale-gas" element={p(<ValeGasPage />, 'valegas.view')} />
      <Route path="/comodatos" element={p(<ComodatoPage />, 'comodato.view')} />

      {/* Financeiro */}
      <Route path="/financeiro" element={p(<FinanceiroPage />, 'financeiro.view')} />
      <Route path="/financeiro/configuracoes" element={<Navigate to="/configuracoes?tab=financeiro" replace />} />
      <Route path="/cartoes" element={p(<CartaoPage />, 'cartao.view')} />
      <Route path="/gas-do-povo" element={p(<GasDoPovoPage />, 'gasdopovo.view')} />
      <Route path="/convenios" element={p(<ConvenioPage />, 'convenio.view')} />

      {/* CRM */}
      <Route path="/pos-venda" element={p(<PosVendaPage />, 'posvenda.view')} />
      <Route path="/promocoes" element={p(<PromocaoPage />, 'promocao.view')} />
      <Route path="/sorteios" element={p(<SorteioPage />, 'sorteio.view')} />
      <Route path="/metas" element={p(<MetaPage />, 'meta.view')} />
      <Route path="/checklists" element={p(<ChecklistPage />, 'checklist.view')} />

      {/* Gestão */}
      <Route path="/mcmm" element={p(<McmmPage />, 'mcmm.view')} />
      <Route path="/documentos" element={p(<DocumentoPage />, 'documento.view')} />
      <Route path="/bens" element={p(<BemPage />, 'bem.view')} />

      {/* RH & Frota */}
      <Route path="/colaboradores" element={p(<ColaboradoresListPage />, 'colaborador.view')} />
      <Route path="/colaboradores/configuracoes" element={<Navigate to="/configuracoes?tab=colaboradores" replace />} />
      <Route path="/colaboradores/novo" element={p(<ColaboradorFormPage />, 'colaborador.view')} />
      <Route path="/colaboradores/:id" element={p(<ColaboradorFormPage />, 'colaborador.view')} />
      <Route path="/veiculos" element={p(<VeiculosListPage />, 'veiculo.view')} />
      <Route path="/veiculos/novo" element={p(<VeiculoFormPage />, 'veiculo.view')} />
      <Route path="/veiculos/:id" element={p(<VeiculoFormPage />, 'veiculo.view')} />
      <Route path="/monitora" element={p(<MonitoraPage />, 'monitora.view')} />

      {/* Administração */}
      <Route path="/empresas" element={p(<EmpresasListPage />, 'empresa.view')} />
      <Route path="/empresas/novo" element={p(<EmpresaFormPage />, 'empresa.view')} />
      <Route path="/empresas/:id" element={p(<EmpresaFormPage />, 'empresa.view')} />
      <Route path="/configuracoes" element={p(<ConfiguracoesPage />, 'grupo.view')} />
      <Route path="/acessos" element={p(<AcessosPage />, 'usuario.view')} />
      <Route path="/seguranca" element={p(<SegurancaPage />)} />

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
