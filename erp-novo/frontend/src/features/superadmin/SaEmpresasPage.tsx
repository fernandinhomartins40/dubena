import { useState } from 'react'
import { Building2, CreditCard, SlidersHorizontal, Ban, CheckCircle2 } from 'lucide-react'
import {
  Button, Badge, type Column, Field, Switch, Select, SelectTrigger, SelectValue, SelectContent, SelectItem,
  ResourceList, FormDialog, SearchBar, AsyncState, toast,
} from '@/components/ui'
import {
  useSaEmpresas, useSaEmpresaAcoes, useSaPlanos, useSaRecursos, useSaOverride,
  type SaEmpresa,
} from './api'

const STATUS_LABEL: Record<string, string> = {
  trial: 'Trial', ativa: 'Ativa', inadimplente: 'Inadimplente', cancelada: 'Cancelada',
}
function statusBadge(s: string | null) {
  if (!s) return <Badge variant="secondary">Sem assinatura</Badge>
  if (s === 'ativa' || s === 'trial') return <Badge variant="success">{STATUS_LABEL[s]}</Badge>
  if (s === 'inadimplente') return <Badge variant="destructive">Inadimplente</Badge>
  return <Badge variant="secondary">{STATUS_LABEL[s] ?? s}</Badge>
}

/** Empresas (cross-tenant) — P4: busca, suspender/reativar, assinatura, overrides. */
export function SaEmpresasPage() {
  const [q, setQ] = useState('')
  const { data, isLoading } = useSaEmpresas(q || undefined)
  const acoes = useSaEmpresaAcoes()

  const [assinaturaDe, setAssinaturaDe] = useState<SaEmpresa | null>(null)
  const [recursosDe, setRecursosDe] = useState<SaEmpresa | null>(null)

  const columns: Column<SaEmpresa>[] = [
    {
      key: 'empresa', header: 'Empresa',
      cell: (v) => (
        <div>
          <div className="font-medium">{v.nome_fantasia || v.razao_social}</div>
          <div className="text-xs text-muted-foreground">{v.cnpj ?? '—'}{v.grupo ? ` · ${v.grupo}` : ''}</div>
        </div>
      ),
    },
    { key: 'plano', header: 'Plano', cell: (v) => v.plano ? <Badge variant="outline">{v.plano}</Badge> : <span className="text-muted-foreground">—</span> },
    { key: 'status', header: 'Assinatura', cell: (v) => statusBadge(v.status_assinatura) },
    { key: 'ativo', header: 'Acesso', cell: (v) => v.ativo ? <Badge variant="success">Ativa</Badge> : <Badge variant="destructive">Suspensa</Badge> },
    {
      key: 'acoes', header: '', align: 'right',
      cell: (v) => (
        <div className="flex items-center justify-end gap-1">
          <Button variant="ghost" size="icon" aria-label="Assinatura" onClick={() => setAssinaturaDe(v)}><CreditCard size={15} /></Button>
          <Button variant="ghost" size="icon" aria-label="Recursos" onClick={() => setRecursosDe(v)}><SlidersHorizontal size={15} /></Button>
          {v.ativo ? (
            <Button variant="ghost" size="icon" aria-label="Suspender" onClick={() => { if (confirm(`Suspender ${v.razao_social}? O acesso da empresa será bloqueado.`)) acoes.suspender.mutate(v.id) }}><Ban size={15} /></Button>
          ) : (
            <Button variant="ghost" size="icon" aria-label="Reativar" onClick={() => acoes.reativar.mutate(v.id)}><CheckCircle2 size={15} /></Button>
          )}
        </div>
      ),
    },
  ]

  return (
    <>
      <ResourceList
        title="Empresas"
        subtitle="Todas as empresas da plataforma (acesso cross-tenant, auditado)"
        filtros={<SearchBar value={q} onChange={setQ} onSearch={() => { /* a query refaz ao mudar q */ }} placeholder="Buscar por razão social, fantasia ou CNPJ…" />}
        columns={columns}
        rows={data}
        loading={isLoading}
        rowKey={(v) => v.id}
        emptyIcon={<Building2 />}
        emptyTitle="Nenhuma empresa"
        emptyDescription="Nenhuma empresa corresponde à busca."
      />

      {assinaturaDe && (
        <AssinaturaDialog empresa={assinaturaDe} onClose={() => setAssinaturaDe(null)} />
      )}
      {recursosDe && (
        <RecursosDialog empresa={recursosDe} onClose={() => setRecursosDe(null)} />
      )}
    </>
  )
}

