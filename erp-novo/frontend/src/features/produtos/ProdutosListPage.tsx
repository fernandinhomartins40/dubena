import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Search, Plus, MoreHorizontal, Pencil, Trash2, Package, Tags, DollarSign } from 'lucide-react'
import { useProdutos, useExcluirProduto, type ProdutoListItem } from './api'
import {
  Button, Card, PageHeader, Input, Badge, DataTable, type Column, EmptyState,
  DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter, DialogClose,
  toast,
} from '@/components/ui'
import { useAuth } from '@/lib/auth'
import { brl } from '@/lib/format'

function moeda(v: number | string | null) {
  const n = typeof v === 'string' ? parseFloat(v) : v
  if (n == null || isNaN(n)) return '—'
  return brl(n)
}

export function ProdutosListPage() {
  const navigate = useNavigate()
  const { can } = useAuth()
  const [busca, setBusca] = useState('')
  const [q, setQ] = useState('')
  const [page, setPage] = useState(1)
  const [excluindo, setExcluindo] = useState<ProdutoListItem | null>(null)
  const { data, isLoading, isFetching } = useProdutos(q, page)
  const excluir = useExcluirProduto()

  function buscar(e: React.FormEvent) {
    e.preventDefault()
    setPage(1)
    setQ(busca)
  }

  async function confirmarExclusao() {
    if (!excluindo) return
    try {
      await excluir.mutateAsync(excluindo.id)
      toast.success(`Produto "${excluindo.descricao}" excluído.`)
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Não foi possível excluir o produto.')
    } finally {
      setExcluindo(null)
    }
  }

  const columns: Column<ProdutoListItem>[] = [
    {
      key: 'descricao', header: 'Descrição',
      cell: (p) => (
        <div className="flex items-center gap-3">
          <div className="grid size-9 place-items-center rounded-md bg-secondary text-muted-foreground shrink-0">
            <Package size={16} />
          </div>
          <div className="min-w-0">
            <div className="font-medium truncate flex items-center gap-2">
              {p.descricao}
              {p.ativo === 0 && <Badge variant="secondary">inativo</Badge>}
            </div>
            {p.classe && <div className="text-xs text-muted-foreground">{p.classe}</div>}
          </div>
        </div>
      ),
    },
    { key: 'precovenda', header: 'Preço de venda', align: 'right', width: 'w-40', cell: (p) => <span className="tabular-nums">{moeda(p.precovenda)}</span> },
    {
      key: 'nfe', header: 'NF-e', align: 'center', width: 'w-24',
      cell: (p) => p.nfepermite === 1 ? <Badge variant="success">Sim</Badge> : <span className="text-muted-foreground text-xs">Não</span>,
    },
    {
      key: 'acoes', header: '', align: 'right', width: 'w-16',
      cell: (p) => (
        <div onClick={(e) => e.stopPropagation()} className="flex justify-end">
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" size="icon" aria-label="Ações"><MoreHorizontal size={18} /></Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              {can('produto.edit') && <DropdownMenuItem onClick={() => navigate(`/produtos/${p.id}`)}><Pencil /> Editar</DropdownMenuItem>}
              {can('produto.delete') && (
                <>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem destructive onClick={() => setExcluindo(p)}><Trash2 /> Excluir</DropdownMenuItem>
                </>
              )}
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      ),
    },
  ]

  return (
    <div>
      <PageHeader
        title="Produtos"
        subtitle={data ? `${data.meta.total.toLocaleString('pt-BR')} produtos cadastrados` : 'Carregando…'}
        action={
          <>
            {can('produto.view') && (
              <Button variant="outline" onClick={() => navigate('/produtos/configuracoes')}><Tags size={16} /> Configurações</Button>
            )}
            {can('produto.edit') && (
              <Button variant="outline" onClick={() => navigate('/produtos/precos')}><DollarSign size={16} /> Atualizar preços</Button>
            )}
            {can('produto.create') && (
              <Button onClick={() => navigate('/produtos/novo')}><Plus size={16} /> Novo produto</Button>
            )}
          </>
        }
      />

      <Card className="mb-4 p-3">
        <form onSubmit={buscar} className="flex gap-2">
          <div className="relative flex-1">
            <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
            <Input value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Buscar por descrição, EAN ou código…" className="pl-9" />
          </div>
          <Button type="submit" variant="secondary">Buscar</Button>
        </form>
      </Card>

      <DataTable
        columns={columns}
        rows={data?.data}
        loading={isLoading}
        rowKey={(p) => p.id}
        onRowClick={can('produto.edit') ? (p) => navigate(`/produtos/${p.id}`) : undefined}
        page={data?.meta.current_page}
        lastPage={data?.meta.last_page}
        onPageChange={setPage}
        fetching={isFetching}
        empty={
          <EmptyState
            icon={<Package />}
            title={q ? 'Nenhum produto encontrado' : 'Nenhum produto cadastrado'}
            description={q ? 'Tente outro termo de busca.' : 'Comece cadastrando seu primeiro produto.'}
            action={can('produto.create') && !q ? <Button onClick={() => navigate('/produtos/novo')}><Plus size={16} /> Novo produto</Button> : undefined}
          />
        }
      />

      <Dialog open={!!excluindo} onOpenChange={(o) => !o && setExcluindo(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Excluir produto</DialogTitle>
            <DialogDescription>
              Tem certeza que deseja excluir <strong>{excluindo?.descricao}</strong>? Esta ação não pode ser desfeita.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose>
            <Button variant="destructive" loading={excluir.isPending} onClick={confirmarExclusao}><Trash2 size={16} /> Excluir</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
