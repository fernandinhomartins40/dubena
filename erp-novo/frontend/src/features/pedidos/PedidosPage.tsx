import { useEffect, useRef, useState } from 'react'
import { Plus, LayoutGrid, List, Trash2, Pencil, MoreHorizontal, ShoppingCart } from 'lucide-react'
import {
  Button, Card, CardContent, PageHeader, Input, Badge, DataTable, type Column, EmptyState, Field, AsyncSelect, AsyncState, SearchBar,
  Tabs, TabsList, TabsTrigger, TabsContent,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem,
  DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator,
  FormDialog, ConfirmDialog,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter, DialogClose, toast,
} from '@/components/ui'
import { useAuth } from '@/lib/auth'
import { useBusca } from '@/lib/useBusca'
import {
  usePedidos, usePedidosKanban, usePedidoSituacoes, usePedido, useCriarPedido, useEmitirNfce,
  useSalvarSituacao, useExcluirSituacao, useReordenarSituacoes, useMudarSituacaoPedido,
  type PedidoListItem, type KanbanColuna, type EfeitoPedido, type SituacaoForm,
} from './api'
import { brl, dataHora as fmtData } from '@/lib/format'

function situacaoBadge(p: { fechadoconcluido?: number; fechadocancelado?: number; situacao?: string | null; descricao?: string }) {
  const label = p.situacao ?? p.descricao ?? '—'
  if (Number(p.fechadocancelado)) return <Badge variant="destructive">{label}</Badge>
  if (Number(p.fechadoconcluido)) return <Badge variant="success">{label}</Badge>
  return <Badge variant="warning">{label}</Badge>
}

export function PedidosPage() {
  const [verFicha, setVerFicha] = useState<number | null>(null)
  return (
    <div>
      <PageHeader title="Pedidos" subtitle="Painel de vendas e jornada do pedido" action={<NovoPedidoDialog />} />
      <Tabs defaultValue="kanban">
        <TabsList><TabsTrigger value="kanban"><LayoutGrid size={15} className="mr-1" /> Kanban</TabsTrigger><TabsTrigger value="lista"><List size={15} className="mr-1" /> Lista</TabsTrigger></TabsList>
        <TabsContent value="kanban"><KanbanView onOpen={setVerFicha} /></TabsContent>
        <TabsContent value="lista"><ListaView onOpen={setVerFicha} /></TabsContent>
      </Tabs>
      <FichaDialog id={verFicha} onClose={() => setVerFicha(null)} />
    </div>
  )
}

/** Item arrastado entre colunas. */
type ArrastoCard = { pedidoId: number; deSituacao: number }
/** Confirmação pendente de mover card para coluna com efeito (concretiza/cancela). */
type MoverPendente = { pedido: KanbanColuna['pedidos'][number]; destino: KanbanColuna }

