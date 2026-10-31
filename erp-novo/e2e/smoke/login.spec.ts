import { test, expect, semErroGrave, PERFIS } from '../support/fixtures'

/** Cada perfil entra e cai numa tela utilizável, sem 5xx nem exceção. */
for (const perfil of Object.keys(PERFIS) as (keyof typeof PERFIS)[]) {
  test(`${perfil} entra no painel`, async ({ page, entrar, evidencia }) => {
    test.skip(perfil === 'entregador', 'o entregador trabalha no app, não no painel')
    await entrar(perfil)
    await expect(page.getByRole('navigation').first()).toBeVisible()
    semErroGrave(evidencia)
  })
}

test('senha errada mostra mensagem em português e não entra', async ({ page, evidencia }) => {
  void evidencia
  await page.goto('login')
  await page.getByLabel('E-mail / usuário').fill(PERFIS.operador.email)
  await page.getByLabel('Senha', { exact: true }).fill('senha-errada')
  await page.getByRole('button', { name: /entrar/i }).click()
  await expect(page).toHaveURL(/login/)
  await expect(page.getByText(/credenciais|senha|inválid/i).first()).toBeVisible()
})
