import { useState } from 'react'
import { Plus, Building, Pencil, Trash2 } from 'lucide-react'
import {
  Button, PageHeader, Input, DataTable, type Column, EmptyState, Field, CheckboxField,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogClose, toast,
} from '@/components/ui'
import { useBens, useSalvarBem, useExcluirBem, type Bem } from './api'

const brl = (v: string | number) => Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
const fmtData = (s: string | null) => (s ? new Date(s).toLocaleDateString('pt-BR') : '—')

export function BemPage() {
  const { data, isLoading } = useBens()
  const salvar = useSalvarBem()
  const excluir = useExcluirBem()
  const [open, setOpen] = useState(false)
  const [edit, setEdit] = useState<Bem | null>(null)
  const [form, setForm] = useState<Record<string, any>>({})

  function abrir(reg?: Bem) {
    setEdit(reg ?? null)
    setForm(reg ? { ...reg } : { ativo: true, taxa_depreciacao_anual: 10, valor_residual: 0 })
    setOpen(true)
  }
  async function onSalvar() {
    try { await salvar.mutateAsync({ id: edit?.id ?? null, data: form }); toast.success('Bem salvo.'); setOpen(false) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao salvar.') }
  }

  const columns: Column<Bem>[] = [
    { key: 'descricao', header: 'Descrição', cell: (v) => <span className="font-medium">{v.descricao}</span> },
    { key: 'aq', header: 'Aquisição', cell: (v) => fmtData(v.data_aquisicao) },
    { key: 'valor', header: 'Valor aquis.', cell: (v) => <span className="tabular-nums">{brl(v.valor_aquisicao)}</span> },
    { key: 'taxa', header: 'Taxa a.a.', cell: (v) => `${v.taxa_depreciacao_anual}%` },
    { key: 'acum', header: 'Depreciação acum.', cell: (v) => <span className="tabular-nums">{brl(v.depreciacao.depreciacao_acumulada)}</span> },
    { key: 'contabil', header: 'Valor contábil', cell: (v) => <span className="tabular-nums font-medium">{brl(v.depreciacao.valor_contabil)}</span> },
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
      <PageHeader title="Bens (imobilizado)" subtitle="Ativo imobilizado com depreciação linear"
        action={<Button onClick={() => abrir()}><Plus size={16} /> Novo bem</Button>} />
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(v) => v.id}
        empty={<EmptyState icon={<Building />} title="Nenhum bem cadastrado" />} />

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>{edit ? 'Editar bem' : 'Novo bem'}</DialogTitle></DialogHeader>
          <div className="space-y-4">
            <Field label="Descrição" required><Input value={form.descricao ?? ''} onChange={(e) => setForm((f) => ({ ...f, descricao: e.target.value }))} /></Field>
            <div className="grid grid-cols-2 gap-3">
              <Field label="Data de aquisição"><Input type="date" value={form.data_aquisicao ?? ''} onChange={(e) => setForm((f) => ({ ...f, data_aquisicao: e.target.value }))} /></Field>
              <Field label="Valor de aquisição (R$)" required><Input type="number" step="0.01" min={0} value={form.valor_aquisicao ?? ''} onChange={(e) => setForm((f) => ({ ...f, valor_aquisicao: e.target.value }))} /></Field>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <Field label="Taxa depreciação anual (%)"><Input type="number" step="0.01" min={0} max={100} value={form.taxa_depreciacao_anual ?? ''} onChange={(e) => setForm((f) => ({ ...f, taxa_depreciacao_anual: e.target.value }))} /></Field>
              <Field label="Valor residual (R$)"><Input type="number" step="0.01" min={0} value={form.valor_residual ?? ''} onChange={(e) => setForm((f) => ({ ...f, valor_residual: e.target.value }))} /></Field>
            </div>
            <CheckboxField label="Bem ativo" checked={!!form.ativo} onChange={(c) => setForm((f) => ({ ...f, ativo: c }))} />
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
