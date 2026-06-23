import { useState } from 'react'
import { Plus, MessageSquareHeart, Pencil, Trash2 } from 'lucide-react'
import {
  Button, PageHeader, Input, Textarea, Badge, DataTable, type Column, EmptyState, Field,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem, AsyncSelect,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogClose, toast,
} from '@/components/ui'
import { usePosVendas, useSalvarPosVenda, useExcluirPosVenda, type PosVenda } from './api'

const fmtData = (s: string | null) => (s ? new Date(s).toLocaleDateString('pt-BR') : '—')

export function PosVendaPage() {
  const { data, isLoading } = usePosVendas()
  const salvar = useSalvarPosVenda()
  const excluir = useExcluirPosVenda()
  const [open, setOpen] = useState(false)
  const [edit, setEdit] = useState<PosVenda | null>(null)
  const [form, setForm] = useState<Record<string, any>>({})
  const [clienteLabel, setClienteLabel] = useState('')

  function abrir(reg?: PosVenda) {
    setEdit(reg ?? null)
    setForm(reg ? { ...reg } : { situacao: 'pendente', canal: 'whatsapp' })
    setClienteLabel('')
    setOpen(true)
  }

  async function onSalvar() {
    try {
      await salvar.mutateAsync({ id: edit?.id ?? null, data: form })
      toast.success('Pós-venda salva.'); setOpen(false)
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao salvar.') }
  }

  const columns: Column<PosVenda>[] = [
    { key: 'data', header: 'Data', cell: (v) => fmtData(v.data) },
    { key: 'canal', header: 'Canal', cell: (v) => v.canal || '—' },
    { key: 'nota', header: 'Nota (NPS)', cell: (v) => v.nota ?? '—' },
    { key: 'obs', header: 'Observação', cell: (v) => <span className="line-clamp-1">{v.observacao || '—'}</span> },
    { key: 'sit', header: 'Situação', cell: (v) => v.situacao === 'realizado' ? <Badge variant="success">Realizado</Badge> : <Badge variant="secondary">Pendente</Badge> },
    {
      key: 'acoes', header: '', cell: (v) => (
        <div className="flex justify-end gap-1">
          <Button variant="ghost" size="icon" onClick={() => abrir(v)}><Pencil size={15} /></Button>
          <Button variant="ghost" size="icon" onClick={() => { if (confirm('Excluir?')) excluir.mutate(v.id) }}><Trash2 size={15} /></Button>
        </div>
      ),
    },
  ]

  return (
    <div>
      <PageHeader title="Pós-venda" subtitle="Pesquisa de satisfação (NPS) por contato"
        action={<Button onClick={() => abrir()}><Plus size={16} /> Novo registro</Button>} />
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(v) => v.id}
        empty={<EmptyState icon={<MessageSquareHeart />} title="Nenhuma pós-venda" description="Registre o primeiro contato de satisfação." />} />

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>{edit ? 'Editar pós-venda' : 'Nova pós-venda'}</DialogTitle></DialogHeader>
          <div className="space-y-4">
            <Field label="Cliente">
              <AsyncSelect endpoint="/lookups/clientes" value={form.cliente_id ?? null} valueLabel={clienteLabel}
                onChange={(id, opt) => { setForm((f) => ({ ...f, cliente_id: id })); setClienteLabel(opt?.label ?? '') }} />
            </Field>
            <div className="grid grid-cols-2 gap-3">
              <Field label="Data"><Input type="date" value={form.data ?? ''} onChange={(e) => setForm((f) => ({ ...f, data: e.target.value }))} /></Field>
              <Field label="Nota (0–10)"><Input type="number" min={0} max={10} value={form.nota ?? ''} onChange={(e) => setForm((f) => ({ ...f, nota: e.target.value === '' ? null : Number(e.target.value) }))} /></Field>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <Field label="Canal">
                <Select value={form.canal ?? ''} onValueChange={(v) => setForm((f) => ({ ...f, canal: v }))}>
                  <SelectTrigger><SelectValue placeholder="Selecione" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="whatsapp">WhatsApp</SelectItem>
                    <SelectItem value="telefone">Telefone</SelectItem>
                    <SelectItem value="email">E-mail</SelectItem>
                  </SelectContent>
                </Select>
              </Field>
              <Field label="Situação">
                <Select value={form.situacao ?? 'pendente'} onValueChange={(v) => setForm((f) => ({ ...f, situacao: v }))}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="pendente">Pendente</SelectItem>
                    <SelectItem value="realizado">Realizado</SelectItem>
                  </SelectContent>
                </Select>
              </Field>
            </div>
            <Field label="Observação"><Textarea value={form.observacao ?? ''} onChange={(e) => setForm((f) => ({ ...f, observacao: e.target.value }))} /></Field>
          </div>
          <DialogFooter>
            <DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose>
            <Button loading={salvar.isPending} onClick={onSalvar}>Salvar</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
