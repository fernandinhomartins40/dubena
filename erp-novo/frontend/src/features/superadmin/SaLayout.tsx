import { useState, type ReactNode } from 'react'
import { NavLink, useNavigate } from 'react-router-dom'
import {
  LayoutDashboard, Building2, Package, MapPin, ScrollText, LogOut, ShieldCheck,
  Menu as MenuIcon, ChevronLeft, ChevronDown, Moon, Sun,
} from 'lucide-react'
import {
  Button, Tooltip,
  DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem,
  DropdownMenuLabel, DropdownMenuSeparator, Badge,
} from '@/components/ui'
import { cn } from '@/lib/cn'
import { useSaAuth } from './auth'

/**
 * Shell do SuperAdmin (P4) — MESMO layout do AppShell do ERP (sidebar escura com
 * grupos, colapsável no desktop, drawer no mobile, topbar com tema e usuário),
 * adaptado ao contexto de PLATAFORMA: sem EmpresaSwitcher (é cross-tenant) e com
 * o selo "Plataforma" sempre visível para deixar claro onde se está.
 */
interface NavItem {
  label: string
  to: string
  end?: boolean
  icon: ReactNode
  group: string
}

const NAV: NavItem[] = [
  { label: 'Dashboard', to: '/superadmin', end: true, icon: <LayoutDashboard size={18} />, group: 'Geral' },
  { label: 'Empresas', to: '/superadmin/empresas', icon: <Building2 size={18} />, group: 'Gestão' },
  { label: 'Planos', to: '/superadmin/planos', icon: <Package size={18} />, group: 'Gestão' },
  { label: 'Cidades', to: '/superadmin/cidades', icon: <MapPin size={18} />, group: 'Gestão' },
  { label: 'Auditoria', to: '/superadmin/auditoria', icon: <ScrollText size={18} />, group: 'Segurança' },
]

const ORDEM_GRUPOS = ['Geral', 'Gestão', 'Segurança']

export function SaLayout({ children }: { children: ReactNode }) {
  const { admin, logout } = useSaAuth()
  const navigate = useNavigate()
  const [open, setOpen] = useState(true)
  const [mobileOpen, setMobileOpen] = useState(false)
  const [dark, setDark] = useState(() => document.documentElement.classList.contains('dark'))
  const [recolhidos, setRecolhidos] = useState<Record<string, boolean>>({})

  const toggleDark = () => {
    const next = !dark
    setDark(next)
    document.documentElement.classList.toggle('dark', next)
  }
  const toggleGrupo = (g: string) => setRecolhidos((r) => ({ ...r, [g]: !r[g] }))

  const expandida = open || mobileOpen
  const presentes = Array.from(new Set(NAV.map((i) => i.group)))
  const grupos = [
    ...ORDEM_GRUPOS.filter((g) => presentes.includes(g)),
    ...presentes.filter((g) => !ORDEM_GRUPOS.includes(g)),
  ]
  const iniciais = (admin?.nome ?? '?').split(' ').map((s) => s[0]).slice(0, 2).join('').toUpperCase()

  async function sair() {
    await logout()
    navigate('/superadmin/login', { replace: true })
  }

  return (
    <div className="flex h-full min-h-screen">
      {/* Backdrop do drawer (só mobile) */}
      {mobileOpen && (
        <div className="fixed inset-0 z-40 bg-black/50 md:hidden" onClick={() => setMobileOpen(false)} aria-hidden />
      )}

      {/* Sidebar — mesma estética do ERP */}
      <aside
        className={cn(
          'bg-sidebar text-sidebar-foreground transition-all duration-200 flex flex-col',
          'fixed inset-y-0 left-0 z-50 w-64 md:static md:z-auto md:shrink-0',
          mobileOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
          open ? 'md:w-64' : 'md:w-16',
        )}
      >
        <div className="h-16 flex items-center gap-2.5 px-4 border-b border-white/10">
          <div className="grid size-9 place-items-center rounded-lg bg-sidebar-accent text-white shadow-md shadow-black/30">
            <ShieldCheck size={20} strokeWidth={2.2} />
          </div>
          {expandida && (
            <div className="leading-tight">
              <span className="font-bold tracking-wide text-lg text-white">Dubena</span>
              <p className="text-[11px] uppercase tracking-wider text-sidebar-foreground/50">SuperAdmin</p>
            </div>
          )}
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
                {!colapsado && NAV.filter((i) => i.group === g).map((i) => {
                  const link = (
                    <NavLink
                      key={i.to}
                      to={i.to}
                      end={i.end}
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
                  return expandida ? link : <Tooltip key={i.to} label={i.label}>{link}</Tooltip>
                })}
              </div>
            )
          })}
        </nav>
      </aside>

      {/* Conteúdo */}
      <div className="flex-1 flex flex-col min-w-0">
        {/* Topbar — mesma do ERP, com selo de contexto de plataforma */}
        <header className="h-16 shrink-0 bg-card border-b border-border flex items-center justify-between px-3 sm:px-4">
          <div className="flex items-center gap-2 sm:gap-3 min-w-0">
            <Button variant="ghost" size="icon" className="md:hidden" onClick={() => setMobileOpen((v) => !v)} aria-label="Abrir menu">
              <MenuIcon size={18} />
            </Button>
            <Button variant="ghost" size="icon" className="hidden md:inline-flex" onClick={() => setOpen((v) => !v)} aria-label="Recolher menu">
              {open ? <ChevronLeft size={18} /> : <MenuIcon size={18} />}
            </Button>
            <Badge variant="outline" className="gap-1.5">
              <ShieldCheck size={13} /> Plataforma · cross-tenant
            </Badge>
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
                    <p className="text-sm font-medium">{admin?.nome}</p>
                    <p className="text-xs text-muted-foreground">Administrador da plataforma</p>
                  </div>
                </button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel>{admin?.email}</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem destructive onClick={sair}>
                  <LogOut /> Sair
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </header>

        <main className="flex-1 overflow-auto">
          <div className="mx-auto max-w-6xl px-4 sm:px-6 py-6">{children}</div>
        </main>
      </div>
    </div>
  )
}
