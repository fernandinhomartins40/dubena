import { useFieldControl } from './field-context'
import { useState } from 'react'
import * as Popover from '@radix-ui/react-popover'
import { Check, ChevronsUpDown, Search, X } from 'lucide-react'
import { useLookup } from '@/lib/useLookup'
import { cn } from '@/lib/cn'
import type { Option } from './async-select'

interface Props {
  /** endpoint relativo à API admin, ex.: '/lookups/bairros' */
  endpoint: string
  params?: Record<string, unknown>
  value: Option[]
  onChange: (options: Option[]) => void
  placeholder?: string
  disabled?: boolean
  className?: string
}

/**
 * Seleção assíncrona de VÁRIOS itens (Radix Popover + busca server-side).
 *
 * Irmão do AsyncSelect, para quando o filtro é "estes bairros" e não "este
 * bairro". Guarda a Option inteira, não só o id: sem o rótulo em mãos, um
 * filtro recarregado mostraria "3 selecionados" sem dizer QUAIS — e o dono
 * assinaria uma exportação sem saber o que pediu.
 */
export function AsyncMultiSelect({
  endpoint, params, value, onChange, placeholder = 'Todos', disabled, className,
}: Props) {
  const accessible = useFieldControl({})
  const [open, setOpen] = useState(false)
  const [busca, setBusca] = useState('')

  const { options, loading, error: loadError, retry } = useLookup(open, endpoint, busca, params)

  function alternar(o: Option) {
    onChange(value.some((v) => v.id === o.id) ? value.filter((v) => v.id !== o.id) : [...value, o])
  }

  return (
    <div className={className}>
      <Popover.Root open={open} onOpenChange={setOpen}>
        <Popover.Trigger asChild disabled={disabled}>
          <button
            type="button"
          {...accessible}
            className={cn(
              'flex h-10 w-full items-center justify-between rounded-md border border-input bg-card px-3 py-2 text-sm shadow-sm transition-colors',
              'focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-1 focus:ring-offset-background disabled:cursor-not-allowed disabled:opacity-50',
            )}
          >
            <span className={cn('truncate', value.length === 0 && 'text-muted-foreground')}>
              {value.length === 0 ? placeholder : `${value.length} selecionado${value.length > 1 ? 's' : ''}`}
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
                autoFocus value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Buscar…" aria-label="Buscar opções"
                className="flex h-10 w-full bg-transparent px-2 text-sm outline-none placeholder:text-muted-foreground"
              />
            </div>
            <ul className="max-h-60 overflow-y-auto p-1">
              {value.length > 0 && (
                <li>
                  <button type="button" onClick={() => onChange([])}
                    className="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-sm text-muted-foreground hover:bg-secondary">
                    <X className="size-4" /> Limpar seleção
                  </button>
                </li>
              )}
              {loading ? (
                <li className="px-2 py-3 text-sm text-muted-foreground">Carregando…</li>
              ) : loadError ? (
              <li role="alert" className="px-2 py-3 text-sm">
                <p>{loadError}</p>
                <button type="button" onClick={retry} className="mt-2 rounded px-2 py-2 font-medium text-accent-foreground underline">Tentar novamente</button>
              </li>
            ) : options.length === 0 ? (
                <li className="px-2 py-3 text-sm text-muted-foreground">Nenhum resultado.</li>
              ) : options.map((o) => (
                <li key={o.id}>
                  <button type="button" onClick={() => alternar(o)}
                    className="flex w-full items-center justify-between gap-2 rounded-sm px-2 py-1.5 text-left text-sm hover:bg-secondary">
                    <span className="truncate">{o.label}</span>
                    {value.some((v) => v.id === o.id) && <Check className="size-4 shrink-0 text-primary" />}
                  </button>
                </li>
              ))}
            </ul>
          </Popover.Content>
        </Popover.Portal>
      </Popover.Root>

      {/* Os escolhidos ficam à vista: o filtro é a justificativa do que saiu. */}
      {value.length > 0 && (
        <div className="mt-2 flex flex-wrap gap-1">
          {value.map((o) => (
            <button key={o.id} type="button" onClick={() => alternar(o)}
              className="inline-flex items-center gap-1 rounded-full bg-secondary px-2 py-0.5 text-xs text-secondary-foreground hover:bg-secondary/70">
              <span className="max-w-[14rem] truncate">{o.label}</span>
              <X className="size-3 shrink-0" />
            </button>
          ))}
        </div>
      )}
    </div>
  )
}
