import { ShoppingCart } from 'lucide-react'
import { DataTable, type Column, EmptyState } from '@/components/ui'
import { brl, dataHora } from '@/lib/format'
import { useHistorico } from './api'

interface Pedido { id: number; datahora: string | null; valorvenda: number | null }

export function HistoricoTab({ clienteId }: { clienteId: number }) {
  const { data, isLoading } = useHistorico(clienteId)
  const columns: Column<Pedido>[] = [
    { key: 'id', header: 'Pedido', cell: (h) => <span className="font-medium">#{h.id}</span> },
    { key: 'data', header: 'Data', cell: (h) => dataHora(h.datahora) },
    { key: 'valor', header: 'Valor', align: 'right', cell: (h) => <span className="tabular-nums">{brl(h.valorvenda)}</span> },
  ]
  return (
    <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(h) => h.id}
      empty={<EmptyState icon={<ShoppingCart />} title="Sem pedidos" description="Este cliente ainda não fez pedidos." />} />
  )
}
