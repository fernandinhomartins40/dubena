import { useState, type ReactNode } from 'react'
import { NavLink, useNavigate } from 'react-router-dom'
import {
  LayoutDashboard, Users, MapPin, LogOut, Menu as MenuIcon,
  Moon, Sun, ChevronLeft, Building2, Package,
} from 'lucide-react'
import { useAuth } from '@/lib/auth'

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
  { label: 'Cidades', to: '/cidades', icon: <MapPin size={18} />, permission: 'cidade.view', group: 'Cadastros' },
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

  return (
    <div className="flex h-full">
      {/* Sidebar */}
      <aside className={`${open ? 'w-64' : 'w-16'} shrink-0 bg-marca-800 text-white transition-all duration-200 flex flex-col`}>
        <div className="h-16 flex items-center gap-2 px-4 border-b border-white/10">
          <div className="h-8 w-8 rounded bg-destaque text-marca-900 font-black grid place-items-center">D</div>
          {open && <span className="font-bold tracking-wide">Dubena</span>}
        </div>
        <nav className="flex-1 overflow-y-auto py-3">
          {grupos.map((g) => (
            <div key={g} className="mb-3">
              {open && <p className="px-4 py-1 text-[11px] uppercase tracking-wider text-white/40">{g}</p>}
              {visiveis.filter((i) => i.group === g).map((i) => (
                <NavLink
                  key={i.to}
                  to={i.to}
                  end={i.to === '/'}
                  className={({ isActive }) =>
                    `flex items-center gap-3 px-4 py-2 text-sm hover:bg-white/10 transition-colors ${
                      isActive ? 'bg-white/15 border-l-4 border-destaque font-medium' : 'border-l-4 border-transparent'
                    }`
                  }
                >
                  {i.icon}
                  {open && <span>{i.label}</span>}
                </NavLink>
              ))}
            </div>
          ))}
        </nav>
      </aside>

      {/* Conteúdo */}
      <div className="flex-1 flex flex-col min-w-0">
        {/* Header */}
        <header className="h-16 shrink-0 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between px-4">
          <button onClick={() => setOpen(!open)} className="p-2 rounded hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="Alternar menu">
            {open ? <ChevronLeft size={18} /> : <MenuIcon size={18} />}
          </button>
          <div className="flex items-center gap-3">
            <button onClick={toggleDark} className="p-2 rounded hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="Tema">
              {dark ? <Sun size={18} /> : <Moon size={18} />}
            </button>
            <div className="text-right leading-tight">
              <p className="text-sm font-medium">{user?.name}</p>
              <p className="text-xs text-slate-500">{user?.roles.join(', ') || (user?.is_support ? 'Suporte' : '')}</p>
            </div>
            <button
              onClick={async () => { await logout(); navigate('/login') }}
              className="p-2 rounded hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300"
              aria-label="Sair"
              title="Sair"
            >
              <LogOut size={18} />
            </button>
          </div>
        </header>

        <main className="flex-1 overflow-y-auto p-6">{children}</main>
      </div>
    </div>
  )
}
