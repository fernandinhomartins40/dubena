import { Trash2, Plus, Fuel } from 'lucide-react'
import {
  Button, Field, Input, Badge, AsyncSelect, EmptyState,
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem,
} from '@/components/ui'
import type { OrigemCombustivel } from './api'

const IND_IMPORT = [
  { v: 0, l: 'Nacional' },
  { v: 1, l: 'Estrangeira (importação direta)' },
  { v: 2, l: 'Estrangeira (mercado interno)' },
]

/**
 * Origens do combustível (sub-recurso). A soma dos percentuais deve dar 100%
 * (regra do ProdutoController). Mostra o total ao vivo.
 */
export function OrigensTab({
  origens, onChange, ufLabels, setUfLabel,
}: {
  origens: OrigemCombustivel[]
  onChange: (o: OrigemCombustivel[]) => void
  ufLabels: Record<number, string | null>
  setUfLabel: (idx: number, label: string | null) => void
}) {
  const total = origens.reduce((s, o) => s + (Number(o.porig) || 0), 0)
  const ok = Math.abs(total - 100) < 0.001 || origens.length === 0

  function set(idx: number, patch: Partial<OrigemCombustivel>) {
    onChange(origens.map((o, i) => (i === idx ? { ...o, ...patch } : o)))
  }
  function add() { onChange([...origens, { indimport: 0, cuforig: 0, porig: 0 }]) }
  function remove(idx: number) { onChange(origens.filter((_, i) => i !== idx)) }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm font-medium">Composição de origem do combustível</p>
          <p className="text-sm text-muted-foreground">A soma dos percentuais deve ser 100%.</p>
        </div>
        <div className="flex items-center gap-3">
          {origens.length > 0 && (
            <Badge variant={ok ? 'success' : 'destructive'}>
              Total: {total.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}%
            </Badge>
          )}
          <Button variant="outline" size="sm" onClick={add}><Plus size={16} /> Adicionar</Button>
        </div>
      </div>

      {origens.length === 0 ? (
        <EmptyState icon={<Fuel />} title="Nenhuma origem cadastrada" description="Adicione as origens para produtos GLP." />
      ) : (
        <div className="space-y-3">
          {origens.map((o, idx) => (
            <div key={idx} className="grid grid-cols-1 md:grid-cols-12 gap-3 items-end rounded-lg border border-border p-3">
              <div className="md:col-span-5">
                <Field label="Indicador de importação">
                  <Select value={String(o.indimport)} onValueChange={(v) => set(idx, { indimport: Number(v) })}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                      {IND_IMPORT.map((i) => <SelectItem key={i.v} value={String(i.v)}>{i.l}</SelectItem>)}
                    </SelectContent>
                  </Select>
                </Field>
              </div>
              <div className="md:col-span-4">
                <Field label="UF de origem">
                  <AsyncSelect endpoint="/lookups/estados" value={o.cuforig || null} valueLabel={ufLabels[idx]}
                    onChange={(id, opt) => { set(idx, { cuforig: id ?? 0 }); setUfLabel(idx, opt?.label ?? null) }} />
                </Field>
              </div>
              <div className="md:col-span-2">
                <Field label="% Origem">
                  <Input type="number" step="0.0001" value={o.porig} onChange={(e) => set(idx, { porig: parseFloat(e.target.value) || 0 })} />
                </Field>
              </div>
              <div className="md:col-span-1 flex justify-end">
                <Button variant="ghost" size="icon" onClick={() => remove(idx)} aria-label="Remover"><Trash2 size={16} /></Button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
