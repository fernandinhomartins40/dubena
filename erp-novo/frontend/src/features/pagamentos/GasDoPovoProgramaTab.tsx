import { useState } from 'react'
import { Flame, Users, Receipt, Tag, Settings2, AlertTriangle } from 'lucide-react'
import {
  Card, CardContent, Badge, StatCard, Field, Input, AsyncState, EmptyState,
  DataTable, type Column, SearchBar,
} from '@/components/ui'
import { brl, data as fmtData } from '@/lib/format'
import {
  usePrograma, useBeneficiarios, useVendasGp,
  type GpBeneficiario, type GpVenda, type GpParametros, type GpMes,
} from './api'

/** Primeiro dia do mês corrente e hoje — o período que se confere no dia a dia. */
function periodoPadrao(): [string, string] {
  const hoje = new Date()
  const inicio = new Date(hoje.getFullYear(), hoje.getMonth(), 1)
  return [inicio.toISOString().slice(0, 10), hoje.toISOString().slice(0, 10)]
}

/**
 * O programa Gás do Povo como o legado opera: parâmetros da empresa,
 * beneficiários e vendas subsidiadas.
 *
 * Não há saldo nem saque aqui — a venda vira "do programa" quando o cliente é
 * beneficiário E a condição de pagamento é a do programa. Ver
 * `docs/02-auditoria-legado/GAS_DO_POVO_NO_LEGADO.md`.
 */
export function GasDoPovoProgramaTab() {
  const [de, setDe] = useState(periodoPadrao()[0])
  const [ate, setAte] = useState(periodoPadrao()[1])
  const { data, isLoading } = usePrograma(de, ate)

  return (
    <div className="space-y-6">
      <Card>
        <CardContent className="flex flex-wrap items-end gap-4 pt-6">
          <Field label="De" className="w-40">
            <Input type="date" value={de} onChange={(e) => setDe(e.target.value)} />
          </Field>
          <Field label="Até" className="w-40">
            <Input type="date" value={ate} onChange={(e) => setAte(e.target.value)} />
          </Field>
          <p className="pb-2 text-sm text-muted-foreground">
            A venda entra no programa quando o cliente é beneficiário <em>e</em> a
            condição de pagamento é a do programa — que é o cartão do benefício,
            não um desconto de tabela.
          </p>
        </CardContent>
      </Card>

      <AsyncState loading={isLoading} skeletonRows={3}>
        {data && (
          <>
            {!data.parametros.configurado && <ProgramaNaoConfigurado />}

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
              <StatCard
                titulo="Vendas no período" valor={data.resumo.pedidos}
                icon={Receipt} accent="primary"
                hint={data.resumo.pedidos > 0 ? `Ticket médio ${brl(data.resumo.ticket_medio)}` : undefined}
              />
              <StatCard
                titulo="Botijões entregues" valor={data.resumo.botijoes}
                icon={Flame} accent="lime"
              />
              <StatCard
                titulo="Faturado" valor={brl(data.resumo.valor)}
                icon={Receipt} accent="neutral"
              />
              <StatCard
                titulo="Preço médio"
                valor={data.resumo.preco_medio !== null ? brl(data.resumo.preco_medio) : '—'}
                icon={Tag} accent="success"
                hint="Praticado nas vendas, não o do cadastro"
              />
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
              <ParametrosDoPrograma p={data.parametros} beneficiarios={data.resumo.beneficiarios} />
              <EvolucaoMensal meses={data.por_mes} />
            </div>
          </>
        )}
      </AsyncState>
    </div>
  )
}

function ProgramaNaoConfigurado() {
  return (
    <div className="flex items-start gap-3 rounded-lg border border-destructive/40 bg-destructive/5 p-4">
      <AlertTriangle className="mt-0.5 size-5 shrink-0 text-destructive" />
      <div className="text-sm">
        <p className="font-medium">Programa não configurado nesta empresa.</p>
        <p className="text-muted-foreground">
          Defina o produto e a condição de pagamento do programa em
          Empresas → Configurações. Sem eles a venda não é marcada como subsidiada.
        </p>
      </div>
    </div>
  )
}

