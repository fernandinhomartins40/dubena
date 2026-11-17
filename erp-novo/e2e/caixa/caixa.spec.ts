import { test, expect, semErroGrave, recarregar } from '../support/fixtures'
import { consultar } from '../support/db'
import {
  parcelaAberta, movimentosDaParcela, parcela, saldoConta, invarianteDoCaixa, irParaTitulo,
  escolherConta, garantirCaixaAberto, api, cab, tokenDe,
} from './apoio'

/**
 * Perfil CAIXA (no banco-base, o papel Operador tem caixa.* e financeiro.baixar)
 * e GERENTE financeiro. Protocolo §5: abrir caixa → receber título → malote →
 * fechar, com prova no banco a cada passo.
 */
const CAIXA_GERAL = 1
const BANCO = 2

test.describe('caixa — contas e movimentos', () => {
  test('o caixa vê saldo e movimentos legíveis da conta', async ({ page, entrar, evidencia, acessibilidade }) => {
    await entrar('operador')
    await page.goto('financeiro?tab=caixa')
    await page.getByRole('row', { name: /Caixa Geral/ }).getByText('Caixa Geral').click()

    const saldo = saldoConta(CAIXA_GERAL)
    const painel = page.getByText(/Saldo atual:/)
    await expect(painel).toContainText(saldo.toLocaleString('pt-BR', { minimumFractionDigits: 2 }))
    // O painel não pode mostrar lixo de campo inexistente.
    await expect(page.getByText(/NaN/)).toHaveCount(0)
    await expect(page.getByText('Saldo inicial').first()).toBeVisible()
    await acessibilidade('caixa-contas')
    semErroGrave(evidencia)
  })

  test('fechar e reabrir o caixa grava o fechamento com hora e saldo', async ({ page, entrar, evidencia, request }) => {
    await garantirCaixaAberto(request, CAIXA_GERAL)
    await entrar('operador')
    await page.goto('financeiro?tab=caixa')
    const linha = page.getByRole('row', { name: /Caixa Geral/ })
    await linha.getByRole('button', { name: /Fechar caixa/ }).click()
    await expect(page.getByText('Caixa fechado.')).toBeVisible()

    let [c] = consultar(`select c.fechado, (select saldo_final from contafechamentos f where f.conta_id = c.id order by id desc limit 1), c.saldo_atual from contas c where id = ${CAIXA_GERAL}`)
    expect(c[0]).toBe('t')
    expect(Number(c[1])).toBe(Number(c[2]))

    await recarregar(page)
    await expect(page.getByRole('row', { name: /Caixa Geral/ }).getByText('Fechado')).toBeVisible()

    await page.getByRole('row', { name: /Caixa Geral/ }).getByRole('button', { name: /Abrir caixa/ }).click()
    await expect(page.getByText('Caixa aberto.')).toBeVisible()
    ;[c] = consultar(`select c.fechado, (select count(*) from contafechamentos f where f.conta_id = c.id and f.aberto) from contas c where id = ${CAIXA_GERAL}`)
    expect(c).toEqual(['f', '1'])
    semErroGrave(evidencia)
  })

  test('P2-D-09: conta sem abertura registrada mostra como abrir e depois fecha', async ({ page, entrar, evidencia }) => {
    const [antes] = consultar(`select c.fechado, exists(select 1 from contafechamentos f where f.conta_id = c.id and f.aberto) from contas c where id = ${BANCO}`)
    test.skip(!(antes[0] === 'f' && antes[1] === 'f'), 'a conta já foi regularizada por uma execução anterior')
    await entrar('gerente')
    await page.goto('financeiro?tab=caixa')
    const linha = page.getByRole('row', { name: /Banco - Conta Movimento/ })
    await expect(linha.getByText('Sem abertura')).toBeVisible()
    await linha.getByRole('button', { name: /Abrir caixa/ }).click()
    await expect(page.getByText('Caixa aberto.')).toBeVisible()
    await linha.getByRole('button', { name: /Fechar caixa/ }).click()
    await expect(page.getByText('Caixa fechado.')).toBeVisible()
    const [depois] = consultar(`select fechado, (select count(*) from contafechamentos f where f.conta_id = ${BANCO} and not f.aberto) from contas where id = ${BANCO}`)
    expect(depois[0]).toBe('t')
    expect(Number(depois[1])).toBeGreaterThan(0)
    // Deixa a conta aberta para as outras jornadas.
    await linha.getByRole('button', { name: /Abrir caixa/ }).click()
    await expect(page.getByText('Caixa aberto.')).toBeVisible()
    semErroGrave(evidencia)
  })
})

