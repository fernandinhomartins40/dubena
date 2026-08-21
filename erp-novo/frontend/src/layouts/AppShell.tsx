import { useState, type ReactNode } from 'react'
import { NavLink, useNavigate } from 'react-router-dom'
import {
  LayoutDashboard, Users, MapPin, LogOut, Menu as MenuIcon,
  Moon, Sun, ChevronLeft, ChevronDown, Building2, Package, Wallet, Warehouse, FileText,
  UserCog, Truck, Flame, FileBarChart, ShoppingCart, Bike, Compass,
  MessageSquareHeart, Tag, Gift, Target, ListChecks, BadgePercent,
  Receipt, ArrowLeftRight, FolderArchive, Building,
  CreditCard, HandCoins, PackageCheck, Handshake, Navigation, Settings, ShieldCheck,
} from 'lucide-react'
import { useAuth } from '@/lib/auth'
import { cn } from '@/lib/cn'
import { EmpresaSwitcher } from './EmpresaSwitcher'
import {
  DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem,
  DropdownMenuLabel, DropdownMenuSeparator, Tooltip, Button,
} from '@/components/ui'

interface NavItem {
  label: string
  to: string
  icon: ReactNode
  permission?: string
  group: string
}

// Navegação DECLARATIVA (sem menu-no-banco). Cresce conforme migramos módulos.
const NAV: NavItem[] = [
  { label: 'Dashboard', to: '/', icon: <LayoutDashboard size={18} />, group: 'Geral' },
  { label: 'Relatórios', to: '/relatorios', icon: <FileBarChart size={18} />, permission: 'relatorio.view', group: 'Geral' },

  { label: 'Clientes', to: '/clientes', icon: <Users size={18} />, permission: 'cliente.view', group: 'Cadastros' },
  { label: 'Produtos', to: '/produtos', icon: <Package size={18} />, permission: 'produto.view', group: 'Cadastros' },
  { label: 'Geográfico', to: '/geografico', icon: <MapPin size={18} />, permission: 'cliente.view', group: 'Cadastros' },
  { label: 'Pedidos', to: '/pedidos', icon: <ShoppingCart size={18} />, permission: 'pedido.view', group: 'Operações' },
  { label: 'Central de Logística', to: '/central', icon: <Bike size={18} />, permission: 'logistica.view', group: 'Operações' },
  { label: 'Central de Vendas', to: '/central-vendas', icon: <BadgePercent size={18} />, permission: 'venda.view', group: 'Operações' },
  { label: 'Alçadas de desconto', to: '/alcadas', icon: <Tag size={18} />, permission: 'venda.alcada', group: 'Configurações' },
  { label: 'Missões de Campo', to: '/missoes', icon: <Compass size={18} />, permission: 'missao.view', group: 'Operações' },
  { label: 'Estoque', to: '/estoque', icon: <Warehouse size={18} />, permission: 'estoque.view', group: 'Operações' },
  { label: 'Fiscal', to: '/fiscal', icon: <FileText size={18} />, permission: 'fiscal.view', group: 'Operações' },
  { label: 'Cupons SAT/CFe', to: '/cupons-fiscais', icon: <Receipt size={18} />, permission: 'cupomfiscal.view', group: 'Operações' },
  { label: 'Vale-Gás', to: '/vale-gas', icon: <Flame size={18} />, permission: 'valegas.view', group: 'Operações' },
  { label: 'Comodatos', to: '/comodatos', icon: <PackageCheck size={18} />, permission: 'comodato.view', group: 'Operações' },

  { label: 'Financeiro', to: '/financeiro', icon: <Wallet size={18} />, permission: 'financeiro.view', group: 'Financeiro' },
  { label: 'Cartões', to: '/cartoes', icon: <CreditCard size={18} />, permission: 'cartao.view', group: 'Financeiro' },
  { label: 'Gás do Povo', to: '/gas-do-povo', icon: <HandCoins size={18} />, permission: 'gasdopovo.view', group: 'Financeiro' },
  { label: 'Convênios', to: '/convenios', icon: <Handshake size={18} />, permission: 'convenio.view', group: 'Financeiro' },

  { label: 'Pós-venda', to: '/pos-venda', icon: <MessageSquareHeart size={18} />, permission: 'posvenda.view', group: 'CRM' },
  { label: 'Promoções', to: '/promocoes', icon: <Tag size={18} />, permission: 'promocao.view', group: 'CRM' },
  { label: 'Sorteios', to: '/sorteios', icon: <Gift size={18} />, permission: 'sorteio.view', group: 'CRM' },
  { label: 'Metas', to: '/metas', icon: <Target size={18} />, permission: 'meta.view', group: 'CRM' },
  { label: 'Checklists', to: '/checklists', icon: <ListChecks size={18} />, permission: 'checklist.view', group: 'CRM' },

  { label: 'MCMM', to: '/mcmm', icon: <ArrowLeftRight size={18} />, permission: 'mcmm.view', group: 'Gestão' },
  { label: 'Documentos', to: '/documentos', icon: <FolderArchive size={18} />, permission: 'documento.view', group: 'Gestão' },
  { label: 'Bens', to: '/bens', icon: <Building size={18} />, permission: 'bem.view', group: 'Gestão' },

  { label: 'Colaboradores', to: '/colaboradores', icon: <UserCog size={18} />, permission: 'colaborador.view', group: 'RH & Frota' },
  { label: 'Veículos', to: '/veiculos', icon: <Truck size={18} />, permission: 'veiculo.view', group: 'RH & Frota' },
  { label: 'Monitora (GPS)', to: '/monitora', icon: <Navigation size={18} />, permission: 'monitora.view', group: 'RH & Frota' },

  { label: 'Empresas', to: '/empresas', icon: <Building2 size={18} />, permission: 'empresa.view', group: 'Administração' },
  { label: 'Acessos', to: '/acessos', icon: <ShieldCheck size={18} />, permission: 'usuario.view', group: 'Administração' },
  { label: 'Configurações', to: '/configuracoes', icon: <Settings size={18} />, permission: 'grupo.view', group: 'Administração' },
]

