import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Plus, MoreHorizontal, Pencil, Trash2, Building2, CheckCircle2, Power } from 'lucide-react'
import {
  Button, PageHeader, Badge, DataTable, type Column, EmptyState, Field, Input, CheckboxField,
  Tabs, TabsList, TabsTrigger, TabsContent,
  DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter, DialogClose, toast,
} from '@/components/ui'
import { useAuth } from '@/lib/auth'
import {
  useEmpresas, useExcluirEmpresa, useAtivarEmpresa, type EmpresaListItem,
  useGrupos, useSalvarGrupo, useExcluirGrupo, type GrupoItem,
} from './api'

export function EmpresasListPage() {
  return (
    <div>
      <PageHeader title="Empresas" subtitle="Empresas do grupo, configurações e matriz/filiais" />
      <Tabs defaultValue="empresas">
        <TabsList><TabsTrigger value="empresas">Empresas</TabsTrigger><TabsTrigger value="grupos">Grupos</TabsTrigger></TabsList>
        <TabsContent value="empresas"><EmpresasTab /></TabsContent>
        <TabsContent value="grupos"><GruposTab /></TabsContent>
      </Tabs>
    </div>
  )
}

function EmpresasTab() {
  const navigate = useNavigate()
  const { can, refresh } = useAuth()
  const { data, isLoading } = useEmpresas()
  const excluir = useExcluirEmpresa()
  const ativar = useAtivarEmpresa()
  const [del, setDel] = useState<EmpresaListItem | null>(null)

  async function onAtivar(e: EmpresaListItem) {
    try { await ativar.mutateAsync(e.id); await refresh?.(); toast.success(`Empresa ativa: ${e.nome_informal || e.razao_social}`) }
    catch { toast.error('Não foi possível ativar a empresa.') }
  }

  const columns: Column<EmpresaListItem>[] = [
    {
      key: 'nome', header: 'Empresa',
      cell: (e) => (
        <div className="flex items-center gap-3">
          <div className="grid size-9 place-items-center rounded-md bg-secondary text-muted-foreground shrink-0"><Building2 size={16} /></div>
          <div className="min-w-0">
            <div className="font-medium truncate flex items-center gap-2">
              {e.nome_informal || e.razao_social}
              {e.ativa && <Badge variant="success"><CheckCircle2 size={12} className="mr-1" /> ativa</Badge>}
              {!!Number(e.matriz) && <Badge variant="warning">matriz</Badge>}
              {!Number(e.ativo) && <Badge variant="secondary">inativa</Badge>}
            </div>
            <div className="text-xs text-muted-foreground truncate">{e.razao_social}</div>
          </div>
        </div>
      ),
    },
    { key: 'cnpj', header: 'CNPJ', cell: (e) => <span className="text-muted-foreground tabular-nums">{e.cnpj || '—'}</span> },
    { key: 'uf', header: 'UF', align: 'center', width: 'w-16', cell: (e) => e.uf || '—' },
    {
      key: 'acoes', header: '', align: 'right', width: 'w-16',
      cell: (e) => (
        <div onClick={(ev) => ev.stopPropagation()} className="flex justify-end">
          <DropdownMenu>
            <DropdownMenuTrigger asChild><Button variant="ghost" size="icon"><MoreHorizontal size={18} /></Button></DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              {!e.ativa && <DropdownMenuItem onClick={() => onAtivar(e)}><Power /> Tornar ativa</DropdownMenuItem>}
              {can('empresa.edit') && <DropdownMenuItem onClick={() => navigate(`/empresas/${e.id}`)}><Pencil /> Editar</DropdownMenuItem>}
              {can('empresa.delete') && (<><DropdownMenuSeparator /><DropdownMenuItem destructive onClick={() => setDel(e)}><Trash2 /> Excluir</DropdownMenuItem></>)}
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      ),
    },
  ]

  return (
    <>
      <div className="mb-3 flex justify-end">
        {can('empresa.create') && <Button onClick={() => navigate('/empresas/novo')}><Plus size={16} /> Nova empresa</Button>}
      </div>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(e) => e.id}
        onRowClick={can('empresa.edit') ? (e) => navigate(`/empresas/${e.id}`) : undefined}
        empty={<EmptyState icon={<Building2 />} title="Nenhuma empresa" />} />

      <Dialog open={!!del} onOpenChange={(o) => !o && setDel(null)}>
        <DialogContent>
          <DialogHeader><DialogTitle>Excluir empresa</DialogTitle><DialogDescription>Excluir <strong>{del?.nome_informal || del?.razao_social}</strong>? Esta ação não pode ser desfeita.</DialogDescription></DialogHeader>
          <DialogFooter>
            <DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose>
            <Button variant="destructive" loading={excluir.isPending} onClick={async () => {
              try { await excluir.mutateAsync(del!.id); toast.success('Empresa excluída.') } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') } finally { setDel(null) }
            }}><Trash2 size={16} /> Excluir</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}

