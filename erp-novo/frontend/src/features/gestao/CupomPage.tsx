import { useState } from 'react'
import { Plus, Receipt, Send, X } from 'lucide-react'
import {
  Button, Input, Badge, type Column, Field, AsyncSelect,
  ResourceList, FormDialog, ConfirmDialog, toast,
} from '@/components/ui'
import { brl, dataHora } from '@/lib/format'
import { useCupons, useCriarCupom, useEmitirCupom, type Cupom } from './api'

interface ItemForm { produto_id: number | null; produtoLabel: string; quantidade: number; valor_unitario: number }

export function CupomPage() {
  const { data, isLoading, error, refetch } = useCupons()
  const criar = useCriarCupom()
  const emitir = useEmitirCupom()
  const [confirmando, setConfirmando] = useState<Cupom | null>(null)
  const [open, setOpen] = useState(false)
  const [itens, setItens] = useState<ItemForm[]>([{ produto_id: null, produtoLabel: '', quantidade: 1, valor_unitario: 0 }])

  function reset() { setItens([{ produto_id: null, produtoLabel: '', quantidade: 1, valor_unitario: 0 }]) }

  async function onCriar() {
    const validos = itens.filter((i) => i.quantidade > 0 && i.valor_unitario >= 0)
    if (!validos.length) { toast.error('Adicione ao menos um item.'); return }
    try {
      await criar.mutateAsync({ itens: validos.map((i) => ({ produto_id: i.produto_id, quantidade: i.quantidade, valor_unitario: i.valor_unitario })) })
      toast.success('Cupom criado (rascunho).'); setOpen(false); reset()
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao criar.') }
  }
  async function onEmitir(c: Cupom) {
    try { await emitir.mutateAsync(c.id); toast.success('Cupom emitido.'); setConfirmando(null) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao emitir.') }
  }

  const total = itens.reduce((s, i) => s + (i.quantidade || 0) * (i.valor_unitario || 0), 0)

  const columns: Column<Cupom>[] = [
    { key: 'numero', header: 'Número', cell: (v) => <span className="font-medium tabular-nums">{v.numero ?? '—'}</span> },
    { key: 'itens', header: 'Itens', align: 'right', cell: (v) => v.itens.length },
    { key: 'total', header: 'Total', align: 'right', cell: (v) => <span className="tabular-nums">{brl(v.valor_total)}</span> },
    { key: 'emit', header: 'Emitido em', cell: (v) => dataHora(v.emitido_em) },
    {
      key: 'sit', header: 'Situação', cell: (v) => v.situacao === 'emitido'
        ? <Badge variant="success">Emitido</Badge>
        : v.situacao === 'cancelado' ? <Badge variant="destructive">Cancelado</Badge> : <Badge variant="secondary">Rascunho</Badge>,
    },
    {
      key: 'acoes', header: '', align: 'right', cell: (v) => v.situacao === 'rascunho'
        ? <Button variant="secondary" size="sm" loading={emitir.isPending} onClick={() => setConfirmando(v)}><Send size={15} /> Emitir</Button>
        : null,
    },
  ]

  return (
    <>
      <ResourceList
        title="Cupons fiscais (SAT/CFe)"
        subtitle="Cupom fiscal eletrônico — emissão e consulta"
        action={<Button onClick={() => { reset(); setOpen(true) }}><Plus size={16} /> Novo cupom</Button>}
        columns={columns} rows={data} loading={isLoading} error={error} onRetry={() => { void refetch() }} rowKey={(v) => v.id}
        emptyIcon={<Receipt />} emptyTitle="Nenhum cupom"
      />

      <ConfirmDialog open={!!confirmando} onOpenChange={(open) => { if (!open) setConfirmando(null) }}
        title="Emitir cupom fiscal" confirmLabel="Emitir" variant="default" loading={emitir.isPending}
        description={<>Emitir o cupom <strong>#{confirmando?.numero ?? confirmando?.id}</strong>? Confira os itens e valores antes de confirmar a emissão fiscal.</>} onConfirm={() => { if (confirmando) void onEmitir(confirmando) }} />

      <FormDialog
        dirty={JSON.stringify(itens) !== JSON.stringify([{ produto_id: null, produtoLabel: '', quantidade: 1, valor_unitario: 0 }])}
        open={open} onOpenChange={setOpen}
        title="Novo cupom" confirmLabel="Criar rascunho"
        loading={criar.isPending} onConfirm={onCriar}
      >
        {itens.map((it, i) => (
          <div key={i} className="grid grid-cols-2 sm:grid-cols-[1fr_auto_auto_auto] items-end gap-2">
            <Field label={`Produto ${i + 1}`}>
              <AsyncSelect endpoint="/lookups/produtos" value={it.produto_id} valueLabel={it.produtoLabel}
                onChange={(id, opt) => setItens((arr) => arr.map((x, j) => j === i ? { ...x, produto_id: id, produtoLabel: opt?.label ?? '' } : x))} />
            </Field>
            <Field label={`Quantidade ${i + 1}`}><Input type="number" min={0} step="0.001" className="w-20" value={it.quantidade} onChange={(e) => setItens((arr) => arr.map((x, j) => j === i ? { ...x, quantidade: Number(e.target.value) } : x))} /></Field>
            <Field label={`Valor unitário ${i + 1}`}><Input type="number" min={0} step="0.01" className="w-28" value={it.valor_unitario} onChange={(e) => setItens((arr) => arr.map((x, j) => j === i ? { ...x, valor_unitario: Number(e.target.value) } : x))} /></Field>
            <Button variant="ghost" size="icon" aria-label={`Remover item ${i + 1}`} onClick={() => setItens((arr) => arr.filter((_, j) => j !== i))}><X size={15} /></Button>
          </div>
        ))}
        <Button variant="outline" size="sm" onClick={() => setItens((arr) => [...arr, { produto_id: null, produtoLabel: '', quantidade: 1, valor_unitario: 0 }])}><Plus size={14} /> Item</Button>
        <p className="text-right text-sm font-medium">Total: <span className="tabular-nums">{brl(total)}</span></p>
      </FormDialog>
    </>
  )
}
