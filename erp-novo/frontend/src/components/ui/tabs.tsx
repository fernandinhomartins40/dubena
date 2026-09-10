import { Children, isValidElement, forwardRef, type ComponentPropsWithoutRef, type ElementRef, type ReactNode } from 'react'
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
  return (
    <TabsPrimitive.List
      ref={ref}
      // Rola na horizontal no mobile (muitas abas não cabem) sem cortar.
      className={cn(
        'flex items-center gap-1 border-b border-border w-full overflow-x-auto',
        '[scrollbar-width:thin] [&::-webkit-scrollbar]:h-1.5',
        className,
      )}
      {...props}
    />
  )
})

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
