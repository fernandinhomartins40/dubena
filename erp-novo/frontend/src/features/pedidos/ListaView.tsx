import { useState } from 'react'
import { ShoppingCart } from 'lucide-react'
import {
  DataTable, type Column, EmptyState, AsyncSelect, SearchBar,
} from '@/components/ui'
import { useBusca } from '@/lib/useBusca'
import { usePedidos, usePedidoSituacoes, type PedidoListItem } from './api'
import { brl, dataHora as fmtData } from '@/lib/format'
import { situacaoBadge } from './shared'

export function ListaView({ onOpen }: { onOpen: (id: number) => void }) {
  const [sit, setSit] = useState(0)
  const { busca, setBusca, q, page, setPage, submit } = useBusca()
  const { data, isLoading, isFetching } = usePedidos(sit, q, page)
  const { data: situacoes } = usePedidoSituacoes()

  const columns: Column<PedidoListItem>[] = [
    { key: 'id', header: 'Pedido', width: 'w-24', cell: (p) => <span className="font-medium tabular-nums">#{p.id}</span> },
    { key: 'cliente', header: 'Cliente', cell: (p) => p.cliente || '—' },
    { key: 'data', header: 'Data', cell: (p) => fmtData(p.datahora) },
    { key: 'valor', header: 'Valor', align: 'right', cell: (p) => <span className="tabular-nums">{brl(p.valorvenda)}</span> },
    { key: 'sit', header: 'Situação', align: 'center', cell: (p) => situacaoBadge(p) },
  ]
  return (
    <>
      <SearchBar value={busca} onChange={setBusca} onSearch={submit} placeholder="Buscar cliente ou nº…">
        <div className="w-56"><AsyncSelect endpoint="/lookups/pedido-situacoes" value={sit || null} valueLabel={situacoes?.find((s) => s.id === sit)?.descricao ?? null} placeholder="Todas as situações"
          onChange={(id) => { setPage(1); setSit(id ?? 0) }} /></div>
      </SearchBar>
      <DataTable columns={columns} rows={data?.data} loading={isLoading} rowKey={(p) => p.id} onRowClick={(p) => onOpen(p.id)}
        page={data?.meta.current_page} lastPage={data?.meta.last_page} onPageChange={setPage} fetching={isFetching}
        empty={<EmptyState icon={<ShoppingCart />} title="Nenhum pedido" />} />
    </>
  )
}
