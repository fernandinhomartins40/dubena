import { useState } from 'react'
import { Plus, Target } from 'lucide-react'
import {
  Button, Card, Input, type Column, Field, AsyncSelect,
  ResourceList, FormDialog, RowActions, toast,
} from '@/components/ui'
import { brl } from '@/lib/format'
import { useMetas, useSalvarMeta, useExcluirMeta, type Meta } from './api'

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
    { key: 'meta', header: 'Meta', align: 'right', cell: (v) => <span className="tabular-nums">{brl(v.meta_valor)}</span> },
    { key: 'realizado', header: 'Realizado', align: 'right', cell: (v) => <span className="tabular-nums">{brl(v.realizado_valor)}</span> },
    {
      key: 'perc', header: '% atingido', align: 'right', cell: (v) => {
        const m = Number(v.meta_valor) || 0; const r = Number(v.realizado_valor) || 0
        const p = m > 0 ? Math.round((r / m) * 100) : 0
        return <span className={p >= 100 ? 'font-medium text-success' : ''}>{p}%</span>
      },
    },
    {
      key: 'acoes', header: '', align: 'right',
      cell: (v) => <RowActions onEdit={() => abrir(v)} onDelete={() => excluir.mutate(v.id)} confirmMsg="Excluir esta meta?" />,
    },
  ]

  return (
    <>
      <ResourceList
        title="Metas de venda"
        subtitle="Meta por colaborador e competência"
        action={<Button onClick={() => abrir()}><Plus size={16} /> Nova meta</Button>}
        filtros={
          <Card className="mb-4 p-3">
            <Field label="Filtrar por competência">
              <Input type="month" value={competencia} onChange={(e) => setCompetencia(e.target.value)} className="max-w-xs" />
            </Field>
          </Card>
        }
        columns={columns} rows={data} loading={isLoading} rowKey={(v) => v.id}
        emptyIcon={<Target />} emptyTitle="Nenhuma meta"
      />

      <FormDialog
        open={open} onOpenChange={setOpen}
        title={edit ? 'Editar meta' : 'Nova meta'}
        loading={salvar.isPending} onConfirm={onSalvar}
      >
        <Field label="Colaborador">
          <AsyncSelect endpoint="/lookups/colaboradores" value={form.colaborador_id ?? null} valueLabel={colabLabel}
            onChange={(id, opt) => { setForm((f) => ({ ...f, colaborador_id: id })); setColabLabel(opt?.label ?? '') }} />
        </Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Competência" required><Input type="month" value={form.competencia ?? ''} onChange={(e) => setForm((f) => ({ ...f, competencia: e.target.value }))} /></Field>
          <Field label="Meta (R$)" required><Input type="number" step="0.01" min={0} value={form.meta_valor ?? ''} onChange={(e) => setForm((f) => ({ ...f, meta_valor: e.target.value }))} /></Field>
        </div>
      </FormDialog>
    </>
  )
}
