import { Users } from 'lucide-react'
import { DataTable, type Column, EmptyState } from '@/components/ui'
import { useRecessos } from '../api'
import { data as fmtData } from '@/lib/format'

export function RecessosTab({ colaboradorId }: { colaboradorId: number }) {
  const { data, isLoading } = useRecessos(colaboradorId)
  const columns: Column<any>[] = [
    { key: 'desc', header: 'Descrição', cell: (r) => r.descricao || '—' },
    { key: 'ini', header: 'Início', cell: (r) => fmtData(r.datainicio) },
    { key: 'fim', header: 'Fim', cell: (r) => fmtData(r.datafinal) },
  ]
  return <DataTable columns={columns} rows={data} loading={isLoading} rowKey={(r) => r.id} empty={<EmptyState icon={<Users />} title="Nenhum recesso" />} />
}
