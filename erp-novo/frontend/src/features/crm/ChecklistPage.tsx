import { useState } from 'react'
import { Plus, ListChecks, X } from 'lucide-react'
import {
  Button, Input, Badge, type Column, Field, CheckboxField,
  ResourceList, FormDialog, RowActions, toast,
} from '@/components/ui'
import { useChecklists, useSalvarChecklist, useExcluirChecklist, type Checklist } from './api'

export function ChecklistPage() {
  const { data, isLoading } = useChecklists()
  const salvar = useSalvarChecklist()
  const excluir = useExcluirChecklist()
  const [open, setOpen] = useState(false)
  const [edit, setEdit] = useState<Checklist | null>(null)
  const [descricao, setDescricao] = useState('')
  const [ativo, setAtivo] = useState(true)
  const [perguntas, setPerguntas] = useState<string[]>([''])

  function abrir(reg?: Checklist) {
    setEdit(reg ?? null)
    setDescricao(reg?.descricao ?? '')
    setAtivo(reg?.ativo ?? true)
    setPerguntas(reg?.perguntas.length ? reg.perguntas.map((p) => p.pergunta) : [''])
    setOpen(true)
  }

  async function onSalvar() {
    const limpas = perguntas.map((p) => p.trim()).filter(Boolean)
    try {
      await salvar.mutateAsync({ id: edit?.id ?? null, data: { descricao, ativo, perguntas: limpas } })
      toast.success('Checklist salvo.'); setOpen(false)
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao salvar.') }
  }

  const columns: Column<Checklist>[] = [
    { key: 'descricao', header: 'Descrição', cell: (v) => <span className="font-medium">{v.descricao}</span> },
    { key: 'perguntas', header: 'Itens', align: 'right', cell: (v) => <span className="tabular-nums">{v.perguntas.length}</span> },
    { key: 'ativo', header: 'Ativo', cell: (v) => v.ativo ? <Badge variant="success">Ativo</Badge> : <Badge variant="secondary">Inativo</Badge> },
    {
      key: 'acoes', header: '', align: 'right',
      cell: (v) => <RowActions onEdit={() => abrir(v)} onDelete={() => excluir.mutate(v.id)} confirmMsg="Excluir este checklist?" />,
    },
  ]

  return (
    <>
      <ResourceList
        title="Checklists"
        subtitle="Modelos de verificação (abertura, conferência…)"
        action={<Button onClick={() => abrir()}><Plus size={16} /> Novo checklist</Button>}
        columns={columns} rows={data} loading={isLoading} rowKey={(v) => v.id}
        emptyIcon={<ListChecks />} emptyTitle="Nenhum checklist"
      />

      <FormDialog
        open={open} onOpenChange={setOpen}
        title={edit ? 'Editar checklist' : 'Novo checklist'}
        loading={salvar.isPending} onConfirm={onSalvar}
      >
        <Field label="Descrição" required><Input value={descricao} onChange={(e) => setDescricao(e.target.value)} /></Field>
        <div>
          <p className="mb-2 text-sm font-medium">Perguntas</p>
          <div className="space-y-2">
            {perguntas.map((p, i) => (
              <div key={i} className="flex gap-2">
                <Input value={p} onChange={(e) => setPerguntas((arr) => arr.map((x, j) => j === i ? e.target.value : x))} placeholder={`Pergunta ${i + 1}`} />
                <Button variant="ghost" size="icon" onClick={() => setPerguntas((arr) => arr.filter((_, j) => j !== i))}><X size={15} /></Button>
              </div>
            ))}
          </div>
          <Button variant="outline" size="sm" className="mt-2" onClick={() => setPerguntas((arr) => [...arr, ''])}><Plus size={14} /> Adicionar pergunta</Button>
        </div>
        <CheckboxField label="Checklist ativo" checked={ativo} onChange={setAtivo} />
      </FormDialog>
    </>
  )
}
