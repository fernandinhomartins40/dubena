import { Trash2, Plus } from 'lucide-react'
import { Button } from '@/components/ui'
import { AsyncSelect } from '@/components/AsyncSelect'
import type { OrigemCombustivel } from './api'

const IND_IMPORT = [
  { v: 0, l: 'Nacional' },
  { v: 1, l: 'Estrangeira (importação direta)' },
  { v: 2, l: 'Estrangeira (mercado interno)' },
]

/**
 * Origens do combustível (sub-recurso). A soma dos percentuais deve dar 100%
 * (regra do ProdutoController). Mostra o total ao vivo e bloqueia o salvar se ≠ 100.
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
  function add() {
    onChange([...origens, { indimport: 0, cuforig: 0, porig: 0 }])
  }
  function remove(idx: number) {
    onChange(origens.filter((_, i) => i !== idx))
  }

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <p className="text-sm text-slate-500">Composição de origem do combustível (a soma dos percentuais deve ser 100%).</p>
        <Button variant="ghost" onClick={add}><Plus size={16} /> Adicionar origem</Button>
      </div>

      {origens.length === 0 && <p className="text-sm text-slate-400">Nenhuma origem cadastrada.</p>}

      {origens.map((o, idx) => (
        <div key={idx} className="grid grid-cols-1 md:grid-cols-12 gap-3 items-end border-b border-slate-100 dark:border-slate-800 pb-3">
          <div className="md:col-span-4">
            <label className="block text-sm font-medium mb-1">Indicador de importação</label>
            <select
              value={o.indimport} onChange={(e) => set(idx, { indimport: Number(e.target.value) })}
              className="w-full rounded-md border border-slate-300 dark:border-slate-700 bg-transparent px-3 py-2 outline-none focus:ring-2 focus:ring-marca-500"
            >
              {IND_IMPORT.map((i) => <option key={i.v} value={i.v}>{i.l}</option>)}
            </select>
          </div>
          <div className="md:col-span-5">
            <AsyncSelect
              label="UF de origem" endpoint="/lookups/estados"
              value={o.cuforig || null} valueLabel={ufLabels[idx]}
              onChange={(id, opt) => { set(idx, { cuforig: id ?? 0 }); setUfLabel(idx, opt?.label ?? null) }}
            />
          </div>
          <div className="md:col-span-2">
            <label className="block text-sm font-medium mb-1">% Origem</label>
            <input
              type="number" step="0.0001" value={o.porig}
              onChange={(e) => set(idx, { porig: parseFloat(e.target.value) || 0 })}
              className="w-full rounded-md border border-slate-300 dark:border-slate-700 bg-transparent px-3 py-2 outline-none focus:ring-2 focus:ring-marca-500"
            />
          </div>
          <div className="md:col-span-1">
            <button onClick={() => remove(idx)} title="Remover"
              className="p-2 rounded text-slate-400 hover:text-red-600 hover:bg-slate-100 dark:hover:bg-slate-800"><Trash2 size={16} /></button>
          </div>
        </div>
      ))}

      {origens.length > 0 && (
        <div className={`text-sm font-medium ${ok ? 'text-emerald-600' : 'text-red-600'}`}>
          Total: {total.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}% {ok ? '✓' : '(precisa ser 100%)'}
        </div>
      )}
    </div>
  )
}
