import { useState } from 'react'
import { Plus, FolderArchive, Pencil, Trash2, AlertTriangle } from 'lucide-react'
import {
  Button, PageHeader, Input, DataTable, type Column, EmptyState, Field,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogClose, toast,
} from '@/components/ui'
import { useDocumentos, useSalvarDocumento, useExcluirDocumento, type Documento } from './api'

const fmtData = (s: string | null) => (s ? new Date(s).toLocaleDateString('pt-BR') : '—')
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
        ? <span className={vencido(v.validade) ? 'text-destructive flex items-center gap-1' : ''}>{vencido(v.validade) && <AlertTriangle size={14} />}{fmtData(v.validade)}</span>
        : '—',
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
      <PageHeader title="Documentos" subtitle="Gestão documental (alvarás, licenças, contratos)"
        action={<Button onClick={() => abrir()}><Plus size={16} /> Novo documento</Button>} />
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(v) => v.id}
        empty={<EmptyState icon={<FolderArchive />} title="Nenhum documento" />} />

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>{edit ? 'Editar documento' : 'Novo documento'}</DialogTitle></DialogHeader>
          <div className="space-y-4">
            <Field label="Descrição" required><Input value={form.descricao ?? ''} onChange={(e) => setForm((f) => ({ ...f, descricao: e.target.value }))} /></Field>
            <div className="grid grid-cols-2 gap-3">
              <Field label="Tipo"><Input value={form.tipo ?? ''} onChange={(e) => setForm((f) => ({ ...f, tipo: e.target.value }))} placeholder="alvará, licença…" /></Field>
              <Field label="Número"><Input value={form.numero ?? ''} onChange={(e) => setForm((f) => ({ ...f, numero: e.target.value }))} /></Field>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <Field label="Emissão"><Input type="date" value={form.emissao ?? ''} onChange={(e) => setForm((f) => ({ ...f, emissao: e.target.value }))} /></Field>
              <Field label="Validade"><Input type="date" value={form.validade ?? ''} onChange={(e) => setForm((f) => ({ ...f, validade: e.target.value }))} /></Field>
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
