import { useState } from 'react'
import { BadgePercent, CheckCircle2, XCircle, Receipt, ShoppingCart, TriangleAlert } from 'lucide-react'
import {
  Button, Card, CardContent, PageHeader, Badge, AsyncState, StatCard,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogClose,
  Field, Input, toast, Can, Tabs, TabsList, TabsTrigger, TabsContent,
} from '@/components/ui'
import { useAuth } from '@/lib/auth'
import { PosVendaPage } from '@/features/crm/PosVendaPage'
import { MissoesPage } from '@/features/missoes/MissoesPage'
import { brl, dataHora } from '@/lib/format'
import {
  useSolicitacoes, useSolicitacao, useAprovar, useRecusar, useFaturar,
  type Solicitacao,
} from './api'

/**
 * Central de Vendas (F3) — a fila de solicitações do campo.
 *
 * O franqueado não fecha pedido: ele pede, e aqui o atendente decide. Aprovar
 * gera o pedido; faturar transita a situação (a máquina de estados é que baixa
 * estoque e gera financeiro).
 *
 * "Quase ao vivo" por polling enquanto o Reverb não sobe — mesmo caminho da
 * Central de Logística.
 */
export function CentralVendasPage() {
  const { can } = useAuth()

  return (
    <div>
      <PageHeader
        title="Central de Vendas"
        subtitle="A operação do atendente: solicitações do campo, pós-venda e missões"
      />

      {/* As três frentes do atendente num lugar só. Pós-venda e missões já
          existiam como páginas próprias e são REUSADAS aqui — duplicá-las faria
          duas telas divergirem com o tempo. */}
      <Tabs defaultValue="solicitacoes">
        <TabsList>
          <TabsTrigger value="solicitacoes">Solicitações</TabsTrigger>
          {can('missao.view') && <TabsTrigger value="missoes">Missões</TabsTrigger>}
          {can('posvenda.view') && <TabsTrigger value="posvenda">Pós-venda</TabsTrigger>}
        </TabsList>

        <TabsContent value="solicitacoes"><FilaSolicitacoes /></TabsContent>
        {can('missao.view') && <TabsContent value="missoes"><MissoesPage /></TabsContent>}
        {can('posvenda.view') && <TabsContent value="posvenda"><PosVendaPage /></TabsContent>}
      </Tabs>
    </div>
  )
}

/** A fila propriamente dita — o que era a página inteira antes das abas. */
function FilaSolicitacoes() {
  const [aberta, setAberta] = useState<number | null>(null)
  const fila = useSolicitacoes('pendente')
  const solicitacoes = fila.data ?? []

  const totalPedido = solicitacoes.reduce((acc, s) => acc + Number(s.desconto_solicitado ?? 0), 0)

  return (
    <div className="pt-3">

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
        <StatCard titulo="Aguardando decisão" valor={String(solicitacoes.length)} icon={ShoppingCart} accent="primary" />
        <StatCard titulo="Desconto pedido" valor={brl(totalPedido)} icon={BadgePercent} accent="destructive" />
        <StatCard
          titulo="Mais antiga"
          valor={solicitacoes.length > 0 ? dataHora(solicitacoes[0].created_at) : '—'}
          icon={TriangleAlert}
          accent="neutral"
        />
      </div>

      <AsyncState
        loading={fila.isLoading}
        error={fila.error}
        empty={solicitacoes.length === 0}
        emptyTitle="Nenhuma solicitação"
        emptyDescription="O campo não pediu nada aguardando decisão."
      >
        <div className="space-y-2">
          {solicitacoes.map((s) => (
            <SolicitacaoCard key={s.id} solicitacao={s} onAbrir={() => setAberta(s.id)} />
          ))}
        </div>
      </AsyncState>

      <DecisaoDialog id={aberta} onClose={() => setAberta(null)} />
    </div>
  )
}

function SolicitacaoCard({ solicitacao: s, onAbrir }: { solicitacao: Solicitacao; onAbrir: () => void }) {
  const itens = s.itens ?? []
  const bruto = itens.reduce((acc, i) => acc + Number(i.quantidade) * Number(i.preco_unitario), 0)

  return (
    <Card>
      <CardContent className="p-3 flex items-center justify-between gap-3">
        <div className="min-w-0">
          <div className="font-medium truncate">{s.cliente?.nome ?? 'Cliente sem nome'}</div>
          <div className="text-xs text-muted-foreground truncate">
            {s.solicitante?.name ?? 'Vendedor'} · {dataHora(s.created_at)} · {itens.length} item(ns)
          </div>
          {s.justificativa && (
            <div className="text-xs text-muted-foreground mt-1 truncate italic">"{s.justificativa}"</div>
          )}
        </div>
        <div className="flex items-center gap-3 shrink-0">
          <div className="text-right">
            <div className="text-sm font-semibold">{brl(bruto)}</div>
            <Badge variant="destructive">−{brl(Number(s.desconto_solicitado ?? 0))}</Badge>
          </div>
          <Button size="sm" onClick={onAbrir}>Analisar</Button>
        </div>
      </CardContent>
    </Card>
  )
}

