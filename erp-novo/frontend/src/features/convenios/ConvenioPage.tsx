import { useEffect, useState } from 'react'
import { Plus, Handshake, Lock } from 'lucide-react'
import {
  Button, Input, Badge, type Column, Field, CheckboxField, AsyncSelect,
  ResourceList, FormDialog, toast,
} from '@/components/ui'
import { useConvenios, useCriarConvenio, useFecharConvenio, useFechamentosConvenio, type Convenio } from './api'
import { periodoSugerido } from './periodo'
import { data as fmtData } from '@/lib/format'

export function ConvenioPage() {
  const { data, isLoading, error, refetch } = useConvenios()
  const criar = useCriarConvenio()
  const fechar = useFecharConvenio()
  const [confirmando, setConfirmando] = useState<Convenio | null>(null)
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState<Record<string, any>>({ ativo: true })
  const [clienteLabel, setClienteLabel] = useState('')

  function abrir() { setForm({ ativo: true }); setClienteLabel(''); setOpen(true) }
  async function onCriar() {
    if (!form.descricao) { toast.error('Informe a descrição.'); return }
    try { await criar.mutateAsync(form); toast.success('Convênio criado.'); setOpen(false) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao criar.') }
  }
  // Período já sugerido (dia de fechamento + último fechamento): a pessoa só confere.
  const anteriores = useFechamentosConvenio(confirmando?.id ?? null)
  const [periodo, setPeriodo] = useState({ inicio: '', fim: '' })
  useEffect(() => {
    if (!confirmando || anteriores.isLoading) return
    const ultimo = (anteriores.data ?? []).filter((x) => !!x.periodo_fim).sort((x, y) => y.periodo_fim.localeCompare(x.periodo_fim))[0]
    setPeriodo(periodoSugerido(confirmando.dia_fechamento, ultimo?.periodo_fim ?? null, new Date().toLocaleDateString('sv-SE')))
  }, [confirmando, anteriores.isLoading, anteriores.data])

  async function onFechar(c: Convenio) {
    if (!periodo.inicio || !periodo.fim || periodo.fim < periodo.inicio) { toast.error('Confira o período: o fim não pode vir antes do início.'); return }
    try { await fechar.mutateAsync({ id: c.id, periodo_inicio: periodo.inicio, periodo_fim: periodo.fim }); toast.success(`Período de ${fmtData(periodo.inicio)} a ${fmtData(periodo.fim)} fechado.`); setConfirmando(null) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao fechar.') }
  }

  const columns: Column<Convenio>[] = [
    { key: 'descricao', header: 'Descrição', cell: (v) => <span className="font-medium">{v.descricao}</span> },
    { key: 'cliente', header: 'Cliente', cell: (v) => v.cliente?.nome || '—' },
    { key: 'fech', header: 'Dia fech.', align: 'right', cell: (v) => v.dia_fechamento ?? '—' },
    { key: 'venc', header: 'Dia venc.', align: 'right', cell: (v) => v.dia_vencimento ?? '—' },
    { key: 'ativo', header: 'Ativo', cell: (v) => v.ativo ? <Badge variant="success">Ativo</Badge> : <Badge variant="secondary">Inativo</Badge> },
    {
      key: 'acoes', header: '', align: 'right',
      cell: (v) => <Button variant="ghost" size="sm" loading={fechar.isPending} onClick={() => setConfirmando(v)}><Lock size={15} /> Fechar período</Button>,
    },
  ]

  return (
    <>
      <ResourceList
        title="Convênios"
        subtitle="Faturamento por convênio (fechamento mensal)"
        action={<Button onClick={abrir}><Plus size={16} /> Novo convênio</Button>}
        columns={columns} rows={data} loading={isLoading} error={error} onRetry={() => { void refetch() }} rowKey={(v) => v.id}
        emptyIcon={<Handshake />} emptyTitle="Nenhum convênio"
      />

      <FormDialog open={!!confirmando} onOpenChange={(o) => { if (!o) setConfirmando(null) }}
        title="Fechar período do convênio" confirmLabel="Fechar período" loading={fechar.isPending}
        onConfirm={() => { if (confirmando) void onFechar(confirmando) }}>
        <p className="text-sm text-muted-foreground">Fechar <strong className="text-foreground">{confirmando?.descricao}</strong> gera o título financeiro com os pedidos do período.</p>
        <div className="grid grid-cols-2 gap-3">
          <Field label="De" required><Input type="date" value={periodo.inicio} onChange={(e) => setPeriodo((p) => ({ ...p, inicio: e.target.value }))} /></Field>
          <Field label="Até" required><Input type="date" min={periodo.inicio} value={periodo.fim} onChange={(e) => setPeriodo((p) => ({ ...p, fim: e.target.value }))} /></Field>
        </div>
        <p className="text-xs text-muted-foreground">Sugerido pelo dia de fechamento do convênio e pelo último fechamento.</p>
      </FormDialog>

      <FormDialog
        dirty={JSON.stringify(form) !== JSON.stringify({ ativo: true })}
        open={open} onOpenChange={setOpen}
        title="Novo convênio" confirmLabel="Criar"
        loading={criar.isPending} onConfirm={onCriar}
      >
        <Field label="Descrição" required><Input value={form.descricao ?? ''} onChange={(e) => setForm((f) => ({ ...f, descricao: e.target.value }))} /></Field>
        <Field label="Cliente">
          <AsyncSelect endpoint="/lookups/clientes" value={form.cliente_id ?? null} valueLabel={clienteLabel}
            onChange={(id, opt) => { setForm((f) => ({ ...f, cliente_id: id })); setClienteLabel(opt?.label ?? '') }} />
        </Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Dia de fechamento"><Input type="number" min={1} max={31} value={form.dia_fechamento ?? ''} onChange={(e) => setForm((f) => ({ ...f, dia_fechamento: e.target.value === '' ? null : Number(e.target.value) }))} /></Field>
          <Field label="Dia de vencimento"><Input type="number" min={1} max={31} value={form.dia_vencimento ?? ''} onChange={(e) => setForm((f) => ({ ...f, dia_vencimento: e.target.value === '' ? null : Number(e.target.value) }))} /></Field>
        </div>
        <CheckboxField label="Convênio ativo" checked={!!form.ativo} onChange={(c) => setForm((f) => ({ ...f, ativo: c }))} />
      </FormDialog>
    </>
  )
}
