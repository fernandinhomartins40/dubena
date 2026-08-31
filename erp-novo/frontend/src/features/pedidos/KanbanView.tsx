import { useEffect, useRef, useState } from 'react'
import { Plus, Trash2, Pencil, MoreHorizontal } from 'lucide-react'
import {
  Button, Card, CardContent, Input, Badge, Field, AsyncState,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem,
  DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator,
  FormDialog, ConfirmDialog, toast,
} from '@/components/ui'
import { useAuth } from '@/lib/auth'
import {
  usePedidosKanban, useSalvarSituacao, useExcluirSituacao, useReordenarSituacoes, useMudarSituacaoPedido,
  type KanbanColuna, type EfeitoPedido, type SituacaoForm, type PapelSituacao,
} from './api'
import { brl, dataHora as fmtData } from '@/lib/format'

/** Item arrastado entre colunas. */
type ArrastoCard = { pedidoId: number; deSituacao: number }
/** Confirmação pendente de mover card para coluna com efeito (concretiza/cancela). */
type MoverPendente = { pedido: KanbanColuna['pedidos'][number]; destino: KanbanColuna }

export function KanbanView({ onOpen }: { onOpen: (id: number) => void }) {
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
  const [form, setForm] = useState<SituacaoForm>({ descricao: '', efeito: 'PENDENTE', papel: 'NENHUM', cor: null })

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
      {/* F3-04A: o app do entregador precisa saber QUAL coluna significa "saiu
          para entrega". Antes ele adivinhava pelo nome, e criava uma coluna
          nova quando não reconhecia — aqui isso vira uma escolha explícita. */}
      <Field label="Papel operacional" hint="Marque a coluna que representa a mercadoria a caminho do cliente. É ela que o app do entregador usa ao iniciar a rota.">
        <Select value={form.papel ?? 'NENHUM'} onValueChange={(v) => set('papel', v as PapelSituacao)}>
          <SelectTrigger><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="NENHUM">Sem papel especial</SelectItem>
            <SelectItem value="EM_ROTA">Saiu para entrega (em rota)</SelectItem>
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
