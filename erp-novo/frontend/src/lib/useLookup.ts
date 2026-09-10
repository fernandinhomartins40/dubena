import { useEffect, useState } from 'react'
import { api } from './api'
import type { Option } from '@/components/ui/async-select'

export function parseLookup(data: unknown, labelField = 'label'): Option[] {
  const list = Array.isArray(data) ? data : (data as { data?: unknown } | null)?.data
  if (!Array.isArray(list) || list.some((item) => !item || typeof item.id !== 'number' || typeof item[labelField] !== 'string')) {
    throw new Error('A lista recebida está em um formato inesperado.')
  }
  return list.map((item) => ({ ...item, label: item[labelField] }))
}

/** Cancela requests e ignora respostas de buscas/contextos anteriores. */
export function useLookup(open: boolean, endpoint: string, search: string, params?: Record<string, unknown>, labelField = 'label') {
  const [options, setOptions] = useState<Option[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [attempt, setAttempt] = useState(0)
  const paramsKey = JSON.stringify(params ?? {})
  useEffect(() => {
    if (!open) return
    const controller = new AbortController()
    setLoading(true)
    setError(null)
    setOptions([])
    const timer = setTimeout(async () => {
      try {
        const response = await api.get(endpoint, { params: { q: search, ...JSON.parse(paramsKey) }, signal: controller.signal })
        const next = parseLookup(response.data, labelField)
        if (!controller.signal.aborted) setOptions(next)
      } catch {
        if (!controller.signal.aborted) setError('Não foi possível carregar as opções. Tente novamente.')
      } finally {
        if (!controller.signal.aborted) setLoading(false)
      }
    }, 250)
    return () => { controller.abort(); clearTimeout(timer) }
  }, [open, endpoint, search, paramsKey, attempt, labelField])
  return { options, loading, error, retry: () => setAttempt((n) => n + 1) }
}
