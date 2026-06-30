import { type ReactNode } from 'react'
import { NavLink, useNavigate } from 'react-router-dom'
import { LayoutDashboard, Building2, Package, MapPin, ScrollText, LogOut, ShieldCheck } from 'lucide-react'
import { cn } from '@/lib/cn'
import { useSaAuth } from './auth'

/**
 * Shell do SuperAdmin (P4) — layout PRÓPRIO, isolado da SPA de tenant (AppShell).
 * Sidebar fixa + topo com o admin logado. Visual alinhado ao design system, mas
 * com acento que deixa claro "você está na plataforma, não num tenant".
 */
const NAV = [
  { to: '/superadmin', end: true, label: 'Dashboard', icon: LayoutDashboard },
  { to: '/superadmin/empresas', label: 'Empresas', icon: Building2 },
  { to: '/superadmin/planos', label: 'Planos', icon: Package },
  { to: '/superadmin/cidades', label: 'Cidades', icon: MapPin },
  { to: '/superadmin/auditoria', label: 'Auditoria', icon: ScrollText },
]

export function SaLayout({ children }: { children: ReactNode }) {
  const { admin, logout } = useSaAuth()
  const navigate = useNavigate()

  async function sair() {
    await logout()
    navigate('/superadmin/login', { replace: true })
  }

  return (
    <div className="grid min-h-screen grid-cols-[240px_1fr] bg-background text-foreground">
      <aside className="flex flex-col border-r border-border bg-card">
        <div className="flex items-center gap-2 px-5 py-5">
          <div className="grid size-9 place-items-center rounded-lg bg-primary/12 text-primary">
            <ShieldCheck size={20} strokeWidth={2.2} />
          </div>
          <div>
            <p className="text-sm font-bold leading-tight">SuperAdmin</p>
            <p className="text-xs text-muted-foreground leading-tight">Plataforma</p>
          </div>
        </div>

        <nav className="flex-1 space-y-1 px-3 py-2">
          {NAV.map(({ to, end, label, icon: Icon }) => (
            <NavLink
              key={to}
              to={to}
              end={end}
              className={({ isActive }) =>
                cn(
                  'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                  isActive ? 'bg-primary/12 text-primary' : 'text-muted-foreground hover:bg-foreground/5 hover:text-foreground',
                )
              }
            >
              <Icon size={18} />
              {label}
            </NavLink>
          ))}
        </nav>

        <div className="border-t border-border p-3">
          <div className="px-2 pb-2">
            <p className="truncate text-sm font-medium">{admin?.nome}</p>
            <p className="truncate text-xs text-muted-foreground">{admin?.email}</p>
          </div>
          <button
            onClick={sair}
            className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
          >
            <LogOut size={18} /> Sair
          </button>
        </div>
      </aside>

      <main className="overflow-auto">
        <div className="mx-auto max-w-6xl px-6 py-6">{children}</div>
      </main>
    </div>
  )
}
