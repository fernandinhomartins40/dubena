import { useState } from 'react'
import { Search, Plus, Trash2, ArrowRightLeft, ClipboardList, PackageCheck, Warehouse, Lock, SlidersHorizontal } from 'lucide-react'
import {
  Button, Card, CardContent, PageHeader, Input, Badge, DataTable, type Column, EmptyState, Field,
  AsyncSelect, Tabs, TabsList, TabsTrigger, TabsContent,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter, DialogClose, toast,
} from '@/components/ui'
import {
  useSaldos, useAcerto, useTransferencias, useCriarTransferencia, useRequisicoes, useCriarRequisicao,
  useInventarios, useCriarInventario, useFisicos, useCriarFisico, useEfetivarFisico,
  useFechamentos, useFechar, useAbrirFechamento, type SaldoRow,
} from './api'

const fmt = (n: number) => Number(n).toLocaleString('pt-BR', { maximumFractionDigits: 4 })
const fmtData = (s: string | null) => s ? new Date(s).toLocaleString('pt-BR') : '—'

export function EstoquePage() {
  return (
    <div>
      <PageHeader title="Estoque" subtitle="Saldos, movimentações, inventário e fechamento" />
      <Tabs defaultValue="saldos">
        <TabsList className="overflow-x-auto">
          <TabsTrigger value="saldos">Saldos</TabsTrigger>
          <TabsTrigger value="acerto">Acerto</TabsTrigger>
          <TabsTrigger value="transferencia">Transferência</TabsTrigger>
          <TabsTrigger value="requisicao">Requisição</TabsTrigger>
          <TabsTrigger value="inventario">Inventário</TabsTrigger>
          <TabsTrigger value="fisico">Físico</TabsTrigger>
          <TabsTrigger value="fechamento">Fechamento</TabsTrigger>
        </TabsList>
        <TabsContent value="saldos"><SaldosTab /></TabsContent>
        <TabsContent value="acerto"><AcertoTab /></TabsContent>
        <TabsContent value="transferencia"><TransferenciaTab /></TabsContent>
        <TabsContent value="requisicao"><RequisicaoTab /></TabsContent>
        <TabsContent value="inventario"><InventarioTab /></TabsContent>
        <TabsContent value="fisico"><FisicoTab /></TabsContent>
        <TabsContent value="fechamento"><FechamentoTab /></TabsContent>
      </Tabs>
    </div>
  )
}

// ---------- SALDOS ----------
function SaldosTab() {
  const [setorId, setSetorId] = useState<number | null>(null)
  const [setorLabel, setSetorLabel] = useState<string | null>(null)
  const [busca, setBusca] = useState(''); const [q, setQ] = useState('')
  const { data, isLoading } = useSaldos(setorId, q)

  const columns: Column<SaldoRow>[] = [
    { key: 'produto', header: 'Produto', cell: (r) => <span className="font-medium">{r.produto}</span> },
    { key: 'setor', header: 'Setor', cell: (r) => <span className="text-muted-foreground">{r.setor}</span> },
    { key: 'qtd', header: 'Quantidade', align: 'right', cell: (r) => <span className={`tabular-nums font-medium ${r.quantidade < 0 ? 'text-destructive' : ''}`}>{fmt(r.quantidade)}</span> },
    { key: 'min', header: 'Mín.', align: 'right', cell: (r) => <span className="tabular-nums text-muted-foreground">{fmt(r.quantidademinima)}</span> },
    { key: 'max', header: 'Máx.', align: 'right', cell: (r) => <span className="tabular-nums text-muted-foreground">{fmt(r.quantidademaxima)}</span> },
  ]
  return (
    <>
      <Card className="mb-4 p-3"><div className="flex flex-wrap gap-2 items-center">
        <div className="w-56"><AsyncSelect endpoint="/lookups/setores" value={setorId} valueLabel={setorLabel} placeholder="Filtrar setor"
          onChange={(id, o) => { setSetorId(id); setSetorLabel(o?.label ?? null) }} /></div>
        <div className="relative flex-1 min-w-[200px]">
          <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
          <Input value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Buscar produto…" className="pl-9"
            onKeyDown={(e) => e.key === 'Enter' && setQ(busca)} />
        </div>
        <Button variant="secondary" onClick={() => setQ(busca)}>Buscar</Button>
      </div></Card>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(r) => r.id}
        empty={<EmptyState icon={<Warehouse />} title="Sem saldo" description="Nenhum saldo encontrado para o filtro." />} />
    </>
  )
}

