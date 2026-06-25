import { useState } from 'react'
import { Search, Plus, TrendingUp, TrendingDown, Wallet } from 'lucide-react'
import {
  Button, Card, CardContent, Input, Badge, DataTable, type Column, EmptyState, Field,
  AsyncSelect, Select, SelectTrigger, SelectValue, SelectContent, SelectItem, FormDialog, toast,
} from '@/components/ui'
import { useLancamentos, useResumoFinanceiro, useCriarLancamento, type Lancamento } from '../api'
import { brl, data as fmtData } from '@/lib/format'

export function LancamentosTab() {
  const [pr, setPr] = useState(''); const [status, setStatus] = useState('aberto')
  const [busca, setBusca] = useState(''); const [q, setQ] = useState(''); const [page, setPage] = useState(1)
  const { data, isLoading, isFetching } = useLancamentos(pr, status, q, page)
  const { data: resumo } = useResumoFinanceiro()

  const columns: Column<Lancamento>[] = [
    { key: 'cliente', header: 'Cliente', cell: (l) => <div><div className="font-medium">{l.cliente ?? '—'}</div><div className="text-xs text-muted-foreground">{l.descricao || l.documento || ''}</div></div> },
    { key: 'pr', header: 'Tipo', align: 'center', width: 'w-24', cell: (l) => l.pagarreceber === 'R' ? <Badge variant="success">Receber</Badge> : <Badge variant="destructive">Pagar</Badge> },
    { key: 'venc', header: 'Vencimento', cell: (l) => fmtData(l.datavencimento) },
    { key: 'valor', header: 'Valor', align: 'right', cell: (l) => <span className="tabular-nums">{brl(l.valorefetivado)}</span> },
    { key: 'status', header: 'Status', align: 'center', width: 'w-28', cell: (l) => Number(l.baixado) ? <Badge variant="secondary">Baixado</Badge> : <Badge variant="warning">Aberto</Badge> },
  ]

  return (
    <>
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
        <CardResumo titulo="A receber (aberto)" valor={resumo?.receber_aberto} icon={<TrendingUp className="text-success" />} />
        <CardResumo titulo="Recebido" valor={resumo?.receber_baixado} icon={<TrendingUp className="text-muted-foreground" />} />
        <CardResumo titulo="A pagar (aberto)" valor={resumo?.pagar_aberto} icon={<TrendingDown className="text-destructive" />} />
        <CardResumo titulo="Pago" valor={resumo?.pagar_baixado} icon={<TrendingDown className="text-muted-foreground" />} />
      </div>

      <Card className="mb-4 p-3"><div className="flex flex-wrap gap-2 items-center">
        <Select value={pr || 'todos'} onValueChange={(v) => { setPage(1); setPr(v === 'todos' ? '' : v) }}>
          <SelectTrigger className="w-40"><SelectValue /></SelectTrigger>
          <SelectContent><SelectItem value="todos">Receber + Pagar</SelectItem><SelectItem value="R">A receber</SelectItem><SelectItem value="P">A pagar</SelectItem></SelectContent>
        </Select>
        <Select value={status} onValueChange={(v) => { setPage(1); setStatus(v) }}>
          <SelectTrigger className="w-36"><SelectValue /></SelectTrigger>
          <SelectContent><SelectItem value="aberto">Em aberto</SelectItem><SelectItem value="baixado">Baixados</SelectItem><SelectItem value="todos">Todos</SelectItem></SelectContent>
        </Select>
        <div className="relative flex-1 min-w-[200px]">
          <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
          <Input value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Buscar cliente, documento ou descrição…" className="pl-9" onKeyDown={(e) => e.key === 'Enter' && setQ(busca)} />
        </div>
        <Button variant="secondary" onClick={() => { setPage(1); setQ(busca) }}>Buscar</Button>
        <NovoLancamentoDialog />
      </div></Card>

      <DataTable columns={columns} rows={data?.data} loading={isLoading} rowKey={(l) => l.id}
        page={data?.meta.current_page} lastPage={data?.meta.last_page} onPageChange={setPage} fetching={isFetching}
        empty={<EmptyState icon={<Wallet />} title="Nenhum lançamento" />} />
    </>
  )
}

