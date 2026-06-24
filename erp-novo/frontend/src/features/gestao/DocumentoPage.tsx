import { useState } from 'react'
import { Plus, FolderArchive, AlertTriangle } from 'lucide-react'
import {
  Button, Input, type Column, Field,
  ResourceList, FormDialog, RowActions, toast,
} from '@/components/ui'
import { data as fmtData } from '@/lib/format'
import { useDocumentos, useSalvarDocumento, useExcluirDocumento, type Documento } from './api'

const vencido = (s: string | null) => !!s && new Date(s) < new Date()

export function DocumentoPage() {
  const { data, isLoading } = useDocumentos()
  const salvar = useSalvarDocumento()
  const excluir = useExcluirDocumento()
  const [open, setOpen] = useState(false)
  const [edit, setEdit] = useState<Documento | null>(null)
  const [form, setForm] = useState<Record<string, any>>({})

  function abrir(reg?: Documento) { setEdit(reg ?? null); setForm(reg ? { ...reg } : {}); setOpen(true) }
  async function onSalvar() {
    try { await salvar.mutateAsync({ id: edit?.id ?? null, data: form }); toast.success('Documento salvo.'); setOpen(false) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao salvar.') }
  }

  const columns: Column<Documento>[] = [
    { key: 'descricao', header: 'Descrição', cell: (v) => <span className="font-medium">{v.descricao}</span> },
    { key: 'tipo', header: 'Tipo', cell: (v) => v.tipo || '—' },
    { key: 'numero', header: 'Número', cell: (v) => v.numero || '—' },
    { key: 'emissao', header: 'Emissão', cell: (v) => fmtData(v.emissao) },
    {
      key: 'validade', header: 'Validade', cell: (v) => v.validade
        ? <span className={vencido(v.validade) ? 'flex items-center gap-1 text-destructive' : ''}>{vencido(v.validade) && <AlertTriangle size={14} />}{fmtData(v.validade)}</span>
        : '—',
    },
    {
      key: 'acoes', header: '', align: 'right',
      cell: (v) => <RowActions onEdit={() => abrir(v)} onDelete={() => excluir.mutate(v.id)} confirmMsg="Excluir este documento?" />,
    },
  ]

  return (
    <>
      <ResourceList
        title="Documentos"
        subtitle="Gestão documental (alvarás, licenças, contratos)"
        action={<Button onClick={() => abrir()}><Plus size={16} /> Novo documento</Button>}
        columns={columns} rows={data} loading={isLoading} rowKey={(v) => v.id}
        emptyIcon={<FolderArchive />} emptyTitle="Nenhum documento"
      />

      <FormDialog
        open={open} onOpenChange={setOpen}
        title={edit ? 'Editar documento' : 'Novo documento'}
        loading={salvar.isPending} onConfirm={onSalvar}
      >
        <Field label="Descrição" required><Input value={form.descricao ?? ''} onChange={(e) => setForm((f) => ({ ...f, descricao: e.target.value }))} /></Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Tipo"><Input value={form.tipo ?? ''} onChange={(e) => setForm((f) => ({ ...f, tipo: e.target.value }))} placeholder="alvará, licença…" /></Field>
          <Field label="Número"><Input value={form.numero ?? ''} onChange={(e) => setForm((f) => ({ ...f, numero: e.target.value }))} /></Field>
        </div>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Emissão"><Input type="date" value={form.emissao ?? ''} onChange={(e) => setForm((f) => ({ ...f, emissao: e.target.value }))} /></Field>
          <Field label="Validade"><Input type="date" value={form.validade ?? ''} onChange={(e) => setForm((f) => ({ ...f, validade: e.target.value }))} /></Field>
        </div>
      </FormDialog>
    </>
  )
}
