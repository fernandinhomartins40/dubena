import { useState } from 'react'
import { Plus, Handshake, Lock } from 'lucide-react'
import {
  Button, Input, Badge, type Column, Field, CheckboxField, AsyncSelect,
  ResourceList, FormDialog, ConfirmDialog, toast,
} from '@/components/ui'
import { useConvenios, useCriarConvenio, useFecharConvenio, type Convenio } from './api'

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
  async function onFechar(c: Convenio) {
    try { await fechar.mutateAsync(c.id); toast.success('Convênio fechado.'); setConfirmando(null) }
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

      <ConfirmDialog open={!!confirmando} onOpenChange={(open) => { if (!open) setConfirmando(null) }}
        title="Fechar período do convênio" confirmLabel="Fechar período" variant="default" loading={fechar.isPending}
        description={<>Fechar o período de <strong>{confirmando?.descricao}</strong>? O fechamento gera o título financeiro do período.</>} onConfirm={() => { if (confirmando) void onFechar(confirmando) }} />

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
