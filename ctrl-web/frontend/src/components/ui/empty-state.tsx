import { type ReactNode } from 'react'
import { cn } from '@/lib/cn'

/** Estado vazio: ícone + título + descrição + ação opcional. */
export function EmptyState({
  icon, title, description, action, className,
}: {
  icon?: ReactNode
  title: string
  description?: string
  action?: ReactNode
  className?: string
}) {
  return (
    <div className={cn('flex flex-col items-center justify-center gap-3 py-14 text-center', className)}>
      {icon && (
        <div className="grid size-12 place-items-center rounded-full bg-secondary text-muted-foreground [&_svg]:size-6">
          {icon}
        </div>
      )}
      <div className="space-y-1">
        <p className="font-medium">{title}</p>
        {description && <p className="text-sm text-muted-foreground max-w-sm">{description}</p>}
      </div>
      {action}
    </div>
  )
}
