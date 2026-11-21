import { createHmac } from 'node:crypto'
import { test, expect, semErroGrave, recarregar } from '../support/fixtures'
import { consultar } from '../support/db'

/**
 * Agente C (Rodada 3) — plataforma e acessos, no runtime real (erp_app + RLS).
 * Ver docs/pie/rodada3/C_PLATAFORMA_ACESSOS.md.
 */

const API = process.env.PIE_API_URL ?? 'http://127.0.0.1:8000'

/** TOTP (RFC 6238, SHA-1, 30 s, 6 dígitos) — o mesmo que o app autenticador gera. */
function totp(secret: string, agora = Date.now()): string {
  const alfabeto = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'
  let bits = ''
  for (const c of secret.replace(/=+$/, '').toUpperCase()) bits += alfabeto.indexOf(c).toString(2).padStart(5, '0')
  const chave = Buffer.from(bits.match(/.{8}/g)!.map((b) => parseInt(b, 2)))
  const contador = Buffer.alloc(8)
  contador.writeBigUInt64BE(BigInt(Math.floor(agora / 1000 / 30)))
  const h = createHmac('sha1', chave).update(contador).digest()
  const o = h[h.length - 1] & 0xf
  return String((h.readUInt32BE(o) & 0x7fffffff) % 1_000_000).padStart(6, '0')
}

test('login do painel não mostra a marca de outra revenda (P2-A-08)', async ({ page, evidencia, acessibilidade }) => {
  await page.goto('login')
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
  await expect(page.getByText('Dubena')).toHaveCount(0)
  await acessibilidade('login')
  semErroGrave(evidencia)
})

test('rota inexistente mostra 404 dentro do app, sem derrubar a tela (P2-D-11)', async ({ page, entrar, evidencia }) => {
  await entrar('admin')
  await page.goto('rota-que-nao-existe')
  await expect(page.getByRole('heading', { name: 'Página não encontrada' })).toBeVisible()
  await expect(page.getByText(/Algo deu errado/i)).toHaveCount(0)
  await page.getByRole('link', { name: 'Voltar ao início' }).click()
  await expect(page).not.toHaveURL(/rota-que-nao-existe/)
  semErroGrave(evidencia)
})

test('validação em português no cadastro de usuário (PIE-34)', async ({ page, entrar, evidencia }) => {
  void evidencia
  await entrar('admin')
  await page.goto('acessos')
  await page.getByRole('button', { name: /Novo usuário/ }).click()
  const dialogo = page.getByRole('dialog')
  await dialogo.getByLabel('Nome').fill('Sem Senha Forte')
  await dialogo.getByLabel('E-mail').fill(`fraca.${Date.now()}@pie.local`)
  await dialogo.getByLabel(/^Senha( \*)?$/).fill('123')
  await dialogo.getByLabel('Confirmar senha').fill('123')
  await dialogo.getByRole('button', { name: 'Salvar' }).click()
  await expect(page.getByText(/pelo menos 8 caracteres|senha/i).first()).toBeVisible()
  await expect(page.getByText(/validation\./)).toHaveCount(0)
})

test('admin cria usuário; ele entra e vê só o que o papel dá (persistência + negativo)', async ({ page, entrar, evidencia, browser }) => {
  const email = `atendente.${Date.now()}@pie.local`
  const senha = 'Pie-R3-Novo!2026'
  await entrar('admin')
  await page.goto('acessos')
  await page.getByRole('button', { name: /Novo usuário/ }).click()
  const dialogo = page.getByRole('dialog')
  await dialogo.getByLabel('Nome').fill('Atendente PIE C')
  await dialogo.getByLabel('E-mail').fill(email)
  await dialogo.getByLabel(/^Senha( \*)?$/).fill(senha)
  await dialogo.getByLabel('Confirmar senha').fill(senha)
  await dialogo.getByRole('button', { name: 'Salvar' }).click()
  await expect(dialogo).toBeHidden()
  await recarregar(page)
  await expect(page.getByText(email)).toBeVisible()

  // Persistência pelo owner: usuário, fronteira SaaS (membership + grant) e tenant.
  const [linha] = consultar(`select u.id, u.empresa_id, u.ativo, u.support,
      (select count(*) from tenant_memberships m where m.user_id=u.id and m.status='ACTIVE'),
      (select count(*) from tenant_company_grants g join tenant_memberships m on m.id=g.tenant_membership_id where m.user_id=u.id)
    from users u where u.email='${email}'`)
  expect(linha.slice(1)).toEqual(['1', 't', 'f', '1', '1'])
  const id = linha[0]

  // Papel Operador pelo mesmo endpoint da tela (o seletor de papéis é um multi-select assíncrono).
  const token = await page.evaluate(() => localStorage.getItem('erpnovo_token') ?? sessionStorage.getItem('erpnovo_token'))
  const papel = consultar(`select id from roles where grupo_id=1 and nome='Operador'`)[0][0]
  const r = await page.request.put(`${API}/api/admin/usuarios/${id}`, {
    headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
    data: { name: 'Atendente PIE C', email, papeis: [Number(papel)] },
  })
  expect(r.status()).toBe(200)

  // O novo usuário, noutra sessão: vê Pedidos, não vê administração de acesso.
  const ctx = await browser.newContext({ baseURL: test.info().project.use.baseURL })
  const p2 = await ctx.newPage()
  await p2.context().route(/\/novo\/(api|sanctum)\//, async (route) => {
    const u = new URL(route.request().url())
    await route.fulfill({ response: await route.fetch({ url: `${API}${u.pathname.replace(/^\/novo/, '')}${u.search}` }) })
  })
  await p2.goto('login')
  await p2.getByLabel('E-mail / usuário').fill(email)
  await p2.getByLabel('Senha', { exact: true }).fill(senha)
  await p2.getByRole('button', { name: /entrar/i }).click()
  await expect(p2).not.toHaveURL(/login/)
  // No celular o menu é gaveta: aberta, o negativo abaixo confere itens montados, não uma gaveta vazia.
  if (test.info().project.use.isMobile) await p2.getByRole('button', { name: 'Abrir menu' }).click()
  const menu = p2.getByRole('navigation', { name: 'Módulos' })
  await expect(menu.getByRole('link', { name: /Pedidos/ })).toBeVisible()
  await expect(menu.getByRole('link', { name: /Usuários e permissões/ })).toHaveCount(0)
  await expect(menu.getByRole('link', { name: /Histórico de ações/ })).toHaveCount(0)
  // Negativo no servidor: a API também nega.
  const t2 = await p2.evaluate(() => localStorage.getItem('erpnovo_token') ?? sessionStorage.getItem('erpnovo_token'))
  const neg = await p2.request.get(`${API}/api/admin/usuarios`, { headers: { Authorization: `Bearer ${t2}`, Accept: 'application/json' } })
  expect(neg.status()).toBe(403)

  // Desativado pelo dono, o token dele morre na hora (P2-E-06 / R3-C).
  const d = await page.request.put(`${API}/api/admin/usuarios/${id}`, {
    headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
    data: { name: 'Atendente PIE C', email, ativo: false },
  })
  expect(d.status()).toBe(200)
  const morto = await p2.request.get(`${API}/api/me`, { headers: { Authorization: `Bearer ${t2}`, Accept: 'application/json' } })
  expect(morto.status()).toBe(401)
  await ctx.close()
  semErroGrave(evidencia)
})

