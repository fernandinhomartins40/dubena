import { useState } from 'react'
import { Plus, MessageSquareHeart } from 'lucide-react'
import {
  Button, Input, Textarea, Badge, type Column, Field, AsyncSelect,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem,
  ResourceList, FormDialog, RowActions, toast,
} from '@/components/ui'
import { data as fmtData } from '@/lib/format'
import { usePosVendas, useSalvarPosVenda, useExcluirPosVenda, type PosVenda } from './api'

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
      key: 'acoes', header: '', align: 'right',
      cell: (v) => <RowActions onEdit={() => abrir(v)} onDelete={() => excluir.mutate(v.id)} confirmMsg="Excluir esta pós-venda?" />,
    },
  ]

  return (
    <>
      <ResourceList
        title="Pós-venda"
        subtitle="Pesquisa de satisfação (NPS) por contato"
        action={<Button onClick={() => abrir()}><Plus size={16} /> Novo registro</Button>}
        columns={columns} rows={data} loading={isLoading} rowKey={(v) => v.id}
        emptyIcon={<MessageSquareHeart />} emptyTitle="Nenhuma pós-venda"
        emptyDescription="Registre o primeiro contato de satisfação."
      />

      <FormDialog
        open={open} onOpenChange={setOpen}
        title={edit ? 'Editar pós-venda' : 'Nova pós-venda'}
        loading={salvar.isPending} onConfirm={onSalvar}
      >
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
      </FormDialog>
    </>
  )
}