function ParametrosDoPrograma({ p, beneficiarios }: { p: GpParametros; beneficiarios: number }) {
  const linhas: Array<[string, React.ReactNode]> = [
    ['Produto do programa', p.produto ?? <Ausente />],
    ['Preço de tabela do programa', p.preco !== null ? brl(p.preco) : <Ausente />],
    ['Preço de venda normal', p.preco_venda !== null ? brl(p.preco_venda) : <Ausente />],
    ['Condição de pagamento', p.condicaopagamento ?? <Ausente />],
    ['Valor da entrega', p.valor_frete !== null ? brl(p.valor_frete) : <Ausente />],
    ['Cond. pagamento da entrega', p.condicaopagamento_frete ?? <Ausente />],
  ]

  return (
    <Card>
      <CardContent className="pt-6">
        <div className="mb-4 flex items-center gap-2">
          <Settings2 size={18} className="text-muted-foreground" />
          <h3 className="font-semibold">Parâmetros do programa</h3>
          <Badge variant="secondary" className="ml-auto">
            <Users size={13} className="mr-1" /> {beneficiarios.toLocaleString('pt-BR')} beneficiários
          </Badge>
        </div>
        <dl className="divide-y divide-border text-sm">
          {linhas.map(([rotulo, valor]) => (
            <div key={rotulo} className="flex items-center justify-between gap-4 py-2.5">
              <dt className="text-muted-foreground">{rotulo}</dt>
              <dd className="text-right font-medium tabular-nums">{valor}</dd>
            </div>
          ))}
        </dl>
      </CardContent>
    </Card>
  )
}

const Ausente = () => <span className="font-normal text-muted-foreground">não definido</span>

/**
 * Barras horizontais em CSS puro: a série é curta (12 meses) e não justifica
 * uma dependência de gráfico só para isto.
 */
