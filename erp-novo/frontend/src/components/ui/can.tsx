import type { ReactNode } from 'react'
import { useAuth } from '@/lib/auth'

/**
 * <Can permission="x.y"> — renderiza os filhos só se o usuário tiver a permissão
 * (A7). Açúcar para `can()` em JSX; `fallback` opcional para o caso negado. A
 * autoridade é o backend — isto é só UX.
 */
export function Can({ permission, fallback = null, children }: {
  permission: string
  fallback?: ReactNode
  children: ReactNode
}) {
  const { can } = useAuth()
  return <>{can(permission) ? children : fallback}</>
}
