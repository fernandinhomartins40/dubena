import { useState } from 'react'
import { Search, Plus, Pencil, Trash2, TrendingUp, TrendingDown, Lock, Unlock, Wallet } from 'lucide-react'
import {
  Button, Card, CardContent, PageHeader, Input, Badge, DataTable, type Column, EmptyState, Field,
  AsyncSelect, Tabs, TabsList, TabsTrigger, TabsContent,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter, DialogClose, toast,
} from '@/components/ui'
import {
  useLancamentos, useResumoFinanceiro, useCriarLancamento, type Lancamento,
  usePlanosConta, useCentrosCusto, useSalvarPlanoConta, useExcluirPlanoConta, useSalvarCentroCusto, useExcluirCentroCusto,
  type PlanoConta, type CentroCusto,
  useContasCaixa, useMovimentosCaixa, useAbrirCaixa, useFecharCaixa, type ContaCaixa,
} from './api'
import { ChequesTab, BoletosTab, DRETab, ConciliacaoTab } from './FinanceiroExtraTabs'

const brl = (n: number) => Number(n).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
const fmtData = (s: string | null) => s ? new Date(s).toLocaleDateString('pt-BR') : '—'

export function FinanceiroPage() {
  return (
    <div>
      <PageHeader title="Financeiro" subtitle="Lançamentos, caixa e plano de contas" />
      <Tabs defaultValue="lancamentos">
        <TabsList className="overflow-x-auto">
          <TabsTrigger value="lancamentos">Lançamentos</TabsTrigger>
          <TabsTrigger value="caixa">Caixa</TabsTrigger>
          <TabsTrigger value="cheques">Cheques</TabsTrigger>
          <TabsTrigger value="boletos">Boletos / PIX</TabsTrigger>
          <TabsTrigger value="dre">DRE</TabsTrigger>
          <TabsTrigger value="conciliacao">Conciliação</TabsTrigger>
          <TabsTrigger value="plano">Plano de Contas</TabsTrigger>
          <TabsTrigger value="centro">Centro de Custo</TabsTrigger>
        </TabsList>
        <TabsContent value="lancamentos"><LancamentosTab /></TabsContent>
        <TabsContent value="caixa"><CaixaTab /></TabsContent>
        <TabsContent value="cheques"><ChequesTab /></TabsContent>
        <TabsContent value="boletos"><BoletosTab /></TabsContent>
        <TabsContent value="dre"><DRETab /></TabsContent>
        <TabsContent value="conciliacao"><ConciliacaoTab /></TabsContent>
        <TabsContent value="plano"><PlanoTab /></TabsContent>
        <TabsContent value="centro"><CentroTab /></TabsContent>
      </Tabs>
    </div>
  )
}

