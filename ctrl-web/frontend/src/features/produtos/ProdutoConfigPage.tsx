import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ArrowLeft, Plus, Pencil, Trash2 } from 'lucide-react'
import {
  Button, PageHeader, Badge, DataTable, type Column, Field, Input, CheckboxField,
  Tabs, TabsList, TabsTrigger, TabsContent,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogClose, toast,
} from '@/components/ui'
import {
  useClasses, useUnidades, useSalvarClasse, useExcluirClasse, useSalvarUnidade, useExcluirUnidade,
  type ClasseItem, type UnidadeItem,
} from './api'

const TIPOS = [
  { v: 'P', l: 'Produto' }, { v: 'G', l: 'GLP' }, { v: 'V', l: 'Vasilhame' }, { v: 'R', l: 'Ressarcimento' },
]
const tipoLabel = (t: string | null) => TIPOS.find((x) => x.v === t)?.l ?? (t || '—')

export function ProdutoConfigPage() {
  const navigate = useNavigate()
  return (
    <div>
      <PageHeader
        breadcrumb={<button onClick={() => navigate('/produtos')} className="hover:text-foreground">Produtos</button>}
        title="Configurações de Produtos"
        subtitle="Classes e unidades de medida usadas no cadastro de produtos"
        action={<Button variant="outline" onClick={() => navigate('/produtos')}><ArrowLeft size={16} /> Voltar</Button>}
      />
      <Tabs defaultValue="classes">
        <TabsList><TabsTrigger value="classes">Classes</TabsTrigger><TabsTrigger value="unidades">Unidades de medida</TabsTrigger></TabsList>
        <TabsContent value="classes"><ClassesTab /></TabsContent>
        <TabsContent value="unidades"><UnidadesTab /></TabsContent>
      </Tabs>
    </div>
  )
}

function ClassesTab() {
  const { data, isLoading } = useClasses()
  const salvar = useSalvarClasse()
  const excluir = useExcluirClasse()
  const [edit, setEdit] = useState<Partial<ClasseItem> | null>(null)
  const [del, setDel] = useState<ClasseItem | null>(null)

  async function onSalvar() {
    if (!edit?.descricao?.trim()) { toast.error('Informe a descrição.'); return }
    try {
      await salvar.mutateAsync({ id: edit.id, descricao: edit.descricao!, tipo: edit.tipo ?? 'P', ativo: edit.ativo !== 0 })
      toast.success('Classe salva.'); setEdit(null)
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao salvar.') }
  }

  const columns: Column<ClasseItem>[] = [
    { key: 'descricao', header: 'Descrição', cell: (c) => <span className="font-medium">{c.descricao}</span> },
    { key: 'tipo', header: 'Tipo', cell: (c) => <Badge variant="secondary">{tipoLabel(c.tipo)}</Badge> },
    { key: 'ativo', header: 'Status', cell: (c) => c.ativo ? <Badge variant="success">Ativo</Badge> : <Badge variant="secondary">Inativo</Badge> },
    {
      key: 'acoes', header: '', align: 'right', width: 'w-24',
      cell: (c) => (
        <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}>
          <Button variant="ghost" size="icon" onClick={() => setEdit(c)}><Pencil size={16} /></Button>
          <Button variant="ghost" size="icon" onClick={() => setDel(c)}><Trash2 size={16} /></Button>
        </div>
      ),
    },
  ]

  return (
    <>
      <div className="mb-3 flex justify-end"><Button onClick={() => setEdit({ tipo: 'P', ativo: 1 })}><Plus size={16} /> Nova classe</Button></div>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(c) => c.id} onRowClick={(c) => setEdit(c)} />

      <Dialog open={!!edit} onOpenChange={(o) => !o && setEdit(null)}>
        <DialogContent>
          <DialogHeader><DialogTitle>{edit?.id ? 'Editar classe' : 'Nova classe'}</DialogTitle></DialogHeader>
          <div className="space-y-4">
            <Field label="Descrição" required><Input autoFocus value={edit?.descricao ?? ''} onChange={(e) => setEdit((s) => ({ ...s, descricao: e.target.value }))} /></Field>
            <Field label="Tipo">
              <Select value={edit?.tipo ?? 'P'} onValueChange={(v) => setEdit((s) => ({ ...s, tipo: v }))}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>{TIPOS.map((t) => <SelectItem key={t.v} value={t.v}>{t.l}</SelectItem>)}</SelectContent>
              </Select>
            </Field>
            <CheckboxField label="Ativo" checked={edit?.ativo !== 0} onChange={(b) => setEdit((s) => ({ ...s, ativo: b ? 1 : 0 }))} />
          </div>
          <DialogFooter>
            <DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose>
            <Button loading={salvar.isPending} onClick={onSalvar}>Salvar</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <ConfirmDelete item={del} onClose={() => setDel(null)} onConfirm={async () => {
        try { await excluir.mutateAsync(del!.id); toast.success('Classe excluída.') }
        catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao excluir.') }
        finally { setDel(null) }
      }} loading={excluir.isPending} nome={del?.descricao} tipo="classe" />
    </>
  )
}