// ---------- ACERTO ----------
function AcertoTab() {
  const acerto = useAcerto()
  const [setorId, setSetorId] = useState<number | null>(null); const [setorLabel, setSetorLabel] = useState<string | null>(null)
  const [produtoId, setProdutoId] = useState<number | null>(null); const [produtoLabel, setProdutoLabel] = useState<string | null>(null)
  const [mov, setMov] = useState('ENTRADA'); const [qtde, setQtde] = useState(''); const [obs, setObs] = useState('')

  async function salvar() {
    if (!setorId || !produtoId || !qtde || !obs) { toast.error('Preencha setor, produto, quantidade e descrição.'); return }
    try {
      await acerto.mutateAsync({ setor_id: setorId, produto_id: produtoId, movimentacao: mov, quantidade: Number(qtde), observacao: obs })
      toast.success('Acerto realizado.'); setQtde(''); setObs('')
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro no acerto.') }
  }

  return (
    <Card><CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
      <Field label="Setor" required><AsyncSelect endpoint="/lookups/setores" value={setorId} valueLabel={setorLabel} onChange={(id, o) => { setSetorId(id); setSetorLabel(o?.label ?? null) }} /></Field>
      <Field label="Produto" required><AsyncSelect endpoint="/lookups/produtos" value={produtoId} valueLabel={produtoLabel} onChange={(id, o) => { setProdutoId(id); setProdutoLabel(o?.label ?? null) }} /></Field>
      <Field label="Movimentação" required>
        <Select value={mov} onValueChange={setMov}>
          <SelectTrigger><SelectValue /></SelectTrigger>
          <SelectContent><SelectItem value="ENTRADA">Entrada (+)</SelectItem><SelectItem value="SAIDA">Saída (−)</SelectItem></SelectContent>
        </Select>
      </Field>
      <Field label="Quantidade" required><Input type="number" step="0.0001" value={qtde} onChange={(e) => setQtde(e.target.value)} /></Field>
      <Field label="Descrição / motivo" required className="md:col-span-2"><Input value={obs} onChange={(e) => setObs(e.target.value)} /></Field>
      <div><Button loading={acerto.isPending} onClick={salvar}><SlidersHorizontal size={16} /> Aplicar acerto</Button></div>
    </CardContent></Card>
  )
}

/** Editor de itens (produto + quantidade [+ setor / valor]) reusado nos documentos. */
function ItensEditor({ itens, setItens, comSetor, comValor }: {
  itens: any[]; setItens: (i: any[]) => void; comSetor?: boolean; comValor?: boolean
}) {
  const add = () => setItens([...itens, { produto_id: null, produtoLabel: null, quantidade: '', setor_id: null, setorLabel: null, valorunitario: '', entradasaida: 'ENTRADA' }])
  const set = (i: number, patch: any) => setItens(itens.map((it, idx) => idx === i ? { ...it, ...patch } : it))
  const rm = (i: number) => setItens(itens.filter((_, idx) => idx !== i))

  return (
    <div className="space-y-3">
      <div className="flex justify-between items-center">
        <p className="text-sm font-medium">Itens</p>
        <Button variant="outline" size="sm" onClick={add}><Plus size={16} /> Adicionar item</Button>
      </div>
      {itens.length === 0 && <p className="text-sm text-muted-foreground">Nenhum item adicionado.</p>}
      {itens.map((it, i) => (
        <div key={i} className="grid grid-cols-1 md:grid-cols-12 gap-3 items-end rounded-lg border border-border p-3">
          <div className="md:col-span-4"><Field label="Produto"><AsyncSelect endpoint="/lookups/produtos" value={it.produto_id} valueLabel={it.produtoLabel} onChange={(id, o) => set(i, { produto_id: id, produtoLabel: o?.label ?? null })} /></Field></div>
          {comSetor && <div className="md:col-span-3"><Field label="Setor"><AsyncSelect endpoint="/lookups/setores" value={it.setor_id} valueLabel={it.setorLabel} onChange={(id, o) => set(i, { setor_id: id, setorLabel: o?.label ?? null })} /></Field></div>}
          {comSetor && <div className="md:col-span-2"><Field label="Mov."><Select value={it.entradasaida} onValueChange={(v) => set(i, { entradasaida: v })}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="ENTRADA">Entrada</SelectItem><SelectItem value="SAIDA">Saída</SelectItem></SelectContent></Select></Field></div>}
          <div className="md:col-span-2"><Field label="Qtde"><Input type="number" step="0.0001" value={it.quantidade} onChange={(e) => set(i, { quantidade: e.target.value })} /></Field></div>
          {comValor && <div className="md:col-span-2"><Field label="Vlr unit."><Input type="number" step="0.0001" value={it.valorunitario} onChange={(e) => set(i, { valorunitario: e.target.value })} /></Field></div>}
          <div className="md:col-span-1 flex justify-end"><Button variant="ghost" size="icon" onClick={() => rm(i)}><Trash2 size={16} /></Button></div>
        </div>
      ))}
    </div>
  )
}

// ---------- TRANSFERÊNCIA ----------
function TransferenciaTab() {
  const { data, isLoading } = useTransferencias()
  const criar = useCriarTransferencia()
  const [open, setOpen] = useState(false)
  const [origem, setOrigem] = useState<number | null>(null); const [origemL, setOrigemL] = useState<string | null>(null)
  const [destino, setDestino] = useState<number | null>(null); const [destinoL, setDestinoL] = useState<string | null>(null)
  const [obs, setObs] = useState(''); const [itens, setItens] = useState<any[]>([])

  async function salvar() {
    if (!origem || !destino || itens.length === 0) { toast.error('Informe origem, destino e ao menos um item.'); return }
    try {
      await criar.mutateAsync({ origemsetor_id: origem, destinosetor_id: destino, observacoes: obs, itens: itens.map((i) => ({ produto_id: i.produto_id, quantidade: Number(i.quantidade) })) })
      toast.success('Transferência realizada.'); setOpen(false); setItens([]); setObs('')
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro na transferência.') }
  }

  const columns: Column<any>[] = [
    { key: 'id', header: 'Nº', cell: (r) => `#${r.id}` },
    { key: 'data', header: 'Data', cell: (r) => fmtData(r.datahora) },
    { key: 'obs', header: 'Observações', cell: (r) => <span className="text-muted-foreground">{r.observacoes || '—'}</span> },
  ]
  return (
    <>
      <div className="mb-3 flex justify-end">
        <Dialog open={open} onOpenChange={setOpen}>
          <DialogTrigger asChild><Button><ArrowRightLeft size={16} /> Nova transferência</Button></DialogTrigger>
          <DialogContent className="max-w-3xl">
            <DialogHeader><DialogTitle>Nova transferência entre setores</DialogTitle></DialogHeader>
            <div className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Field label="Setor de origem" required><AsyncSelect endpoint="/lookups/setores" value={origem} valueLabel={origemL} onChange={(id, o) => { setOrigem(id); setOrigemL(o?.label ?? null) }} /></Field>
                <Field label="Setor de destino" required><AsyncSelect endpoint="/lookups/setores" value={destino} valueLabel={destinoL} onChange={(id, o) => { setDestino(id); setDestinoL(o?.label ?? null) }} /></Field>
              </div>
              <Field label="Observações"><Input value={obs} onChange={(e) => setObs(e.target.value)} /></Field>
              <ItensEditor itens={itens} setItens={setItens} />
            </div>
            <DialogFooter><DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose><Button loading={criar.isPending} onClick={salvar}>Transferir</Button></DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(r) => r.id} empty={<EmptyState icon={<ArrowRightLeft />} title="Nenhuma transferência" />} />
    </>
  )
}

// ---------- REQUISIÇÃO ----------
function RequisicaoTab() {
  const { data, isLoading } = useRequisicoes()
  const criar = useCriarRequisicao()
  const [open, setOpen] = useState(false)
  const [obs, setObs] = useState(''); const [itens, setItens] = useState<any[]>([])

  async function salvar() {
    if (itens.length === 0) { toast.error('Adicione ao menos um item.'); return }
    try {
      await criar.mutateAsync({ observacoes: obs, itens: itens.map((i) => ({ produto_id: i.produto_id, setor_id: i.setor_id, quantidade: Number(i.quantidade), entradasaida: i.entradasaida })) })
      toast.success('Requisição registrada.'); setOpen(false); setItens([]); setObs('')
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro na requisição.') }
  }

  const columns: Column<any>[] = [
    { key: 'id', header: 'Nº', cell: (r) => `#${r.id}` },
    { key: 'data', header: 'Data', cell: (r) => fmtData(r.datahora) },
    { key: 'cancelado', header: 'Status', cell: (r) => Number(r.cancelado) ? <Badge variant="destructive">Cancelada</Badge> : <Badge variant="success">Ativa</Badge> },
  ]
  return (
    <>
      <div className="mb-3 flex justify-end">
        <Dialog open={open} onOpenChange={setOpen}>
          <DialogTrigger asChild><Button><ClipboardList size={16} /> Nova requisição</Button></DialogTrigger>
          <DialogContent className="max-w-3xl">
            <DialogHeader><DialogTitle>Nova requisição de estoque</DialogTitle></DialogHeader>
            <div className="space-y-4">
              <Field label="Observações"><Input value={obs} onChange={(e) => setObs(e.target.value)} /></Field>
              <ItensEditor itens={itens} setItens={setItens} comSetor />
            </div>
            <DialogFooter><DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose><Button loading={criar.isPending} onClick={salvar}>Registrar</Button></DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(r) => r.id} empty={<EmptyState icon={<ClipboardList />} title="Nenhuma requisição" />} />
    </>
  )
}

// ---------- INVENTÁRIO ----------
function InventarioTab() {
  const { data, isLoading } = useInventarios()
  const criar = useCriarInventario()
  const [open, setOpen] = useState(false)
  const [dataInv, setDataInv] = useState(''); const [mes, setMes] = useState(''); const [itens, setItens] = useState<any[]>([])

  async function salvar() {
    if (!dataInv || !mes || itens.length === 0) { toast.error('Informe data, mês e itens.'); return }
    try {
      await criar.mutateAsync({ datainventario: dataInv, mesentrega: mes + '-01', itens: itens.map((i) => ({ produto_id: i.produto_id, quantidade: Number(i.quantidade), valorunitario: Number(i.valorunitario) })) })
      toast.success('Inventário gravado.'); setOpen(false); setItens([])
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro no inventário.') }
  }

  const columns: Column<any>[] = [
    { key: 'id', header: 'Nº', cell: (r) => `#${r.id}` },
    { key: 'data', header: 'Data', cell: (r) => r.datainventario },
    { key: 'valor', header: 'Valor', align: 'right', cell: (r) => <span className="tabular-nums">{Number(r.valorinventario).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</span> },
  ]
  return (
    <>
      <div className="mb-3 flex justify-end">
        <Dialog open={open} onOpenChange={setOpen}>
          <DialogTrigger asChild><Button><PackageCheck size={16} /> Novo inventário</Button></DialogTrigger>
          <DialogContent className="max-w-3xl">
            <DialogHeader><DialogTitle>Novo inventário (valoração)</DialogTitle></DialogHeader>
            <div className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Field label="Data do inventário" required><Input type="date" value={dataInv} onChange={(e) => setDataInv(e.target.value)} /></Field>
                <Field label="Mês de entrega" required><Input type="month" value={mes} onChange={(e) => setMes(e.target.value)} /></Field>
              </div>
              <ItensEditor itens={itens} setItens={setItens} comValor />
            </div>
            <DialogFooter><DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose><Button loading={criar.isPending} onClick={salvar}>Gravar</Button></DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(r) => r.id} empty={<EmptyState icon={<PackageCheck />} title="Nenhum inventário" />} />
    </>
  )
}

// ---------- FÍSICO ----------
function FisicoTab() {
  const { data, isLoading } = useFisicos()
  const criar = useCriarFisico()
  const efetivar = useEfetivarFisico()
  const [open, setOpen] = useState(false)
  const [dataComp, setDataComp] = useState(''); const [itens, setItens] = useState<any[]>([])

  async function salvar() {
    if (!dataComp || itens.length === 0) { toast.error('Informe a data e os itens.'); return }
    try {
      await criar.mutateAsync({ datacompetencia: dataComp, itens: itens.map((i) => ({ setor_id: i.setor_id, produto_id: i.produto_id, quantidadesistema: Number(i.quantidadesistema || 0), quantidadefisica: Number(i.quantidade || 0) })) })
      toast.success('Estoque físico registrado.'); setOpen(false); setItens([])
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro no estoque físico.') }
  }

  async function onEfetivar(id: number) {
    try { await efetivar.mutateAsync(id); toast.success('Estoque físico efetivado.') }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao efetivar.') }
  }

  const columns: Column<any>[] = [
    { key: 'id', header: 'Nº', cell: (r) => `#${r.id}` },
    { key: 'data', header: 'Competência', cell: (r) => fmtData(r.datacompetencia) },
    { key: 'efetivado', header: 'Status', cell: (r) => Number(r.efetivado) ? <Badge variant="success">Efetivado</Badge> : <Badge variant="warning">Pendente</Badge> },
    { key: 'acoes', header: '', align: 'right', cell: (r) => !Number(r.efetivado) && <Button variant="outline" size="sm" loading={efetivar.isPending} onClick={() => onEfetivar(r.id)}><PackageCheck size={14} /> Efetivar</Button> },
  ]
  return (
    <>
      <div className="mb-3 flex justify-end">
        <Dialog open={open} onOpenChange={setOpen}>
          <DialogTrigger asChild><Button><PackageCheck size={16} /> Novo estoque físico</Button></DialogTrigger>
          <DialogContent className="max-w-3xl">
            <DialogHeader><DialogTitle>Registrar estoque físico</DialogTitle></DialogHeader>
            <div className="space-y-4">
              <Field label="Data de competência" required><Input type="datetime-local" value={dataComp} onChange={(e) => setDataComp(e.target.value)} /></Field>
              <p className="text-xs text-muted-foreground">Informe a quantidade do sistema e a contada fisicamente; ao efetivar, a diferença ajusta o saldo.</p>
              <FisicoItens itens={itens} setItens={setItens} />
            </div>
            <DialogFooter><DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose><Button loading={criar.isPending} onClick={salvar}>Registrar</Button></DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(r) => r.id} empty={<EmptyState icon={<PackageCheck />} title="Nenhum estoque físico" />} />
    </>
  )
}

function FisicoItens({ itens, setItens }: { itens: any[]; setItens: (i: any[]) => void }) {
  const add = () => setItens([...itens, { produto_id: null, produtoLabel: null, setor_id: null, setorLabel: null, quantidadesistema: '', quantidade: '' }])
  const set = (i: number, patch: any) => setItens(itens.map((it, idx) => idx === i ? { ...it, ...patch } : it))
  const rm = (i: number) => setItens(itens.filter((_, idx) => idx !== i))
  return (
    <div className="space-y-3">
      <div className="flex justify-between items-center"><p className="text-sm font-medium">Itens</p><Button variant="outline" size="sm" onClick={add}><Plus size={16} /> Adicionar</Button></div>
      {itens.map((it, i) => (
        <div key={i} className="grid grid-cols-1 md:grid-cols-12 gap-3 items-end rounded-lg border border-border p-3">
          <div className="md:col-span-4"><Field label="Produto"><AsyncSelect endpoint="/lookups/produtos" value={it.produto_id} valueLabel={it.produtoLabel} onChange={(id, o) => set(i, { produto_id: id, produtoLabel: o?.label ?? null })} /></Field></div>
          <div className="md:col-span-3"><Field label="Setor"><AsyncSelect endpoint="/lookups/setores" value={it.setor_id} valueLabel={it.setorLabel} onChange={(id, o) => set(i, { setor_id: id, setorLabel: o?.label ?? null })} /></Field></div>
          <div className="md:col-span-2"><Field label="Qtd sistema"><Input type="number" step="0.0001" value={it.quantidadesistema} onChange={(e) => set(i, { quantidadesistema: e.target.value })} /></Field></div>
          <div className="md:col-span-2"><Field label="Qtd física"><Input type="number" step="0.0001" value={it.quantidade} onChange={(e) => set(i, { quantidade: e.target.value })} /></Field></div>
          <div className="md:col-span-1 flex justify-end"><Button variant="ghost" size="icon" onClick={() => rm(i)}><Trash2 size={16} /></Button></div>
        </div>
      ))}
    </div>
  )
}

// ---------- FECHAMENTO ----------
function FechamentoTab() {
  const { data, isLoading } = useFechamentos()
  const fechar = useFechar()
  const abrir = useAbrirFechamento()
  const [dataF, setDataF] = useState('')
  const [abrirData, setAbrirData] = useState(''); const [motivo, setMotivo] = useState(''); const [openAbrir, setOpenAbrir] = useState(false)

  async function onFechar() {
    if (!dataF) { toast.error('Informe a data/hora de fechamento.'); return }
    try { await fechar.mutateAsync({ datahorafechamento: dataF.replace('T', ' ') + ':00' }); toast.success('Estoque fechado.'); setDataF('') }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao fechar.') }
  }
  async function onAbrir() {
    if (!abrirData || !motivo) { toast.error('Informe data e motivo.'); return }
    try { await abrir.mutateAsync({ datahorafechamento: abrirData.replace('T', ' ') + ':00', motivo }); toast.success('Estoque reaberto.'); setOpenAbrir(false); setMotivo('') }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao reabrir.') }
  }

  const columns: Column<any>[] = [
    { key: 'id', header: 'Nº', cell: (r) => `#${r.id}` },
    { key: 'data', header: 'Fechamento', cell: (r) => fmtData(r.datahorafechamento) },
    { key: 'reaberto', header: 'Status', cell: (r) => Number(r.reaberto) ? <Badge variant="warning">Reaberto</Badge> : <Badge variant="success">Fechado</Badge> },
  ]
  return (
    <>
      <Card className="mb-4"><CardContent className="pt-6 flex flex-wrap items-end gap-3">
        <Field label="Fechar estoque até"><Input type="datetime-local" value={dataF} onChange={(e) => setDataF(e.target.value)} /></Field>
        <Button loading={fechar.isPending} onClick={onFechar}><Lock size={16} /> Fechar estoque</Button>
        <Dialog open={openAbrir} onOpenChange={setOpenAbrir}>
          <DialogTrigger asChild><Button variant="outline">Reabrir período</Button></DialogTrigger>
          <DialogContent>
            <DialogHeader><DialogTitle>Reabrir estoque</DialogTitle></DialogHeader>
            <div className="space-y-4">
              <Field label="Reabrir a partir de" required><Input type="datetime-local" value={abrirData} onChange={(e) => setAbrirData(e.target.value)} /></Field>
              <Field label="Motivo" required><Input value={motivo} onChange={(e) => setMotivo(e.target.value)} /></Field>
            </div>
            <DialogFooter><DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose><Button loading={abrir.isPending} onClick={onAbrir}>Reabrir</Button></DialogFooter>
          </DialogContent>
        </Dialog>
      </CardContent></Card>
      <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(r) => r.id} empty={<EmptyState icon={<Lock />} title="Nenhum fechamento" />} />
    </>
  )
}
