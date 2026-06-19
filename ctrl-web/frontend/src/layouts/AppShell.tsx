import { useState, type ReactNode } from 'react'
import { NavLink, useNavigate } from 'react-router-dom'
import {
  LayoutDashboard, Users, MapPin, LogOut, Menu as MenuIcon,
  Moon, Sun, ChevronLeft, Building2, Package,
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
  { label: 'Clientes', to: '/clientes', icon: <Users size={18} />, permission: 'cliente.view', group: 'Cadastros' },
  { label: 'Produtos', to: '/produtos', icon: <Package size={18} />, permission: 'produto.view', group: 'Cadastros' },
  { label: 'Geográfico', to: '/geografico', icon: <MapPin size={18} />, permission: 'cidade.view', group: 'Cadastros' },
  { label: 'Empresas', to: '/empresas', icon: <Building2 size={18} />, permission: 'empresa.view', group: 'Administração' },
]

export function AppShell({ children }: { children: ReactNode }) {
  const { user, logout, can } = useAuth()
  const navigate = useNavigate()
  const [open, setOpen] = useState(true)
  const [dark, setDark] = useState(() => document.documentElement.classList.contains('dark'))

  const toggleDark = () => {
    const next = !dark
    setDark(next)
    document.documentElement.classList.toggle('dark', next)
  }

  const visiveis = NAV.filter((i) => !i.permission || can(i.permission))
  const grupos = Array.from(new Set(visiveis.map((i) => i.group)))
  const iniciais = (user?.name ?? '?').split(' ').map((s) => s[0]).slice(0, 2).join('').toUpperCase()

  return (
    <div className="flex h-full">
      {/* Sidebar */}
      <aside className={cn('shrink-0 bg-sidebar text-sidebar-foreground transition-all duration-200 flex flex-col', open ? 'w-64' : 'w-16')}>
        <div className="h-16 flex items-center gap-2.5 px-4 border-b border-white/10">
          <div className="grid size-9 place-items-center rounded-lg bg-sidebar-accent font-black text-sidebar shadow">D</div>
          {open && <span className="font-bold tracking-wide text-lg">Dubena</span>}
        </div>
        <nav className="flex-1 overflow-y-auto py-3">
          {grupos.map((g) => (
            <div key={g} className="mb-4">
              {open && <p className="px-4 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-sidebar-foreground/40">{g}</p>}
              {visiveis.filter((i) => i.group === g).map((i) => {
                const link = (
                  <NavLink
                    key={i.to}
                    to={i.to}
                    end={i.to === '/'}
                    className={({ isActive }) =>
                      cn(
                        'mx-2 flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors',
                        isActive
                          ? 'bg-white/15 font-medium text-white shadow-sm'
                          : 'text-sidebar-foreground/80 hover:bg-white/10 hover:text-white',
                        !open && 'justify-center',
                      )
                    }
                  >
                    {i.icon}
                    {open && <span>{i.label}</span>}
                  </NavLink>
                )
                return open ? link : <Tooltip key={i.to} label={i.label}>{link}</Tooltip>
              })}
            </div>
          ))}
        </nav>
      </aside>

      {/* Conteúdo */}
      <div className="flex-1 flex flex-col min-w-0">
        {/* Header */}
        <header className="h-16 shrink-0 bg-card border-b border-border flex items-center justify-between px-4">
          <div className="flex items-center gap-3">
            <Button variant="ghost" size="icon" onClick={() => setOpen(!open)} aria-label="Alternar menu">
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
                <DropdownMenuItem destructive onClick={async () => { await logout(); navigate('/login') }}>
                  <LogOut /> Sair
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </header>

        <main className="flex-1 overflow-y-auto p-6 lg:p-8">{children}</main>
      </div>
    </div>
  )
}
