import type { ReactNode } from 'react'
import { Skeleton } from './skeleton'
import { EmptyState } from './empty-state'

/**
 * AsyncState — contrato único de carregamento/erro/vazio para blocos que NÃO
 * são tabelas (DataTable/ResourceList já tratam isso internamente). Use em
 * abas, painéis e seções com query própria, no lugar de "Carregando…" ad-hoc.
 *
 *   <AsyncState loading={isLoading} error={error} empty={!dados?.length}
 *     emptyTitle="Nenhum registro">
 *     {…conteúdo…}
 *   </AsyncState>
 */
interface Props {
  loading?: boolean
  error?: unknown
  /** condição de vazio (avaliada só quando não há loading/erro) */
  empty?: boolean
  /** nº de linhas de esqueleto durante o loading (default 3) */
  skeletonRows?: number
  emptyIcon?: ReactNode
  emptyTitle?: string
  emptyDescription?: string
  children: ReactNode
}

function mensagemErro(error: unknown): string {
  const e = error as { response?: { data?: { message?: string } }; message?: string }
  return e?.response?.data?.message ?? e?.message ?? 'Não foi possível carregar.'
}

export function AsyncState({
  loading, error, empty, skeletonRows = 3,
  emptyIcon, emptyTitle = 'Nenhum registro', emptyDescription, children,
}: Props) {
  if (loading) {
    return (
      <div className="space-y-2" aria-busy="true">
        {Array.from({ length: skeletonRows }).map((_, i) => <Skeleton key={i} className="h-9 w-full" />)}
      </div>
    )
  }
  if (error) {
    return <EmptyState title="Erro ao carregar" description={mensagemErro(error)} />
  }
  if (empty) {
    return <EmptyState icon={emptyIcon} title={emptyTitle} description={emptyDescription} />
  }
  return <>{children}</>
}
