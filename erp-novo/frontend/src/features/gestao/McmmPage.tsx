import { useState } from 'react'
import { Plus, ArrowLeftRight, Pencil, Trash2 } from 'lucide-react'
import {
  Button, PageHeader, Input, Badge, DataTable, type Column, EmptyState, Field, AsyncSelect,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogClose, toast,
} from '@/components/ui'
import { useMcmms, useSalvarMcmm, useExcluirMcmm, type Mcmm } from './api'

const fmtData = (s: string | null) => (s ? new Date(s).toLocaleDateString('pt-BR') : '—')

export function McmmPage() {
  const { data, isLoading } = useMcmms()
  const salvar = useSalvarMcmm()
  const excluir = useExcluirMcmm()
  const [open, setOpen] = useState(false)
  const [edit, setEdit] = useState<Mcmm | null>(null)
  const [form, setForm] = useState<Record<string, any>>({})
  const [prodLabel, setProdLabel] = useState('')

  function abrir(reg?: Mcmm) {
    setEdit(reg ?? null)
    setForm(reg ? { ...reg } : { tipo: 'entrada', data: new Date().toISOString().slice(0, 10) })
    setProdLabel(''); setOpen(true)
  }
  async function onSalvar() {
    try { await salvar.mutateAsync({ id: edit?.id ?? null, data: form }); toast.success('Movimentação salva.'); setOpen(false) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao salvar.') }
  }

  const columns: Column<Mcmm>[] = [
    { key: 'data', header: 'Data', cell: (v) => fmtData(v.data) },
    { key: 'descricao', header: 'Descrição', cell: (v) => <span className="font-medium">{v.descricao}</span> },
    { key: 'tipo', header: 'Tipo', cell: (v) => v.tipo === 'entrada' ? <Badge variant="success">Entrada</Badge> : <Badge variant="secondary">Saída</Badge> },
    { key: 'qtd', header: 'Quantidade', cell: (v) => <span className="tabular-nums">{v.quantidade}</span> },
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
      <PageHeader title="MCMM" subtitle="Controle de movimentação de material"
        action={<Button onClick={() => abrir()}><Plus size={16} /> Nova movimentação</Button>} />
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(v) => v.id}
        empty={<EmptyState icon={<ArrowLeftRight />} title="Nenhuma movimentação" />} />

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>{edit ? 'Editar movimentação' : 'Nova movimentação'}</DialogTitle></DialogHeader>
          <div className="space-y-4">
            <Field label="Descrição" required><Input value={form.descricao ?? ''} onChange={(e) => setForm((f) => ({ ...f, descricao: e.target.value }))} /></Field>
            <div className="grid grid-cols-2 gap-3">
              <Field label="Tipo" required>
                <Select value={form.tipo ?? 'entrada'} onValueChange={(v) => setForm((f) => ({ ...f, tipo: v }))}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent><SelectItem value="entrada">Entrada</SelectItem><SelectItem value="saida">Saída</SelectItem></SelectContent>
                </Select>
              </Field>
              <Field label="Quantidade" required><Input type="number" step="0.0001" value={form.quantidade ?? ''} onChange={(e) => setForm((f) => ({ ...f, quantidade: e.target.value }))} /></Field>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <Field label="Data"><Input type="date" value={form.data ?? ''} onChange={(e) => setForm((f) => ({ ...f, data: e.target.value }))} /></Field>
              <Field label="Produto">
                <AsyncSelect endpoint="/lookups/produtos" value={form.produto_id ?? null} valueLabel={prodLabel}
                  onChange={(id, opt) => { setForm((f) => ({ ...f, produto_id: id })); setProdLabel(opt?.label ?? '') }} />
              </Field>
            </div>
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