test.describe('caixa — receber título', () => {
  test.beforeEach(async ({ request }) => { await garantirCaixaAberto(request, CAIXA_GERAL) })

  test('recebe o valor integral e o banco confirma parcela, movimento e saldo', async ({ page, entrar, evidencia, acessibilidade }) => {
    const p = parcelaAberta()
    const saldoAntes = saldoConta(CAIXA_GERAL)
    await entrar('operador')
    const linha = await irParaTitulo(page, p)
    await linha.getByRole('button', { name: 'Receber' }).click()
    const dialogo = page.getByRole('dialog', { name: 'Registrar recebimento' })
    await escolherConta(page, /Entrou em qual conta/, 'Caixa Geral')
    await acessibilidade('baixa-dialogo')
    const total = p.valor.toLocaleString('pt-BR', { minimumFractionDigits: 2 })
    await dialogo.getByRole('button', { name: new RegExp(`Receber R\\$\\s${total.replace('.', '\\.')}`) }).click()
    await expect(page.getByText(/Recebimento de R\$/)).toBeVisible()

    expect(parcela(p.id)).toEqual({ baixado: true, valor: p.valor, efetivado: p.valor, comTenant: true })
    const movs = movimentosDaParcela(p.id)
    expect(movs).toHaveLength(1)
    expect(movs[0].slice(1, 4)).toEqual([String(CAIXA_GERAL), 'BAIXA', p.valor.toFixed(2)])
    expect(movs[0][7]).toBe('t')
    expect(saldoConta(CAIXA_GERAL)).toBeCloseTo(saldoAntes + p.valor, 2)
    const inv = invarianteDoCaixa(CAIXA_GERAL)
    expect(inv.soma).toBeCloseTo(inv.saldo, 2)

    // Depois de recarregar, o título não aparece mais como em aberto.
    await recarregar(page)
    await page.getByLabel('Buscar lançamentos').fill(p.descricao)
    await page.getByRole('button', { name: 'Buscar', exact: true }).click()
    await expect(page.getByRole('row').filter({ hasText: new RegExp(`${p.descricao.replace('#', '\\#')}(?!\\d)`) })).toHaveCount(0)
    semErroGrave(evidencia)
  })

  test('o título mostra o nome do cliente na lista e no recebimento', async ({ page, entrar, evidencia }) => {
    const p = parcelaAberta()
    const [[nome]] = consultar(`select c.nome from financeiros f join clientes c on c.id = f.cliente_id where f.id = ${p.financeiroId}`)
    await entrar('operador')
    const linha = await irParaTitulo(page, p)
    await expect(linha).toContainText(nome)
    await linha.getByRole('button', { name: 'Receber' }).click()
    await expect(page.getByRole('dialog')).toContainText(nome)
    semErroGrave(evidencia)
  })

  test('desconto com vírgula e juros com milhar entram pelo valor certo', async ({ page, entrar, evidencia }) => {
    const p = parcelaAberta()
    await entrar('operador')
    const linha = await irParaTitulo(page, p)
    await linha.getByRole('button', { name: 'Receber' }).click()
    const dialogo = page.getByRole('dialog', { name: 'Registrar recebimento' })
    await escolherConta(page, /Entrou em qual conta/, 'Caixa Geral')
    await dialogo.getByRole('button', { name: /Ajustar valor/ }).click()
    await dialogo.getByLabel('Desconto').fill('5,50')
    await dialogo.getByLabel('Juros').fill('1.000,00')
    const esperado = Math.round((p.valor + 1000 - 5.5) * 100) / 100
    const rotulo = esperado.toLocaleString('pt-BR', { minimumFractionDigits: 2 })
    await dialogo.getByRole('button', { name: new RegExp(`Receber R\\$\\s${rotulo.replace(/\./g, '\\.')}`) }).click()
    await expect(page.getByText(/Recebimento de R\$/)).toBeVisible()
    const movs = movimentosDaParcela(p.id)
    expect(movs).toHaveLength(1)
    expect(movs[0].slice(3, 7)).toEqual([esperado.toFixed(2), '1000.00', '0.00', '5.50'])
    expect(parcela(p.id).efetivado).toBeCloseTo(esperado, 2)
    semErroGrave(evidencia)
  })

  for (const [campo, entrada, mensagem] of [
    ['Desconto', '-10', /negativ/i],
    ['Desconto', '5%', /inválid/i],
    ['Juros', 'abc', /inválid/i],
    ['Desconto', '99999', /Desconto maior/i],
  ] as const) {
    test(`recusa ${campo} "${entrada}" sem chamar o servidor nem mostrar valor negativo`, async ({ page, entrar, evidencia }) => {
      const p = parcelaAberta()
      let postou = false
      page.on('request', (r) => { if (r.method() === 'POST' && /\/caixa\/\d+\/baixar/.test(r.url())) postou = true })
      await entrar('operador')
      const linha = await irParaTitulo(page, p)
      await linha.getByRole('button', { name: 'Receber' }).click()
      const dialogo = page.getByRole('dialog', { name: 'Registrar recebimento' })
      await escolherConta(page, /Entrou em qual conta/, 'Caixa Geral')
      await dialogo.getByRole('button', { name: /Ajustar valor/ }).click()
      await dialogo.getByLabel(campo).fill(entrada)
      // R2-08: o botão nunca anuncia "Receber -R$ …".
      await expect(dialogo.getByRole('button', { name: /Receber\s*-/ })).toHaveCount(0)
      await dialogo.getByRole('button', { name: /^Receber/ }).click()
      await expect(page.getByText(mensagem).first()).toBeVisible()
      expect(postou).toBe(false)
      expect(movimentosDaParcela(p.id)).toHaveLength(0)
      semErroGrave(evidencia)
    })
  }

  test('P2-D-16: recebimento parcial baixa o recebido e deixa o restante em aberto', async ({ page, entrar, evidencia }) => {
    const p = parcelaAberta(100)
    await entrar('operador')
    const linha = await irParaTitulo(page, p)
    await linha.getByRole('button', { name: 'Receber' }).click()
    const dialogo = page.getByRole('dialog', { name: 'Registrar recebimento' })
    await escolherConta(page, /Entrou em qual conta/, 'Caixa Geral')
    await dialogo.getByLabel('Valor recebido agora').fill('50,00')
    const resto = (p.valor - 50).toLocaleString('pt-BR', { minimumFractionDigits: 2 })
    await expect(dialogo).toContainText(new RegExp(`Fica em aberto:\\s*R\\$\\s${resto.replace(/\./g, '\\.')}`))
    await dialogo.getByRole('button', { name: /Receber R\$\s50,00/ }).click()
    await expect(page.getByText(/Recebimento de R\$\s50,00/)).toBeVisible()

    const parcelas = consultar(`select numero, valor, baixado, valor_efetivado, vencimento from financeiroparcelas where financeiro_id = ${p.financeiroId} order by numero`)
    expect(parcelas).toHaveLength(2)
    expect(parcelas[0].slice(1, 4)).toEqual(['50.00', 't', '50.00'])
    expect(parcelas[1].slice(1, 4)).toEqual([(p.valor - 50).toFixed(2), 'f', '0.00'])
    expect(parcelas[1][4]).toBe(parcelas[0][4]) // mesmo vencimento
    const movs = movimentosDaParcela(p.id)
    expect(movs).toHaveLength(1)
    expect(movs[0][3]).toBe('50.00')

    // O restante continua na lista para o próximo recebimento.
    await recarregar(page)
    await page.getByLabel('Buscar lançamentos').fill(p.descricao)
    await page.getByRole('button', { name: 'Buscar', exact: true }).click()
    await expect(page.getByRole('row').filter({ hasText: new RegExp(`${p.descricao.replace('#', '\\#')}(?!\\d)`) })).toContainText(resto)
    semErroGrave(evidencia)
  })

  test('parcial maior que o total é recusado', async ({ page, entrar, evidencia }) => {
    const p = parcelaAberta()
    await entrar('operador')
    const linha = await irParaTitulo(page, p)
    await linha.getByRole('button', { name: 'Receber' }).click()
    const dialogo = page.getByRole('dialog', { name: 'Registrar recebimento' })
    await escolherConta(page, /Entrou em qual conta/, 'Caixa Geral')
    await dialogo.getByLabel('Valor recebido agora').fill(String(p.valor + 1).replace('.', ','))
    await dialogo.getByRole('button', { name: /^Receber/ }).click()
    await expect(page.getByText(/maior que o total/i).first()).toBeVisible()
    expect(movimentosDaParcela(p.id)).toHaveLength(0)
    semErroGrave(evidencia)
  })

  test('duplo clique no Receber grava uma baixa só', async ({ page, entrar, evidencia }) => {
    const p = parcelaAberta()
    await entrar('operador')
    const linha = await irParaTitulo(page, p)
    await linha.getByRole('button', { name: 'Receber' }).click()
    const dialogo = page.getByRole('dialog', { name: 'Registrar recebimento' })
    await escolherConta(page, /Entrou em qual conta/, 'Caixa Geral')
    await dialogo.getByRole('button', { name: /^Receber R\$/ }).dblclick()
    await expect(page.getByText(/Recebimento de R\$/)).toBeVisible()
    await page.waitForLoadState('networkidle')
    expect(movimentosDaParcela(p.id)).toHaveLength(1)
    semErroGrave(evidencia)
  })

  test('duas abas recebendo o mesmo título: a segunda é recusada com mensagem', async ({ page, entrar, evidencia }) => {
    const p = parcelaAberta()
    await entrar('operador')
    const aba2 = await page.context().newPage()
    for (const aba of [page, aba2]) {
      const linha = await irParaTitulo(aba, p)
      await linha.getByRole('button', { name: 'Receber' }).click()
      await escolherConta(aba, /Entrou em qual conta/, 'Caixa Geral')
    }
    await page.getByRole('dialog').getByRole('button', { name: /^Receber R\$/ }).click()
    await expect(page.getByText(/Recebimento de R\$/)).toBeVisible()
    await aba2.getByRole('dialog').getByRole('button', { name: /^Receber R\$/ }).click()
    await expect(aba2.getByText(/já baixada/i).first()).toBeVisible()
    expect(movimentosDaParcela(p.id)).toHaveLength(1)
    await aba2.close()
    semErroGrave(evidencia)
  })

  test('rede caindo no recebimento: avisa e não grava nada', async ({ page, entrar, evidencia }) => {
    void evidencia
    const p = parcelaAberta()
    await entrar('operador')
    const linha = await irParaTitulo(page, p)
    await linha.getByRole('button', { name: 'Receber' }).click()
    await escolherConta(page, /Entrou em qual conta/, 'Caixa Geral')
    await page.route(/\/caixa\/\d+\/baixar/, (r) => r.abort())
    await page.getByRole('dialog').getByRole('button', { name: /^Receber R\$/ }).click()
    await expect(page.getByText(/Não foi possível registrar|conexão|rede/i).first()).toBeVisible()
    await expect(page.getByRole('dialog')).toBeVisible()
    expect(movimentosDaParcela(p.id)).toHaveLength(0)
  })
})

