import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Plus, MoreHorizontal, Pencil, Ban, RotateCcw, Users, Building2, User, Settings, Download } from 'lucide-react'
import {
  useClientes, useDesativarCliente, useReativarCliente,
  type ClienteListItem, type SituacaoCliente,
} from './api'
import {
  Button, PageHeader, Badge, DataTable, type Column, EmptyState, SearchBar, Field, Textarea,
  DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator,
  ConfirmDialog, Can, toast,
  Tabs, TabsList, TabsTrigger,
} from '@/components/ui'
import { api } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { useBusca } from '@/lib/useBusca'
import { data as fmtData } from '@/lib/format'

/** Rótulos do filtro de situação — a aba "Inativos" é a área dos desativados. */
const ABAS: { valor: SituacaoCliente; rotulo: string }[] = [
  { valor: 'ativos', rotulo: 'Ativos' },
  { valor: 'inativos', rotulo: 'Desativados' },
  { valor: 'todos', rotulo: 'Todos' },
]

export function ClientesListPage() {
  const navigate = useNavigate()
  const { can } = useAuth()
  const { busca, setBusca, q, page, setPage, submit } = useBusca()
  const [situacao, setSituacao] = useState<SituacaoCliente>('ativos')
  const [desativando, setDesativando] = useState<ClienteListItem | null>(null)
  const [motivo, setMotivo] = useState('')
  const [reativando, setReativando] = useState<ClienteListItem | null>(null)
  const { data, isLoading, isFetching } = useClientes(q, page, situacao)
  const desativar = useDesativarCliente()
  const reativar = useReativarCliente()

  function trocarAba(valor: string) {
    setSituacao(valor as SituacaoCliente)
    setPage(1) // a página 3 de "ativos" não existe em "desativados"
  }

  async function confirmarDesativacao() {
    if (!desativando) return
    try {
      await desativar.mutateAsync({ id: desativando.id, motivo: motivo.trim() || undefined })
      toast.success(`Cliente "${desativando.nome}" desativado. O histórico foi preservado.`)
      setDesativando(null)
      setMotivo('')
    } catch (e: any) {
      // O backend recusa quando há pedido ou parcela em aberto e explica o quê:
      // manter o diálogo aberto deixa o operador ler o motivo e cancelar.
      toast.error(
        e?.response?.data?.errors?.cliente?.[0] ??
        e?.response?.data?.message ??
        'Não foi possível desativar.',
      )
    }
  }

  async function confirmarReativacao() {
    if (!reativando) return
    try {
      await reativar.mutateAsync(reativando.id)
      toast.success(`Cliente "${reativando.nome}" reativado.`)
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Não foi possível reativar.')
    } finally {
      setReativando(null)
    }
  }

  async function exportar() {
    try {
      // Exporta o que está à vista: o CSV segue o filtro de situação da tela.
      const resp = await api.get('/clientes/exportar', { params: { situacao }, responseType: 'blob' })
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
              {!c.ativo && <Badge variant="secondary">desativado</Badge>}
            </div>
            {c.fantasia && <div className="text-xs text-muted-foreground truncate">{c.fantasia}</div>}
          </div>
        </div>
      ),
    },
    { key: 'doc', header: 'CPF/CNPJ', cell: (c) => <span className="text-muted-foreground tabular-nums">{c.cpf || c.cnpj || '—'}</span> },
    // Nas abas que mostram desativados, o e-mail cede espaço para a trilha:
    // saber POR QUE e POR QUEM o cadastro saiu é o que evita reabri-lo à toa.
    ...(situacao === 'ativos'
      ? [{ key: 'email', header: 'E-mail', cell: (c: ClienteListItem) => <span className="text-muted-foreground">{c.email || '—'}</span> }]
      : [{
          key: 'desativacao', header: 'Desativado em',
          cell: (c: ClienteListItem) => c.desativado_em ? (
            <div className="min-w-0">
              <div className="tabular-nums">{fmtData(c.desativado_em)}</div>
              <div className="text-xs text-muted-foreground truncate">
                {[c.desativado_por_nome, c.motivo_desativacao].filter(Boolean).join(' · ') || '—'}
              </div>
            </div>
          ) : <span className="text-muted-foreground">—</span>,
        }]),
    { key: 'uf', header: 'UF', align: 'center', width: 'w-16', cell: (c) => c.uf || '—' },
    {
      key: 'acoes', header: '', align: 'right', width: 'w-16',
      cell: (c) => (
        <div onClick={(e) => e.stopPropagation()} className="flex justify-end">
          <DropdownMenu>
            <DropdownMenuTrigger asChild><Button variant="ghost" size="icon" aria-label="Ações"><MoreHorizontal size={18} /></Button></DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              {can('cliente.edit') && <DropdownMenuItem onClick={() => navigate(`/clientes/${c.id}`)}><Pencil /> Editar</DropdownMenuItem>}
              {can('cliente.delete') && (
                <>
                  <DropdownMenuSeparator />
                  {c.ativo
                    ? <DropdownMenuItem destructive onClick={() => { setDesativando(c); setMotivo('') }}><Ban /> Desativar</DropdownMenuItem>
                    : <DropdownMenuItem onClick={() => setReativando(c)}><RotateCcw /> Reativar</DropdownMenuItem>}
                </>
              )}
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      ),
    },
  ]

  const rotuloTotal = situacao === 'inativos' ? 'desativados' : 'clientes'

  return (
    <div>
      <PageHeader
        title="Clientes"
        subtitle={data ? `${data.meta.total.toLocaleString('pt-BR')} ${rotuloTotal}` : 'Carregando…'}
        action={
          <>
            <Can permission="cliente.export"><Button variant="outline" onClick={exportar}><Download size={16} /> Exportar</Button></Can>
            {can('cliente.view') && <Button variant="outline" onClick={() => navigate('/configuracoes?tab=clientes')}><Settings size={16} /> Configurações</Button>}
            {can('cliente.create') && <Button onClick={() => navigate('/clientes/novo')}><Plus size={16} /> Novo cliente</Button>}
          </>
        }
      />

      <Tabs value={situacao} onValueChange={trocarAba}>
        <TabsList>
          {ABAS.map((a) => <TabsTrigger key={a.valor} value={a.valor}>{a.rotulo}</TabsTrigger>)}
        </TabsList>
      </Tabs>

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
          <EmptyState
            icon={<Users />}
            title={
              q ? 'Nenhum cliente encontrado'
                : situacao === 'inativos' ? 'Nenhum cliente desativado'
                : 'Nenhum cliente cadastrado'
            }
            description={
              q ? 'Tente outro termo de busca.'
                : situacao === 'inativos' ? 'Clientes desativados aparecem aqui e podem ser reativados a qualquer momento.'
                : 'Comece cadastrando seu primeiro cliente.'
            }
            action={can('cliente.create') && !q && situacao !== 'inativos' ? <Button onClick={() => navigate('/clientes/novo')}><Plus size={16} /> Novo cliente</Button> : undefined}
          />
        }
      />

      <ConfirmDialog
        open={!!desativando}
        onOpenChange={(o) => { if (!o) { setDesativando(null); setMotivo('') } }}
        title="Desativar cliente"
        confirmLabel="Desativar"
        description={
          <>
            <strong>{desativando?.nome}</strong> sai da lista de clientes ativos, mas
            {' '}<strong>nada é apagado</strong>: pedidos, títulos e histórico continuam
            vinculados ao cadastro. Você pode reativá-lo na aba “Desativados”.
          </>
        }
        loading={desativar.isPending}
        onConfirm={confirmarDesativacao}
      >
        <Field label="Motivo (opcional)" hint="Ex.: mudou de cidade, cadastro duplicado, cliente inativo há mais de um ano.">
          <Textarea value={motivo} onChange={(e) => setMotivo(e.target.value)} rows={2} maxLength={255} placeholder="Por que este cadastro está sendo desativado?" />
        </Field>
      </ConfirmDialog>

      <ConfirmDialog
        open={!!reativando}
        onOpenChange={(o) => !o && setReativando(null)}
        title="Reativar cliente"
        confirmLabel="Reativar"
        variant="default"
        description={<>Devolver <strong>{reativando?.nome}</strong> à lista de clientes ativos?</>}
        loading={reativar.isPending}
        onConfirm={confirmarReativacao}
      />
    </div>
  )
}
