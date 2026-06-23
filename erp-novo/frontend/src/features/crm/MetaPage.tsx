import { useState } from 'react'
import { Plus, Target, Pencil, Trash2 } from 'lucide-react'
import {
  Button, Card, PageHeader, Input, DataTable, type Column, EmptyState, Field, AsyncSelect,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogClose, toast,
} from '@/components/ui'
import { useMetas, useSalvarMeta, useExcluirMeta, type Meta } from './api'

const brl = (v: string | number) => Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })

export function MetaPage() {
  const [competencia, setCompetencia] = useState('')
  const { data, isLoading } = useMetas(competencia || undefined)
  const salvar = useSalvarMeta()
  const excluir = useExcluirMeta()
  const [open, setOpen] = useState(false)
  const [edit, setEdit] = useState<Meta | null>(null)
  const [form, setForm] = useState<Record<string, any>>({})
  const [colabLabel, setColabLabel] = useState('')

  function abrir(reg?: Meta) {
    setEdit(reg ?? null)
    setForm(reg ? { ...reg } : { competencia: new Date().toISOString().slice(0, 7) })
    setColabLabel('')
    setOpen(true)
  }
  async function onSalvar() {
    try { await salvar.mutateAsync({ id: edit?.id ?? null, data: form }); toast.success('Meta salva.'); setOpen(false) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao salvar.') }
  }

  const columns: Column<Meta>[] = [
    { key: 'competencia', header: 'Competência', cell: (v) => <span className="font-medium tabular-nums">{v.competencia}</span> },
    { key: 'meta', header: 'Meta', cell: (v) => <span className="tabular-nums">{brl(v.meta_valor)}</span> },
    { key: 'realizado', header: 'Realizado', cell: (v) => <span className="tabular-nums">{brl(v.realizado_valor)}</span> },
    {
      key: 'perc', header: '% atingido', cell: (v) => {
        const m = Number(v.meta_valor) || 0; const r = Number(v.realizado_valor) || 0
        const p = m > 0 ? Math.round((r / m) * 100) : 0
        return <span className={p >= 100 ? 'text-emerald-600 font-medium' : ''}>{p}%</span>
      },
    },
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
      <PageHeader title="Metas de venda" subtitle="Meta por colaborador e competência"
        action={<Button onClick={() => abrir()}><Plus size={16} /> Nova meta</Button>} />
      <Card className="mb-4 p-3">
        <Field label="Filtrar por competência">
          <Input type="month" value={competencia} onChange={(e) => setCompetencia(e.target.value)} className="max-w-xs" />
        </Field>
      </Card>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(v) => v.id}
        empty={<EmptyState icon={<Target />} title="Nenhuma meta" />} />

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>{edit ? 'Editar meta' : 'Nova meta'}</DialogTitle></DialogHeader>
          <div className="space-y-4">
            <Field label="Colaborador">
              <AsyncSelect endpoint="/lookups/colaboradores" value={form.colaborador_id ?? null} valueLabel={colabLabel}
                onChange={(id, opt) => { setForm((f) => ({ ...f, colaborador_id: id })); setColabLabel(opt?.label ?? '') }} />
            </Field>
            <div className="grid grid-cols-2 gap-3">
              <Field label="Competência" required><Input type="month" value={form.competencia ?? ''} onChange={(e) => setForm((f) => ({ ...f, competencia: e.target.value }))} /></Field>
              <Field label="Meta (R$)" required><Input type="number" step="0.01" min={0} value={form.meta_valor ?? ''} onChange={(e) => setForm((f) => ({ ...f, meta_valor: e.target.value }))} /></Field>
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