// Ordem fixa das seções (grupos não listados vão ao fim, na ordem de aparição).
const ORDEM_GRUPOS = ['Geral', 'Cadastros', 'Operações', 'Financeiro', 'CRM', 'Gestão', 'RH & Frota', 'Administração']

export function AppShell({ children }: { children: ReactNode }) {
  const { user, logout, can } = useAuth()
  const navigate = useNavigate()
  // `open` = sidebar recolhida/expandida no DESKTOP (md+).
  const [open, setOpen] = useState(true)
  // `mobileOpen` = drawer da sidebar ABERTO no MOBILE (overlay).
  const [mobileOpen, setMobileOpen] = useState(false)
  const [dark, setDark] = useState(() => document.documentElement.classList.contains('dark'))

  const toggleDark = () => {
    const next = !dark
    setDark(next)
    document.documentElement.classList.toggle('dark', next)
  }

  // No mobile a sidebar é overlay e está sempre "expandida" (mostra rótulos).
  const expandida = open || mobileOpen

  const visiveis = NAV.filter((i) => !i.permission || can(i.permission))
  const presentes = Array.from(new Set(visiveis.map((i) => i.group)))
  const grupos = [
    ...ORDEM_GRUPOS.filter((g) => presentes.includes(g)),
    ...presentes.filter((g) => !ORDEM_GRUPOS.includes(g)),
  ]
  // Seções recolhidas (colapsadas) — por padrão todas abertas.
  const [recolhidos, setRecolhidos] = useState<Record<string, boolean>>({})
  const toggleGrupo = (g: string) => setRecolhidos((r) => ({ ...r, [g]: !r[g] }))
  const iniciais = (user?.name ?? '?').split(' ').map((s) => s[0]).slice(0, 2).join('').toUpperCase()

  return (
    <div className="flex h-full">
      {/* Backdrop do drawer (só mobile, quando aberto) */}
      {mobileOpen && (
        <div
          className="fixed inset-0 z-40 bg-black/50 md:hidden"
          onClick={() => setMobileOpen(false)}
          aria-hidden
        />
      )}

      {/* Sidebar: overlay fixo no mobile, em fluxo (recolhível) no md+ */}
      <aside
        className={cn(
          'bg-sidebar text-sidebar-foreground transition-all duration-200 flex flex-col',
          // mobile: drawer fixo que desliza; desktop: parte do layout
          'fixed inset-y-0 left-0 z-50 w-64 md:static md:z-auto md:shrink-0',
          mobileOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
          open ? 'md:w-64' : 'md:w-16',
        )}
      >
        <div className="h-16 flex items-center gap-2.5 px-4 border-b border-white/10">
          <div className="grid size-9 place-items-center rounded-lg bg-sidebar-accent font-black text-white shadow-md shadow-black/30">D</div>
          {expandida && <span className="font-bold tracking-wide text-lg text-white">Dubena</span>}
        </div>
        <nav className="flex-1 overflow-y-auto py-3">
          {grupos.map((g) => {
            const colapsado = expandida && recolhidos[g]
            return (
            <div key={g} className="mb-4">
              {expandida && (
                <button
                  type="button"
                  onClick={() => toggleGrupo(g)}
                  className="flex w-full items-center justify-between px-4 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-sidebar-foreground/40 hover:text-sidebar-foreground/70 transition-colors"
                >
                  <span>{g}</span>
                  <ChevronDown size={13} className={cn('transition-transform', colapsado && '-rotate-90')} />
                </button>
              )}
              {!colapsado && visiveis.filter((i) => i.group === g).map((i) => {
                const link = (
                  <NavLink
                    key={i.to}
                    to={i.to}
                    end={i.to === '/'}
                    onClick={() => setMobileOpen(false)}
                    className={({ isActive }) =>
                      cn(
                        'mx-2 flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors',
                        isActive
                          ? 'bg-sidebar-accent font-medium text-white shadow-sm shadow-black/20'
                          : 'text-sidebar-foreground hover:bg-white/5 hover:text-white',
                        !expandida && 'justify-center',
                      )
                    }
                  >
                    {i.icon}
                    {expandida && <span>{i.label}</span>}
                  </NavLink>
                )
                // Tooltip só faz sentido no modo recolhido do desktop.
                return expandida ? link : <Tooltip key={i.to} label={i.label}>{link}</Tooltip>
              })}
            </div>
            )
          })}
        </nav>
      </aside>

      {/* Conteúdo */}
      <div className="flex-1 flex flex-col min-w-0">
        {/* Header */}
        <header className="h-16 shrink-0 bg-card border-b border-border flex items-center justify-between px-3 sm:px-4">
          <div className="flex items-center gap-2 sm:gap-3 min-w-0">
            {/* Mobile: abre/fecha o drawer. Desktop: recolhe/expande a sidebar. */}
            <Button
              variant="ghost"
              size="icon"
              className="md:hidden"
              onClick={() => setMobileOpen((v) => !v)}
              aria-label="Abrir menu"
            >
              <MenuIcon size={18} />
            </Button>
            <Button
              variant="ghost"
              size="icon"
              className="hidden md:inline-flex"
              onClick={() => setOpen((v) => !v)}
              aria-label="Recolher menu"
            >
              {open ? <ChevronLeft size={18} /> : <MenuIcon size={18} />}
            </Button>
            <EmpresaSwitcher />
          </div>
          <div className="flex items-center gap-2">
            <Button variant="ghost" size="icon" onClick={toggleDark} aria-label="Tema">
              {dark ? <Sun size={18} /> : <Moon size={18} />}
            </Button>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <button className="flex items-center gap-2.5 rounded-md px-2 py-1.5 hover:bg-secondary transition-colors">
                  <div className="grid size-9 place-items-center rounded-full bg-primary text-primary-foreground text-sm font-semibold">{iniciais}</div>
                  <div className="text-right leading-tight hidden sm:block">
                    <p className="text-sm font-medium">{user?.name}</p>
                    <p className="text-xs text-muted-foreground">{user?.roles.join(', ') || (user?.is_support ? 'Suporte' : '')}</p>
                  </div>
                </button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel>{user?.email}</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem onClick={() => navigate('/seguranca')}>
                  <ShieldCheck /> Segurança
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem destructive onClick={async () => { await logout(); navigate('/login') }}>
                  <LogOut /> Sair
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </header>

        <main className="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">{children}</main>
      </div>
    </div>
  )
}
