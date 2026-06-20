import { useEffect, useState } from 'react'
import * as Popover from '@radix-ui/react-popover'
import { Check, ChevronsUpDown, Search, X } from 'lucide-react'
import { api } from '@/lib/api'
import { cn } from '@/lib/cn'

export interface Option { id: number; label: string; [k: string]: unknown }

interface Props {
  /** endpoint relativo à API admin, ex.: '/lookups/cidades' */
  endpoint: string
  params?: Record<string, unknown>
  value: number | null
  /** label inicial p/ exibir quando já há value (modo edição) */
  valueLabel?: string | null
  onChange: (id: number | null, option: Option | null) => void
  placeholder?: string
  disabled?: boolean
  error?: boolean
  className?: string
}

/** Seleção assíncrona moderna (Radix Popover + busca server-side com debounce). */
export function AsyncSelect({
  endpoint, params, value, valueLabel, onChange, placeholder = 'Selecione…', disabled, error, className,
}: Props) {
  const [open, setOpen] = useState(false)
  const [busca, setBusca] = useState('')
  const [options, setOptions] = useState<Option[]>([])
  const [selectedLabel, setSelectedLabel] = useState<string | null>(valueLabel ?? null)
  const [loading, setLoading] = useState(false)

  useEffect(() => { setSelectedLabel(valueLabel ?? null) }, [valueLabel])

  useEffect(() => {
    if (!open) return
    setLoading(true)
    const t = setTimeout(async () => {
      try {
        const { data } = await api.get(endpoint, { params: { q: busca, ...params } })
        // Aceita array puro OU { data: [...] } (lista paginada) — robustez contra endpoint que não é lookup.
        const lista = Array.isArray(data) ? data : Array.isArray(data?.data) ? data.data : []
        setOptions(lista as Option[])
      } catch { setOptions([]) } finally { setLoading(false) }
    }, 250)
    return () => clearTimeout(t)
  }, [busca, open, endpoint, JSON.stringify(params)])

  return (
    <Popover.Root open={open} onOpenChange={setOpen}>
      <Popover.Trigger asChild disabled={disabled}>
        <button
          type="button"
          className={cn(
            'flex h-10 w-full items-center justify-between rounded-md border bg-card px-3 py-2 text-sm shadow-sm transition-colors',
            'focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-1 focus:ring-offset-background disabled:cursor-not-allowed disabled:opacity-50',
            error ? 'border-destructive focus:ring-destructive' : 'border-input',
            className,
          )}
        >
          <span className={cn('truncate', !(value && selectedLabel) && 'text-muted-foreground')}>
            {value && selectedLabel ? selectedLabel : placeholder}
          </span>
          <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
        </button>
      </Popover.Trigger>
      <Popover.Portal>
        <Popover.Content
          align="start" sideOffset={4}
          className="z-50 w-[var(--radix-popover-trigger-width)] overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95"
        >
          <div className="flex items-center border-b border-border px-3">
            <Search className="size-4 shrink-0 text-muted-foreground" />
            <input
              autoFocus value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Buscar…"
              className="flex h-10 w-full bg-transparent px-2 text-sm outline-none placeholder:text-muted-foreground"
            />
          </div>
          <ul className="max-h-60 overflow-y-auto p-1">
            {value != null && (
              <li>
                <button type="button"
                  onClick={() => { onChange(null, null); setSelectedLabel(null); setOpen(false) }}
                  className="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-sm text-muted-foreground hover:bg-secondary">
                  <X className="size-4" /> Limpar seleção
                </button>
              </li>
            )}
            {loading ? (
              <li className="px-2 py-3 text-sm text-muted-foreground">Carregando…</li>
            ) : options.length === 0 ? (
              <li className="px-2 py-3 text-sm text-muted-foreground">Nenhum resultado.</li>
            ) : options.map((o) => (
              <li key={o.id}>
                <button type="button"
                  onClick={() => { onChange(o.id, o); setSelectedLabel(o.label); setOpen(false); setBusca('') }}
                  className="flex w-full items-center justify-between rounded-sm px-2 py-1.5 text-sm hover:bg-secondary">
                  {o.label}
                  {value === o.id && <Check className="size-4 text-primary" />}
                </button>
              </li>
            ))}
          </ul>
        </Popover.Content>
      </Popover.Portal>
    </Popover.Root>
  )
}
