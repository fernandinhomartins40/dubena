import { createContext, useContext, useEffect, useId } from 'react'
import type { AriaAttributes } from 'react'

export const FieldContext = createContext<{
  labelId?: string
  descriptionId?: string
  invalid?: boolean
  required?: boolean
  register: (id: string) => () => void
} | null>(null)

/** Cada controle recebe ID próprio, inclusive em campos compostos. */
export function useFieldControl(props: AriaAttributes & { id?: string }, error?: boolean) {
  const field = useContext(FieldContext)
  const generated = useId()
  const id = props.id ?? generated
  const register = field?.register
  useEffect(() => register?.(id), [register, id])
  const join = (...ids: (string | undefined)[]) => [...new Set(ids.filter(Boolean))].join(' ') || undefined
  return {
    id,
    'aria-labelledby': props['aria-labelledby'] ?? (props['aria-label'] ? undefined : field?.labelId),
    'aria-describedby': join(props['aria-describedby'], field?.descriptionId),
    'aria-invalid': props['aria-invalid'] ?? (error || field?.invalid || undefined),
    'aria-required': props['aria-required'] ?? field?.required,
  }
}
