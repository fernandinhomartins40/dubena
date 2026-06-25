import { useState } from 'react'
import { Lock, Unlock, Wallet } from 'lucide-react'
import { Button, Card, CardContent, Badge, DataTable, type Column, EmptyState, toast } from '@/components/ui'
import { useContasCaixa, useMovimentosCaixa, useAbrirCaixa, useFecharCaixa, type ContaCaixa } from '../api'
import { brl, data as fmtData } from '@/lib/format'

export function CaixaTab() {
  const { data: contas, isLoading } = useContasCaixa()
  const [sel, setSel] = useState<ContaCaixa | null>(null)
  const { data: mov } = useMovimentosCaixa(sel?.id ?? null)
  const abrir = useAbrirCaixa(); const fechar = useFecharCaixa()

  async function toggle(c: ContaCaixa) {
    const agora = new Date().toISOString().slice(0, 19).replace('T', ' ')
    try {
      if (Number(c.fechado)) { await abrir.mutateAsync({ contaId: c.id, datahoraabertura: agora }); toast.success('Caixa aberto.') }
      else { await fechar.mutateAsync({ contaId: c.id, datahorafechamento: agora }); toast.success('Caixa fechado.') }
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro na operação do caixa.') }
  }

  const columns: Column<ContaCaixa>[] = [
    { key: 'descricao', header: 'Caixa / Conta', cell: (c) => <span className="font-medium">{c.descricao}</span> },
    { key: 'saldo', header: 'Saldo', align: 'right', cell: (c) => <span className="tabular-nums">{brl(c.saldoatual)}</span> },
    { key: 'status', header: 'Status', align: 'center', cell: (c) => Number(c.fechado) ? <Badge variant="secondary">Fechado</Badge> : <Badge variant="success">Aberto</Badge> },
    {
      key: 'acoes', header: '', align: 'right', cell: (c) => (
        <div className="flex justify-end gap-2" onClick={(e) => e.stopPropagation()}>
          <Button variant="outline" size="sm" onClick={() => toggle(c)}>{Number(c.fechado) ? <><Unlock size={14} /> Abrir</> : <><Lock size={14} /> Fechar</>}</Button>
        </div>
      ),
    },
  ]

  return (
    <div className="grid gap-4 lg:grid-cols-2">
      <div>
        <DataTable columns={columns} rows={contas} loading={isLoading} rowKey={(c) => c.id} onRowClick={(c) => setSel(c)}
          empty={<EmptyState icon={<Wallet />} title="Nenhum caixa" />} />
      </div>
      <Card><CardContent className="pt-6">
        <p className="font-medium mb-1">{sel ? `Movimentos · ${sel.descricao}` : 'Selecione um caixa'}</p>
        {sel && <p className="text-sm text-muted-foreground mb-3">Saldo atual: <span className="tabular-nums font-medium">{brl(mov?.saldo ?? sel.saldoatual)}</span></p>}
        {sel && mov?.data?.length ? (
          <div className="space-y-2 max-h-[420px] overflow-y-auto">
            {mov.data.map((m: any) => (
              <div key={m.id} className="flex items-center justify-between border-b border-border/60 pb-2 text-sm">
                <div><div>{m.descricao || m.origem}</div><div className="text-xs text-muted-foreground">{fmtData(m.datahorabaixa)}</div></div>
                <span className={`tabular-nums font-medium ${m.pagarreceber === 'R' ? 'text-success' : 'text-destructive'}`}>{m.pagarreceber === 'R' ? '+' : '−'} {brl(Math.abs(m.valorefetivado))}</span>
              </div>
            ))}
          </div>
        ) : sel ? <EmptyState icon={<Wallet />} title="Sem movimentos" /> : null}
      </CardContent></Card>
    </div>
  )
}