/** Define plano + status da assinatura de uma empresa. */
function AssinaturaDialog({ empresa, onClose }: { empresa: SaEmpresa; onClose: () => void }) {
  const { data: planos } = useSaPlanos()
  const { definirAssinatura } = useSaEmpresaAcoes()
  const [planoId, setPlanoId] = useState<string>('')
  const [status, setStatus] = useState<string>(empresa.status_assinatura ?? 'ativa')

  async function salvar() {
    if (!planoId) { toast.error('Escolha um plano.'); return }
    try {
      await definirAssinatura.mutateAsync({ id: empresa.id, plano_id: Number(planoId), status })
      toast.success('Assinatura definida.'); onClose()
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao definir assinatura.') }
  }

  return (
    <FormDialog
      open onOpenChange={(v) => !v && onClose()}
      title={`Assinatura — ${empresa.nome_fantasia || empresa.razao_social}`}
      description="Define o plano e o status da assinatura desta empresa."
      confirmLabel="Definir assinatura"
      loading={definirAssinatura.isPending}
      onConfirm={salvar}
    >
      <Field label="Plano" required>
        <Select value={planoId} onValueChange={setPlanoId}>
          <SelectTrigger><SelectValue placeholder="Selecione um plano" /></SelectTrigger>
          <SelectContent>
            {(planos?.planos ?? []).map((p) => (
              <SelectItem key={p.id} value={String(p.id)}>{p.nome}</SelectItem>
            ))}
          </SelectContent>
        </Select>
      </Field>
      <Field label="Status">
        <Select value={status} onValueChange={setStatus}>
          <SelectTrigger><SelectValue /></SelectTrigger>
          <SelectContent>
            {Object.entries(STATUS_LABEL).map(([k, v]) => <SelectItem key={k} value={k}>{v}</SelectItem>)}
          </SelectContent>
        </Select>
      </Field>
    </FormDialog>
  )
}

/** Mostra os recursos efetivos da empresa e permite override (ligar/desligar). */
function RecursosDialog({ empresa, onClose }: { empresa: SaEmpresa; onClose: () => void }) {
  const { data: efetivos, isLoading, error } = useSaRecursos(empresa.id)
  const { data: planosResp } = useSaPlanos()
  const override = useSaOverride()
  const catalogo = planosResp?.catalogo ?? []
  const ativos = new Set(efetivos ?? [])

  async function alternar(chave: string, on: boolean) {
    try {
      await override.set.mutateAsync({ empresaId: empresa.id, chave, habilitado: on })
      toast.success(on ? 'Recurso habilitado (override).' : 'Recurso desabilitado (override).')
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao alterar recurso.') }
  }

  return (
    <FormDialog
      open onOpenChange={(v) => !v && onClose()}
      title={`Recursos — ${empresa.nome_fantasia || empresa.razao_social}`}
      description="Recursos efetivos (plano + overrides). Ligar/desligar cria um override por empresa, auditado."
      confirmLabel="Fechar"
      onConfirm={onClose}
      widthClass="max-w-lg"
    >
      <AsyncState loading={isLoading} error={error} empty={catalogo.length === 0} emptyTitle="Catálogo de recursos indisponível">
        <ul className="divide-y divide-border">
          {catalogo.map((r) => {
            const on = ativos.has(r.chave)
            return (
              <li key={r.chave} className="flex items-center justify-between gap-3 py-2.5">
                <div className="min-w-0">
                  <p className="truncate text-sm font-medium">{r.descricao}</p>
                  <p className="truncate text-xs text-muted-foreground">{r.chave}</p>
                </div>
                <Switch checked={on} onCheckedChange={(c) => alternar(r.chave, c)} disabled={override.set.isPending} />
              </li>
            )
          })}
        </ul>
      </AsyncState>
    </FormDialog>
  )
}
