import { Users } from 'lucide-react'
import { DataTable, type Column, EmptyState } from '@/components/ui'
import { useComissoes } from '../api'
import { data as fmtData } from '@/lib/format'

export function ComissoesTab({ colaboradorId }: { colaboradorId: number }) {
  const { data, isLoading } = useComissoes(colaboradorId)
  const columns: Column<any>[] = [
    { key: 'prod', header: 'Produto', cell: (c) => c.produto || '—' },
    { key: 'perc', header: '%', align: 'right', cell: (c) => <span className="tabular-nums">{c.percentual ?? 0}</span> },
    { key: 'ini', header: 'Início', cell: (c) => fmtData(c.datainicio) },
    { key: 'fim', header: 'Fim', cell: (c) => fmtData(c.datafim) },
  ]
  return <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(c) => c.id} empty={<EmptyState icon={<Users />} title="Nenhuma comissão" />} />
}