test('sessão vencida: re-login no diálogo e a tela continua (PIE-33)', async ({ page, entrar, evidencia }) => {
  await entrar('admin')
  await page.goto('acessos')
  await page.getByRole('button', { name: /Novo usuário/ }).click()
  const dialogo = page.getByRole('dialog')
  await dialogo.getByLabel('Nome').fill('Rascunho que não pode sumir')
  // Simula o vencimento: troca o token por um inválido.
  await page.evaluate(() => {
    const k = 'erpnovo_token'
    if (localStorage.getItem(k)) localStorage.setItem(k, '999|invalido'); else sessionStorage.setItem(k, '999|invalido')
  })
  await dialogo.getByLabel('E-mail').fill(`rascunho.${Date.now()}@pie.local`)
  await dialogo.getByLabel(/^Senha( \*)?$/).fill('Pie-R3-Novo!2026')
  await dialogo.getByLabel('Confirmar senha').fill('Pie-R3-Novo!2026')
  await dialogo.getByRole('button', { name: 'Salvar' }).click()
  const relogin = page.getByRole('dialog', { name: 'Sua sessão expirou' })
  await expect(relogin).toBeVisible()
  await relogin.getByLabel('Senha').fill('Pie-R3-Admin!2026')
  await relogin.getByRole('button', { name: 'Entrar e continuar' }).click()
  await expect(relogin).toBeHidden()
  await recarregar(page)
  await expect(page.getByText('Rascunho que não pode sumir')).toBeVisible()
  await expect(page.getByText('Unauthenticated.')).toHaveCount(0)
  semErroGrave(evidencia)
})

test('superadmin: 2FA obrigatório no primeiro acesso, depois suspende e reativa (P2-E-06)', async ({ page, evidencia, acessibilidade }) => {
  const [estado] = consultar(`select twofa_habilitado from platform_admins where email='sa@pie.local'`)
  test.skip(estado?.[0] === 't', 'o 2FA do sa@pie.local já foi cadastrado neste banco: clone de novo do pie_r3_base')

  await page.goto('superadmin/login')
  await page.getByLabel('E-mail').fill('sa@pie.local')
  await page.getByLabel('Senha').fill('Pie-R3-Super!2026')
  await page.getByRole('button', { name: 'Entrar' }).click()
  await expect(page.getByRole('heading', { name: /verificação em duas etapas/i })).toBeVisible()
  await acessibilidade('sa-cadastro-2fa')
  const secret = (await page.getByLabel('Chave do autenticador').textContent())!.trim()
  await page.getByLabel(/Código de 6 dígitos/).fill(totp(secret))
  await page.getByRole('button', { name: 'Confirmar e ativar' }).click()
  await expect(page.getByRole('list', { name: 'Códigos de recuperação' }).getByRole('listitem')).toHaveCount(8)
  await page.getByRole('button', { name: /continuar/i }).click()
  await expect(page).toHaveURL(/superadmin/)
  expect(consultar(`select twofa_habilitado from platform_admins where email='sa@pie.local'`)[0][0]).toBe('t')

  // Suspender e reativar a empresa 2 pelo endpoint da tela; efeito relido no banco.
  const token = await page.evaluate(() => localStorage.getItem('superadmin_token'))
  const h = { Authorization: `Bearer ${token}`, Accept: 'application/json' }
  expect((await page.request.post(`${API}/api/superadmin/empresas/2/suspender`, { headers: h, data: { motivo: 'teste PIE R3-C' } })).status()).toBeLessThan(300)
  expect(consultar('select ativo from empresas where id=2')[0][0]).toBe('f')
  expect((await page.request.post(`${API}/api/superadmin/empresas/2/reativar`, { headers: h, data: { motivo: 'teste PIE R3-C' } })).status()).toBeLessThan(300)
  expect(consultar('select ativo from empresas where id=2')[0][0]).toBe('t')
  semErroGrave(evidencia)
})
