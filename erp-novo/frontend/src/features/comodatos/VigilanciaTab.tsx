import { useState } from 'react'
import { Gauge, TrendingDown, Users, AlertTriangle, PackageCheck } from 'lucide-react'
import {
  Badge, Card, CardContent, StatCard, AsyncState, type Column, DataTable,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription,
} from '@/components/ui'
import { data as fmtData } from '@/lib/format'
import { useVigilancia, useHistoricoVigilancia, type Avaliacao } from './api'

/**
 * Painel de giro: o vasilhame emprestado está rodando aqui?
 *
 * Giro = quanto o cliente comprou na janela ÷ vasilhames em poder dele. Mede
 * quantas vezes cada casco emprestado voltou para reabastecer. Quando não volta,
 * ou virou ativo ocioso, ou está sendo enchido na concorrência.
 *
 * A coluna "habitual" é o que separa "compra pouco" de "comprava mais e parou" —
 * e é a segunda que importa.
 */
export function VigilanciaTab() {
  const { data, isLoading, error } = useVigilancia()
  const [detalhe, setDetalhe] = useState<Avaliacao | null>(null)

  const linhas = data?.data ?? []
  const criticos = linhas.filter((a) => a.classificacao === 'CRITICO')
  const atencao = linhas.filter((a) => a.classificacao === 'ATENCAO')
  const vasilhamesEmRisco = [...criticos, ...atencao]
    .reduce((s, a) => s + Number(a.em_posse), 0)

  const columns: Column<Avaliacao>[] = [
    { key: 'cliente', header: 'Cliente', cell: (a) => a.cliente?.nome ?? `#${a.cliente_id}` },
    {
      key: 'posse', header: 'Em posse', align: 'right',
      cell: (a) => <span className="tabular-nums font-medium">{Number(a.em_posse)}</span>,
    },
    {
      key: 'giro', header: 'Giro', align: 'right',
      cell: (a) => <span className="tabular-nums">{Number(a.giro).toFixed(1)}x</span>,
    },
    {
      key: 'habitual', header: 'Habitual', align: 'right',
      cell: (a) => a.baseline_giro == null
        ? <span className="text-muted-foreground">—</span>
        : <span className="tabular-nums text-muted-foreground">{Number(a.baseline_giro).toFixed(1)}x</span>,
    },
    {
      key: 'variacao', header: 'Variação', align: 'right',
      cell: (a) => {
        if (a.variacao == null) return <span className="text-muted-foreground">—</span>
        const v = Number(a.variacao)
        // Variação positiva = QUEDA (caiu tantos %). Vermelho é o correto.
        return (
          <span className={`tabular-nums ${v >= 40 ? 'font-medium text-destructive' : 'text-muted-foreground'}`}>
            {v > 0 ? `-${v.toFixed(0)}%` : `+${Math.abs(v).toFixed(0)}%`}
          </span>
        )
      },
    },
    {
      key: 'sem', header: 'Sem comprar', align: 'right',
      cell: (a) => a.dias_sem_compra == null
        ? <span className="text-muted-foreground">—</span>
        : <span className="tabular-nums">{a.dias_sem_compra}d</span>,
    },
    { key: 'nivel', header: 'Nível', cell: (a) => <NivelBadge nivel={a.classificacao} /> },
  ]

  return (
    <div className="space-y-4">
      <div className="grid gap-3 sm:grid-cols-4">
        <StatCard titulo="Clientes vigiados" valor={linhas.length} icon={Users} accent="neutral" />
        <StatCard titulo="Críticos" valor={criticos.length} icon={AlertTriangle} accent="destructive" />
        <StatCard titulo="Atenção" valor={atencao.length} icon={Gauge} accent="primary" />
        <StatCard titulo="Vasilhames em risco" valor={vasilhamesEmRisco} icon={PackageCheck} accent="primary" />
      </div>

      {data?.config && (
        <Card>
          <CardContent className="p-3 text-xs text-muted-foreground">
            Janela de {data.config.dias_janela} dias · alerta abaixo de {data.config.giro_minimo}x ·
            crítico abaixo de {data.config.giro_critico}x · queda de {data.config.queda_atencao}% contra o
            próprio histórico · comodatos a partir de {data.config.posse_minima_vigiada} vasilhames.
          </CardContent>
        </Card>
      )}

      <AsyncState
        loading={isLoading} error={error} empty={linhas.length === 0}
        emptyIcon={<Gauge />} emptyTitle="Sem avaliações ainda"
        emptyDescription="A vigilância roda toda segunda de madrugada. Rode `comodato:vigiar --aplicar` para antecipar."
      >
        <DataTable
          columns={columns} rows={linhas} rowKey={(a) => a.id}
          onRowClick={(a) => setDetalhe(a)}
        />
      </AsyncState>

      <HistoricoDialog avaliacao={detalhe} onOpenChange={(v) => !v && setDetalhe(null)} />
    </div>
  )
}

function NivelBadge({ nivel }: { nivel: string }) {
  if (nivel === 'CRITICO') return <Badge variant="destructive">Crítico</Badge>
  if (nivel === 'ATENCAO') return <Badge variant="warning">Atenção</Badge>
  return <Badge variant="success">Normal</Badge>
}

/**
 * A evolução do cliente ao longo das rodadas.
 *
 * É o que sustenta a conversa com ele: "seu giro caiu de 18x para 3x desde
 * março" é diferente de "você compra pouco".
 */
function HistoricoDialog({ avaliacao, onOpenChange }: {
  avaliacao: Avaliacao | null
  onOpenChange: (v: boolean) => void
}) {
  const { data, isLoading, error } = useHistoricoVigilancia(avaliacao?.cliente_id ?? null)

  return (
    <Dialog open={avaliacao !== null} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>{avaliacao?.cliente?.nome ?? 'Cliente'}</DialogTitle>
          <DialogDescription>{avaliacao?.motivo}</DialogDescription>
        </DialogHeader>

        <AsyncState
          loading={isLoading} error={error} empty={(data?.length ?? 0) === 0}
          emptyIcon={<TrendingDown />} emptyTitle="Sem histórico"
        >
          <ul className="divide-y rounded-md border text-sm">
            {(data ?? []).map((a) => (
              <li key={a.id} className="flex items-center gap-3 px-3 py-2">
                <span className="w-24 shrink-0 text-muted-foreground">{fmtData(a.referencia)}</span>
                <NivelBadge nivel={a.classificacao} />
                <span className="tabular-nums">{Number(a.em_posse)} em posse</span>
                <span className="tabular-nums">giro {Number(a.giro).toFixed(1)}x</span>
                <span className="ml-auto tabular-nums text-muted-foreground">
                  {Number(a.comprado_janela)} em {a.dias_janela}d
                </span>
              </li>
            ))}
          </ul>
        </AsyncState>
      </DialogContent>
    </Dialog>
  )
}