function CardResumo({ titulo, valor, icon }: { titulo: string; valor?: number; icon: React.ReactNode }) {
  return (
    <Card><CardContent className="pt-6">
      <div className="flex items-center justify-between"><p className="text-sm text-muted-foreground">{titulo}</p><span className="[&_svg]:size-5">{icon}</span></div>
      <p className="mt-1 text-2xl font-bold tabular-nums">{valor != null ? brl(valor) : '—'}</p>
    </CardContent></Card>
  )
}

function NovoLancamentoDialog() {
  const criar = useCriarLancamento()
  const [open, setOpen] = useState(false)
  const [f, setF] = useState<any>({ pagarreceber: 'R' })
  const [labels, setLabels] = useState<Record<string, string | null>>({})
  const set = (k: string, v: any) => setF((s: any) => ({ ...s, [k]: v }))

  async function salvar() {
    const req = ['cliente_id', 'valor', 'dataemissao', 'datacompetencia', 'datavencimento', 'planoconta_id', 'centrocusto_id', 'condicaopagamento_id']
    if (req.some((k) => !f[k])) { toast.error('Preencha cliente, valor, datas, plano, centro e condição.'); return }
    try {
      await criar.mutateAsync({ ...f, valor: Number(f.valor) })
      toast.success('Lançamento criado.'); setOpen(false); setF({ pagarreceber: 'R' })
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao criar lançamento.') }
  }

  return (
    <>
      <Button onClick={() => setOpen(true)}><Plus size={16} /> Novo lançamento</Button>
      <FormDialog open={open} onOpenChange={setOpen} title="Novo lançamento" widthClass="max-w-2xl" loading={criar.isPending} onConfirm={salvar}>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <Field label="Tipo" required>
            <Select value={f.pagarreceber} onValueChange={(v) => set('pagarreceber', v)}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent><SelectItem value="R">A receber</SelectItem><SelectItem value="P">A pagar</SelectItem></SelectContent>
            </Select>
          </Field>
          <Field label="Cliente / Fornecedor" required><AsyncSelect endpoint="/lookups/clientes-fornecedores" value={f.cliente_id ?? null} valueLabel={labels.cli} onChange={(id, o) => { set('cliente_id', id); setLabels((l) => ({ ...l, cli: o?.label ?? null })) }} /></Field>
          <Field label="Valor" required><Input type="number" step="0.01" value={f.valor ?? ''} onChange={(e) => set('valor', e.target.value)} /></Field>
          <Field label="Documento"><Input value={f.documento ?? ''} onChange={(e) => set('documento', e.target.value)} /></Field>
          <Field label="Emissão" required><Input type="date" value={f.dataemissao ?? ''} onChange={(e) => set('dataemissao', e.target.value)} /></Field>
          <Field label="Competência" required><Input type="date" value={f.datacompetencia ?? ''} onChange={(e) => set('datacompetencia', e.target.value)} /></Field>
          <Field label="Vencimento" required><Input type="date" value={f.datavencimento ?? ''} onChange={(e) => set('datavencimento', e.target.value)} /></Field>
          <Field label="Condição de pagamento" required><AsyncSelect endpoint="/lookups/condicoes-pagamento" value={f.condicaopagamento_id ?? null} valueLabel={labels.cond} onChange={(id, o) => { set('condicaopagamento_id', id); setLabels((l) => ({ ...l, cond: o?.label ?? null })) }} /></Field>
          <Field label="Plano de contas" required><AsyncSelect endpoint="/lookups/planos-conta" value={f.planoconta_id ?? null} valueLabel={labels.pc} onChange={(id, o) => { set('planoconta_id', id); setLabels((l) => ({ ...l, pc: o?.label ?? null })) }} /></Field>
          <Field label="Centro de custo" required><AsyncSelect endpoint="/lookups/centros-custo" value={f.centrocusto_id ?? null} valueLabel={labels.cc} onChange={(id, o) => { set('centrocusto_id', id); setLabels((l) => ({ ...l, cc: o?.label ?? null })) }} /></Field>
          <Field label="Descrição" className="md:col-span-2"><Input value={f.descricao ?? ''} onChange={(e) => set('descricao', e.target.value)} /></Field>
        </div>
      </FormDialog>
    </>
  )
}