test.describe('caixa — estorno e recibo', () => {
  test('gerente estorna um recebimento: o caixa volta e a parcela reabre', async ({ page, entrar, evidencia, request }) => {
    await garantirCaixaAberto(request, CAIXA_GERAL)
    const p = parcelaAberta()
    const token = await tokenDe(request, 'operador')
    const r = await request.post(`${api()}/admin/caixa/${CAIXA_GERAL}/baixar`, { headers: cab(token), data: { parcela_id: p.id } })
    expect(r.status(), await r.text()).toBe(201)
    const movId = (await r.json()).data.id
    const saldoAntes = saldoConta(CAIXA_GERAL)

    await entrar('gerente')
    await page.goto('financeiro?tab=caixa')
    await page.getByRole('row', { name: /Caixa Geral/ }).getByText('Caixa Geral').click()
    const mov = page.getByTestId(`movimento-${movId}`)
    await mov.getByRole('button', { name: /Estornar/ }).click()
    await page.getByRole('dialog', { name: 'Estornar movimento' }).getByRole('button', { name: 'Estornar', exact: true }).click()
    await expect(page.getByText(/Estorno registrado/)).toBeVisible()

    expect(parcela(p.id).baixado).toBe(false)
    expect(saldoConta(CAIXA_GERAL)).toBeCloseTo(saldoAntes - p.valor, 2)
    const [[estornos]] = consultar(`select count(*) from contamovimentos where estorno_de_id = ${movId}`)
    expect(estornos).toBe('1')
    // Recarregado, o movimento estornado não oferece novo estorno.
    await recarregar(page)
    await page.getByRole('row', { name: /Caixa Geral/ }).getByText('Caixa Geral').click()
    await expect(page.getByTestId(`movimento-${movId}`).getByRole('button', { name: /Estornar/ })).toHaveCount(0)
    semErroGrave(evidencia)
  })

  test('o caixa sem caixa.estornar não vê o botão e o servidor recusa', async ({ page, entrar, evidencia, request }) => {
    await entrar('operador')
    await page.goto('financeiro?tab=caixa')
    await page.getByRole('row', { name: /Caixa Geral/ }).getByText('Caixa Geral').click()
    await expect(page.getByText('Saldo inicial').first()).toBeVisible()
    await expect(page.getByRole('button', { name: /Estornar/ })).toHaveCount(0)
    const [[movId]] = consultar(`select id from contamovimentos where conta_id = ${CAIXA_GERAL} and tipo = 'ABERTURA' limit 1`)
    const token = await tokenDe(request, 'operador')
    const r = await request.post(`${api()}/admin/caixa/movimentos/${movId}/estornar`, { headers: cab(token) })
    expect(r.status()).toBe(403)
    semErroGrave(evidencia)
  })

  test('recibo do movimento abre em PDF', async ({ page, entrar, evidencia }) => {
    await entrar('operador')
    await page.goto('financeiro?tab=caixa')
    await page.getByRole('row', { name: /Caixa Geral/ }).getByText('Caixa Geral').click()
    const [popup] = await Promise.all([
      page.waitForEvent('popup'),
      page.getByRole('button', { name: /Recibo/ }).first().click(),
    ])
    await expect.poll(() => popup.url()).toMatch(/^blob:/)
    semErroGrave(evidencia)
  })
})

