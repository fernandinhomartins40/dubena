import { useState } from 'react'
import { Plus, Tag } from 'lucide-react'
import {
  Button, Input, Badge, type Column, Field, CheckboxField,
  ResourceList, FormDialog, RowActions, toast,
} from '@/components/ui'
import { data as fmtData } from '@/lib/format'
import { usePromocoes, useSalvarPromocao, useExcluirPromocao, type Promocao } from './api'

/**
 * Página de Promoções — REFERÊNCIA do padrão de CRUD:
 * ResourceList (lista) + FormDialog (criar/editar) + RowActions (ações de linha)
 * + helpers de format. Copie esta estrutura para novas páginas.
 */
export function PromocaoPage() {
  const { data, isLoading } = usePromocoes()
  const salvar = useSalvarPromocao()
  const excluir = useExcluirPromocao()
  const [open, setOpen] = useState(false)
  const [edit, setEdit] = useState<Promocao | null>(null)
  const [form, setForm] = useState<Record<string, any>>({})

  function abrir(reg?: Promocao) {
    setEdit(reg ?? null)
    setForm(reg ? { ...reg } : { ativo: true })
    setOpen(true)
  }

  async function onSalvar() {
    try {
      await salvar.mutateAsync({ id: edit?.id ?? null, data: form })
      toast.success('Promoção salva.'); setOpen(false)
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao salvar.') }
  }

  const columns: Column<Promocao>[] = [
    { key: 'descricao', header: 'Descrição', cell: (v) => <span className="font-medium">{v.descricao}</span> },
    { key: 'inicio', header: 'Início', cell: (v) => fmtData(v.inicio) },
    { key: 'fim', header: 'Fim', cell: (v) => fmtData(v.fim) },
    { key: 'desc', header: 'Desconto', cell: (v) => v.desconto_percentual ? `${v.desconto_percentual}%` : '—' },
    { key: 'ativo', header: 'Ativo', cell: (v) => v.ativo ? <Badge variant="success">Ativa</Badge> : <Badge variant="secondary">Inativa</Badge> },
    {
      key: 'acoes', header: '', align: 'right',
      cell: (v) => <RowActions onEdit={() => abrir(v)} onDelete={() => excluir.mutate(v.id)} confirmMsg="Excluir esta promoção?" />,
    },
  ]

  return (
    <>
      <ResourceList
        title="Promoções"
        subtitle="Campanhas com vigência e desconto"
        action={<Button onClick={() => abrir()}><Plus size={16} /> Nova promoção</Button>}
        columns={columns}
        rows={data}
        loading={isLoading}
        rowKey={(v) => v.id}
        emptyIcon={<Tag />}
        emptyTitle="Nenhuma promoção"
        emptyDescription="Crie a primeira campanha com período e desconto."
      />

      <FormDialog
        open={open} onOpenChange={setOpen}
        title={edit ? 'Editar promoção' : 'Nova promoção'}
        loading={salvar.isPending} onConfirm={onSalvar}
      >
        <Field label="Descrição" required><Input value={form.descricao ?? ''} onChange={(e) => setForm((f) => ({ ...f, descricao: e.target.value }))} /></Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Início" required><Input type="date" value={form.inicio ?? ''} onChange={(e) => setForm((f) => ({ ...f, inicio: e.target.value }))} /></Field>
          <Field label="Fim" required><Input type="date" value={form.fim ?? ''} onChange={(e) => setForm((f) => ({ ...f, fim: e.target.value }))} /></Field>
        </div>
        <Field label="Desconto (%)"><Input type="number" min={0} max={100} step="0.01" value={form.desconto_percentual ?? ''} onChange={(e) => setForm((f) => ({ ...f, desconto_percentual: e.target.value === '' ? null : Number(e.target.value) }))} /></Field>
        <CheckboxField label="Promoção ativa" checked={!!form.ativo} onChange={(c) => setForm((f) => ({ ...f, ativo: c }))} />
      </FormDialog>
    </>
  )
}
