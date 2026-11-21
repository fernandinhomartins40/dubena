import { useFieldControl } from './field-context'
import { useEffect, useRef, useState, type KeyboardEvent, type ReactNode } from 'react'
import * as Popover from '@radix-ui/react-popover'
import { Check, ChevronsUpDown, Search, X } from 'lucide-react'
import { useLookup } from '@/lib/useLookup'
import { cn } from '@/lib/cn'

export interface Option { id: number; label: string; [k: string]: unknown }

interface Props {
  /** endpoint relativo à API admin, ex.: '/lookups/cidades' */
  endpoint: string
  labelField?: string
  params?: Record<string, unknown>
  value: number | null
  /** label inicial p/ exibir quando já há value (modo edição) */
  valueLabel?: string | null
  onChange: (id: number | null, option: Option | null) => void
  placeholder?: string
  disabled?: boolean
  error?: boolean
  className?: string
  /**
   * O que mostrar quando a busca não acha nada (padrão: "Nenhum resultado.").
   * Serve para explicar o vazio e levar à correção — ex.: revenda sem condição
   * de pagamento cadastrada (P2-D-13).
   */
  vazio?: ReactNode
}

/** Seleção assíncrona moderna (Radix Popover + busca server-side com debounce). */
export function AsyncSelect({
  endpoint, params, value, valueLabel, onChange, placeholder = 'Selecione…', disabled, error, className, labelField, vazio,
}: Props) {
  // `aria-required` não é permitido em role=button (axe, crítico); o "*" do rótulo já entra no nome acessível.
  const { 'aria-required': _obrigatorio, ...accessible } = useFieldControl({}, error)
  const [open, setOpen] = useState(false)
  const [busca, setBusca] = useState('')
  const [selectedLabel, setSelectedLabel] = useState<string | null>(valueLabel ?? null)
  const lista = useRef<HTMLUListElement>(null)
  const campoBusca = useRef<HTMLInputElement>(null)

  useEffect(() => { setSelectedLabel(valueLabel ?? null) }, [valueLabel])

  const { options, loading, error: loadError, retry } = useLookup(open, endpoint, busca, params, labelField)

  function escolher(o: Option) { onChange(o.id, o); setSelectedLabel(o.label); setOpen(false); setBusca('') }

  /**
   * P2-D-15: o atendente digita com o fone no ombro e não pega o mouse.
   * Enter no campo escolhe o primeiro resultado; ↓/↑ andam pelas opções (o
   * foco vai para elas, e Enter/Espaço nelas é o clique nativo do botão).
   */
  function opcoesFocaveis(): HTMLButtonElement[] {
    return Array.from(lista.current?.querySelectorAll<HTMLButtonElement>('button[data-opcao]') ?? [])
  }
  function teclaNoCampo(e: KeyboardEvent<HTMLInputElement>) {
    if (e.key === 'Enter') {
      e.preventDefault()
      if (!loading && options.length > 0) escolher(options[0])
    } else if (e.key === 'ArrowDown') {
      e.preventDefault()
      opcoesFocaveis()[0]?.focus()
    }
  }
  function teclaNaLista(e: KeyboardEvent<HTMLUListElement>) {
    if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return
    e.preventDefault()
    const botoes = opcoesFocaveis()
    const atual = botoes.indexOf(document.activeElement as HTMLButtonElement)
    const proximo = e.key === 'ArrowDown' ? atual + 1 : atual - 1
    if (proximo < 0) campoBusca.current?.focus()
    else botoes[Math.min(proximo, botoes.length - 1)]?.focus()
  }

  return (
    <Popover.Root open={open} onOpenChange={setOpen}>
      <Popover.Trigger asChild disabled={disabled}>
        <button
          type="button"
          {...accessible}
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
              ref={campoBusca} onKeyDown={teclaNoCampo}
              autoFocus value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Buscar…" aria-label="Buscar opções"
              className="flex h-10 w-full bg-transparent px-2 text-sm outline-none placeholder:text-muted-foreground"
            />
          </div>
          <ul ref={lista} onKeyDown={teclaNaLista} className="max-h-60 overflow-y-auto p-1">
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
            ) : loadError ? (
              <li role="alert" className="px-2 py-3 text-sm">
                <p>{loadError}</p>
                <button type="button" onClick={retry} className="mt-2 rounded px-2 py-2 font-medium text-accent-foreground underline">Tentar novamente</button>
              </li>
            ) : options.length === 0 ? (
              <li className="px-2 py-3 text-sm text-muted-foreground">{vazio ?? 'Nenhum resultado.'}</li>
            ) : options.map((o) => (
              <li key={o.id}>
                <button type="button" data-opcao=""
                  onClick={() => escolher(o)}
                  className="flex w-full items-center justify-between rounded-sm px-2 py-1.5 text-sm hover:bg-secondary focus:bg-secondary focus:outline-none focus-visible:ring-2 focus-visible:ring-ring">
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
