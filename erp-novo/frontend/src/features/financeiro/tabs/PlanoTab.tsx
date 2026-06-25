import { useState } from 'react'
import { Plus, Pencil, Trash2, Wallet } from 'lucide-react'
import {
  Button, Badge, DataTable, type Column, EmptyState, Field, Input,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem, FormDialog, ConfirmDialog, toast,
} from '@/components/ui'
import { usePlanosConta, useSalvarPlanoConta, useExcluirPlanoConta, type PlanoConta } from '../api'

export function PlanoTab() {
  const { data, isLoading } = usePlanosConta()
  const salvar = useSalvarPlanoConta(); const excluir = useExcluirPlanoConta()
  const [edit, setEdit] = useState<Partial<PlanoConta> | null>(null); const [del, setDel] = useState<PlanoConta | null>(null)

  async function onSalvar() {
    if (!edit?.descricao?.trim()) { toast.error('Informe a descrição.'); return }
    try { await salvar.mutateAsync({ id: edit.id, descricao: edit.descricao, codigo: edit.codigo, pagarreceber: edit.pagarreceber ?? 'R', nivel: edit.nivel ?? 1 }); toast.success('Plano salvo.'); setEdit(null) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') }
  }
  const columns: Column<PlanoConta>[] = [
    { key: 'codigo', header: 'Código', width: 'w-28', cell: (p) => <span className="tabular-nums text-muted-foreground">{p.codigo || '—'}</span> },
    { key: 'descricao', header: 'Descrição', cell: (p) => <span className="font-medium">{p.descricao}</span> },
    { key: 'pr', header: 'Tipo', cell: (p) => p.pagarreceber === 'P' ? <Badge variant="destructive">Pagar</Badge> : <Badge variant="success">Receber</Badge> },
    { key: 'acoes', header: '', align: 'right', width: 'w-24', cell: (p) => <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}><Button variant="ghost" size="icon" onClick={() => setEdit(p)}><Pencil size={16} /></Button><Button variant="ghost" size="icon" onClick={() => setDel(p)}><Trash2 size={16} /></Button></div> },
  ]
  return (
    <>
      <div className="mb-3 flex justify-end"><Button onClick={() => setEdit({ pagarreceber: 'R' })}><Plus size={16} /> Novo plano</Button></div>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(p) => p.id} onRowClick={(p) => setEdit(p)} empty={<EmptyState icon={<Wallet />} title="Nenhum plano de contas" />} />
      <FormDialog open={!!edit} onOpenChange={(o) => !o && setEdit(null)} title={edit?.id ? 'Editar plano' : 'Novo plano'} loading={salvar.isPending} onConfirm={onSalvar}>
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <Field label="Código"><Input value={edit?.codigo ?? ''} onChange={(e) => setEdit((s) => ({ ...s, codigo: e.target.value }))} /></Field>
          <Field label="Descrição" required className="col-span-2"><Input autoFocus value={edit?.descricao ?? ''} onChange={(e) => setEdit((s) => ({ ...s, descricao: e.target.value }))} /></Field>
        </div>
        <Field label="Tipo">
          <Select value={edit?.pagarreceber ?? 'R'} onValueChange={(v) => setEdit((s) => ({ ...s, pagarreceber: v }))}>
            <SelectTrigger><SelectValue /></SelectTrigger>
            <SelectContent><SelectItem value="R">Receber</SelectItem><SelectItem value="P">Pagar</SelectItem></SelectContent>
          </Select>
        </Field>
      </FormDialog>
      <ConfirmDialog open={!!del} onOpenChange={(o) => !o && setDel(null)} title="Excluir plano"
        description={<>Excluir <strong>{del?.descricao}</strong>?</>} loading={excluir.isPending}
        onConfirm={async () => { try { await excluir.mutateAsync(del!.id); toast.success('Excluído.') } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') } finally { setDel(null) } }} />
    </>
  )
}