test.describe('caixa — malote do entregador', () => {
  test('confere o período e fecha o malote na conta escolhida', async ({ page, entrar, evidencia, request, acessibilidade }) => {
    await garantirCaixaAberto(request, CAIXA_GERAL)
    await entrar('operador')
    await page.goto('financeiro?tab=malote')
    await page.getByLabel('Início').fill('2026-10-01')
    await page.getByRole('button', { name: /Conferir/ }).click()
    const caixa = page.getByRole('checkbox', { name: /Selecionar pedido/ }).first()
    await expect(caixa).toBeVisible()
    const pedidoId = Number((await caixa.getAttribute('aria-label'))!.replace(/\D/g, ''))
    const [[finId]] = consultar(`select financeiro_id from pedidos where id = ${pedidoId}`)
    await caixa.check()
    await escolherConta(page, /Conta do malote/, 'Caixa Geral')
    await page.getByRole('button', { name: /Fechar malote \(1\)/ }).click()
    const confirma = page.getByRole('dialog', { name: 'Fechar malote' })
    await acessibilidade('malote-confirmacao')
    // O botão diz o que faz: antes o padrão do ConfirmDialog anunciava "Excluir".
    await confirma.getByRole('button', { name: 'Fechar malote', exact: true }).click()
    await expect(page.getByText(/parcela\(s\) baixada\(s\)/)).toBeVisible()

    const [l] = consultar(`select bool_and(p.baixado), count(m.id), min(m.conta_id) from financeiroparcelas p
                             left join contamovimentos m on m.financeiroparcela_id = p.id where p.financeiro_id = ${finId}`)
    expect(l[0]).toBe('t')
    expect(Number(l[1])).toBeGreaterThan(0)
    expect(l[2]).toBe(String(CAIXA_GERAL))
    semErroGrave(evidencia)
  })
})

