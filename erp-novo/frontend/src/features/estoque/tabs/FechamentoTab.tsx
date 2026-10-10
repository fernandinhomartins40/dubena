import { useState } from 'react'
import { Lock } from 'lucide-react'
import { Button, Card, CardContent, DataTable, type Column, EmptyState, Field, Input, AsyncSelect, toast } from '@/components/ui'
import { useFechamentos, useFechar, type FechamentoRow } from '../api'
import { data as fmtData, qtd } from '@/lib/format'

/**
 * Fechamento: fotografia do saldo de um produto num setor, numa data.
 *
 * É por setor × produto porque é assim que o servidor o registra (saldo inicial
 * = final do fechamento anterior daquele par). A tela antiga fechava "o estoque
 * até uma data", sem dizer de quê, e oferecia "reabrir período" — operação que
 * não existe: o fechamento é um registro, não uma trava sobre os movimentos.
 */
export function FechamentoTab() {
  const { data, isLoading, error, refetch } = useFechamentos()
  const fechar = useFechar()
  const [setor, setSetor] = useState<number | null>(null); const [setorL, setSetorL] = useState<string | null>(null)
  const [produto, setProduto] = useState<number | null>(null); const [produtoL, setProdutoL] = useState<string | null>(null)
  const [dataF, setDataF] = useState('')

  async function onFechar() {
    if (!setor || !produto || !dataF) { toast.error('Informe setor, produto e data do fechamento.'); return }
    try {
      await fechar.mutateAsync({ setor_id: setor, produto_id: produto, data_fechamento: dataF })
      toast.success('Fechamento registrado.'); setProduto(null); setProdutoL(null)
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao fechar.') }
  }

  const columns: Column<FechamentoRow>[] = [
    { key: 'id', header: 'Nº', cell: (r) => `#${r.id}` },
    { key: 'data', header: 'Fechamento', cell: (r) => fmtData(r.data_fechamento.slice(0, 10)) },
    { key: 'setor', header: 'Setor', cell: (r) => r.setor?.descricao ?? `#${r.setor_id}` },
    { key: 'produto', header: 'Produto', cell: (r) => <span className="font-medium">{r.produto?.descricao ?? `#${r.produto_id}`}</span> },
    { key: 'inicial', header: 'Saldo inicial', align: 'right', cell: (r) => <span className="tabular-nums text-muted-foreground">{qtd(Number(r.saldo_inicial))}</span> },
    { key: 'final', header: 'Saldo final', align: 'right', cell: (r) => <span className="tabular-nums font-medium">{qtd(Number(r.saldo_final))}</span> },
  ]
  return (
    <>
      <Card className="mb-4"><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        <Field label="Setor" required><AsyncSelect endpoint="/lookups/setores" value={setor} valueLabel={setorL} onChange={(id, o) => { setSetor(id); setSetorL(o?.label ?? null) }} /></Field>
        <Field label="Produto" required><AsyncSelect endpoint="/lookups/produtos" value={produto} valueLabel={produtoL} onChange={(id, o) => { setProduto(id); setProdutoL(o?.label ?? null) }} /></Field>
        <Field label="Data do fechamento" required><Input type="date" value={dataF} onChange={(e) => setDataF(e.target.value)} /></Field>
        <div><Button loading={fechar.isPending} onClick={onFechar}><Lock size={16} /> Registrar fechamento</Button></div>
      </CardContent></Card>
      <DataTable columns={columns} rows={data} loading={isLoading} error={error} onRetry={() => { void refetch() }} rowKey={(r) => r.id} empty={<EmptyState icon={<Lock />} title="Nenhum fechamento" />} />
    </>
  )
}
