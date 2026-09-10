import { useState, type SetStateAction } from 'react'

/** Somente preferências de apresentação. Falha no storage não impede uso. */
export function usePreference<T>(key: string, fallback: T) {
  const read = (): T => {
    try { const stored = localStorage.getItem(key); return stored === null ? fallback : JSON.parse(stored) as T } catch { return fallback }
  }
  const [state, setState] = useState(() => ({ key, value: read() }))
  // Não reutiliza preferências de outra pessoa quando a sessão muda sem remount.
  const value = state.key === key ? state.value : read()
  const setValue = (next: SetStateAction<T>) => {
    const resolved = typeof next === 'function' ? (next as (current: T) => T)(value) : next
    setState({ key, value: resolved })
    try { localStorage.setItem(key, JSON.stringify(resolved)) } catch { /* memória continua válida */ }
  }
  return [value, setValue] as const
}
