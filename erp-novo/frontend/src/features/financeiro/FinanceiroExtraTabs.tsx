import { useState } from 'react'
import { Search, Plus, Pencil, Trash2, TrendingUp, Wallet } from 'lucide-react'
import {
  Button, Card, CardContent, Input, Badge, DataTable, type Column, EmptyState, Field,
  AsyncSelect, Tabs, TabsList, TabsTrigger, TabsContent,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogClose, toast,
} from '@/components/ui'
import {
  useChequesEmitidos, useChequesRecebidos, useSalvarChequeRecebido, useExcluirChequeRecebido,
  useBoletos, useResumoBoletos, usePixStatus, useDRE, useConciliacao,
} from './api'

const brl = (n: number) => Number(n).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
const fmtData = (s: string | null) => (s ? new Date(s).toLocaleDateString('pt-BR') : '—')

// ---------- CHEQUES ----------
export function ChequesTab() {
  return (
    <Tabs defaultValue="recebidos">
      <TabsList><TabsTrigger value="recebidos">Recebidos</TabsTrigger><TabsTrigger value="emitidos">Emitidos</TabsTrigger></TabsList>
      <TabsContent value="recebidos"><ChequesRecebidosTab /></TabsContent>
      <TabsContent value="emitidos"><ChequesEmitidosTab /></TabsContent>
    </Tabs>
  )
}

