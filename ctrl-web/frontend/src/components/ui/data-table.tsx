import { type ReactNode } from 'react'
import { ChevronLeft, ChevronRight } from 'lucide-react'
import { cn } from '@/lib/cn'
import { Card } from './card'
import { Button } from './button'
import { Skeleton } from './skeleton'
import { EmptyState } from './empty-state'

export interface Column<T> {
  /** chave/identificador da coluna */
  key: string
  header: ReactNode
  /** célula (recebe a linha) */
  cell: (row: T) => ReactNode
  className?: string
  /** alinhamento do conteúdo */
  align?: 'left' | 'right' | 'center'
  /** largura fixa (ex.: 'w-24') */
  width?: string
}

interface DataTableProps<T> {
  columns: Column<T>[]
  rows: T[] | undefined
  loading?: boolean
  rowKey: (row: T) => string | number
  onRowClick?: (row: T) => void
  /** estado vazio customizado */
  empty?: ReactNode
  /** paginação (opcional) */
  page?: number
  lastPage?: number
  onPageChange?: (page: number) => void
  fetching?: boolean
  pageInfo?: string
  className?: string
}

const alignClass = { left: 'text-left', right: 'text-right', center: 'text-center' }

export function DataTable<T>({
  columns, rows, loading, rowKey, onRowClick, empty,
  page, lastPage, onPageChange, fetching, pageInfo, className,
}: DataTableProps<T>) {
  const hasPagination = page != null && lastPage != null && onPageChange && lastPage > 1

  return (
    <div className={className}>
      <Card className="overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="sticky top-0 z-10 bg-muted/60 backdrop-blur text-muted-foreground">
              <tr className="border-b border-border">
                {columns.map((c) => (
                  <th key={c.key} className={cn('px-4 py-3 font-medium', alignClass[c.align ?? 'left'], c.width)}>
                    {c.header}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {loading ? (
                Array.from({ length: 6 }).map((_, i) => (
                  <tr key={i} className="border-b border-border/60">
                    {columns.map((c) => (
                      <td key={c.key} className="px-4 py-3"><Skeleton className="h-4 w-full max-w-[12rem]" /></td>
                    ))}
                  </tr>
                ))
              ) : rows && rows.length > 0 ? (
                rows.map((row) => (
                  <tr
                    key={rowKey(row)}
                    onClick={onRowClick ? () => onRowClick(row) : undefined}
                    className={cn(
                      'border-b border-border/60 transition-colors',
                      onRowClick && 'cursor-pointer hover:bg-secondary/60',
                    )}
                  >
                    {columns.map((c) => (
                      <td key={c.key} className={cn('px-4 py-3', alignClass[c.align ?? 'left'], c.className)}>
                        {c.cell(row)}
                      </td>
                    ))}
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={columns.length}>
                    {empty ?? <EmptyState title="Nenhum registro encontrado." />}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </Card>

      {hasPagination && (
        <div className="mt-4 flex items-center justify-between text-sm text-muted-foreground">
          <span>{pageInfo ?? `Página ${page} de ${lastPage}`} {fetching && '· atualizando…'}</span>
          <div className="flex gap-2">
            <Button variant="outline" size="sm" disabled={page! <= 1} onClick={() => onPageChange!(page! - 1)}>
              <ChevronLeft /> Anterior
            </Button>
            <Button variant="outline" size="sm" disabled={page! >= lastPage!} onClick={() => onPageChange!(page! + 1)}>
              Próxima <ChevronRight />
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}
