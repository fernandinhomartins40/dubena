import { useState, type SetStateAction } from 'react'
import { useLocation } from 'react-router-dom'
import { useAuth } from './auth'
import { getFiltroEmpresa } from './api'

/**
 * useBusca — padrão de busca das telas de lista: um valor "digitado" (`busca`)
 * e um valor "submetido" (`q`, o que de fato vai para a query), além de `page`
 * com reset automático a cada nova busca.
 *
 *   const { busca, setBusca, q, page, setPage, submit } = useBusca()
 *   const { data } = useLista(q, page)
 *   <SearchBar value={busca} onChange={setBusca} onSearch={submit} />
 */
export function useBusca(inicial = '', scope = 'lista') {
  const { user } = useAuth()
  const location = useLocation()
  // Termos podem conter CPF/telefone: ficam na sessão, nunca na URL.
  const key = `erpnovo.search.${user?.id}.${user?.empresa_id}.${getFiltroEmpresa()}.${location.pathname}.${location.search}.${scope}`
  const read = () => {
    try {
      const saved = JSON.parse(sessionStorage.getItem(key) ?? 'null')
      if (saved && typeof saved.busca === 'string' && typeof saved.q === 'string' && Number.isInteger(saved.page) && saved.page > 0) return saved as { busca: string; q: string; page: number }
    } catch { /* fallback limpo */ }
    return { busca: inicial, q: inicial, page: 1 }
  }
  const [state, setState] = useState(() => ({ key, ...read() }))
  const current = state.key === key ? state : { key, ...read() }
  function update(fn: (value: typeof current) => typeof current) {
    setState((previous) => {
      const next = fn(previous.key === key ? previous : { key, ...read() })
      try { sessionStorage.setItem(key, JSON.stringify({ busca: next.busca, q: next.q, page: next.page })) } catch { /* memória */ }
      return next
    })
  }
  const setBusca = (value: SetStateAction<string>) => update((s) => ({ ...s, busca: typeof value === 'function' ? value(s.busca) : value }))
  const setQ = (value: SetStateAction<string>) => update((s) => ({ ...s, q: typeof value === 'function' ? value(s.q) : value }))
  const setPage = (value: SetStateAction<number>) => update((s) => ({ ...s, page: typeof value === 'function' ? value(s.page) : value }))

  /** Aplica o termo digitado: zera a página e dispara a query. */
  function submit() {
    update((s) => ({ ...s, page: 1, q: s.busca }))
  }

  return { busca: current.busca, setBusca, q: current.q, setQ, page: current.page, setPage, submit }
}
