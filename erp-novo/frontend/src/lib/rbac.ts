import type { AuthUser } from './auth'

/**
 * Predicados PUROS de RBAC do cliente (FE-3) — extraídos do AuthProvider para
 * serem testáveis sem montar React/Query. O provider apenas os aplica sobre o
 * `user` atual. Espelham o backend: `support` = pode tudo; senão a permissão
 * precisa estar na lista efetiva.
 */

/** O usuário tem a permissão "modulo.acao"? */
export function can(user: AuthUser | null, permission: string): boolean {
  if (!user) return false
  if (user.is_support) return true
  return user.permissions.includes(permission)
}

/** Field-level (A7): pode ver/editar o campo controlado? */
export function canField(
  user: AuthUser | null,
  modulo: string,
  campo: string,
  acao: 'view' | 'edit',
): boolean {
  if (!user) return false
  if (user.is_support) return true
  return user.permissions.includes(`${modulo}.campo.${campo}.${acao}`)
}
