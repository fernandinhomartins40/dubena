import { useState, type ReactNode } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { Search, Star } from 'lucide-react'
import { Input } from '@/components/ui'
import { usePreference } from '@/lib/usePreference'

export interface ModuleLink { label: string; to: string; group: string; icon?: ReactNode }
const normalize = (text: string) => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase()

/** Recebe exclusivamente itens já autorizados pelo shell. */
export function ModuleFinder({ items, userKey, onNavigate }: { items: ModuleLink[]; userKey: string; onNavigate: () => void }) {
  const [query, setQuery] = useState('')
  const [favorites, setFavorites] = usePreference<string[]>(`erpnovo.ui.favorites.${userKey}`, [])
  const found = query.trim() ? items.filter((item) => normalize(`${item.label} ${item.group}`).includes(normalize(query))) : items.filter((item) => favorites.includes(item.to))
  return <div className="shrink-0 px-3 pb-3">
    <div className="relative"><Search className="pointer-events-none absolute left-2.5 top-2.5 text-sidebar-foreground/70" size={16} />
      <Input aria-label="Buscar módulos" placeholder="Buscar módulos…" value={query} onChange={(event) => setQuery(event.target.value)} className="h-9 border-white/10 bg-white/5 pl-8 text-[13px] text-white placeholder:text-sidebar-foreground/70 focus-visible:ring-destaque" />
    </div>
    {found.length > 0 && <div className="mt-3 max-h-56 overflow-y-auto">
      <p className="mb-1 px-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-sidebar-foreground/60">{query ? 'Resultados' : 'Favoritos'}</p>
      {found.map((item) => <div key={item.to} className="flex items-center rounded hover:bg-white/5">
        <Link className="flex-1 rounded px-2 py-1.5 text-[13px] text-white focus-visible:outline focus-visible:outline-2" to={item.to} onClick={() => { setQuery(''); onNavigate() }}>{item.label}</Link>
        <button type="button" aria-label={`${favorites.includes(item.to) ? 'Remover' : 'Adicionar'} ${item.label} ${favorites.includes(item.to) ? 'dos' : 'aos'} favoritos`}
          aria-pressed={favorites.includes(item.to)} className="grid size-9 shrink-0 place-items-center rounded text-sidebar-foreground focus-visible:outline focus-visible:outline-2"
          onClick={() => setFavorites((current) => current.includes(item.to) ? current.filter((to) => to !== item.to) : [...current, item.to])}>
          <Star size={15} fill={favorites.includes(item.to) ? 'currentColor' : 'none'} />
        </button>
      </div>)}
    </div>}
    {query && !found.length && <p role="status" className="mt-3 text-sm">Nenhum módulo encontrado.</p>}
  </div>
}

export function RouteTrail({ items, home = '/' }: { items: ModuleLink[]; home?: string }) {
  const { pathname } = useLocation()
  const active = [...items].sort((a, b) => b.to.length - a.to.length).find((item) => item.to !== home && (pathname === item.to || pathname.startsWith(`${item.to}/`)))
  if (!active) return null
  return <nav aria-label="Você está em" className="mb-5 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
    <Link className="rounded hover:underline focus-visible:outline focus-visible:outline-2" to={home}>Início</Link><span aria-hidden>/</span>
    <span>{active.group}</span><span aria-hidden>/</span>
    {pathname === active.to ? <span aria-current="page" className="font-medium text-foreground">{active.label}</span> : <><Link to={active.to} className="hover:underline">{active.label}</Link><span aria-hidden>/</span><span aria-current="page">{pathname.endsWith('/novo') ? 'Novo cadastro' : 'Detalhes'}</span></>}
  </nav>
}
