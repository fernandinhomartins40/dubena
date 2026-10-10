import { useState } from 'react'
import { Lock, LockOpen } from 'lucide-react'
import { Badge, Button, Card, CardContent, DataTable, type Column, EmptyState, Field, Input, AsyncSelect, FormDialog, toast } from '@/components/ui'
import { useFechamentos, useFechar, useReabrirFechamento, type FechamentoRow } from '../api'
import { data as fmtData, qtd } from '@/lib/format'

const hoje = () => new Date().toLocaleDateString('en-CA') // AAAA-MM-DD no fuso local

/**
 * Fechamento: retrato do saldo de um produto num setor, numa data — e trava.
 *
 * Enquanto o fechamento vale, o par setor × produto não recebe movimento até a
 * data fechada (inclusive): nem venda, nem nota, nem transferência, nem acerto.
 * Reabrir destrava e exige motivo, que fica na trilha de auditoria.
 *
 * É por setor × produto porque é assim que o servidor o registra (o saldo
 * inicial é o final do fechamento vigente anterior daquele par).
 */
export function FechamentoTab() {
  const { data, isLoading, error, refetch } = useFechamentos()
  const fechar = useFechar()
  const reabrir = useReabrirFechamento()
  const [setor, setSetor] = useState<number | null>(null); const [setorL, setSetorL] = useState<string | null>(null)
  const [produto, setProduto] = useState<number | null>(null); const [produtoL, setProdutoL] = useState<string | null>(null)
  const [dataF, setDataF] = useState(hoje)
  const [reabrindo, setReabrindo] = useState<FechamentoRow | null>(null); const [motivo, setMotivo] = useState('')

  async function onFechar() {
    if (!setor || !produto || !dataF) { toast.error('Informe setor, produto e data do fechamento.'); return }
    if (dataF > hoje()) { toast.error('Não é possível fechar o estoque em data futura.'); return }
    try {
      await fechar.mutateAsync({ setor_id: setor, produto_id: produto, data_fechamento: dataF })
      toast.success('Fechamento registrado.'); setProduto(null); setProdutoL(null)
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao fechar.') }
  }

  async function onReabrir() {
    if (!reabrindo) return
    if (motivo.trim().length < 3) { toast.error('Informe o motivo da reabertura.'); return }
    try {
      await reabrir.mutateAsync({ id: reabrindo.id, motivo: motivo.trim() })
      toast.success('Fechamento reaberto.'); setReabrindo(null); setMotivo('')
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao reabrir.') }
  }

  const columns: Column<FechamentoRow>[] = [
    { key: 'id', header: 'Nº', cell: (r) => `#${r.id}` },
    { key: 'data', header: 'Fechado até', cell: (r) => fmtData(r.data_fechamento.slice(0, 10)) },
    { key: 'setor', header: 'Setor', cell: (r) => r.setor?.descricao ?? `#${r.setor_id}` },
    { key: 'produto', header: 'Produto', cell: (r) => <span className="font-medium">{r.produto?.descricao ?? `#${r.produto_id}`}</span> },
    { key: 'inicial', header: 'Saldo inicial', align: 'right', cell: (r) => <span className="tabular-nums text-muted-foreground">{qtd(Number(r.saldo_inicial))}</span> },
    { key: 'final', header: 'Saldo final', align: 'right', cell: (r) => <span className="tabular-nums font-medium">{qtd(Number(r.saldo_final))}</span> },
    { key: 'situacao', header: 'Situação', cell: (r) => r.aberto ? <Badge variant="warning">Reaberto</Badge> : <Badge variant="success">Fechado</Badge> },
    { key: 'acoes', header: '', align: 'right', cell: (r) => !r.aberto && <Button variant="outline" size="sm" onClick={() => { setMotivo(''); setReabrindo(r) }}><LockOpen size={14} /> Reabrir</Button> },
  ]
  return (
    <>
      <Card className="mb-4"><CardContent className="pt-6 space-y-3">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
          <Field label="Setor" required><AsyncSelect endpoint="/lookups/setores" value={setor} valueLabel={setorL} onChange={(id, o) => { setSetor(id); setSetorL(o?.label ?? null) }} /></Field>
          <Field label="Produto" required><AsyncSelect endpoint="/lookups/produtos" value={produto} valueLabel={produtoL} onChange={(id, o) => { setProduto(id); setProdutoL(o?.label ?? null) }} /></Field>
          <Field label="Fechar até" required><Input type="date" max={hoje()} value={dataF} onChange={(e) => setDataF(e.target.value)} /></Field>
          <div><Button loading={fechar.isPending} onClick={onFechar}><Lock size={16} /> Fechar estoque</Button></div>
        </div>
        <p className="text-xs text-muted-foreground">Depois de fechado, este produto neste setor não recebe movimento até a data informada — nem venda, nota, transferência ou acerto. Para movimentar antes disso, reabra o fechamento.</p>
      </CardContent></Card>
      <FormDialog open={reabrindo !== null} onOpenChange={(next) => { if (!next) { setReabrindo(null); setMotivo('') } }} title={`Reabrir fechamento #${reabrindo?.id ?? ''}`}
        dirty={!!motivo} loading={reabrir.isPending} onConfirm={onReabrir} confirmLabel="Reabrir">
          <div className="space-y-3">
            <p className="text-sm text-muted-foreground">Reabrir libera os movimentos de <strong>{reabrindo?.produto?.descricao}</strong> em <strong>{reabrindo?.setor?.descricao}</strong>. O saldo que foi dado por conferido poderá mudar.</p>
            <Field label="Motivo" required><Input maxLength={255} value={motivo} onChange={(e) => setMotivo(e.target.value)} /></Field>
          </div>
      </FormDialog>
      <DataTable columns={columns} rows={data} loading={isLoading} error={error} onRetry={() => { void refetch() }} rowKey={(r) => r.id} empty={<EmptyState icon={<Lock />} title="Nenhum fechamento" />} />
    </>
  )
}
