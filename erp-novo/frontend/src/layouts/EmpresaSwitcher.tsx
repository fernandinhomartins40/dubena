import { useQueryClient } from '@tanstack/react-query'
import { Building2, Check, ChevronsUpDown, Network } from 'lucide-react'
import {
  DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem,
  DropdownMenuLabel, DropdownMenuSeparator, toast,
} from '@/components/ui'
import { useAuth } from '@/lib/auth'
import { getFiltroEmpresa, setFiltroEmpresa } from '@/lib/api'
import { useEmpresas, useAtivarEmpresa } from '@/features/empresas/api'

/**
 * Seletor de empresa do cabeçalho.
 *
 * Numa REDE (matriz + filiais) as telas mostram a operação inteira por padrão —
 * é como o ERP antigo funciona, e é o que o dono espera ao abrir uma listagem.
 * Este seletor filtra a visão para uma unidade; "Toda a rede" volta ao geral.
 *
 * Ao escolher uma empresa também se troca a EMPRESA ATIVA (contexto de config,
 * caixa e numeração fiscal), porque quem opera uma filial precisa estar
 * posicionado nela. São coisas distintas, mas a escolha do usuário é uma só.
 */
export function EmpresaSwitcher() {
  const { user, can, refresh } = useAuth()
  const { data: empresas } = useEmpresas()
  const ativar = useAtivarEmpresa()
  const qc = useQueryClient()

  const filtro = getFiltroEmpresa()
  const podeTrocar = can('empresa.view') && (empresas?.length ?? 0) > 1

  if (!empresas || empresas.length === 0) return null

  const ativa = empresas.find((e) => e.ativa) ?? empresas.find((e) => e.id === user?.empresa_id)
  const nomeDe = (e: { nome_informal?: string | null; razao_social: string }) =>
    e.nome_informal || e.razao_social

  // Uma empresa só: nada a escolher, mostra o nome.
  if (!podeTrocar) {
    if (!ativa) return null
    return (
      <div className="hidden md:flex items-center gap-2 rounded-md border border-border px-3 py-1.5 text-sm">
        <Building2 size={15} className="text-muted-foreground" />
        <span className="font-medium truncate max-w-[160px]">{nomeDe(ativa)}</span>
      </div>
    )
  }

  const empresaFiltrada = filtro ? empresas.find((e) => e.id === filtro) : null
  const rotulo = empresaFiltrada ? nomeDe(empresaFiltrada) : 'Toda a rede'

  /** Recarrega tudo: a troca muda o conjunto de dados visível. */
  const recarregar = () => qc.invalidateQueries()

  async function verTodaARede() {
    setFiltroEmpresa(null)
    recarregar()
    toast.success('Mostrando toda a rede.')
  }

  async function selecionar(id: number, nome: string) {
    try {
      setFiltroEmpresa(id)
      // Também posiciona o usuário na empresa (config/caixa/numeração).
      if (id !== ativa?.id) {
        await ativar.mutateAsync(id)
        await refresh()
      }
      recarregar()
      toast.success(`Mostrando ${nome}.`)
    } catch {
      setFiltroEmpresa(filtro)
      toast.error('Não foi possível trocar a empresa.')
    }
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <button className="flex items-center gap-2 rounded-md border border-border px-3 py-1.5 text-sm hover:bg-secondary transition-colors max-w-[240px]">
          {empresaFiltrada
            ? <Building2 size={15} className="text-muted-foreground shrink-0" />
            : <Network size={15} className="text-muted-foreground shrink-0" />}
          <span className="font-medium truncate">{rotulo}</span>
          <ChevronsUpDown size={14} className="text-muted-foreground shrink-0" />
        </button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" className="w-72">
        <DropdownMenuLabel>Empresa exibida</DropdownMenuLabel>
        <DropdownMenuSeparator />

        <DropdownMenuItem onClick={verTodaARede}>
          <Network />
          <span className="flex-1">Toda a rede</span>
          {!filtro && <Check className="text-primary" />}
        </DropdownMenuItem>

        <DropdownMenuSeparator />

        {empresas.map((e) => (
          <DropdownMenuItem key={e.id} onClick={() => selecionar(e.id, nomeDe(e))}>
            <Building2 />
            <span className="flex-1 truncate">{nomeDe(e)}</span>
            {filtro === e.id && <Check className="text-primary" />}
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