function KanbanView({ onOpen }: { onOpen: (id: number) => void }) {
  const { data, isLoading } = usePedidosKanban()
  const { can } = useAuth()
  const excluir = useExcluirSituacao()
  const reordenar = useReordenarSituacoes()
  const mudar = useMudarSituacaoPedido()
  const [editar, setEditar] = useState<SituacaoForm | null>(null)
  const [excluindo, setExcluindo] = useState<KanbanColuna | null>(null)
  const [mover, setMover] = useState<MoverPendente | null>(null)

  // Espelho local das colunas p/ reordenação otimista (sincroniza quando a query muda).
  const [colunas, setColunas] = useState<KanbanColuna[]>([])
  useEffect(() => { if (data) setColunas(data) }, [data])

  const dragCol = useRef<number | null>(null)
  const dragCard = useRef<ArrastoCard | null>(null)

  // Feedback visual do DnD (re-renderiza, ao contrário dos refs acima):
  // card em arraste, coluna sob o cursor e índice de inserção (placeholder).
  const [cardArrastando, setCardArrastando] = useState<number | null>(null)
  const [colArrastando, setColArrastando] = useState<number | null>(null)
  const [alvo, setAlvo] = useState<{ situacao: number; indice: number } | null>(null)

  const podeCriar = can('pedidosituacao.create')
  const podeEditar = can('pedidosituacao.edit')
  const podeExcluir = can('pedidosituacao.delete')
  const podeMover = can('pedido.edit')

  if (isLoading) return <AsyncState loading skeletonRows={4}>{null}</AsyncState>

  /** Limpa todo o estado visual de arraste. */
  function limparArraste() {
    dragCol.current = null
    dragCard.current = null
    setCardArrastando(null)
    setColArrastando(null)
    setAlvo(null)
  }

  /** Reordena colunas localmente e persiste. */
  function soltarColuna(alvoId: number) {
    const origemId = dragCol.current
    if (origemId == null || origemId === alvoId) { limparArraste(); return }
    const atual = [...colunas]
    const de = atual.findIndex((c) => c.situacao_id === origemId)
    const para = atual.findIndex((c) => c.situacao_id === alvoId)
    if (de < 0 || para < 0) { limparArraste(); return }
    const [item] = atual.splice(de, 1)
    atual.splice(para, 0, item)
    setColunas(atual)
    limparArraste()
    reordenar.mutate(atual.map((c) => c.situacao_id), {
      onError: () => toast.error('Não foi possível salvar a ordem.'),
    })
  }

  /** Move card; pede confirmação se o destino concretiza/cancela (mexe em estoque/financeiro). */
  async function moverCardPara(destino: KanbanColuna) {
    const arrasto = dragCard.current
    limparArraste()
    if (!arrasto || arrasto.deSituacao === destino.situacao_id) return
    const origem = colunas.find((c) => c.situacao_id === arrasto.deSituacao)
    const pedido = origem?.pedidos.find((p) => p.id === arrasto.pedidoId)
    if (!pedido) return

    if (destino.efeito === 'PENDENTE') {
      await aplicarMover(pedido.id, destino.situacao_id)
    } else {
      setMover({ pedido, destino }) // confirma antes de baixar/estornar estoque
    }
  }

  async function aplicarMover(pedidoId: number, situacaoId: number) {
    try { await mudar.mutateAsync({ pedidoId, situacaoId }); toast.success('Pedido movido.') }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Não foi possível mover o pedido.') }
  }

  return (
    <>
      <div className="flex gap-4 overflow-x-auto pb-2">
        {colunas.map((col: KanbanColuna) => {
          const arrastandoCard = cardArrastando !== null
          const colDestaque = arrastandoCard && alvo?.situacao === col.situacao_id
          const colReorder = colArrastando !== null && colArrastando !== col.situacao_id
          const pedidos = col.pedidos ?? []
          const indiceAlvo = colDestaque ? alvo!.indice : -1
          return (
          <div
            key={col.situacao_id}
            className={`flex w-80 shrink-0 flex-col rounded-xl border border-border bg-muted/40 shadow-sm transition-all
              ${colReorder ? 'ring-2 ring-primary/40' : ''}
              ${colDestaque ? 'ring-2 ring-primary bg-primary/5' : ''}
              ${colArrastando === col.situacao_id ? 'opacity-50' : ''}`}
            onDragOver={(e) => {
              if (dragCard.current || dragCol.current != null) e.preventDefault()
              // Card sobre área vazia da coluna → inserir no fim.
              if (dragCard.current) setAlvo({ situacao: col.situacao_id, indice: pedidos.length })
            }}
            onDrop={() => { if (dragCard.current) moverCardPara(col); else if (dragCol.current != null) soltarColuna(col.situacao_id) }}
          >
            {/* Cabeçalho da coluna — faixa de cor no topo + título/contador/ações */}
            <div
              className={`rounded-t-xl border-b border-border ${podeEditar ? 'cursor-grab active:cursor-grabbing' : ''}`}
              style={{ borderTop: `3px solid ${col.cor ?? 'hsl(var(--border))'}` }}
              draggable={podeEditar}
              onDragStart={() => { dragCol.current = col.situacao_id; setColArrastando(col.situacao_id) }}
              onDragEnd={limparArraste}
            >
              <div className="flex items-center justify-between gap-2 px-3 pt-2.5">
                <div className="flex items-center gap-2 min-w-0">
                  <span className="size-2.5 shrink-0 rounded-full" style={{ background: col.cor ?? 'hsl(var(--muted-foreground))' }} />
                  <span className="font-semibold text-sm truncate">{col.descricao}</span>
                  <Badge variant="secondary" className="shrink-0 tabular-nums">{col.total}</Badge>
                </div>
                <div className="flex items-center gap-1 shrink-0">
                  <StatusBadge efeito={col.efeito} />
                  {(podeEditar || podeExcluir) && (
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild><Button variant="ghost" size="icon" className="size-7"><MoreHorizontal size={16} /></Button></DropdownMenuTrigger>
                      <DropdownMenuContent align="end">
                        {podeEditar && <DropdownMenuItem onClick={() => setEditar({ id: col.situacao_id, descricao: col.descricao, efeito: col.efeito, cor: col.cor })}><Pencil /> Editar coluna</DropdownMenuItem>}
                        {podeExcluir && (<><DropdownMenuSeparator /><DropdownMenuItem destructive onClick={() => setExcluindo(col)}><Trash2 /> Excluir coluna</DropdownMenuItem></>)}
                      </DropdownMenuContent>
                    </DropdownMenu>
                  )}
                </div>
              </div>
              <div className="px-3 pb-2.5 pt-1 text-xs text-muted-foreground tabular-nums">{brl(col.valor)}</div>
            </div>

            {/* Corpo da coluna — área rolável com os cards dentro */}
            <div className="flex flex-1 flex-col gap-2 overflow-y-auto p-2 min-h-[120px] max-h-[calc(100vh-19rem)]">
              {pedidos.map((p, idx) => [
                indiceAlvo === idx ? <Placeholder key={`ph-${idx}`} /> : null,
                <Card
                  key={p.id}
                  className={`shrink-0 cursor-grab active:cursor-grabbing border-border bg-card shadow-sm hover:border-primary/60 hover:shadow-md transition-all duration-150 ${cardArrastando === p.id ? 'opacity-40 scale-[0.97] rotate-1 ring-2 ring-primary shadow-lg' : ''}`}
                  draggable={podeMover}
                  onDragStart={(e) => {
                    dragCard.current = { pedidoId: p.id, deSituacao: col.situacao_id }
                    e.dataTransfer.effectAllowed = 'move'
                    setCardArrastando(p.id)
                  }}
                  onDragEnd={limparArraste}
                  onDragOver={(e) => {
                    if (!dragCard.current) return
                    e.preventDefault(); e.stopPropagation()
                    // Antes ou depois deste card conforme a metade do cursor.
                    const r = e.currentTarget.getBoundingClientRect()
                    const depois = e.clientY > r.top + r.height / 2
                    setAlvo({ situacao: col.situacao_id, indice: idx + (depois ? 1 : 0) })
                  }}
                  onClick={() => onOpen(p.id)}
                >
                  <CardContent className="p-3">
                    <div className="flex items-center justify-between"><span className="font-semibold text-sm">#{p.id}</span><span className="tabular-nums text-sm font-medium">{brl(p.valorvenda)}</span></div>
                    <div className="text-xs text-muted-foreground truncate mt-1.5">{p.cliente || '—'}</div>
                    <div className="text-[11px] text-muted-foreground mt-0.5">{fmtData(p.datahora)}</div>
                  </CardContent>
                </Card>,
              ])}
              {indiceAlvo === pedidos.length && pedidos.length > 0 && <Placeholder />}
              {pedidos.length === 0 && (
                colDestaque
                  ? <Placeholder />
                  : <div className="flex flex-1 items-center justify-center rounded-lg border border-dashed border-border/60 py-8 text-xs text-muted-foreground">Sem pedidos</div>
              )}
            </div>
          </div>
          )
        })}

        {podeCriar && (
          <div className="w-80 shrink-0">
            <button onClick={() => setEditar({ descricao: '', efeito: 'PENDENTE', cor: null })}
              className="flex h-full min-h-[160px] w-full flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-border bg-muted/20 text-muted-foreground hover:border-primary/50 hover:bg-muted/40 hover:text-foreground transition-colors">
              <Plus size={20} /> <span className="text-sm font-medium">Nova coluna</span>
            </button>
          </div>
        )}
      </div>

      {podeMover && <p className="mt-2 text-xs text-muted-foreground">Dica: arraste um card entre colunas para mudar a situação{podeEditar ? '; arraste o cabeçalho para reordenar as colunas' : ''}.</p>}

      <SituacaoDialog value={editar} onClose={() => setEditar(null)} />
      <ConfirmDialog
        open={!!excluindo} onOpenChange={(o) => !o && setExcluindo(null)}
        title="Excluir coluna"
        description={<>Excluir a coluna <strong>{excluindo?.descricao}</strong>? Só é possível se ela não tiver pedidos.</>}
        loading={excluir.isPending}
        onConfirm={async () => { try { await excluir.mutateAsync(excluindo!.situacao_id); toast.success('Coluna excluída.') } catch (e: any) { toast.error(e?.response?.data?.message ?? e?.response?.data?.errors?.situacao?.[0] ?? 'Não foi possível excluir.') } finally { setExcluindo(null) } }}
      />
      <ConfirmDialog
        open={!!mover} onOpenChange={(o) => !o && setMover(null)}
        title={`Mover para "${mover?.destino.descricao ?? ''}"`}
        confirmLabel="Mover" variant="default"
        description={mover?.destino.efeito === 'CONCLUIDO'
          ? <>Mover o pedido <strong>#{mover?.pedido.id}</strong> para esta coluna vai <strong>concluir a venda</strong>: baixa o estoque e gera o financeiro. Confirmar?</>
          : <>Mover o pedido <strong>#{mover?.pedido.id}</strong> para esta coluna vai <strong>cancelar a venda</strong>: estorna estoque e financeiro. Confirmar?</>}
        loading={mudar.isPending}
        onConfirm={async () => { const m = mover!; setMover(null); await aplicarMover(m.pedido.id, m.destino.situacao_id) }}
      />
    </>
  )
}

const EFEITO_META: Record<EfeitoPedido, { label: string; variant: 'warning' | 'success' | 'destructive' }> = {
  PENDENTE: { label: 'Pendente', variant: 'warning' },
  CONCLUIDO: { label: 'Concluído', variant: 'success' },
  CANCELADO: { label: 'Cancelado', variant: 'destructive' },
}

/** Badge do status (efeito) da coluna — governa estoque/financeiro na transição. */
function StatusBadge({ efeito }: { efeito: EfeitoPedido }) {
  const m = EFEITO_META[efeito] ?? EFEITO_META.PENDENTE
  return <Badge variant={m.variant}>{m.label}</Badge>
}

/** Espaço "fantasma" que abre na coluna mostrando onde o card vai cair. */
function Placeholder() {
  return <div className="h-16 rounded-lg border-2 border-dashed border-primary/60 bg-primary/10 animate-pulse" />
}

const CORES = ['#FF6200', '#DBFB3B', '#22C55E', '#3B82F6', '#A855F7', '#EF4444', '#64748B', '#0EA5E9']

/** Cria/edita uma coluna (situação) do Kanban: descrição + status (efeito) + cor. */
function SituacaoDialog({ value, onClose }: { value: SituacaoForm | null; onClose: () => void }) {
  const salvar = useSalvarSituacao()
  const [form, setForm] = useState<SituacaoForm>({ descricao: '', efeito: 'PENDENTE', cor: null })

  useEffect(() => { if (value) setForm(value) }, [value])
  const set = <K extends keyof SituacaoForm>(k: K, v: SituacaoForm[K]) => setForm((s) => ({ ...s, [k]: v }))

  async function onConfirm() {
    if (!form.descricao.trim()) { toast.error('Informe o nome da coluna.'); return }
    try {
      await salvar.mutateAsync(form)
      toast.success(form.id ? 'Coluna atualizada.' : 'Coluna criada.')
      onClose()
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? e?.response?.data?.errors?.descricao?.[0] ?? 'Erro ao salvar a coluna.')
    }
  }

  return (
    <FormDialog open={!!value} onOpenChange={(o) => !o && onClose()} title={form.id ? 'Editar coluna' : 'Nova coluna'} loading={salvar.isPending} onConfirm={onConfirm}>
      <Field label="Nome da coluna" required><Input autoFocus value={form.descricao} onChange={(e) => set('descricao', e.target.value)} placeholder="Ex.: Em separação" /></Field>
      <Field label="Status (efeito na máquina de estados)" required hint="CONCLUÍDO baixa estoque + gera financeiro; CANCELADO estorna. Mudar afeta como os pedidos desta coluna se comportam.">
        <Select value={form.efeito} onValueChange={(v) => set('efeito', v as EfeitoPedido)}>
          <SelectTrigger><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="PENDENTE">Pendente — em aberto</SelectItem>
            <SelectItem value="CONCLUIDO">Concluído — baixa estoque/financeiro</SelectItem>
            <SelectItem value="CANCELADO">Cancelado — estorna</SelectItem>
          </SelectContent>
        </Select>
      </Field>
      <Field label="Cor">
        <div className="flex items-center gap-2 flex-wrap">
          {CORES.map((c) => (
            <button key={c} type="button" onClick={() => set('cor', c)} aria-label={`Cor ${c}`}
              className={`size-7 rounded-full border-2 transition ${form.cor === c ? 'border-foreground scale-110' : 'border-transparent'}`} style={{ background: c }} />
          ))}
          <button type="button" onClick={() => set('cor', null)}
            className={`px-2 h-7 rounded-md border text-xs ${!form.cor ? 'border-foreground' : 'border-border text-muted-foreground'}`}>Sem cor</button>
        </div>
      </Field>
    </FormDialog>
  )
}

