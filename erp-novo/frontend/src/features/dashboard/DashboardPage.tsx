import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { Users, Package, ShoppingCart, Banknote, Truck, ArrowUpRight, RefreshCw } from 'lucide-react'
import { api } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { StatCard, AsyncState, Button, PageHeader, type StatAccent } from '@/components/ui'
import { dataHora } from '@/lib/format'
import { useAlertas } from '@/features/alertas/api'

interface Resumo {
  clientes: number
  produtos: number
  pedidos: number
  financeiro: number
}

export function DashboardPage() {
  const { user, can } = useAuth()
  const { data, isLoading, error, refetch, isFetching, dataUpdatedAt } = useQuery<Resumo>({
    queryKey: ['dashboard-resumo'],
    queryFn: async () => {
      const value = (await api.get<Resumo>('/dashboard/resumo')).data
      if (!value || ['clientes', 'produtos', 'pedidos', 'financeiro'].some((key) => !Number.isFinite(value[key as keyof Resumo]))) {
        throw new Error('O resumo recebido está incompleto. Tente novamente.')
      }
      return value
    },
  })

  const v = (n?: number) => (isLoading ? '…' : (n ?? '—'))

  // Acentos com propósito (tokens centrais): relacionamento, catálogo, operação, dinheiro.
  const cards: { titulo: string; valor: number | string; icon: typeof Users; accent: StatAccent; permission: string; hint: string }[] = [
    { titulo: 'Clientes cadastrados', valor: v(data?.clientes), icon: Users, accent: 'primary', permission: 'cliente.view', hint: 'Total cadastrado na empresa' },
    { titulo: 'Produtos cadastrados', valor: v(data?.produtos), icon: Package, accent: 'neutral', permission: 'produto.view', hint: 'Total cadastrado na empresa' },
    { titulo: 'Pedidos registrados', valor: v(data?.pedidos), icon: ShoppingCart, accent: 'lime', permission: 'pedido.view', hint: 'Histórico completo, sem recorte diário' },
    { titulo: 'Parcelas a receber em aberto', valor: v(data?.financeiro), icon: Banknote, accent: 'primary', permission: 'financeiro.view', hint: 'Quantidade de parcelas, não valor em reais' },
  ]
  const atalhos = [
    { title: 'Pedidos', description: 'Acompanhe a jornada de cada venda.', to: '/pedidos', permission: 'pedido.view', icon: ShoppingCart },
    { title: 'Clientes', description: 'Encontre um cadastro e seu histórico.', to: '/clientes', permission: 'cliente.view', icon: Users },
    { title: 'Central de Vendas', description: 'Analise solicitações do campo.', to: '/central-vendas', permission: 'venda.view', icon: ShoppingCart },
    { title: 'Central de Logística', description: 'Organize a fila e atribua entregas.', to: '/central', permission: 'logistica.view', icon: Truck },
    { title: 'Financeiro', description: 'Consulte lançamentos e caixa.', to: '/financeiro', permission: 'financeiro.view', icon: Banknote },
    { title: 'Estoque', description: 'Confira saldos e movimentações.', to: '/estoque', permission: 'estoque.view', icon: Package },
  ].filter((item) => can(item.permission))

  return (
    <div className="mx-auto max-w-7xl space-y-6">
      <PageHeader title={`Olá, ${user?.name?.split(' ')[0] ?? ''}`} subtitle="Sua operação, com contexto e próximos passos."
        action={<Button variant="outline" loading={isFetching} onClick={() => { void refetch() }}><RefreshCw /> Atualizar resumo</Button>} />
      <section aria-labelledby="resumo-titulo">
        <div className="mb-3 flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
          <h2 id="resumo-titulo" className="font-semibold uppercase tracking-wider">Resumo da empresa operacional</h2>
          {dataUpdatedAt > 0 && <span>Consulta: {dataHora(new Date(dataUpdatedAt).toISOString())}</span>}
        </div>
        <AsyncState loading={isLoading} error={error} onRetry={() => { void refetch() }}>
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">{cards.filter((c) => can(c.permission)).map((c) => <StatCard key={c.titulo} {...c} />)}</div>
        </AsyncState>
      </section>
      {can('alerta.view') && <Pendencias />}
      {atalhos.length > 0 && <section aria-labelledby="trabalho-titulo">
        <h2 id="trabalho-titulo" className="mb-3 text-lg font-semibold">Onde vamos trabalhar?</h2>
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">{atalhos.map(({ icon: Icon, ...item }) => <Link key={item.to} to={item.to}
          className="flex items-start gap-4 rounded-xl border bg-card p-5 transition-colors hover:border-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
          <span className="grid size-10 shrink-0 place-items-center rounded-lg bg-secondary"><Icon size={19} /></span>
          <div className="flex-1"><h3 className="text-sm font-semibold">{item.title}</h3><p className="mt-1 text-sm text-muted-foreground">{item.description}</p></div><ArrowUpRight size={16} className="text-muted-foreground" />
        </Link>)}</div>
      </section>}
    </div>
  )
}

function Pendencias() {
  const query = useAlertas()
  const rows = query.data?.data
  const invalid = query.data && !Array.isArray(rows) ? new Error('A consulta de pendências retornou um formato inválido.') : null
  return <section aria-labelledby="pendencias-titulo" className="rounded-xl border bg-card p-5">
    <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
      <h2 id="pendencias-titulo" className="text-lg font-semibold">Atenção da equipe</h2>
      <Link className="text-sm font-medium text-accent-foreground underline underline-offset-4" to="/alertas">Abrir central de alertas</Link>
    </div>
    <AsyncState loading={query.isLoading} error={query.error || invalid} onRetry={() => { void query.refetch() }} empty={rows?.length === 0} emptyTitle="Nenhum alerta pendente">
      <ul className="divide-y">{Array.isArray(rows) && rows.slice(0, 5).map((row) => <li key={row.id} className="flex flex-wrap justify-between gap-2 py-3 text-sm">
        <div><p className="font-medium">{row.titulo}</p><p className="text-muted-foreground">{row.responsavel?.name ?? 'Sem responsável'} · {row.situacao === 'EM_ANALISE' ? 'Em análise' : 'Aberto'}</p></div>
        <span className={row.severidade === 'ALTA' ? 'font-medium text-destructive' : 'text-muted-foreground'}>Prioridade {row.severidade.toLocaleLowerCase('pt-BR')}</span>
      </li>)}</ul>
    </AsyncState>
  </section>
}
