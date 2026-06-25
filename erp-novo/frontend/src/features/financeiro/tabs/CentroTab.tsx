import { useState } from 'react'
import { Plus, Pencil, Trash2, Wallet } from 'lucide-react'
import {
  Button, Badge, DataTable, type Column, EmptyState, Field, Input, FormDialog, ConfirmDialog, toast,
} from '@/components/ui'
import { useCentrosCusto, useSalvarCentroCusto, useExcluirCentroCusto, type CentroCusto } from '../api'

export function CentroTab() {
  const { data, isLoading } = useCentrosCusto()
  const salvar = useSalvarCentroCusto(); const excluir = useExcluirCentroCusto()
  const [edit, setEdit] = useState<Partial<CentroCusto> | null>(null); const [del, setDel] = useState<CentroCusto | null>(null)

  async function onSalvar() {
    if (!edit?.descricao?.trim()) { toast.error('Informe a descrição.'); return }
    try { await salvar.mutateAsync({ id: edit.id, descricao: edit.descricao, codigo: edit.codigo, nivel: edit.nivel ?? 1, ativo: edit.ativo !== 0 }); toast.success('Centro salvo.'); setEdit(null) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') }
  }
  const columns: Column<CentroCusto>[] = [
    { key: 'codigo', header: 'Código', width: 'w-28', cell: (c) => <span className="tabular-nums text-muted-foreground">{c.codigo || '—'}</span> },
    { key: 'descricao', header: 'Descrição', cell: (c) => <span className="font-medium">{c.descricao}</span> },
    { key: 'ativo', header: 'Status', cell: (c) => c.ativo ? <Badge variant="success">Ativo</Badge> : <Badge variant="secondary">Inativo</Badge> },
    { key: 'acoes', header: '', align: 'right', width: 'w-24', cell: (c) => <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}><Button variant="ghost" size="icon" onClick={() => setEdit(c)}><Pencil size={16} /></Button><Button variant="ghost" size="icon" onClick={() => setDel(c)}><Trash2 size={16} /></Button></div> },
  ]
  return (
    <>
      <div className="mb-3 flex justify-end"><Button onClick={() => setEdit({ ativo: 1 })}><Plus size={16} /> Novo centro</Button></div>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(c) => c.id} onRowClick={(c) => setEdit(c)} empty={<EmptyState icon={<Wallet />} title="Nenhum centro de custo" />} />
      <FormDialog open={!!edit} onOpenChange={(o) => !o && setEdit(null)} title={edit?.id ? 'Editar centro' : 'Novo centro'} loading={salvar.isPending} onConfirm={onSalvar}>
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <Field label="Código"><Input value={edit?.codigo ?? ''} onChange={(e) => setEdit((s) => ({ ...s, codigo: e.target.value }))} /></Field>
          <Field label="Descrição" required className="col-span-2"><Input autoFocus value={edit?.descricao ?? ''} onChange={(e) => setEdit((s) => ({ ...s, descricao: e.target.value }))} /></Field>
        </div>
      </FormDialog>
      <ConfirmDialog open={!!del} onOpenChange={(o) => !o && setDel(null)} title="Excluir centro"
        description={<>Excluir <strong>{del?.descricao}</strong>?</>} loading={excluir.isPending}
        onConfirm={async () => { try { await excluir.mutateAsync(del!.id); toast.success('Excluído.') } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') } finally { setDel(null) } }} />
    </>
  )
}
