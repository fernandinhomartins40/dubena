import { useEffect, useState, useRef } from 'react'
import { useDirtyRegistration } from './UnsavedChanges'

/**
 * useResourceForm — abstrai o boilerplate das form-pages (cliente/produto/…):
 *   - estado `form` + setter por campo `campo(k, v)`
 *   - hidratação a partir do registro existente (com coerção 1/0 → boolean
 *     para as chaves listadas em `booleanKeys`)
 *   - `erros` de validação do backend (HTTP 422) + helper `submit`
 *   - flag `dirty` (mudou em relação ao snapshot inicial)
 *
 * O `submit` recebe a função que persiste (mutateAsync) e devolve o registro
 * salvo; em 422 popula `erros` e relança para o chamador decidir foco/aba.
 */
export interface ResourceFormOptions<T> {
  /** valores iniciais (estado "novo") */
  vazio: T
  /** registro vindo do backend para edição (ou null/undefined p/ novo) */
  existente?: Partial<T> | null
  /** chaves que vêm como 1/0 no backend e devem virar boolean no form */
  booleanKeys?: (keyof T)[]
  /** mapeia o registro do backend → form (sobrescreve a hidratação padrão) */
  hidratar?: (existente: Partial<T>, vazio: T) => T
}

export function useResourceForm<T extends Record<string, any>>(opts: ResourceFormOptions<T>) {
  const { vazio, existente, booleanKeys = [], hidratar } = opts
  const [form, setForm] = useState<T>(vazio)
  const [inicial, setInicial] = useState<T>(vazio)
  const [erros, setErros] = useState<Record<string, string>>({})
  const dirty = JSON.stringify(form) !== JSON.stringify(inicial)
  const markClean = useDirtyRegistration(dirty)
  const dirtyRef = useRef(dirty)
  dirtyRef.current = dirty
  const entityRef = useRef<unknown>(undefined)

  useEffect(() => {
    if (!existente) return
    // Refetch do mesmo cadastro não sobrescreve o trabalho em andamento.
    if (entityRef.current === existente.id && dirtyRef.current) return
    entityRef.current = existente.id
    let f: T
    if (hidratar) {
      f = hidratar(existente, vazio)
    } else {
      f = { ...vazio }
      ;(Object.keys(vazio) as (keyof T)[]).forEach((k) => {
        const v = existente[k]
        if (v !== undefined && v !== null) {
          ;(f as any)[k] = booleanKeys.includes(k) ? Number(v) === 1 : v
        }
      })
    }
    setForm(f)
    setInicial(f)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [existente])

  function campo<K extends keyof T>(k: K, v: T[K]) {
    setForm((prev) => ({ ...prev, [k]: v }))
  }

  useEffect(() => {
    const protect = (event: BeforeUnloadEvent) => {
      if (!dirtyRef.current) return
      event.preventDefault()
      event.returnValue = ''
    }
    window.addEventListener('beforeunload', protect)
    return () => window.removeEventListener('beforeunload', protect)
  }, [])

  /** Persiste via `fn`; em 422 popula `erros` e relança. */
  async function submit<R>(fn: (data: T) => Promise<R>): Promise<R> {
    setErros({})
    try {
      const result = await fn(form)
      setInicial(form)
      dirtyRef.current = false
      markClean()
      return result
    } catch (e: any) {
      if (e?.response?.status === 422 && e.response.data?.errors) {
        const ve = e.response.data.errors as Record<string, string[]>
        setErros(Object.fromEntries(Object.entries(ve).map(([k, v]) => [k, v[0]])))
      }
      throw e
    }
  }

  return { form, setForm, campo, erros, setErros, dirty, submit }
}
