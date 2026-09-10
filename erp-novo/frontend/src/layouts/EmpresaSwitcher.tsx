import { useQueryClient } from '@tanstack/react-query'
import { useRequestLeave } from '@/lib/UnsavedChanges'
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
export function EmpresaSwitcher({ onScopeChange }: { onScopeChange?: () => void }) {
  const { user, can, refresh } = useAuth()
  const { data: empresas } = useEmpresas()
  const ativar = useAtivarEmpresa()
  const qc = useQueryClient()
  const requestLeave = useRequestLeave()

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
      <div className="flex min-w-0 items-center gap-2 rounded-md border border-border px-2 sm:px-3 py-1.5 text-sm">
        <Building2 size={15} className="text-muted-foreground" />
        <div className="min-w-0"><span className="block text-[11px] text-muted-foreground">Operando em</span><span className="block font-medium truncate max-w-[160px]" title={nomeDe(ativa)}>{nomeDe(ativa)}</span></div>
      </div>
    )
  }

  const empresaFiltrada = filtro ? empresas.find((e) => e.id === filtro) : null
  const rotulo = empresaFiltrada ? nomeDe(empresaFiltrada) : 'Toda a rede'

  /**
   * Recarrega tudo: a troca muda o conjunto de dados visível.
   *
   * F9-03 — `invalidateQueries` sozinho deixa uma janela aberta. Ele marca as
   * queries como obsoletas e dispara o refetch, mas **o dado antigo continua no
   * cache até a resposta chegar** — e as telas o renderizam nesse intervalo.
   *
   * Numa rede de filiais isso é um susto ("cadê meus pedidos?"). Entre revendas
   * concorrentes é vazamento: por alguns segundos a tela mostra a carteira da
   * outra empresa com o rótulo da nova no cabeçalho.
   *
   * `cancelQueries` primeiro, pelo mesmo motivo do logout: uma requisição em voo
   * disparada com o filtro ANTIGO chega depois e repovoa o cache já sob o
   * contexto novo.
   */
  const recarregar = async () => {
    await qc.cancelQueries()
    qc.removeQueries()
  }

  async function verTodaARede() {
    setFiltroEmpresa(null)
    await recarregar()
    onScopeChange?.()
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
      await recarregar()
      onScopeChange?.()
      toast.success(`Mostrando ${nome}.`)
    } catch {
      setFiltroEmpresa(filtro)
      toast.error('Não foi possível trocar a empresa.')
    }
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <button disabled={ativar.isPending} aria-label={`Visualizando ${rotulo}. Operando em ${ativa ? nomeDe(ativa) : 'empresa não identificada'}`}
          className="flex min-w-0 items-center gap-2 rounded-md border border-border px-2 sm:px-3 py-1.5 text-sm hover:bg-secondary transition-colors max-w-[240px]">
          {empresaFiltrada
            ? <Building2 size={15} className="text-muted-foreground shrink-0" />
            : <Network size={15} className="text-muted-foreground shrink-0" />}
          <span className="min-w-0 text-left"><span className="block truncate text-[11px] text-muted-foreground">{ativar.isPending ? 'Trocando empresa…' : `Visualizando: ${rotulo}`}</span>
            <span className="block truncate text-xs font-medium" title={ativa ? nomeDe(ativa) : undefined}>Operando em: {ativa ? nomeDe(ativa) : 'não identificada'}</span></span>
          <ChevronsUpDown size={14} className="text-muted-foreground shrink-0" />
        </button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" className="w-72">
        <DropdownMenuLabel>Escopo de visualização</DropdownMenuLabel>
        <p className="px-2 pb-2 text-xs text-muted-foreground">Escolher uma empresa também muda a operação. Toda a rede mantém a empresa operacional atual.</p>
        <DropdownMenuSeparator />

        <DropdownMenuItem disabled={ativar.isPending} onClick={() => { if (filtro !== null) requestLeave(() => { void verTodaARede() }) }}>
          <Network />
          <span className="flex-1">Toda a rede</span>
          {!filtro && <Check className="text-primary" />}
        </DropdownMenuItem>

        <DropdownMenuSeparator />

        {empresas.map((e) => (
          <DropdownMenuItem disabled={ativar.isPending} key={e.id} onClick={() => { if (filtro !== e.id || ativa?.id !== e.id) requestLeave(() => { void selecionar(e.id, nomeDe(e)) }) }}>
            <Building2 />
            <span className="flex-1 truncate">{nomeDe(e)}</span>
            {filtro === e.id && <Check className="text-primary" />}
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
