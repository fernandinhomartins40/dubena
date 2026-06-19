import { type ReactNode, useId } from 'react'
import { Label } from './label'
import { cn } from '@/lib/cn'

/**
 * Padrão de campo de formulário: rótulo + controle + (erro | hint).
 * Use envolvendo um Input/Select/Textarea. `htmlFor` é gerado e injetado no filho
 * via render-prop quando preciso; por simplicidade, passe o id você mesmo se quiser.
 */
export function Field({
  label, required, error, hint, children, className,
}: {
  label?: string
  required?: boolean
  error?: string
  hint?: string
  children: ReactNode
  className?: string
}) {
  const id = useId()
  return (
    <div className={cn('space-y-1.5', className)}>
      {label && (
        <Label htmlFor={id}>
          {label} {required && <span className="text-destructive">*</span>}
        </Label>
      )}
      {/* passa o id por contexto simples: o consumidor pode ignorar */}
      <div id={id}>{children}</div>
      {error ? (
        <p className="text-xs font-medium text-destructive">{error}</p>
      ) : hint ? (
        <p className="text-xs text-muted-foreground">{hint}</p>
      ) : null}
    </div>
  )
}
