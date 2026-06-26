import { useState } from 'react'

/**
 * useBusca — padrão de busca das telas de lista: um valor "digitado" (`busca`)
 * e um valor "submetido" (`q`, o que de fato vai para a query), além de `page`
 * com reset automático a cada nova busca.
 *
 *   const { busca, setBusca, q, page, setPage, submit } = useBusca()
 *   const { data } = useLista(q, page)
 *   <SearchBar value={busca} onChange={setBusca} onSearch={submit} />
 */
export function useBusca(inicial = '') {
  const [busca, setBusca] = useState(inicial)
  const [q, setQ] = useState(inicial)
  const [page, setPage] = useState(1)

  /** Aplica o termo digitado: zera a página e dispara a query. */
  function submit() {
    setPage(1)
    setQ(busca)
  }

  return { busca, setBusca, q, setQ, page, setPage, submit }
}
