import { describe, it, expect } from 'vitest'
import { can, canField } from './rbac'
import { normalizarMe } from './auth'
import type { AuthUser } from './auth'

const base: AuthUser = {
  id: 1, name: 'Ana', email: 'a@x.com', empresa_id: 1, grupo_id: 1,
  is_support: false, roles: ['Vendas'], permissions: ['pedido.view', 'pedido.create'],
}

describe('rbac.can', () => {
  it('null → false', () => expect(can(null, 'pedido.view')).toBe(false))

  it('permissão na lista → true; fora → false', () => {
    expect(can(base, 'pedido.view')).toBe(true)
    expect(can(base, 'financeiro.baixar')).toBe(false)
  })

  it('support ignora a lista (pode tudo)', () => {
    const sup = { ...base, is_support: true, permissions: [] as string[] }
    expect(can(sup, 'qualquer.coisa')).toBe(true)
  })
})

describe('rbac.canField', () => {
  it('exige a chave modulo.campo.{nome}.{acao}', () => {
    const u = { ...base, permissions: ['cliente.campo.credito_limite.view'] }
    expect(canField(u, 'cliente', 'credito_limite', 'view')).toBe(true)
    expect(canField(u, 'cliente', 'credito_limite', 'edit')).toBe(false)
  })

  it('support vê/edita tudo', () => {
    const sup = { ...base, is_support: true, permissions: [] as string[] }
    expect(canField(sup, 'cliente', 'credito_limite', 'edit')).toBe(true)
  })
})

describe('normalizarMe', () => {
  it('achata o shape {user,tenant} do backend novo', () => {
    const u = normalizarMe({
      user: { id: 9, name: 'B', email: 'b@x.com', support: true, roles: ['Adm'], permissions: ['x.y'] },
      tenant: { empresa_id: 5, grupo_id: 2 },
    })
    expect(u).toMatchObject({ id: 9, empresa_id: 5, grupo_id: 2, is_support: true, roles: ['Adm'], permissions: ['x.y'] })
  })

  it('tolera o shape plano e defaults', () => {
    const u = normalizarMe({ id: 3, name: 'C', email: 'c@x.com' })
    expect(u).toMatchObject({ id: 3, empresa_id: null, grupo_id: null, is_support: false, roles: [], permissions: [] })
  })
})
