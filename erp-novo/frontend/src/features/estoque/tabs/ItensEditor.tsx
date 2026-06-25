import { Plus, Trash2 } from 'lucide-react'
import { Button, Field, AsyncSelect, Input, Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui'

/** Editor de itens (produto + quantidade [+ setor / valor]) reusado nos documentos de estoque. */
export function ItensEditor({ itens, setItens, comSetor, comValor }: {
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
