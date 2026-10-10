import { Plus, Trash2 } from 'lucide-react'
import { Button, Field, AsyncSelect, Input } from '@/components/ui'

/** Editor de itens (produto + quantidade) da transferência entre setores. */
export function ItensEditor({ itens, setItens }: { itens: any[]; setItens: (i: any[]) => void }) {
  const add = () => setItens([...itens, { produto_id: null, produtoLabel: null, quantidade: '' }])
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
          <div className="md:col-span-8"><Field label="Produto"><AsyncSelect endpoint="/lookups/produtos" value={it.produto_id} valueLabel={it.produtoLabel} onChange={(id, o) => set(i, { produto_id: id, produtoLabel: o?.label ?? null })} /></Field></div>
          <div className="md:col-span-3"><Field label="Qtde"><Input type="number" step="0.0001" value={it.quantidade} onChange={(e) => set(i, { quantidade: e.target.value })} /></Field></div>
          <div className="md:col-span-1 flex justify-end"><Button variant="ghost" size="icon" aria-label={`Remover item ${i + 1}`} onClick={() => rm(i)}><Trash2 size={16} /></Button></div>
        </div>
      ))}
    </div>
  )
}
