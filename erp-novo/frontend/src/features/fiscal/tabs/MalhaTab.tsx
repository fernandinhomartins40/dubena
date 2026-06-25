import { useState } from 'react'
import { Plus, Pencil, Trash2, FileText } from 'lucide-react'
import {
  Button, Badge, DataTable, type Column, EmptyState, Field, Input, CheckboxField,
  Tabs, TabsList, TabsTrigger, TabsContent, FormDialog, ConfirmDialog, toast,
} from '@/components/ui'
import {
  useMalha, useSalvarMalha, useExcluirMalha, type MalhaRow,
  useOperacoes, useSalvarOperacao, useExcluirOperacao, type OperacaoRow,
} from '../api'

export function MalhaTab() {
  return (
    <Tabs defaultValue="grupos-fiscais">
      <TabsList className="overflow-x-auto">
        <TabsTrigger value="grupos-fiscais">Grupos</TabsTrigger>
        <TabsTrigger value="operacoes">Operações</TabsTrigger>
        <TabsTrigger value="cst-icms">CST ICMS</TabsTrigger>
        <TabsTrigger value="cst-ipi">CST IPI</TabsTrigger>
        <TabsTrigger value="cst-pis">CST PIS</TabsTrigger>
        <TabsTrigger value="cst-cofins">CST COFINS</TabsTrigger>
        <TabsTrigger value="cst">CST</TabsTrigger>
      </TabsList>
      <TabsContent value="grupos-fiscais"><MalhaCadastro tipo="grupos-fiscais" titulo="Grupo Fiscal" comCodigo={false} /></TabsContent>
      <TabsContent value="operacoes"><OperacoesTab /></TabsContent>
      <TabsContent value="cst-icms"><MalhaCadastro tipo="cst-icms" titulo="CST ICMS" /></TabsContent>
      <TabsContent value="cst-ipi"><MalhaCadastro tipo="cst-ipi" titulo="CST IPI" /></TabsContent>
      <TabsContent value="cst-pis"><MalhaCadastro tipo="cst-pis" titulo="CST PIS" /></TabsContent>
      <TabsContent value="cst-cofins"><MalhaCadastro tipo="cst-cofins" titulo="CST COFINS" /></TabsContent>
      <TabsContent value="cst"><MalhaCadastro tipo="cst" titulo="CST" /></TabsContent>
    </Tabs>
  )
}

