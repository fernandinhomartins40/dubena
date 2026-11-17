import { expect, type Page, type APIRequestContext } from '@playwright/test'
import { consultar } from '../support/db'
import { PERFIS, type Perfil } from '../support/fixtures'

/**
 * Apoio das jornadas de caixa e financeiro.
 *
 * As jornadas rodam em série (workers: 1) e em dois projetos (desktop e
 * celular) contra o MESMO banco: cada teste escolhe no banco um título ainda
 * em aberto, em vez de fixar um id, para o segundo projeto não tropeçar no
 * que o primeiro já baixou.
 */
export interface ParcelaAberta { id: number; financeiroId: number; descricao: string; valor: number }

export function parcelaAberta(minimo = 50): ParcelaAberta {
  const [l] = consultar(`
    select p.id, p.financeiro_id, f.descricao, p.valor
      from financeiroparcelas p join financeiros f on f.id = p.financeiro_id
     where f.empresa_id = 1 and f.pagarreceber = 'R' and not f.cancelado and not p.baixado
       and coalesce(f.agrupamento_status, 0) = 0 and f.descricao like 'Pedido #%' and p.valor >= ${minimo}
       and not exists (select 1 from contamovimentos m where m.financeiroparcela_id = p.id)
       and not exists (select 1 from financeiroparcelas o where o.financeiro_id = p.financeiro_id and o.id <> p.id)
     order by p.id limit 1`)
  if (!l) throw new Error('Sem parcela em aberto no banco do agente: recrie pie_r3_b a partir do pie_r3_base.')
  return { id: Number(l[0]), financeiroId: Number(l[1]), descricao: l[2], valor: Number(l[3]) }
}

/** Movimentos de caixa gravados para a parcela (prova de persistência). */
export function movimentosDaParcela(parcelaId: number) {
  return consultar(`select id, conta_id, tipo, valor, juros, multa, desconto, tenant_account_id is not null
                      from contamovimentos where financeiroparcela_id = ${parcelaId} order by id`)
}

export function parcela(parcelaId: number) {
  const [l] = consultar(`select baixado, valor, valor_efetivado, tenant_account_id is not null from financeiroparcelas where id = ${parcelaId}`)
  return { baixado: l[0] === 't', valor: Number(l[1]), efetivado: Number(l[2]), comTenant: l[3] === 't' }
}

export function saldoConta(contaId: number): number {
  return Number(consultar(`select saldo_atual from contas where id = ${contaId}`)[0][0])
}

/** Σ movimentos = saldo_atual — a invariante do CaixaService. */
export function invarianteDoCaixa(contaId: number) {
  const [l] = consultar(`select c.saldo_atual, coalesce(sum(m.valor), 0) from contas c
                           left join contamovimentos m on m.conta_id = c.id where c.id = ${contaId} group by c.saldo_atual`)
  return { saldo: Number(l[0]), soma: Number(l[1]) }
}

/** Abre a aba "A pagar e receber" já filtrada no título da jornada. */
export async function irParaTitulo(page: Page, p: ParcelaAberta) {
  await page.goto('financeiro?tab=lancamentos')
  await page.getByLabel('Buscar lançamentos').fill(p.descricao)
  await page.getByRole('button', { name: 'Buscar', exact: true }).click()
  return linhaDoTitulo(page, p.descricao)
}

export function linhaDoTitulo(page: Page, descricao: string) {
  // "Pedido #1" também casa "Pedido #10": exige que o número termine ali.
  return page.getByRole('row').filter({ hasText: new RegExp(`${descricao.replace('#', '\\#')}(?!\\d)`) }).first()
}

export async function escolherConta(page: Page, rotulo: RegExp, conta: string) {
  await page.getByLabel(rotulo).click()
  await page.getByRole('button', { name: conta, exact: true }).click()
}

/** Garante a conta com caixa aberto (fechamento aberto), como o operador encontra de manhã. */
export async function garantirCaixaAberto(request: APIRequestContext, contaId: number) {
  const [l] = consultar(`select c.fechado, exists(select 1 from contafechamentos f where f.conta_id = c.id and f.aberto) from contas c where c.id = ${contaId}`)
  if (l[0] === 'f' && l[1] === 't') return
  const token = await tokenDe(request, 'gerente')
  const r = await request.post(`${api()}/admin/caixa/${contaId}/abrir`, { headers: cab(token), data: {} })
  expect(r.status(), await r.text()).toBe(201)
}

export function api() {
  return `${process.env.PIE_API_URL ?? 'http://127.0.0.1:8000'}/api`
}

export function cab(token?: string) {
  return { Accept: 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) }
}

export async function tokenDe(request: APIRequestContext, perfil: Perfil): Promise<string> {
  const { email, senha } = PERFIS[perfil]
  const r = await request.post(`${api()}/login`, { headers: cab(), data: { email, password: senha } })
  expect(r.status(), `login de ${perfil}`).toBe(200)
  const j = await r.json()
  return j.token ?? j.data?.token
}

export function valorBR(v: number) {
  return v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }).replace(/ /g, ' ')
}
