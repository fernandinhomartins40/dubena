import { type ReactNode } from 'react'
import { cn } from '@/lib/cn'

/** Cabeçalho de página: (breadcrumb) + título + subtítulo + ações à direita. */
export function PageHeader({
  title, subtitle, breadcrumb, action, className,
}: {
  title: ReactNode
  subtitle?: ReactNode
  breadcrumb?: ReactNode
  action?: ReactNode
  className?: string
}) {
  return (
    <div className={cn('mb-6', className)}>
      {breadcrumb && <div className="mb-2 text-sm text-muted-foreground">{breadcrumb}</div>}
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="min-w-0">
          <h1 className="text-2xl font-bold tracking-tight truncate">{title}</h1>
          {subtitle && <p className="mt-1 text-sm text-muted-foreground">{subtitle}</p>}
        </div>
        {action && <div className="flex items-center gap-2 shrink-0">{action}</div>}
      </div>
    </div>
  )
}