function MalhaCadastro({ tipo, titulo, comCodigo = true }: { tipo: string; titulo: string; comCodigo?: boolean }) {
  const { data, isLoading } = useMalha(tipo)
  const salvar = useSalvarMalha(tipo); const excluir = useExcluirMalha(tipo)
  const [edit, setEdit] = useState<Partial<MalhaRow> | null>(null); const [del, setDel] = useState<MalhaRow | null>(null)

  async function onSalvar() {
    if (!edit?.descricao?.trim()) { toast.error('Informe a descrição.'); return }
    try { await salvar.mutateAsync({ id: edit.id, descricao: edit.descricao, codigo: edit.codigo }); toast.success(`${titulo} salvo.`); setEdit(null) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') }
  }
  const columns: Column<MalhaRow>[] = [
    ...(comCodigo ? [{ key: 'codigo', header: 'Código', width: 'w-24', cell: (r: MalhaRow) => <span className="tabular-nums text-muted-foreground">{r.codigo || '—'}</span> }] : []),
    { key: 'descricao', header: 'Descrição', cell: (r) => <span className="font-medium">{r.descricao}</span> },
    { key: 'acoes', header: '', align: 'right', width: 'w-24', cell: (r) => <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}><Button variant="ghost" size="icon" onClick={() => setEdit(r)}><Pencil size={16} /></Button><Button variant="ghost" size="icon" onClick={() => setDel(r)}><Trash2 size={16} /></Button></div> },
  ]
  return (
    <>
      <div className="mb-3 flex justify-end"><Button onClick={() => setEdit({})}><Plus size={16} /> Novo</Button></div>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(r) => r.id} onRowClick={(r) => setEdit(r)} empty={<EmptyState icon={<FileText />} title={`Nenhum registro em ${titulo}`} />} />
      <FormDialog open={!!edit} onOpenChange={(o) => !o && setEdit(null)} title={edit?.id ? `Editar ${titulo}` : `Novo ${titulo}`} loading={salvar.isPending} onConfirm={onSalvar}>
        {comCodigo && <Field label="Código"><Input value={edit?.codigo ?? ''} onChange={(e) => setEdit((s) => ({ ...s, codigo: e.target.value }))} /></Field>}
        <Field label="Descrição" required><Input autoFocus value={edit?.descricao ?? ''} onChange={(e) => setEdit((s) => ({ ...s, descricao: e.target.value }))} /></Field>
      </FormDialog>
      <ConfirmDialog open={!!del} onOpenChange={(o) => !o && setDel(null)} title="Excluir"
        description={<>Excluir <strong>{del?.descricao}</strong>?</>} loading={excluir.isPending}
        onConfirm={async () => { try { await excluir.mutateAsync(del!.id); toast.success('Excluído.') } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') } finally { setDel(null) } }} />
    </>
  )
}

function OperacoesTab() {
  const { data, isLoading } = useOperacoes()
  const salvar = useSalvarOperacao(); const excluir = useExcluirOperacao()
  const [edit, setEdit] = useState<Partial<OperacaoRow> | null>(null); const [del, setDel] = useState<OperacaoRow | null>(null)

  async function onSalvar() {
    if (!edit?.descricao?.trim()) { toast.error('Informe a descrição.'); return }
    try { await salvar.mutateAsync({ id: edit.id, descricao: edit.descricao, descricaofiscal: edit.descricaofiscal, cfop: edit.cfop, movimentaestoque: edit.movimentaestoque === 1, movimentafinanceiro: edit.movimentafinanceiro === 1 }); toast.success('Operação salva.'); setEdit(null) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') }
  }
  const columns: Column<OperacaoRow>[] = [
    { key: 'descricao', header: 'Descrição', cell: (o) => <span className="font-medium">{o.descricao}</span> },
    { key: 'cfop', header: 'CFOP', width: 'w-24', cell: (o) => <span className="tabular-nums text-muted-foreground">{o.cfop || '—'}</span> },
    { key: 'mov', header: 'Movimenta', cell: (o) => <div className="flex gap-1">{Number(o.movimentaestoque) ? <Badge variant="secondary">Estoque</Badge> : null}{Number(o.movimentafinanceiro) ? <Badge variant="secondary">Financeiro</Badge> : null}</div> },
    { key: 'acoes', header: '', align: 'right', width: 'w-24', cell: (o) => <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}><Button variant="ghost" size="icon" onClick={() => setEdit(o)}><Pencil size={16} /></Button><Button variant="ghost" size="icon" onClick={() => setDel(o)}><Trash2 size={16} /></Button></div> },
  ]
  return (
    <>
      <div className="mb-3 flex justify-end"><Button onClick={() => setEdit({})}><Plus size={16} /> Nova operação</Button></div>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(o) => o.id} onRowClick={(o) => setEdit(o)} empty={<EmptyState icon={<FileText />} title="Nenhuma operação fiscal" />} />
      <FormDialog open={!!edit} onOpenChange={(o) => !o && setEdit(null)} title={edit?.id ? 'Editar operação' : 'Nova operação'} loading={salvar.isPending} onConfirm={onSalvar}>
        <Field label="Descrição" required><Input autoFocus value={edit?.descricao ?? ''} onChange={(e) => setEdit((s) => ({ ...s, descricao: e.target.value }))} /></Field>
        <Field label="Descrição fiscal"><Input value={edit?.descricaofiscal ?? ''} onChange={(e) => setEdit((s) => ({ ...s, descricaofiscal: e.target.value }))} /></Field>
        <Field label="CFOP"><Input value={edit?.cfop ?? ''} onChange={(e) => setEdit((s) => ({ ...s, cfop: e.target.value }))} /></Field>
        <div className="flex gap-6">
          <CheckboxField label="Movimenta estoque" checked={edit?.movimentaestoque === 1} onChange={(b) => setEdit((s) => ({ ...s, movimentaestoque: b ? 1 : 0 }))} />
          <CheckboxField label="Movimenta financeiro" checked={edit?.movimentafinanceiro === 1} onChange={(b) => setEdit((s) => ({ ...s, movimentafinanceiro: b ? 1 : 0 }))} />
        </div>
      </FormDialog>
      <ConfirmDialog open={!!del} onOpenChange={(o) => !o && setDel(null)} title="Excluir operação"
        description={<>Excluir <strong>{del?.descricao}</strong>?</>} loading={excluir.isPending}
        onConfirm={async () => { try { await excluir.mutateAsync(del!.id); toast.success('Excluída.') } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') } finally { setDel(null) } }} />
    </>
  )
}
