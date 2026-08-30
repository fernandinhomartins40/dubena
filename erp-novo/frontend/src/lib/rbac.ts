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

/**
 * O módulo está CONTRATADO no plano da empresa? (F2-03)
 *
 * Distinto de `can`: permissão responde "este usuário pode", feature responde
 * "a empresa comprou". Sem isto o menu mostraria Monitoramento para quem não
 * contratou, e o clique voltaria 402 — o usuário descobriria pelo erro.
 *
 * `support` NÃO fura licença: bypass de RBAC é acesso, não é contrato. Quem dá
 * suporte não passa a ter direito a um módulo que a revenda não comprou.
 *
 * Ausência de `features` no payload (backend antigo) libera, para a SPA não
 * quebrar durante um deploy em que o backend ainda não envia o campo.
 */
export function hasFeature(user: AuthUser | null, feature: string): boolean {
  if (!user) return false
  if (!user.features) return true
  return user.features.includes(feature)
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