test.describe('caixa — autorização (BOLA/BFLA)', () => {
  test('sem token 401; entregador 403; parcela de outra empresa recusada', async ({ request }) => {
    const sem = await request.get(`${api()}/admin/caixa/contas`, { headers: cab() })
    expect(sem.status()).toBe(401)
    const ent = await tokenDe(request, 'entregador')
    for (const [m, url] of [['get', '/admin/caixa/contas'], ['get', '/admin/financeiro/lancamentos'], ['post', `/admin/caixa/${CAIXA_GERAL}/fechar`]] as const) {
      const r = await request[m](`${api()}${url}`, { headers: cab(ent) })
      expect(r.status(), `${m} ${url}`).toBe(403)
    }
    // Parcela de outra empresa do mesmo tenant: o operador da empresa 1 não baixa.
    const outra = consultar(`select p.id from financeiroparcelas p where p.empresa_id <> 1 and not p.baixado limit 1`)
    test.skip(outra.length === 0, 'o banco-base não tem título na empresa 2')
    const op = await tokenDe(request, 'operador')
    const r = await request.post(`${api()}/admin/caixa/${CAIXA_GERAL}/baixar`, { headers: cab(op), data: { parcela_id: Number(outra[0][0]) } })
    expect([403, 404, 422]).toContain(r.status())
    expect(parcela(Number(outra[0][0])).baixado).toBe(false)
  })
})