function GruposTab() {
  const { can } = useAuth()
  const { data, isLoading } = useGrupos()
  const salvar = useSalvarGrupo(); const excluir = useExcluirGrupo()
  const [edit, setEdit] = useState<Partial<GrupoItem> | null>(null)
  const [del, setDel] = useState<GrupoItem | null>(null)

  async function onSalvar() {
    if (!edit?.descricao?.trim()) { toast.error('Informe a descrição.'); return }
    try { await salvar.mutateAsync({ id: edit.id, descricao: edit.descricao, ativo: edit.ativo !== 0 }); toast.success('Grupo salvo.'); setEdit(null) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao salvar.') }
  }

  const columns: Column<GrupoItem>[] = [
    { key: 'descricao', header: 'Grupo', cell: (g) => <span className="font-medium">{g.descricao}</span> },
    { key: 'ativo', header: 'Status', cell: (g) => g.ativo ? <Badge variant="success">Ativo</Badge> : <Badge variant="secondary">Inativo</Badge> },
    {
      key: 'acoes', header: '', align: 'right', width: 'w-24',
      cell: (g) => (
        <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}>
          {can('empresa.edit') && <Button variant="ghost" size="icon" onClick={() => setEdit(g)}><Pencil size={16} /></Button>}
          {can('empresa.delete') && <Button variant="ghost" size="icon" onClick={() => setDel(g)}><Trash2 size={16} /></Button>}
        </div>
      ),
    },
  ]

  return (
    <>
      <div className="mb-3 flex justify-end">{can('empresa.create') && <Button onClick={() => setEdit({ ativo: 1 })}><Plus size={16} /> Novo grupo</Button>}</div>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(g) => g.id} onRowClick={(g) => setEdit(g)}
        empty={<EmptyState icon={<Building2 />} title="Nenhum grupo" />} />

      <Dialog open={!!edit} onOpenChange={(o) => !o && setEdit(null)}>
        <DialogContent>
          <DialogHeader><DialogTitle>{edit?.id ? 'Editar grupo' : 'Novo grupo'}</DialogTitle></DialogHeader>
          <div className="space-y-4">
            <Field label="Descrição" required><Input autoFocus value={edit?.descricao ?? ''} onChange={(e) => setEdit((s) => ({ ...s, descricao: e.target.value }))} /></Field>
            <CheckboxField label="Ativo" checked={edit?.ativo !== 0} onChange={(b) => setEdit((s) => ({ ...s, ativo: b ? 1 : 0 }))} />
          </div>
          <DialogFooter><DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose><Button loading={salvar.isPending} onClick={onSalvar}>Salvar</Button></DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={!!del} onOpenChange={(o) => !o && setDel(null)}>
        <DialogContent>
          <DialogHeader><DialogTitle>Excluir grupo</DialogTitle></DialogHeader>
          <p className="text-sm text-muted-foreground">Excluir <strong>{del?.descricao}</strong>?</p>
          <DialogFooter>
            <DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose>
            <Button variant="destructive" loading={excluir.isPending} onClick={async () => { try { await excluir.mutateAsync(del!.id); toast.success('Grupo excluído.') } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') } finally { setDel(null) } }}><Trash2 size={16} /> Excluir</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}