function ListaView({ onOpen }: { onOpen: (id: number) => void }) {
  const [sit, setSit] = useState(0)
  const { busca, setBusca, q, page, setPage, submit } = useBusca()
  const { data, isLoading, isFetching } = usePedidos(sit, q, page)
  const { data: situacoes } = usePedidoSituacoes()

  const columns: Column<PedidoListItem>[] = [
    { key: 'id', header: 'Pedido', width: 'w-24', cell: (p) => <span className="font-medium tabular-nums">#{p.id}</span> },
    { key: 'cliente', header: 'Cliente', cell: (p) => p.cliente || '—' },
    { key: 'data', header: 'Data', cell: (p) => fmtData(p.datahora) },
    { key: 'valor', header: 'Valor', align: 'right', cell: (p) => <span className="tabular-nums">{brl(p.valorvenda)}</span> },
    { key: 'sit', header: 'Situação', align: 'center', cell: (p) => situacaoBadge(p) },
  ]
  return (
    <>
      <SearchBar value={busca} onChange={setBusca} onSearch={submit} placeholder="Buscar cliente ou nº…">
        <div className="w-56"><AsyncSelect endpoint="/lookups/pedido-situacoes" value={sit || null} valueLabel={situacoes?.find((s) => s.id === sit)?.descricao ?? null} placeholder="Todas as situações"
          onChange={(id) => { setPage(1); setSit(id ?? 0) }} /></div>
      </SearchBar>
      <DataTable columns={columns} rows={data?.data} loading={isLoading} rowKey={(p) => p.id} onRowClick={(p) => onOpen(p.id)}
        page={data?.meta.current_page} lastPage={data?.meta.last_page} onPageChange={setPage} fetching={isFetching}
        empty={<EmptyState icon={<ShoppingCart />} title="Nenhum pedido" />} />
    </>
  )
}

function FichaDialog({ id, onClose }: { id: number | null; onClose: () => void }) {
  const { data, isLoading } = usePedido(id)
  const { can } = useAuth()
  const emitir = useEmitirNfce(id)

  async function onEmitir() {
    try {
      await emitir.mutateAsync('65')
      toast.success('Documento fiscal emitido.')
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Erro ao emitir o documento fiscal.')
    }
  }

  // Pode faturar quando concluído e ainda sem NF, com permissão fiscal.
  const podeEmitir = !!data && Number(data.fechadoconcluido) === 1 && !data.tem_nf && can('fiscal.emitir')

  return (
    <Dialog open={id !== null} onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="max-w-2xl">
        <DialogHeader><DialogTitle>Pedido #{id}</DialogTitle></DialogHeader>
        {isLoading || !data ? <AsyncState loading skeletonRows={4}>{null}</AsyncState> : (
          <div className="space-y-4">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
              <div><span className="text-muted-foreground">Cliente:</span> <span className="font-medium">{data.cliente || '—'}</span></div>
              <div><span className="text-muted-foreground">Situação:</span> {situacaoBadge({ situacao: data.situacao })}</div>
              <div><span className="text-muted-foreground">Condição:</span> {data.condicao || '—'}</div>
              <div><span className="text-muted-foreground">Valor:</span> <span className="font-medium tabular-nums">{brl(data.valorvenda)}</span></div>
              <div><span className="text-muted-foreground">Data:</span> {fmtData(data.datahora)}</div>
              <div><span className="text-muted-foreground">NF-e:</span> {data.tem_nf ? <Badge variant="success">Emitida</Badge> : <Badge variant="secondary">—</Badge>}</div>
            </div>
            <div>
              <p className="text-sm font-medium mb-2">Itens</p>
              <div className="rounded-lg border border-border divide-y divide-border">
                {(data.itens ?? []).map((it: any) => (
                  <div key={it.id} className="flex items-center justify-between px-3 py-2 text-sm">
                    <span>{it.produto || '—'} <span className="text-muted-foreground">× {it.quantidade}</span></span>
                    <span className="tabular-nums">{brl(it.precovendatotal)}</span>
                  </div>
                ))}
                {(!data.itens || data.itens.length === 0) && <p className="px-3 py-2 text-sm text-muted-foreground">Sem itens.</p>}
              </div>
            </div>
          </div>
        )}
        <DialogFooter>
          {podeEmitir && <Button loading={emitir.isPending} onClick={onEmitir}>Emitir NFC-e</Button>}
          <DialogClose asChild><Button variant="outline">Fechar</Button></DialogClose>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

function NovoPedidoDialog() {
  const { can } = useAuth()
  const criar = useCriarPedido()
  const [open, setOpen] = useState(false)
  const [f, setF] = useState<any>({})
  const [labels, setLabels] = useState<Record<string, string | null>>({})
  const [itens, setItens] = useState<any[]>([])
  if (!can('pedido.create')) return null
  const set = (k: string, v: any) => setF((s: any) => ({ ...s, [k]: v }))

  const total = itens.reduce((s, i) => s + (Number(i.preco_unitario) || 0) * (Number(i.quantidade) || 0), 0)

  async function salvar() {
    const req = ['cliente_id', 'condicaopagamento_id', 'pedidooperacao_id', 'pedidosituacao_id', 'entregasetor_id', 'colaborador_id', 'entregarua_id', 'entregabairro_id', 'entregacidade_id', 'entreganumero', 'ufentrega']
    if (req.some((k) => !f[k]) || itens.length === 0) { toast.error('Preencha cliente, endereço, operação, situação, setor, colaborador e ao menos um item.'); return }
    try {
      const salvo = await criar.mutateAsync({ ...f, valorvenda: total, itens: itens.map((i) => ({ produto_id: i.produto_id, quantidade: Number(i.quantidade), preco_unitario: Number(i.preco_unitario) })) })
      toast.success(`Pedido #${salvo.id} criado.`); setOpen(false); setF({}); setItens([]); setLabels({})
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao criar pedido.') }
  }
  function addItem() { setItens([...itens, { produto_id: null, produtoLabel: null, quantidade: 1, preco_unitario: '' }]) }
  function setItem(i: number, patch: any) { setItens(itens.map((it, idx) => idx === i ? { ...it, ...patch } : it)) }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button><Plus size={16} /> Novo pedido</Button></DialogTrigger>
      <DialogContent className="max-w-3xl">
        <DialogHeader><DialogTitle>Novo pedido</DialogTitle></DialogHeader>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
          <Field label="Cliente" required><AsyncSelect endpoint="/lookups/clientes-fornecedores" value={f.cliente_id ?? null} valueLabel={labels.cli} onChange={(id, o) => { set('cliente_id', id); setLabels((l) => ({ ...l, cli: o?.label ?? null })) }} /></Field>
          <Field label="Situação" required><AsyncSelect endpoint="/lookups/pedido-situacoes" value={f.pedidosituacao_id ?? null} valueLabel={labels.sit} onChange={(id, o) => { set('pedidosituacao_id', id); setLabels((l) => ({ ...l, sit: o?.label ?? null })) }} /></Field>
          <Field label="Condição de pagamento" required><AsyncSelect endpoint="/lookups/condicoes-pagamento" value={f.condicaopagamento_id ?? null} valueLabel={labels.cond} onChange={(id, o) => { set('condicaopagamento_id', id); setLabels((l) => ({ ...l, cond: o?.label ?? null })) }} /></Field>
          <Field label="Setor de entrega" required><AsyncSelect endpoint="/lookups/setores" value={f.entregasetor_id ?? null} valueLabel={labels.setor} onChange={(id, o) => { set('entregasetor_id', id); setLabels((l) => ({ ...l, setor: o?.label ?? null })) }} /></Field>
          <Field label="Operação" required><AsyncSelect endpoint="/lookups/pedido-operacoes" value={f.pedidooperacao_id ?? null} valueLabel={labels.oper} onChange={(id, o) => { set('pedidooperacao_id', id); setLabels((l) => ({ ...l, oper: o?.label ?? null })) }} /></Field>
          <Field label="Colaborador" required><AsyncSelect endpoint="/lookups/colaboradores" value={f.colaborador_id ?? null} valueLabel={labels.colab} onChange={(id, o) => { set('colaborador_id', id); setLabels((l) => ({ ...l, colab: o?.label ?? null })) }} /></Field>
          <Field label="Cidade (entrega)" required><AsyncSelect endpoint="/lookups/cidades" value={f.entregacidade_id ?? null} valueLabel={labels.cid} onChange={(id, o) => { set('entregacidade_id', id); setLabels((l) => ({ ...l, cid: o?.label ?? null })); if (o?.uf) set('ufentrega', String(o.uf)) }} /></Field>
          <Field label="Bairro (entrega)" required><AsyncSelect endpoint="/lookups/bairros" params={{ cidade_id: f.entregacidade_id }} value={f.entregabairro_id ?? null} valueLabel={labels.bai} disabled={!f.entregacidade_id} onChange={(id, o) => { set('entregabairro_id', id); setLabels((l) => ({ ...l, bai: o?.label ?? null })) }} /></Field>
          <Field label="Rua (entrega)" required><AsyncSelect endpoint="/lookups/ruas" params={{ cidade_id: f.entregacidade_id }} value={f.entregarua_id ?? null} valueLabel={labels.rua} disabled={!f.entregacidade_id} onChange={(id, o) => { set('entregarua_id', id); setLabels((l) => ({ ...l, rua: o?.label ?? null })) }} /></Field>
          <Field label="Número (entrega)" required><Input value={f.entreganumero ?? ''} onChange={(e) => set('entreganumero', e.target.value)} /></Field>
        </div>

        <div className="mt-2">
          <div className="flex items-center justify-between mb-2"><p className="text-sm font-medium">Itens</p><Button variant="outline" size="sm" onClick={addItem}><Plus size={16} /> Item</Button></div>
          {itens.map((it, i) => (
            <div key={i} className="grid grid-cols-12 gap-2 items-end mb-2">
              <div className="col-span-6"><Field label="Produto"><AsyncSelect endpoint="/lookups/produtos" value={it.produto_id} valueLabel={it.produtoLabel} onChange={(id, o) => setItem(i, { produto_id: id, produtoLabel: o?.label ?? null })} /></Field></div>
              <div className="col-span-2"><Field label="Qtd"><Input type="number" value={it.quantidade} onChange={(e) => setItem(i, { quantidade: e.target.value })} /></Field></div>
              <div className="col-span-3"><Field label="Preço unit."><Input type="number" step="0.01" value={it.preco_unitario} onChange={(e) => setItem(i, { preco_unitario: e.target.value })} /></Field></div>
              <div className="col-span-1 flex justify-end"><Button variant="ghost" size="icon" onClick={() => setItens(itens.filter((_, idx) => idx !== i))}><Trash2 size={16} /></Button></div>
            </div>
          ))}
          {itens.length > 0 && <p className="text-right text-sm font-medium mt-1">Total: <span className="tabular-nums">{brl(total)}</span></p>}
        </div>
        <DialogFooter><DialogClose asChild><Button variant="outline">Cancelar</Button></DialogClose><Button loading={criar.isPending} onClick={salvar}>Criar pedido</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