function ChequesRecebidosTab() {
  const [busca, setBusca] = useState(''); const [q, setQ] = useState('')
  const { data, isLoading } = useChequesRecebidos(q)
  const salvar = useSalvarChequeRecebido(); const excluir = useExcluirChequeRecebido()
  const [edit, setEdit] = useState<any | null>(null); const [del, setDel] = useState<any | null>(null)
  const [labels, setLabels] = useState<Record<string, string | null>>({})

  async function onSalvar() {
    const req = ['numerocheque', 'valor', 'banco_id', 'chequesituacao_id', 'dataemissao', 'datavencimento']
    if (req.some((k) => !edit?.[k])) { toast.error('Preencha número, valor, banco, situação e datas.'); return }
    try { await salvar.mutateAsync({ ...edit, valor: Number(edit.valor) }); toast.success('Cheque salvo.'); setEdit(null) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') }
  }
  const columns: Column<any>[] = [
    { key: 'num', header: 'Número', cell: (c) => <span className="font-medium tabular-nums">{c.numerocheque}</span> },
    { key: 'valor', header: 'Valor', align: 'right', cell: (c) => <span className="tabular-nums">{brl(c.valor)}</span> },
    { key: 'venc', header: 'Vencimento', cell: (c) => fmtData(c.datavencimento) },
    { key: 'sit', header: 'Situação', cell: (c) => <Badge variant="secondary">{c.situacao || '—'}</Badge> },
    { key: 'acoes', header: '', align: 'right', width: 'w-24', cell: (c) => <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}><Button variant="ghost" size="icon" onClick={() => setEdit(c)}><Pencil size={16} /></Button><Button variant="ghost" size="icon" onClick={() => setDel(c)}><Trash2 size={16} /></Button></div> },
  ]
  return (
    <>
      <Card className="mb-4 p-3"><div className="flex gap-2">
        <Input value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Buscar nº do cheque…" onKeyDown={(e) => e.key === 'Enter' && setQ(busca)} />
        <Button variant="secondary" onClick={() => setQ(busca)}>Buscar</Button>
        <Button onClick={() => { setEdit({}); setLabels({}) }}><Plus size={16} /> Novo</Button>
      </div></Card>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(c) => c.id} onRowClick={(c) => setEdit(c)} empty={<EmptyState icon={<Wallet />} title="Nenhum cheque recebido" />} />
      <Dialog open={!!edit} onOpenChange={(o) => !o && setEdit(null)}>
        <DialogContent className="max-w-2xl">
          <DialogHeader><DialogTitle>{edit?.id ? 'Editar cheque' : 'Novo cheque recebido'}</DialogTitle></DialogHeader>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Field label="Número" required><Input value={edit?.numerocheque ?? ''} onChange={(e) => setEdit((s: any) => ({ ...s, numerocheque: e.target.value }))} /></Field>
            <Field label="Valor" required><Input type="number" step="0.01" value={edit?.valor ?? ''} onChange={(e) => setEdit((s: any) => ({ ...s, valor: e.target.value }))} /></Field>
            <Field label="Banco" required><AsyncSelect endpoint="/cadastros/bancos" value={edit?.banco_id ?? null} valueLabel={labels.banco} onChange={(id, o) => { setEdit((s: any) => ({ ...s, banco_id: id })); setLabels((l) => ({ ...l, banco: o?.label ?? null })) }} /></Field>
            <Field label="Situação" required><AsyncSelect endpoint="/cheques/situacoes" params={{ tipo: 'recebido' }} value={edit?.chequesituacao_id ?? null} valueLabel={labels.sit} onChange={(id, o) => { setEdit((s: any) => ({ ...s, chequesituacao_id: id })); setLabels((l) => ({ ...l, sit: o?.label ?? null })) }} /></Field>
            <Field label="Agência"><Input value={edit?.agencia ?? ''} onChange={(e) => setEdit((s: any) => ({ ...s, agencia: e.target.value }))} /></Field>
            <Field label="Conta"><Input value={edit?.numeroconta ?? ''} onChange={(e) => setEdit((s: any) => ({ ...s, numeroconta: e.target.value }))} /></Field>
            <Field label="Emissão" required><Input type="date" value={edit?.dataemissao ?? ''} onChange={(e) => setEdit((s: any) => ({ ...s, dataemissao: e.target.value }))} /></Field>
            <Field label="Vencimento" required><Input type="date" value={edit?.datavencimento ?? ''} onChange={(e) => setEdit((s: any) => ({ ...s, datavencimento: e.target.value }))} /></Field>
          </div>
          <DialogFooter><DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose><Button loading={salvar.isPending} onClick={onSalvar}>Salvar</Button></DialogFooter>
        </DialogContent>
      </Dialog>
      <Dialog open={!!del} onOpenChange={(o) => !o && setDel(null)}>
        <DialogContent>
          <DialogHeader><DialogTitle>Excluir cheque</DialogTitle></DialogHeader>
          <p className="text-sm text-muted-foreground">Excluir o cheque <strong>{del?.numerocheque}</strong>?</p>
          <DialogFooter><DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose><Button variant="destructive" loading={excluir.isPending} onClick={async () => { try { await excluir.mutateAsync(del!.id); toast.success('Excluído.') } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') } finally { setDel(null) } }}><Trash2 size={16} /> Excluir</Button></DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}

function ChequesEmitidosTab() {
  const [busca, setBusca] = useState(''); const [q, setQ] = useState('')
  const { data, isLoading } = useChequesEmitidos(q)
  const columns: Column<any>[] = [
    { key: 'num', header: 'Número', cell: (c) => <span className="font-medium tabular-nums">{c.numerocheque}</span> },
    { key: 'valor', header: 'Valor', align: 'right', cell: (c) => <span className="tabular-nums">{brl(c.valor)}</span> },
    { key: 'venc', header: 'Vencimento', cell: (c) => fmtData(c.datavencimento) },
    { key: 'sit', header: 'Situação', cell: (c) => <Badge variant="secondary">{c.situacao || '—'}</Badge> },
  ]
  return (
    <>
      <Card className="mb-4 p-3"><div className="flex gap-2">
        <Input value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Buscar nº do cheque…" onKeyDown={(e) => e.key === 'Enter' && setQ(busca)} />
        <Button variant="secondary" onClick={() => setQ(busca)}>Buscar</Button>
      </div></Card>
      <p className="text-xs text-muted-foreground mb-2">Cheques emitidos são gerados a partir do talão no fluxo de pagamento; aqui é consulta.</p>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(c) => c.id} empty={<EmptyState icon={<Wallet />} title="Nenhum cheque emitido" />} />
    </>
  )
}

// ---------- BOLETOS / PIX ----------
export function BoletosTab() {
  const [status, setStatus] = useState('pendente'); const [busca, setBusca] = useState(''); const [q, setQ] = useState('')
  const { data, isLoading } = useBoletos(status, q)
  const { data: resumo } = useResumoBoletos()
  const { data: pix } = usePixStatus()

  const columns: Column<any>[] = [
    { key: 'nn', header: 'Nosso número', cell: (b) => <span className="font-medium tabular-nums">{b.nossonumero || '—'}</span> },
    { key: 'cliente', header: 'Cliente', cell: (b) => b.cliente || '—' },
    { key: 'valor', header: 'Valor', align: 'right', cell: (b) => <span className="tabular-nums">{b.valor != null ? brl(b.valor) : '—'}</span> },
    { key: 'venc', header: 'Vencimento', cell: (b) => fmtData(b.datavencimento) },
    { key: 'status', header: 'Status', align: 'center', cell: (b) => Number(b.cancelado) ? <Badge variant="destructive">Cancelado</Badge> : Number(b.baixado) ? <Badge variant="secondary">Baixado</Badge> : <Badge variant="warning">Pendente</Badge> },
  ]
  return (
    <>
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
        <Card><CardContent className="pt-6"><p className="text-sm text-muted-foreground">Total</p><p className="mt-1 text-2xl font-bold tabular-nums">{resumo?.total ?? '—'}</p></CardContent></Card>
        <Card><CardContent className="pt-6"><p className="text-sm text-muted-foreground">Pendentes</p><p className="mt-1 text-2xl font-bold tabular-nums">{resumo?.pendentes ?? '—'}</p></CardContent></Card>
        <Card><CardContent className="pt-6"><p className="text-sm text-muted-foreground">Com remessa</p><p className="mt-1 text-2xl font-bold tabular-nums">{resumo?.com_remessa ?? '—'}</p></CardContent></Card>
        <Card><CardContent className="pt-6"><p className="text-sm text-muted-foreground">PIX</p><p className="mt-1 text-lg font-semibold">{pix?.configurado ? <Badge variant="success">Configurado</Badge> : <Badge variant="secondary">Não configurado</Badge>}</p></CardContent></Card>
      </div>
      <Card className="mb-4 p-3"><div className="flex flex-wrap gap-2 items-center">
        <Select value={status} onValueChange={setStatus}><SelectTrigger className="w-40"><SelectValue /></SelectTrigger><SelectContent><SelectItem value="pendente">Pendentes</SelectItem><SelectItem value="cancelado">Cancelados</SelectItem><SelectItem value="todos">Todos</SelectItem></SelectContent></Select>
        <div className="relative flex-1 min-w-[200px]"><Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" /><Input value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Buscar nosso número ou cliente…" className="pl-9" onKeyDown={(e) => e.key === 'Enter' && setQ(busca)} /></div>
        <Button variant="secondary" onClick={() => setQ(busca)}>Buscar</Button>
      </div></Card>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(b) => b.id} empty={<EmptyState icon={<Wallet />} title="Nenhum boleto" description="A geração de boletos (remessa CNAB) ocorre no fluxo de cobrança." />} />
    </>
  )
}

// ---------- DRE ----------
export function DRETab() {
  const hoje = new Date()
  const [inicio, setInicio] = useState(new Date(hoje.getFullYear(), hoje.getMonth(), 1).toISOString().slice(0, 10))
  const [fim, setFim] = useState(hoje.toISOString().slice(0, 10))
  const [run, setRun] = useState(false)
  const { data, isLoading } = useDRE(inicio, fim, run)

  return (
    <>
      <Card className="mb-4"><CardContent className="pt-6 flex flex-wrap items-end gap-3">
        <Field label="Início"><Input type="date" value={inicio} onChange={(e) => setInicio(e.target.value)} /></Field>
        <Field label="Fim"><Input type="date" value={fim} onChange={(e) => setFim(e.target.value)} /></Field>
        <Button onClick={() => setRun(true)}>Gerar DRE</Button>
      </CardContent></Card>
      {!run ? <EmptyState icon={<TrendingUp />} title="Informe o período e gere a DRE" /> : isLoading ? <p className="text-sm text-muted-foreground">Calculando…</p> : data && (
        <div className="grid gap-4 md:grid-cols-2">
          <Card><CardContent className="pt-6">
            <p className="font-medium text-success mb-2">Receitas</p>
            {data.receitas.length ? data.receitas.map((r: any, i: number) => <div key={i} className="flex justify-between text-sm border-b border-border/60 py-1"><span>{r.plano}</span><span className="tabular-nums">{brl(r.total)}</span></div>) : <p className="text-sm text-muted-foreground">Sem receitas no período.</p>}
            <div className="flex justify-between font-semibold mt-2"><span>Total</span><span className="tabular-nums text-success">{brl(data.total_receitas)}</span></div>
          </CardContent></Card>
          <Card><CardContent className="pt-6">
            <p className="font-medium text-destructive mb-2">Despesas</p>
            {data.despesas.length ? data.despesas.map((d: any, i: number) => <div key={i} className="flex justify-between text-sm border-b border-border/60 py-1"><span>{d.plano}</span><span className="tabular-nums">{brl(d.total)}</span></div>) : <p className="text-sm text-muted-foreground">Sem despesas no período.</p>}
            <div className="flex justify-between font-semibold mt-2"><span>Total</span><span className="tabular-nums text-destructive">{brl(data.total_despesas)}</span></div>
          </CardContent></Card>
          <Card className="md:col-span-2"><CardContent className="pt-6 flex items-center justify-between">
            <p className="font-medium">Resultado do período</p>
            <p className={`text-2xl font-bold tabular-nums ${data.resultado >= 0 ? 'text-success' : 'text-destructive'}`}>{brl(data.resultado)}</p>
          </CardContent></Card>
        </div>
      )}
    </>
  )
}

// ---------- CONCILIAÇÃO ----------
export function ConciliacaoTab() {
  const hoje = new Date()
  const [contaId, setContaId] = useState<number | null>(null); const [contaLabel, setContaLabel] = useState<string | null>(null)
  const [inicio, setInicio] = useState(new Date(hoje.getFullYear(), hoje.getMonth(), 1).toISOString().slice(0, 10))
  const [fim, setFim] = useState(hoje.toISOString().slice(0, 10))
  const [run, setRun] = useState(false)
  const { data, isLoading } = useConciliacao(contaId, inicio, fim, run && contaId !== null)

  const columns: Column<any>[] = [
    { key: 'data', header: 'Data', cell: (m) => fmtData(m.datahorabaixa) },
    { key: 'desc', header: 'Descrição', cell: (m) => m.descricao || m.origem || '—' },
    { key: 'ofx', header: 'OFX', cell: (m) => m.ofxuniqueid ? <Badge variant="success">conciliado</Badge> : <Badge variant="secondary">—</Badge> },
    { key: 'valor', header: 'Valor', align: 'right', cell: (m) => <span className={`tabular-nums ${m.pagarreceber === 'R' ? 'text-success' : 'text-destructive'}`}>{m.pagarreceber === 'R' ? '+' : '−'} {brl(Math.abs(m.valorefetivado))}</span> },
  ]
  return (
    <>
      <Card className="mb-4"><CardContent className="pt-6 flex flex-wrap items-end gap-3">
        <div className="w-56"><Field label="Conta"><AsyncSelect endpoint="/lookups/contas" value={contaId} valueLabel={contaLabel} onChange={(id, o) => { setContaId(id); setContaLabel(o?.label ?? null); setRun(false) }} /></Field></div>
        <Field label="Início"><Input type="date" value={inicio} onChange={(e) => setInicio(e.target.value)} /></Field>
        <Field label="Fim"><Input type="date" value={fim} onChange={(e) => setFim(e.target.value)} /></Field>
        <Button disabled={!contaId} onClick={() => setRun(true)}>Gerar extrato</Button>
      </CardContent></Card>
      {run && data && (
        <>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
            <Card><CardContent className="pt-6"><p className="text-sm text-muted-foreground">Entradas</p><p className="mt-1 text-xl font-bold tabular-nums text-success">{brl(data.entradas)}</p></CardContent></Card>
            <Card><CardContent className="pt-6"><p className="text-sm text-muted-foreground">Saídas</p><p className="mt-1 text-xl font-bold tabular-nums text-destructive">{brl(data.saidas)}</p></CardContent></Card>
            <Card><CardContent className="pt-6"><p className="text-sm text-muted-foreground">Saldo</p><p className="mt-1 text-xl font-bold tabular-nums">{brl(data.saldo)}</p></CardContent></Card>
          </div>
          <DataTable columns={columns} rows={data.movimentos} loading={isLoading} rowKey={(m: any) => m.id} empty={<EmptyState icon={<Wallet />} title="Sem movimentos no período" />} />
        </>
      )}
    </>
  )
}
