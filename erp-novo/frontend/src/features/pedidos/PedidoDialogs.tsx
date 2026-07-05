import { useState } from 'react'
import { Plus, Trash2 } from 'lucide-react'
import {
  Button, Badge, Field, AsyncSelect, AsyncState, Input,
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter, DialogClose, toast,
} from '@/components/ui'
import { useAuth } from '@/lib/auth'
import { usePedido, useCriarPedido, useEmitirNfce } from './api'
import { brl, dataHora as fmtData } from '@/lib/format'
import { situacaoBadge } from './shared'

export function FichaDialog({ id, onClose }: { id: number | null; onClose: () => void }) {
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

export function NovoPedidoDialog() {
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
