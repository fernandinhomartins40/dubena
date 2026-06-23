import { useState } from 'react'
import { Plus, Handshake, Lock } from 'lucide-react'
import {
  Button, PageHeader, Input, Badge, DataTable, type Column, EmptyState, Field, CheckboxField, AsyncSelect,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogClose, toast,
} from '@/components/ui'
import { useConvenios, useCriarConvenio, useFecharConvenio, type Convenio } from './extraApi'

export function ConvenioPage() {
  const { data, isLoading } = useConvenios()
  const criar = useCriarConvenio()
  const fechar = useFecharConvenio()
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
    if (!confirm(`Fechar o convênio "${c.descricao}"? Gera o título do período.`)) return
    try { await fechar.mutateAsync(c.id); toast.success('Convênio fechado.') }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao fechar.') }
  }

  const columns: Column<Convenio>[] = [
    { key: 'descricao', header: 'Descrição', cell: (v) => <span className="font-medium">{v.descricao}</span> },
    { key: 'cliente', header: 'Cliente', cell: (v) => v.cliente?.nome || '—' },
    { key: 'fech', header: 'Dia fech.', cell: (v) => v.dia_fechamento ?? '—' },
    { key: 'venc', header: 'Dia venc.', cell: (v) => v.dia_vencimento ?? '—' },
    { key: 'ativo', header: 'Ativo', cell: (v) => v.ativo ? <Badge variant="success">Ativo</Badge> : <Badge variant="secondary">Inativo</Badge> },
    {
      key: 'acoes', header: '', cell: (v) => (
        <div className="flex justify-end"><Button variant="ghost" size="sm" loading={fechar.isPending} onClick={() => onFechar(v)}><Lock size={15} /> Fechar período</Button></div>
      ),
    },
  ]

  return (
    <div>
      <PageHeader title="Convênios" subtitle="Faturamento por convênio (fechamento mensal)"
        action={<Button onClick={abrir}><Plus size={16} /> Novo convênio</Button>} />
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(v) => v.id}
        empty={<EmptyState icon={<Handshake />} title="Nenhum convênio" />} />

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>Novo convênio</DialogTitle></DialogHeader>
          <div className="space-y-4">
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
          </div>
          <DialogFooter>
            <DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose>
            <Button loading={criar.isPending} onClick={onCriar}>Criar</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
