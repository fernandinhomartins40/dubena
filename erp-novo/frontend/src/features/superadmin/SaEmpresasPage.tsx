import { useState } from 'react'
import { Building2, CreditCard, SlidersHorizontal, Ban, CheckCircle2, AlertTriangle } from 'lucide-react'
import {
  Button, Badge, type Column, Field, Input, Switch, Select, SelectTrigger, SelectValue, SelectContent, SelectItem,
  ResourceList, FormDialog, ConfirmDialog, SearchBar, AsyncState, StatCard, toast,
} from '@/components/ui'
import {
  useSaEmpresas, useSaEmpresaAcoes, useSaPlanos, useSaRecursos, useSaOverride,
  useSaLimites, useSaLimiteOverride,
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
  const [suspenderDe, setSuspenderDe] = useState<SaEmpresa | null>(null)

  const todas = data ?? []
  const ativas = todas.filter((e) => e.ativo).length
  const inadimplentes = todas.filter((e) => e.status_assinatura === 'inadimplente').length

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
            <Button variant="ghost" size="icon" aria-label="Suspender" onClick={() => setSuspenderDe(v)}><Ban size={15} /></Button>
          ) : (
            <Button variant="ghost" size="icon" aria-label="Reativar" onClick={() => acoes.reativar.mutate(v.id)}><CheckCircle2 size={15} /></Button>
          )}
        </div>
      ),
    },
  ]

  return (
    <>
      <div className="mb-4 grid grid-cols-3 gap-4">
        <StatCard titulo="Total" valor={todas.length} icon={Building2} accent="primary" />
        <StatCard titulo="Ativas" valor={ativas} icon={CheckCircle2} accent="success" />
        <StatCard titulo="Inadimplentes" valor={inadimplentes} icon={AlertTriangle} accent="destructive" />
      </div>

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
      <ConfirmDialog
        open={suspenderDe !== null}
        onOpenChange={(v) => !v && setSuspenderDe(null)}
        title={`Suspender ${suspenderDe?.nome_fantasia || suspenderDe?.razao_social}?`}
        description="O acesso de todos os usuários desta empresa será bloqueado imediatamente. A ação é auditada e pode ser revertida com Reativar."
        confirmLabel="Suspender empresa"
        loading={acoes.suspender.isPending}
        onConfirm={async () => {
          if (!suspenderDe) return
          await acoes.suspender.mutateAsync(suspenderDe.id)
          toast.success('Empresa suspensa.')
          setSuspenderDe(null)
        }}
      />
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

/**
 * Recursos e limites EFETIVOS da empresa, com override auditado.
 *
 * Sobrepor o plano contratado é exceção comercial, então `motivo` é obrigatório
 * e o prazo é oferecido em destaque: cortesia sem validade vira permanente por
 * esquecimento. O diálogo pergunta ANTES de aplicar — o switch sozinho aplicaria
 * uma exceção sem que ninguém registrasse por quê.
 */
function RecursosDialog({ empresa, onClose }: { empresa: SaEmpresa; onClose: () => void }) {
  const { data: efetivos, isLoading, error } = useSaRecursos(empresa.id)
  const { data: limitesEfetivos } = useSaLimites(empresa.id)
  const { data: planosResp } = useSaPlanos()
  const override = useSaOverride()
  const limiteOverride = useSaLimiteOverride()
  const catalogo = planosResp?.catalogo ?? []
  const catalogoLimites = planosResp?.catalogoLimites ?? []
  const ativos = new Set(efetivos ?? [])

  // Exceção pendente de justificativa. Enquanto houver uma aqui, o formulário
  // de motivo fica aberto e nada é enviado.
  const [pendente, setPendente] = useState<
    | { tipo: 'recurso'; chave: string; rotulo: string; habilitado: boolean }
    | { tipo: 'limite'; chave: string; rotulo: string; valor: number | null }
    | null
  >(null)
  const [motivo, setMotivo] = useState('')
  const [expiraEm, setExpiraEm] = useState('')
  const [rascunhoLimite, setRascunhoLimite] = useState<Record<string, string>>({})

  async function confirmar() {
    if (!pendente || !motivo.trim()) return
    try {
      if (pendente.tipo === 'recurso') {
        await override.set.mutateAsync({
          empresaId: empresa.id, chave: pendente.chave,
          habilitado: pendente.habilitado, motivo, expiraEm: expiraEm || null,
        })
      } else {
        await limiteOverride.set.mutateAsync({
          empresaId: empresa.id, chave: pendente.chave,
          valor: pendente.valor, motivo, expiraEm: expiraEm || null,
        })
      }
      toast.success('Override registrado.')
      setPendente(null); setMotivo(''); setExpiraEm('')
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao aplicar override.') }
  }

  const salvando = override.set.isPending || limiteOverride.set.isPending

  return (
    <FormDialog
      open onOpenChange={(v) => !v && onClose()}
      title={`Plano da empresa — ${empresa.nome_fantasia || empresa.razao_social}`}
      description="Recursos e limites efetivos (plano + overrides). Toda exceção exige motivo e fica auditada."
      confirmLabel="Fechar"
      onConfirm={onClose}
      widthClass="max-w-2xl"
    >
      <AsyncState loading={isLoading} error={error} empty={catalogo.length === 0} emptyTitle="Catálogo de recursos indisponível">
        <p className="mb-1 text-sm font-medium">Recursos</p>
        <ul className="mb-4 divide-y divide-border">
          {catalogo.map((r) => {
            const on = ativos.has(r.chave)
            return (
              <li key={r.chave} className="flex items-center justify-between gap-3 py-2.5">
                <div className="min-w-0">
                  <p className="truncate text-sm font-medium">{r.descricao}</p>
                  <p className="truncate text-xs text-muted-foreground">{r.chave}</p>
                </div>
                <Switch
                  checked={on} disabled={salvando}
                  onCheckedChange={(c) => setPendente({ tipo: 'recurso', chave: r.chave, rotulo: r.descricao, habilitado: c })}
                />
              </li>
            )
          })}
        </ul>

        <p className="mb-1 text-sm font-medium">Limites</p>
        <p className="mb-2 text-xs text-muted-foreground">Em branco = ilimitado. Zero bloqueia por completo.</p>
        <ul className="divide-y divide-border">
          {catalogoLimites.map((l) => {
            const efetivo = limitesEfetivos?.[l.chave]
            const rascunho = rascunhoLimite[l.chave] ?? (efetivo === null || efetivo === undefined ? '' : String(efetivo))
            return (
              <li key={l.chave} className="flex items-center justify-between gap-3 py-2.5">
                <div className="min-w-0">
                  <p className="truncate text-sm font-medium">{l.descricao}</p>
                  <p className="truncate text-xs text-muted-foreground">
                    Atual: {efetivo === null || efetivo === undefined ? 'ilimitado' : efetivo}
                  </p>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                  <Input
                    type="number" min={0} placeholder="Ilimitado" className="w-28"
                    value={rascunho}
                    onChange={(e) => setRascunhoLimite((p) => ({ ...p, [l.chave]: e.target.value }))}
                  />
                  <Button
                    variant="outline" size="sm" disabled={salvando}
                    onClick={() => setPendente({
                      tipo: 'limite', chave: l.chave, rotulo: l.descricao,
                      valor: rascunho.trim() === '' ? null : Number(rascunho),
                    })}
                  >
                    Aplicar
                  </Button>
                </div>
              </li>
            )
          })}
        </ul>
      </AsyncState>

      {pendente && (
        <div className="mt-4 rounded-lg border border-amber-500/40 bg-amber-500/5 p-3">
          <p className="mb-2 text-sm font-medium">
            Sobrepor o plano — {pendente.rotulo}
          </p>
          <Field label="Motivo" required>
            <Input
              autoFocus value={motivo} onChange={(e) => setMotivo(e.target.value)}
              placeholder="ex.: cortesia comercial — chamado 4321"
            />
          </Field>
          <Field label="Expira em (deixe vazio para sem prazo)">
            <Input type="datetime-local" value={expiraEm} onChange={(e) => setExpiraEm(e.target.value)} />
          </Field>
          <div className="mt-2 flex justify-end gap-2">
            <Button variant="ghost" size="sm" onClick={() => { setPendente(null); setMotivo('') }}>Cancelar</Button>
            <Button size="sm" disabled={!motivo.trim() || salvando} onClick={confirmar}>Confirmar</Button>
          </div>
        </div>
      )}
    </FormDialog>
  )
}
