import { Children, isValidElement, forwardRef, useEffect, useRef, useState, type ComponentPropsWithoutRef, type ElementRef, type ReactNode } from 'react'
import { useSearchParams } from 'react-router-dom'
import * as TabsPrimitive from '@radix-ui/react-tabs'
import { cn } from '@/lib/cn'

type TabsProps = ComponentPropsWithoutRef<typeof TabsPrimitive.Root> & { urlKey?: string }
export function Tabs({ urlKey, ...props }: TabsProps) {
  return urlKey ? <UrlTabs urlKey={urlKey} {...props} /> : <TabsPrimitive.Root {...props} />
}
function UrlTabs({ urlKey, ...props }: TabsProps & { urlKey: string }) {
  const [params, setParams] = useSearchParams()
  const allowed: string[] = []
  function visit(children: ReactNode) {
    Children.forEach(children, (child) => {
      if (!isValidElement<{ value?: string; children?: ReactNode }>(child)) return
      if (child.type === TabsTrigger && child.props.value) allowed.push(child.props.value)
      else if (child.type !== Tabs && child.type !== TabsContent) visit(child.props.children)
    })
  }
  visit(props.children)
  const requested = params.get(urlKey)
  const value = props.value ?? (requested && allowed.includes(requested) ? requested : props.defaultValue)
  return <TabsPrimitive.Root {...props} value={value} onValueChange={(next) => {
    props.onValueChange?.(next)
    setParams((current) => { const copy = new URLSearchParams(current); copy.set(urlKey, next); return copy }, { replace: false })
  }} />
}

export const TabsList = forwardRef<
  ElementRef<typeof TabsPrimitive.List>,
  ComponentPropsWithoutRef<typeof TabsPrimitive.List>
>(function TabsList({ className, ...props }, ref) {
  const interno = useRef<HTMLDivElement | null>(null)
  const [sobra, setSobra] = useState({ esquerda: false, direita: false })

  // Quando as abas não cabem, as últimas ficavam cortadas no meio da palavra
  // sem nada dizer que havia mais à direita. O esmaecimento na borda mostra
  // que a faixa continua — e some quando se chega ao fim.
  useEffect(() => {
    const el = interno.current
    if (!el) return
    const medir = () => {
      const esquerda = el.scrollLeft > 1
      const direita = el.scrollLeft + el.clientWidth < el.scrollWidth - 1
      setSobra((s) => (s.esquerda === esquerda && s.direita === direita ? s : { esquerda, direita }))
    }
    medir()
    el.addEventListener('scroll', medir, { passive: true })
    const obs = typeof ResizeObserver === 'undefined' ? null : new ResizeObserver(medir)
    obs?.observe(el)
    return () => { el.removeEventListener('scroll', medir); obs?.disconnect() }
  }, [])

  // Máscara, não gradiente de cor: a faixa aparece no fundo da página e
  // dentro de cartões, e a máscara serve aos dois sem saber a cor de fundo.
  const mascara = sobra.esquerda || sobra.direita
    ? `linear-gradient(to right, ${sobra.esquerda ? 'transparent 0, #000 48px' : '#000 0'}, ${sobra.direita ? '#000 calc(100% - 48px), transparent 100%' : '#000 100%'})`
    : undefined

  return (
    <TabsPrimitive.List
      ref={(no) => { interno.current = no; if (typeof ref === 'function') ref(no); else if (ref) ref.current = no }}
      data-sobra-direita={sobra.direita || undefined}
      style={mascara ? { maskImage: mascara, WebkitMaskImage: mascara } : undefined}
      // Rola na horizontal no mobile (muitas abas não cabem) sem cortar.
      className={cn(
        'flex items-end gap-1 border-b border-border w-full overflow-x-auto',
        '[scrollbar-width:thin] [&::-webkit-scrollbar]:h-1.5',
        className,
      )}
      {...props}
    />
  )
})

/**
 * Agrupa abas da mesma natureza dentro da faixa, com rótulo e divisória.
 * Financeiro tinha 10 abas numa fila só: o lançamento do dia ao lado do plano
 * de contas que se mexe uma vez por ano, e a pessoa lia todas para achar uma.
 * O rótulo é visual (aria-hidden): para o leitor de tela a faixa continua uma
 * lista de abas, que é o único conteúdo que o papel `tablist` admite.
 */
export function TabsGroup({ label, children, className }: { label: string; children: ReactNode; className?: string }) {
  return (
    // Rótulo EM CIMA das abas, não ao lado: ao lado ele comia ~250px da
    // faixa e empurrava as últimas abas para fora da tela.
    <div role="presentation" className={cn('flex shrink-0 flex-col [&+&]:ml-2 [&+&]:border-l [&+&]:border-border [&+&]:pl-2', className)}>
      <span aria-hidden className="hidden select-none px-3 pt-1 text-[10px] font-semibold uppercase leading-4 tracking-[0.1em] text-muted-foreground/80 lg:block">{label}</span>
      <div className="flex items-center gap-1">{children}</div>
    </div>
  )
}

export const TabsTrigger = forwardRef<
  ElementRef<typeof TabsPrimitive.Trigger>,
  ComponentPropsWithoutRef<typeof TabsPrimitive.Trigger>
>(function TabsTrigger({ className, ...props }, ref) {
  return (
    <TabsPrimitive.Trigger
      ref={ref}
      className={cn(
        'inline-flex shrink-0 items-center justify-center gap-2 whitespace-nowrap border-b-2 border-transparent px-4 py-2.5 text-sm font-medium text-muted-foreground transition-colors -mb-px',
        'hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-t-md',
        'data-[state=active]:border-primary data-[state=active]:text-accent-foreground disabled:pointer-events-none disabled:opacity-50',
        className,
      )}
      {...props}
    />
  )
})

export const TabsContent = forwardRef<
  ElementRef<typeof TabsPrimitive.Content>,
  ComponentPropsWithoutRef<typeof TabsPrimitive.Content>
>(function TabsContent({ className, ...props }, ref) {
  return (
    <TabsPrimitive.Content
      ref={ref}
      className={cn('mt-6 focus-visible:outline-none', className)}
      {...props}
    />
  )
})