// ---------- LANÇAMENTOS ----------
function LancamentosTab() {
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
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button><Plus size={16} /> Novo lançamento</Button></DialogTrigger>
      <DialogContent className="max-w-2xl">
        <DialogHeader><DialogTitle>Novo lançamento</DialogTitle></DialogHeader>
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
        <DialogFooter><DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose><Button loading={criar.isPending} onClick={salvar}>Salvar</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

// ---------- CAIXA ----------
function CaixaTab() {
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

// ---------- PLANO DE CONTAS ----------
function PlanoTab() {
  const { data, isLoading } = usePlanosConta()
  const salvar = useSalvarPlanoConta(); const excluir = useExcluirPlanoConta()
  const [edit, setEdit] = useState<Partial<PlanoConta> | null>(null); const [del, setDel] = useState<PlanoConta | null>(null)

  async function onSalvar() {
    if (!edit?.descricao?.trim()) { toast.error('Informe a descrição.'); return }
    try { await salvar.mutateAsync({ id: edit.id, descricao: edit.descricao, codigo: edit.codigo, pagarreceber: edit.pagarreceber ?? 'R', nivel: edit.nivel ?? 1 }); toast.success('Plano salvo.'); setEdit(null) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') }
  }
  const columns: Column<PlanoConta>[] = [
    { key: 'codigo', header: 'Código', width: 'w-28', cell: (p) => <span className="tabular-nums text-muted-foreground">{p.codigo || '—'}</span> },
    { key: 'descricao', header: 'Descrição', cell: (p) => <span className="font-medium">{p.descricao}</span> },
    { key: 'pr', header: 'Tipo', cell: (p) => p.pagarreceber === 'P' ? <Badge variant="destructive">Pagar</Badge> : <Badge variant="success">Receber</Badge> },
    { key: 'acoes', header: '', align: 'right', width: 'w-24', cell: (p) => <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}><Button variant="ghost" size="icon" onClick={() => setEdit(p)}><Pencil size={16} /></Button><Button variant="ghost" size="icon" onClick={() => setDel(p)}><Trash2 size={16} /></Button></div> },
  ]
  return (
    <>
      <div className="mb-3 flex justify-end"><Button onClick={() => setEdit({ pagarreceber: 'R' })}><Plus size={16} /> Novo plano</Button></div>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(p) => p.id} onRowClick={(p) => setEdit(p)} empty={<EmptyState icon={<Wallet />} title="Nenhum plano de contas" />} />
      <Dialog open={!!edit} onOpenChange={(o) => !o && setEdit(null)}>
        <DialogContent>
          <DialogHeader><DialogTitle>{edit?.id ? 'Editar plano' : 'Novo plano'}</DialogTitle></DialogHeader>
          <div className="space-y-4">
            <div className="grid grid-cols-3 gap-3">
              <Field label="Código"><Input value={edit?.codigo ?? ''} onChange={(e) => setEdit((s) => ({ ...s, codigo: e.target.value }))} /></Field>
              <Field label="Descrição" required className="col-span-2"><Input autoFocus value={edit?.descricao ?? ''} onChange={(e) => setEdit((s) => ({ ...s, descricao: e.target.value }))} /></Field>
            </div>
            <Field label="Tipo">
              <Select value={edit?.pagarreceber ?? 'R'} onValueChange={(v) => setEdit((s) => ({ ...s, pagarreceber: v }))}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent><SelectItem value="R">Receber</SelectItem><SelectItem value="P">Pagar</SelectItem></SelectContent>
              </Select>
            </Field>
          </div>
          <DialogFooter><DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose><Button loading={salvar.isPending} onClick={onSalvar}>Salvar</Button></DialogFooter>
        </DialogContent>
      </Dialog>
      <Dialog open={!!del} onOpenChange={(o) => !o && setDel(null)}>
        <DialogContent>
          <DialogHeader><DialogTitle>Excluir plano</DialogTitle></DialogHeader>
          <p className="text-sm text-muted-foreground">Excluir <strong>{del?.descricao}</strong>?</p>
          <DialogFooter><DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose><Button variant="destructive" loading={excluir.isPending} onClick={async () => { try { await excluir.mutateAsync(del!.id); toast.success('Excluído.') } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') } finally { setDel(null) } }}><Trash2 size={16} /> Excluir</Button></DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}

// ---------- CENTRO DE CUSTO ----------
function CentroTab() {
  const { data, isLoading } = useCentrosCusto()
  const salvar = useSalvarCentroCusto(); const excluir = useExcluirCentroCusto()
  const [edit, setEdit] = useState<Partial<CentroCusto> | null>(null); const [del, setDel] = useState<CentroCusto | null>(null)

  async function onSalvar() {
    if (!edit?.descricao?.trim()) { toast.error('Informe a descrição.'); return }
    try { await salvar.mutateAsync({ id: edit.id, descricao: edit.descricao, codigo: edit.codigo, nivel: edit.nivel ?? 1, ativo: edit.ativo !== 0 }); toast.success('Centro salvo.'); setEdit(null) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') }
  }
  const columns: Column<CentroCusto>[] = [
    { key: 'codigo', header: 'Código', width: 'w-28', cell: (c) => <span className="tabular-nums text-muted-foreground">{c.codigo || '—'}</span> },
    { key: 'descricao', header: 'Descrição', cell: (c) => <span className="font-medium">{c.descricao}</span> },
    { key: 'ativo', header: 'Status', cell: (c) => c.ativo ? <Badge variant="success">Ativo</Badge> : <Badge variant="secondary">Inativo</Badge> },
    { key: 'acoes', header: '', align: 'right', width: 'w-24', cell: (c) => <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}><Button variant="ghost" size="icon" onClick={() => setEdit(c)}><Pencil size={16} /></Button><Button variant="ghost" size="icon" onClick={() => setDel(c)}><Trash2 size={16} /></Button></div> },
  ]
  return (
    <>
      <div className="mb-3 flex justify-end"><Button onClick={() => setEdit({ ativo: 1 })}><Plus size={16} /> Novo centro</Button></div>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(c) => c.id} onRowClick={(c) => setEdit(c)} empty={<EmptyState icon={<Wallet />} title="Nenhum centro de custo" />} />
      <Dialog open={!!edit} onOpenChange={(o) => !o && setEdit(null)}>
        <DialogContent>
          <DialogHeader><DialogTitle>{edit?.id ? 'Editar centro' : 'Novo centro'}</DialogTitle></DialogHeader>
          <div className="grid grid-cols-3 gap-3">
            <Field label="Código"><Input value={edit?.codigo ?? ''} onChange={(e) => setEdit((s) => ({ ...s, codigo: e.target.value }))} /></Field>
            <Field label="Descrição" required className="col-span-2"><Input autoFocus value={edit?.descricao ?? ''} onChange={(e) => setEdit((s) => ({ ...s, descricao: e.target.value }))} /></Field>
          </div>
          <DialogFooter><DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose><Button loading={salvar.isPending} onClick={onSalvar}>Salvar</Button></DialogFooter>
        </DialogContent>
      </Dialog>
      <Dialog open={!!del} onOpenChange={(o) => !o && setDel(null)}>
        <DialogContent>
          <DialogHeader><DialogTitle>Excluir centro</DialogTitle></DialogHeader>
          <p className="text-sm text-muted-foreground">Excluir <strong>{del?.descricao}</strong>?</p>
          <DialogFooter><DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose><Button variant="destructive" loading={excluir.isPending} onClick={async () => { try { await excluir.mutateAsync(del!.id); toast.success('Excluído.') } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro.') } finally { setDel(null) } }}><Trash2 size={16} /> Excluir</Button></DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}
