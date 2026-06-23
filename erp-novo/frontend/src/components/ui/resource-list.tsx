import type { ReactNode } from 'react'
import { PageHeader } from './page-header'
import { DataTable, type Column } from './data-table'
import { EmptyState } from './empty-state'

/**
 * ResourceList — scaffold de página de listagem do padrão:
 * PageHeader (título/subtítulo + ação) → [filtros opcionais] → DataTable com
 * loading/empty. Reduz cada página de lista a colunas + dados + ação.
 */
interface Props<T> {
  title: string
  subtitle?: string
  /** botão(ões) de ação no header, ex.: "Novo" */
  action?: ReactNode
  /** barra de filtros opcional, renderizada entre o header e a tabela */
  filtros?: ReactNode
  columns: Column<T>[]
  rows: T[] | undefined
  loading?: boolean
  rowKey: (row: T) => string | number
  onRowClick?: (row: T) => void
  /** estado vazio: ícone + título (description opcional) */
  emptyIcon?: ReactNode
  emptyTitle?: string
  emptyDescription?: string
}

export function ResourceList<T>({
  title, subtitle, action, filtros, columns, rows, loading, rowKey, onRowClick,
  emptyIcon, emptyTitle = 'Nenhum registro', emptyDescription,
}: Props<T>) {
  return (
    <div>
      <PageHeader title={title} subtitle={subtitle} action={action} />
      {filtros}
      <DataTable
        columns={columns}
        rows={rows}
        loading={loading}
        rowKey={rowKey}
        onRowClick={onRowClick}
        empty={<EmptyState icon={emptyIcon} title={emptyTitle} description={emptyDescription} />}
      />
    </div>
  )
}
