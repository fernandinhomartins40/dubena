import { type ReactNode, useId, useCallback, useState } from 'react'
import { FieldContext } from './field-context'
import { Label } from './label'
import { cn } from '@/lib/cn'

/**
 * Padrão de campo de formulário: rótulo + controle + (erro | hint).
 * Controles do design system registram seu ID e recebem nome/descrição acessíveis.
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
  const [controls, setControls] = useState<string[]>([])
  const register = useCallback((controlId: string) => {
    setControls((ids) => ids.includes(controlId) ? ids : [...ids, controlId])
    return () => setControls((ids) => ids.filter((value) => value !== controlId))
  }, [])
  const labelId = label ? `${id}-label` : undefined
  const descriptionId = error || hint ? `${id}-description` : undefined
  return (
    <FieldContext.Provider value={{ labelId, descriptionId, invalid: !!error, required, register }}>
    <div className={cn('space-y-1.5', className)}>
      {label && (
        <Label id={labelId} htmlFor={controls[0]}>
          {label} {required && <span className="text-destructive">*</span>}
        </Label>
      )}
      <div>{children}</div>
      {error ? (
        <p id={descriptionId} role="alert" className="text-xs font-medium text-destructive">{error}</p>
      ) : hint ? (
        <p id={descriptionId} className="text-xs text-muted-foreground">{hint}</p>
      ) : null}
    </div>
    </FieldContext.Provider>
  )
}