function DecisaoDialog({ id, onClose }: { id: number | null; onClose: () => void }) {
  const detalhe = useSolicitacao(id)
  const aprovar = useAprovar()
  const recusar = useRecusar()
  const faturar = useFaturar()

  const [desconto, setDesconto] = useState('')
  const [motivo, setMotivo] = useState('')

  const s = detalhe.data?.data
  const alcada = detalhe.data?.alcada

  const fechar = () => { setDesconto(''); setMotivo(''); onClose() }

  const onAprovar = async () => {
    if (id === null) return
    try {
      await aprovar.mutateAsync({
        id,
        // Vazio = aprova o que foi pedido; preenchido = contraproposta.
        desconto_aprovado: desconto === '' ? null : Number(desconto.replace(',', '.')),
        motivo: motivo || undefined,
      })
      toast.success('Solicitação aprovada — pedido gerado.')
      fechar()
    } catch (e: any) {
      // 422 do DomainException: recusa de regra (piso do produto, por exemplo).
      toast.error(e?.response?.data?.message ?? 'Não foi possível aprovar.')
    }
  }

  const onRecusar = async () => {
    if (id === null) return
    try {
      await recusar.mutateAsync({ id, motivo: motivo || undefined })
      toast.success('Solicitação recusada.')
      fechar()
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Não foi possível recusar.')
    }
  }

  const onFaturar = async () => {
    if (id === null) return
    try {
      await faturar.mutateAsync({ id })
      toast.success('Pedido faturado.')
      fechar()
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Não foi possível faturar.')
    }
  }

  return (
    <Dialog open={id !== null} onOpenChange={(o) => !o && fechar()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Solicitação de venda</DialogTitle>
        </DialogHeader>

        <AsyncState loading={detalhe.isLoading} error={detalhe.error}>
          {s && (
            <div className="space-y-3">
              <div>
                <div className="font-medium">{s.cliente?.nome ?? '—'}</div>
                <div className="text-xs text-muted-foreground">
                  Pedido por {s.solicitante?.name ?? '—'} em {dataHora(s.created_at)}
                </div>
              </div>

              {s.justificativa && (
                <div className="text-sm bg-muted/50 rounded p-2 italic">"{s.justificativa}"</div>
              )}

              <div className="space-y-1">
                {(s.itens ?? []).map((i, idx) => (
                  <div key={idx} className="flex justify-between text-sm">
                    <span>{i.quantidade} × produto #{i.produto_id}</span>
                    <span>{brl(Number(i.quantidade) * Number(i.preco_unitario))}</span>
                  </div>
                ))}
              </div>

              {/* O que o vendedor já poderia conceder sozinho — evita o atendente
                  aprovar por reflexo algo que nem precisava passar por aqui. */}
              {alcada && (
                <div className="text-xs rounded border p-2 space-y-1">
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">Alçada do vendedor</span>
                    <span>{brl(alcada.teto_do_solicitante)}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">Pediu</span>
                    <span>{brl(alcada.desconto_solicitado)}</span>
                  </div>
                  {alcada.excede_em > 0 && (
                    <div className="flex justify-between font-medium text-destructive">
                      <span>Excede em</span>
                      <span>{brl(alcada.excede_em)}</span>
                    </div>
                  )}
                </div>
              )}

              {s.situacao === 'pendente' && (
                <>
                  <Field label="Desconto a conceder (vazio = o pedido)">
                    <Input
                      value={desconto}
                      onChange={(e) => setDesconto(e.target.value)}
                      placeholder={String(s.desconto_solicitado)}
                      inputMode="decimal"
                    />
                  </Field>
                  <Field label="Motivo da decisão">
                    <Input value={motivo} onChange={(e) => setMotivo(e.target.value)} placeholder="Opcional" />
                  </Field>
                </>
              )}

              {s.situacao === 'aprovada' && (
                <div className="text-sm">
                  Aprovada com {brl(Number(s.desconto_aprovado ?? 0))}
                  {s.pedido && <> — pedido #{s.pedido.id}</>}
                </div>
              )}
            </div>
          )}
        </AsyncState>

        <DialogFooter>
          <DialogClose asChild><Button variant="outline">Fechar</Button></DialogClose>

          {s?.situacao === 'pendente' && (
            <Can permission="venda.aprovar">
              <Button variant="outline" onClick={onRecusar} disabled={recusar.isPending}>
                <XCircle size={15} className="mr-1" /> Recusar
              </Button>
              <Button onClick={onAprovar} disabled={aprovar.isPending}>
                <CheckCircle2 size={15} className="mr-1" /> Aprovar
              </Button>
            </Can>
          )}

          {s?.situacao === 'aprovada' && (
            <Can permission="venda.faturar">
              <Button onClick={onFaturar} disabled={faturar.isPending}>
                <Receipt size={15} className="mr-1" /> Faturar
              </Button>
            </Can>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