function EvolucaoMensal({ meses }: { meses: GpMes[] }) {
  const maximo = Math.max(...meses.map((m) => m.valor), 1)

  return (
    <Card>
      <CardContent className="pt-6">
        <h3 className="mb-4 font-semibold">Evolução mensal</h3>
        {meses.length === 0 ? (
          <p className="py-8 text-center text-sm text-muted-foreground">
            Nenhuma venda pelo programa nos últimos 12 meses.
          </p>
        ) : (
          <div className="space-y-2.5">
            {meses.map((m) => (
              <div key={m.mes} className="grid grid-cols-[4.5rem_1fr_auto] items-center gap-3 text-sm">
                <span className="tabular-nums text-muted-foreground">{rotuloMes(m.mes)}</span>
                <div className="h-2 overflow-hidden rounded-full bg-secondary">
                  <div
                    className="h-full rounded-full bg-primary transition-[width] duration-500"
                    style={{ width: `${Math.max((m.valor / maximo) * 100, 2)}%` }}
                  />
                </div>
                <span className="tabular-nums font-medium">{brl(m.valor)}</span>
              </div>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  )
}

function rotuloMes(mes: string): string {
  const [ano, m] = mes.split('-')
  const nomes = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez']
  return `${nomes[Number(m) - 1]}/${ano.slice(2)}`
}

/** Os clientes marcados como beneficiários no cadastro. */
export function GasDoPovoBeneficiariosTab() {
  const [q, setQ] = useState('')
  const [page, setPage] = useState(1)
  const { data, isLoading } = useBeneficiarios(q, page)

  const columns: Column<GpBeneficiario>[] = [
    { key: 'nome', header: 'Cliente', cell: (c) => <span className="font-medium">{c.nome}</span> },
    { key: 'doc', header: 'CPF / CNPJ', cell: (c) => c.cpf || c.cnpj || '—' },
    {
      key: 'ultima', header: 'Última compra',
      cell: (c) => (c.data_ultima_compra ? fmtData(c.data_ultima_compra) : '—'),
    },
    {
      key: 'ativo', header: 'Situação', align: 'right',
      cell: (c) => (c.ativo ? <Badge variant="success">Ativo</Badge> : <Badge variant="secondary">Inativo</Badge>),
    },
  ]

  return (
    <div className="space-y-4">
      <SearchBar
        value={q} onChange={setQ} onSearch={() => setPage(1)}
        placeholder="Buscar por nome ou CPF…"
      />
      <DataTable
        columns={columns} rows={data?.data} loading={isLoading} rowKey={(c) => c.id}
        empty={<EmptyState
          icon={<Users />} title="Nenhum beneficiário"
          description="Marque o cliente como Gás do Povo no cadastro para que ele apareça aqui."
        />}
      />
      <Paginacao meta={data?.meta} onPage={setPage} />
    </div>
  )
}

/** As vendas efetivamente marcadas como do programa. */
export function GasDoPovoVendasTab() {
  const [de, setDe] = useState(periodoPadrao()[0])
  const [ate, setAte] = useState(periodoPadrao()[1])
  const [page, setPage] = useState(1)
  const { data, isLoading } = useVendasGp(de, ate, page)

  const columns: Column<GpVenda>[] = [
    { key: 'id', header: 'Pedido', cell: (v) => <span className="font-medium">#{v.id}</span> },
    { key: 'data', header: 'Data', cell: (v) => fmtData(v.datahora) },
    { key: 'cliente', header: 'Cliente', cell: (v) => v.cliente ?? '—' },
    { key: 'situacao', header: 'Situação', cell: (v) => v.situacao ?? '—' },
    {
      key: 'valor', header: 'Valor', align: 'right',
      cell: (v) => <span className="tabular-nums">{brl(v.valorvenda)}</span>,
    },
  ]

  return (
    <div className="space-y-4">
      <Card>
        <CardContent className="flex flex-wrap items-end gap-4 pt-6">
          <Field label="De" className="w-40">
            <Input type="date" value={de} onChange={(e) => { setDe(e.target.value); setPage(1) }} />
          </Field>
          <Field label="Até" className="w-40">
            <Input type="date" value={ate} onChange={(e) => { setAte(e.target.value); setPage(1) }} />
          </Field>
          {data && (
            <p className="pb-2 text-sm text-muted-foreground">
              <span className="font-medium text-foreground">{data.meta.total.toLocaleString('pt-BR')}</span> venda(s) no período
            </p>
          )}
        </CardContent>
      </Card>
      <DataTable
        columns={columns} rows={data?.data} loading={isLoading} rowKey={(v) => v.id}
        empty={<EmptyState
          icon={<Flame />} title="Nenhuma venda pelo programa"
          description="Não há pedidos marcados como Gás do Povo no período escolhido."
        />}
      />
      <Paginacao meta={data?.meta} onPage={setPage} />
    </div>
  )
}

function Paginacao({
  meta, onPage,
}: {
  meta?: { current_page: number; last_page: number; total: number }
  onPage: (p: number) => void
}) {
  if (!meta || meta.last_page <= 1) return null

  return (
    <div className="flex items-center justify-between text-sm">
      <span className="text-muted-foreground">
        Página {meta.current_page} de {meta.last_page} · {meta.total.toLocaleString('pt-BR')} registro(s)
      </span>
      <div className="flex gap-2">
        <button
          className="rounded-md border border-border px-3 py-1.5 disabled:opacity-40"
          disabled={meta.current_page <= 1}
          onClick={() => onPage(meta.current_page - 1)}
        >
          Anterior
        </button>
        <button
          className="rounded-md border border-border px-3 py-1.5 disabled:opacity-40"
          disabled={meta.current_page >= meta.last_page}
          onClick={() => onPage(meta.current_page + 1)}
        >
          Próxima
        </button>
      </div>
    </div>
  )
}
