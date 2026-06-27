import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Plus, MoreHorizontal, Pencil, Trash2, Users, Building2, User, Settings, Download } from 'lucide-react'
import { useClientes, useExcluirCliente, type ClienteListItem } from './api'
import {
  Button, PageHeader, Badge, DataTable, type Column, EmptyState, SearchBar,
  DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator,
  ConfirmDialog, Can, toast,
} from '@/components/ui'
import { api } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { useBusca } from '@/lib/useBusca'

export function ClientesListPage() {
  const navigate = useNavigate()
  const { can } = useAuth()
  const { busca, setBusca, q, page, setPage, submit } = useBusca()
  const [excluindo, setExcluindo] = useState<ClienteListItem | null>(null)
  const { data, isLoading, isFetching } = useClientes(q, page)
  const excluir = useExcluirCliente()

  async function confirmarExclusao() {
    if (!excluindo) return
    try { await excluir.mutateAsync(excluindo.id); toast.success(`Cliente "${excluindo.nome}" excluído.`) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Não foi possível excluir.') }
    finally { setExcluindo(null) }
  }

  async function exportar() {
    try {
      const resp = await api.get('/clientes/exportar', { responseType: 'blob' })
      const url = URL.createObjectURL(resp.data as Blob)
      const a = document.createElement('a')
      a.href = url; a.download = 'clientes.csv'; a.click()
      URL.revokeObjectURL(url)
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Não foi possível exportar.') }
  }

  const columns: Column<ClienteListItem>[] = [
    {
      key: 'nome', header: 'Nome',
      cell: (c) => (
        <div className="flex items-center gap-3">
          <div className="grid size-9 place-items-center rounded-full bg-secondary text-muted-foreground shrink-0">
            {c.cnpj ? <Building2 size={16} /> : <User size={16} />}
          </div>
          <div className="min-w-0">
            <div className="font-medium truncate flex items-center gap-2">
              {c.nome}
              {c.ativo === 0 && <Badge variant="secondary">inativo</Badge>}
            </div>
            {c.fantasia && <div className="text-xs text-muted-foreground truncate">{c.fantasia}</div>}
          </div>
        </div>
      ),
    },
    { key: 'doc', header: 'CPF/CNPJ', cell: (c) => <span className="text-muted-foreground tabular-nums">{c.cpf || c.cnpj || '—'}</span> },
    { key: 'email', header: 'E-mail', cell: (c) => <span className="text-muted-foreground">{c.email || '—'}</span> },
    { key: 'uf', header: 'UF', align: 'center', width: 'w-16', cell: (c) => c.uf || '—' },
    {
      key: 'acoes', header: '', align: 'right', width: 'w-16',
      cell: (c) => (
        <div onClick={(e) => e.stopPropagation()} className="flex justify-end">
          <DropdownMenu>
            <DropdownMenuTrigger asChild><Button variant="ghost" size="icon" aria-label="Ações"><MoreHorizontal size={18} /></Button></DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              {can('cliente.edit') && <DropdownMenuItem onClick={() => navigate(`/clientes/${c.id}`)}><Pencil /> Editar</DropdownMenuItem>}
              {can('cliente.delete') && (<><DropdownMenuSeparator /><DropdownMenuItem destructive onClick={() => setExcluindo(c)}><Trash2 /> Excluir</DropdownMenuItem></>)}
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      ),
    },
  ]

  return (
    <div>
      <PageHeader
        title="Clientes"
        subtitle={data ? `${data.meta.total.toLocaleString('pt-BR')} clientes cadastrados` : 'Carregando…'}
        action={
          <>
            <Can permission="cliente.export"><Button variant="outline" onClick={exportar}><Download size={16} /> Exportar</Button></Can>
            {can('cliente.view') && <Button variant="outline" onClick={() => navigate('/configuracoes?tab=clientes')}><Settings size={16} /> Configurações</Button>}
            {can('cliente.create') && <Button onClick={() => navigate('/clientes/novo')}><Plus size={16} /> Novo cliente</Button>}
          </>
        }
      />

      <SearchBar value={busca} onChange={setBusca} onSearch={submit} placeholder="Buscar por nome, fantasia, CPF/CNPJ ou código…" />

      <DataTable
        columns={columns}
        rows={data?.data}
        loading={isLoading}
        rowKey={(c) => c.id}
        onRowClick={can('cliente.edit') ? (c) => navigate(`/clientes/${c.id}`) : undefined}
        page={data?.meta.current_page}
        lastPage={data?.meta.last_page}
        onPageChange={setPage}
        fetching={isFetching}
        empty={
          <EmptyState icon={<Users />} title={q ? 'Nenhum cliente encontrado' : 'Nenhum cliente cadastrado'}
            description={q ? 'Tente outro termo de busca.' : 'Comece cadastrando seu primeiro cliente.'}
            action={can('cliente.create') && !q ? <Button onClick={() => navigate('/clientes/novo')}><Plus size={16} /> Novo cliente</Button> : undefined} />
        }
      />

      <ConfirmDialog
        open={!!excluindo} onOpenChange={(o) => !o && setExcluindo(null)}
        title="Excluir cliente"
        description={<>Tem certeza que deseja excluir <strong>{excluindo?.nome}</strong>? Esta ação não pode ser desfeita.</>}
        loading={excluir.isPending} onConfirm={confirmarExclusao}
      />
    </div>
  )
}
