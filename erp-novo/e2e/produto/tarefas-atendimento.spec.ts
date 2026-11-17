import { mkdirSync, writeFileSync } from 'node:fs'
import path from 'node:path'
import { test, expect } from '../support/fixtures'
import { consultar } from '../support/db'
import { entrarDevagar, Tarefa } from './apoio'

/**
 * Protocolo §7.1 — análise de tarefa MEDIDA no navegador (Agente G, Rodada 3).
 *
 * Tarefa principal do operador/atendente: "o cliente ligou e quer o de
 * sempre". Cada ação do usuário é contada (clique, digitação, escolha) com a
 * tela em que aconteceu e o tempo decorrido. O pedido criado é relido no banco
 * — a medição só vale se a tarefa de fato terminou.
 *
 * Grava no banco do agente (PIE_DB), nunca no banco-base.
 */

const SAIDA = path.join(__dirname, '..', 'resultados', 'produto')

function salvar(nome: string, dado: unknown) {
  mkdirSync(SAIDA, { recursive: true })
  writeFileSync(path.join(SAIDA, `${nome}.json`), JSON.stringify(dado, null, 2))
}

test('operador: pedido por telefone de cliente conhecido ("o de sempre")', async ({ page, evidencia }, info) => {
  test.skip(info.project.name !== 'desktop', 'medido no desktop do atendente')
  test.setTimeout(10 * 60_000)
  void evidencia
  const msLogin = await entrarDevagar(page, 'operador')
  const t = new Tarefa(page, 'pedido-telefone-cliente-conhecido')

  // Telefone do cliente de demonstração com pedido anterior (Rafael Ferreira).
  const [cli] = consultar("select c.id, c.nome, t.telefone from clientes c join clientetelefones t on t.cliente_id=c.id where c.empresa_id=1 and exists(select 1 from pedidos p where p.cliente_id=c.id) and c.nome='Rafael Ferreira' limit 1")
  const final4 = cli[2].replace(/\D/g, '').slice(-4)
  const antes = Number(consultar(`select count(*) from pedidos where cliente_id=${cli[0]}`)[0][0])

  await page.getByRole('navigation', { name: 'Módulos' }).getByRole('link', { name: 'Pedidos' }).click()
  t.registrar('clique', 'menu Pedidos')
  await page.getByRole('button', { name: 'Novo pedido' }).click()
  t.registrar('clique', 'Novo pedido')
  const dialogo = page.getByRole('dialog', { name: 'Novo pedido' })
  await dialogo.getByRole('button', { name: /^Cliente/ }).click()
  t.registrar('clique', 'abrir a busca de cliente')
  await page.getByLabel('Buscar opções').fill(final4)
  t.registrar('digitar', `telefone (4 últimos dígitos: ${final4})`)
  const opcao = page.getByRole('button', { name: new RegExp(cli[1]) }).first()
  await opcao.waitFor({ timeout: 60_000 })
  await opcao.click()
  t.registrar('escolher', 'cliente na lista')
  const repetir = dialogo.getByRole('button', { name: /Repetir último pedido/ })
  await repetir.waitFor({ timeout: 60_000 })
  await repetir.click()
  t.registrar('clique', 'Repetir último pedido')
  await dialogo.getByRole('button', { name: 'Criar pedido' }).click()
  t.registrar('clique', 'Criar pedido')

  // Pode faltar algo que a sugestão não preencheu: isso também é medida.
  const toastOk = page.getByText(/Pedido #\d+ criado/)
  const toastFalta = page.getByText(/Falta informar/)
  await expect(toastOk.or(toastFalta)).toBeVisible({ timeout: 60_000 })
  const faltou = await toastFalta.isVisible().catch(() => false)
  const resumo = { ...t.resumo(), ms_login: msLogin, concluiu_sem_passo_extra: !faltou, toast: faltou ? await toastFalta.innerText() : await toastOk.innerText() }

  const depois = Number(consultar(`select count(*) from pedidos where cliente_id=${cli[0]}`)[0][0])
  salvar('tarefa-operador-pedido-telefone', { ...resumo, pedidos_antes: antes, pedidos_depois: depois })
  await info.attach('tarefa.json', { body: JSON.stringify(resumo, null, 2), contentType: 'application/json' })
  expect(depois, 'o pedido tem de existir no banco').toBe(antes + (faltou ? 0 : 1))
})

test('operador: pedido de cliente NOVO no meio da ligação', async ({ page, evidencia }, info) => {
  test.skip(info.project.name !== 'desktop', 'medido no desktop do atendente')
  test.setTimeout(10 * 60_000)
  void evidencia
  await entrarDevagar(page, 'operador')
  const t = new Tarefa(page, 'pedido-cliente-novo')

  await page.getByRole('navigation', { name: 'Módulos' }).getByRole('link', { name: 'Pedidos' }).click()
  t.registrar('clique', 'menu Pedidos')
  await page.getByRole('button', { name: 'Novo pedido' }).click()
  t.registrar('clique', 'Novo pedido')
  const dialogo = page.getByRole('dialog', { name: 'Novo pedido' })
  const atalho = dialogo.getByRole('button', { name: /Cliente novo\? Cadastrar sem sair do pedido/ })
  const temAtalho = await atalho.isVisible().catch(() => false)
  if (temAtalho) {
    await atalho.click()
    t.registrar('clique', 'Cliente novo? Cadastrar sem sair do pedido')
  }
  // O que vem a seguir depende do formulário rápido — registra os campos que ele pede.
  await page.waitForTimeout(1500)
  const campos = await page.getByRole('dialog').last().locator('input:visible, select:visible, textarea:visible, button[aria-haspopup]:visible').count()
  const rotulos = await page.getByRole('dialog').last().locator('label:visible').allInnerTexts()
  salvar('tarefa-operador-cliente-novo', { ...t.resumo(), atalho_no_dialogo: temAtalho, campos_do_cadastro_rapido: campos, rotulos })
  await page.screenshot({ path: path.join(SAIDA, 'cliente-novo-dialogo.png') })
})
