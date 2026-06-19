import { Building2, Check, ChevronsUpDown } from 'lucide-react'
import {
  DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, toast,
} from '@/components/ui'
import { useAuth } from '@/lib/auth'
import { useEmpresas, useAtivarEmpresa } from '@/features/empresas/api'

/** Seletor de empresa ativa no header (equivale ao "change" do legado). */
export function EmpresaSwitcher() {
  const { user, can, refresh } = useAuth()
  const habilitado = can('empresa.view')
  const { data: empresas } = useEmpresas()
  const ativar = useAtivarEmpresa()

  if (!habilitado || !empresas || empresas.length <= 1) {
    // Sem permissão ou só uma empresa: mostra o nome da ativa (ou nada).
    const ativa = empresas?.find((e) => e.ativa)
    if (!ativa) return null
    return (
      <div className="hidden md:flex items-center gap-2 rounded-md border border-border px-3 py-1.5 text-sm">
        <Building2 size={15} className="text-muted-foreground" />
        <span className="font-medium truncate max-w-[160px]">{ativa.nome_informal || ativa.razao_social}</span>
      </div>
    )
  }

  const ativa = empresas.find((e) => e.ativa) ?? empresas.find((e) => e.id === user?.empresa_id)

  async function trocar(id: number, nome: string) {
    if (id === ativa?.id) return
    try { await ativar.mutateAsync(id); await refresh(); toast.success(`Empresa ativa: ${nome}`) }
    catch { toast.error('Não foi possível trocar a empresa.') }
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <button className="flex items-center gap-2 rounded-md border border-border px-3 py-1.5 text-sm hover:bg-secondary transition-colors max-w-[220px]">
          <Building2 size={15} className="text-muted-foreground shrink-0" />
          <span className="font-medium truncate">{ativa?.nome_informal || ativa?.razao_social || 'Selecionar empresa'}</span>
          <ChevronsUpDown size={14} className="text-muted-foreground shrink-0" />
        </button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" className="w-64">
        <DropdownMenuLabel>Empresa ativa</DropdownMenuLabel>
        <DropdownMenuSeparator />
        {empresas.map((e) => (
          <DropdownMenuItem key={e.id} onClick={() => trocar(e.id, e.nome_informal || e.razao_social)}>
            <Building2 />
            <span className="flex-1 truncate">{e.nome_informal || e.razao_social}</span>
            {e.ativa && <Check className="text-primary" />}
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