function UnidadesTab() {
  const { data, isLoading } = useUnidades()
  const salvar = useSalvarUnidade()
  const excluir = useExcluirUnidade()
  const [edit, setEdit] = useState<Partial<UnidadeItem> | null>(null)
  const [del, setDel] = useState<UnidadeItem | null>(null)

  async function onSalvar() {
    if (!edit?.descricao?.trim()) { toast.error('Informe a descrição.'); return }
    try {
      await salvar.mutateAsync({ id: edit.id, descricao: edit.descricao!, sigla: edit.sigla ?? undefined, ativo: edit.ativo !== 0 })
      toast.success('Unidade salva.'); setEdit(null)
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao salvar.') }
  }

  const columns: Column<UnidadeItem>[] = [
    { key: 'descricao', header: 'Descrição', cell: (u) => <span className="font-medium">{u.descricao}</span> },
    { key: 'sigla', header: 'Sigla', cell: (u) => u.sigla || '—' },
    { key: 'ativo', header: 'Status', cell: (u) => u.ativo ? <Badge variant="success">Ativo</Badge> : <Badge variant="secondary">Inativo</Badge> },
    {
      key: 'acoes', header: '', align: 'right', width: 'w-24',
      cell: (u) => (
        <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}>
          <Button variant="ghost" size="icon" onClick={() => setEdit(u)}><Pencil size={16} /></Button>
          <Button variant="ghost" size="icon" onClick={() => setDel(u)}><Trash2 size={16} /></Button>
        </div>
      ),
    },
  ]

  return (
    <>
      <div className="mb-3 flex justify-end"><Button onClick={() => setEdit({ ativo: 1 })}><Plus size={16} /> Nova unidade</Button></div>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(u) => u.id} onRowClick={(u) => setEdit(u)} />

      <Dialog open={!!edit} onOpenChange={(o) => !o && setEdit(null)}>
        <DialogContent>
          <DialogHeader><DialogTitle>{edit?.id ? 'Editar unidade' : 'Nova unidade'}</DialogTitle></DialogHeader>
          <div className="space-y-4">
            <Field label="Descrição" required><Input autoFocus value={edit?.descricao ?? ''} onChange={(e) => setEdit((s) => ({ ...s, descricao: e.target.value }))} /></Field>
            <Field label="Sigla"><Input value={edit?.sigla ?? ''} maxLength={10} onChange={(e) => setEdit((s) => ({ ...s, sigla: e.target.value }))} /></Field>
            <CheckboxField label="Ativo" checked={edit?.ativo !== 0} onChange={(b) => setEdit((s) => ({ ...s, ativo: b ? 1 : 0 }))} />
          </div>
          <DialogFooter>
            <DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose>
            <Button loading={salvar.isPending} onClick={onSalvar}>Salvar</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <ConfirmDelete item={del} onClose={() => setDel(null)} onConfirm={async () => {
        try { await excluir.mutateAsync(del!.id); toast.success('Unidade excluída.') }
        catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao excluir.') }
        finally { setDel(null) }
      }} loading={excluir.isPending} nome={del?.descricao} tipo="unidade" />
    </>
  )
}

function ConfirmDelete({ item, onClose, onConfirm, loading, nome, tipo }: {
  item: unknown | null; onClose: () => void; onConfirm: () => void; loading: boolean; nome?: string; tipo: string
}) {
  return (
    <Dialog open={!!item} onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader><DialogTitle>Excluir {tipo}</DialogTitle></DialogHeader>
        <p className="text-sm text-muted-foreground">Excluir <strong>{nome}</strong>? Esta ação não pode ser desfeita.</p>
        <DialogFooter>
          <DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose>
          <Button variant="destructive" loading={loading} onClick={onConfirm}><Trash2 size={16} /> Excluir</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
