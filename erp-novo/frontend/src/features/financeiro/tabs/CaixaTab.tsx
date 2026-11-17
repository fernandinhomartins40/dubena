import { useState } from 'react'
import { FileText, Lock, Undo2, Unlock, Wallet } from 'lucide-react'
import { AsyncState, Button, Card, CardContent, Badge, ConfirmDialog, DataTable, type Column, EmptyState, toast } from '@/components/ui'
import {
  useContasCaixa, useMovimentosCaixa, useAbrirCaixa, useFecharCaixa, useEstornarMovimento,
  type ContaCaixa, type MovimentoCaixa,
} from '../api'
import { brl, dataHora, agoraLocal } from '@/lib/format'
import { useAuth } from '@/lib/auth'
import { abrirPdf } from '@/lib/pdf'

/**
 * Três estados, um botão cada (P2-D-09). "Sem abertura" é a conta que nunca
 * teve o caixa aberto pelo sistema (conta nova, conta migrada): antes ela
 * aparecia "Aberto", o Fechar respondia "Não há caixa aberto para fechar" e
 * não havia Abrir — um beco sem saída. O Abrir registra a abertura e daí o
 * ciclo normal segue.
 */
function estadoDa(c: ContaCaixa): 'ABERTO' | 'FECHADO' | 'SEM_ABERTURA' {
  return c.estado ?? (Number(c.fechado) ? 'FECHADO' : 'ABERTO')
}

export function CaixaTab() {
  const { data: contas, isLoading, error, refetch } = useContasCaixa()
  const { can } = useAuth()
  const [sel, setSel] = useState<ContaCaixa | null>(null)
  const movimentos = useMovimentosCaixa(sel?.id ?? null)
  const mov = movimentos.data
  const abrir = useAbrirCaixa(); const fechar = useFecharCaixa(); const estornar = useEstornarMovimento()
  const [estornando, setEstornando] = useState<MovimentoCaixa | null>(null)

  async function toggle(c: ContaCaixa) {
    const agora = agoraLocal()
    try {
      if (estadoDa(c) === 'ABERTO') { await fechar.mutateAsync({ contaId: c.id, datahorafechamento: agora }); toast.success('Caixa fechado.') }
      else { await abrir.mutateAsync({ contaId: c.id, datahoraabertura: agora }); toast.success('Caixa aberto.') }
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro na operação do caixa.') }
  }

  async function onEstornar() {
    if (!estornando) return
    try {
      await estornar.mutateAsync(estornando.id)
      toast.success('Estorno registrado.'); setEstornando(null)
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Não foi possível estornar.') }
  }

  async function recibo(m: MovimentoCaixa) {
    try { await abrirPdf(`/caixa/movimentos/${m.id}/recibo`) }
    catch { toast.error('Não foi possível abrir o recibo.') }
  }

  const columns: Column<ContaCaixa>[] = [
    { key: 'descricao', header: 'Caixa / Conta', cell: (c) => <span className="font-medium">{c.descricao}</span> },
    { key: 'saldo', header: 'Saldo', align: 'right', cell: (c) => <span className="tabular-nums">{brl(c.saldoatual)}</span> },
    {
      key: 'status', header: 'Status', align: 'center', cell: (c) => {
        const e = estadoDa(c)
        return e === 'FECHADO' ? <Badge variant="secondary">Fechado</Badge>
          : e === 'SEM_ABERTURA' ? <Badge variant="warning" title="Este caixa nunca foi aberto pelo sistema: abra para começar a controlar o dia.">Sem abertura</Badge>
          : <Badge variant="success">Aberto</Badge>
      },
    },
    {
      key: 'acoes', header: '', align: 'right', cell: (c) => (
        <div className="flex justify-end gap-2" onClick={(e) => e.stopPropagation()}>
          <Button variant="outline" size="sm" disabled={abrir.isPending || fechar.isPending} onClick={() => toggle(c)}>
            {estadoDa(c) === 'ABERTO' ? <><Lock size={14} /> Fechar caixa</> : <><Unlock size={14} /> Abrir caixa</>}
          </Button>
        </div>
      ),
    },
  ]

  return (
    <div className="grid gap-4 lg:grid-cols-2">
      <div>
        <DataTable columns={columns} rows={contas} loading={isLoading} error={error} onRetry={() => { void refetch() }} rowKey={(c) => c.id} onRowClick={(c) => setSel(c)}
          empty={<EmptyState icon={<Wallet />} title="Nenhum caixa" />} />
      </div>
      <Card><CardContent className="pt-6">
        <p className="font-medium mb-1">{sel ? `Movimentos · ${sel.descricao}` : 'Selecione um caixa'}</p>
        {sel && <AsyncState loading={movimentos.isLoading} error={movimentos.error} onRetry={() => { void movimentos.refetch() }}>
        {/* Os campos são os de `contamovimentos` (datahora, valor ASSINADO).
            Antes a tela lia `datahorabaixa`, `valorefetivado` e `saldo` — que a
            API nunca devolveu: o saldo era sempre "—", a data vazia e o valor
            "R$ NaN"; o sinal vinha de `pagarreceber`, nulo em abertura,
            transferência e estorno (entrada aparecia como saída). */}
        <p className="text-sm text-muted-foreground mb-3">Saldo atual: <span className="tabular-nums font-medium">{mov?.saldo == null ? '—' : brl(mov.saldo)}</span></p>
        {mov?.data?.length ? (
          <ul className="space-y-2 max-h-[420px] overflow-y-auto">
            {mov.data.map((m) => {
              const valor = Number(m.valor)
              const podeEstornar = can('caixa.estornar') && m.tipo !== 'ESTORNO' && !m.estorno_de_id && !m.estornado
              return (
                <li key={m.id} data-testid={`movimento-${m.id}`} className="flex items-center justify-between gap-2 border-b border-border/60 pb-2 text-sm">
                  <div className="min-w-0">
                    <div className="truncate">{m.descricao || m.origem || m.tipo}{m.estornado ? <Badge variant="secondary" className="ml-2">estornado</Badge> : null}</div>
                    <div className="text-xs text-muted-foreground">{dataHora(m.datahora)}</div>
                  </div>
                  <div className="flex shrink-0 items-center gap-1">
                    <span className={`tabular-nums font-medium ${valor >= 0 ? 'text-success' : 'text-destructive'}`}>{valor >= 0 ? '+' : '−'} {brl(Math.abs(valor))}</span>
                    <Button variant="ghost" size="icon" aria-label={`Recibo do movimento ${m.id}`} title="Recibo" onClick={() => { void recibo(m) }}><FileText size={15} /></Button>
                    {podeEstornar && <Button variant="ghost" size="icon" aria-label={`Estornar movimento ${m.id}`} title="Estornar" onClick={() => setEstornando(m)}><Undo2 size={15} /></Button>}
                  </div>
                </li>
              )
            })}
          </ul>
        ) : <EmptyState icon={<Wallet />} title="Sem movimentos" />}
        </AsyncState>}
      </CardContent></Card>
      <ConfirmDialog open={!!estornando} onOpenChange={(o) => !o && setEstornando(null)}
        title="Estornar movimento"
        description={<>Lançar o inverso de <strong>{brl(Math.abs(Number(estornando?.valor ?? 0)))}</strong> ({estornando?.descricao})?
          Se for o recebimento de um título, ele volta a ficar em aberto. O lançamento original continua no histórico.</>}
        confirmLabel="Estornar" loading={estornar.isPending} onConfirm={onEstornar} />
    </div>
  )
}
