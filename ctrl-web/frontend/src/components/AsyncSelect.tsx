import { useEffect, useRef, useState } from 'react'
import { api } from '@/lib/api'

export interface Option { id: number; label: string; [k: string]: unknown }

interface Props {
  label?: string
  /** endpoint relativo à API admin, ex.: '/lookups/cidades' */
  endpoint: string
  /** params extras (ex.: { cidade_id }) */
  params?: Record<string, unknown>
  value: number | null
  /** label inicial p/ exibir quando já há value (modo edição) */
  valueLabel?: string | null
  onChange: (id: number | null, option: Option | null) => void
  placeholder?: string
  disabled?: boolean
  error?: string
}

/** Select assíncrono reaproveitável (busca server-side, debounce). */
export function AsyncSelect({
  label, endpoint, params, value, valueLabel, onChange, placeholder = 'Selecione…', disabled, error,
}: Props) {
  const [open, setOpen] = useState(false)
  const [busca, setBusca] = useState('')
  const [options, setOptions] = useState<Option[]>([])
  const [selectedLabel, setSelectedLabel] = useState<string | null>(valueLabel ?? null)
  const boxRef = useRef<HTMLDivElement>(null)

  useEffect(() => { setSelectedLabel(valueLabel ?? null) }, [valueLabel])

  useEffect(() => {
    if (!open) return
    const t = setTimeout(async () => {
      try {
        const { data } = await api.get<Option[]>(endpoint, { params: { q: busca, ...params } })
        setOptions(data)
      } catch { setOptions([]) }
    }, 250)
    return () => clearTimeout(t)
  }, [busca, open, endpoint, JSON.stringify(params)])

  useEffect(() => {
    function onClickOut(e: MouseEvent) {
      if (boxRef.current && !boxRef.current.contains(e.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', onClickOut)
    return () => document.removeEventListener('mousedown', onClickOut)
  }, [])

  return (
    <div className="space-y-1" ref={boxRef}>
      {label && <label className="block text-sm font-medium">{label}</label>}
      <div className="relative">
        <button
          type="button" disabled={disabled} onClick={() => setOpen((o) => !o)}
          className={`w-full text-left rounded-md border bg-transparent px-3 py-2 outline-none focus:ring-2 focus:ring-marca-500 ${
            error ? 'border-red-500' : 'border-slate-300 dark:border-slate-700'
          } ${disabled ? 'opacity-60' : ''}`}
        >
          {value && selectedLabel ? selectedLabel : <span className="text-slate-400">{placeholder}</span>}
        </button>

        {open && !disabled && (
          <div className="absolute z-20 mt-1 w-full rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-lg">
            <input
              autoFocus value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Digite para buscar…"
              className="w-full border-b border-slate-200 dark:border-slate-700 bg-transparent px-3 py-2 outline-none"
            />
            <ul className="max-h-56 overflow-y-auto py-1">
              {value && (
                <li>
                  <button type="button" onClick={() => { onChange(null, null); setSelectedLabel(null); setOpen(false) }}
                    className="w-full text-left px-3 py-1.5 text-sm text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800">
                    — limpar —
                  </button>
                </li>
              )}
              {options.map((o) => (
                <li key={o.id}>
                  <button type="button"
                    onClick={() => { onChange(o.id, o); setSelectedLabel(o.label); setOpen(false); setBusca('') }}
                    className="w-full text-left px-3 py-1.5 text-sm hover:bg-slate-50 dark:hover:bg-slate-800">
                    {o.label}
                  </button>
                </li>
              ))}
              {options.length === 0 && <li className="px-3 py-2 text-sm text-slate-400">Nenhum resultado.</li>}
            </ul>
          </div>
        )}
      </div>
      {error && <p className="text-xs text-red-600">{error}</p>}
    </div>
  )
}
