import { test as base, expect, type Page } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

/** Contas de teste do banco-base local (nunca dados reais). */
export const PERFIS = {
  admin: { email: 'admin@pie.local', senha: process.env.PIE_SENHA_ADMIN ?? 'Pie-R3-Admin!2026' },
  gerente: { email: 'gerente@pie.local', senha: process.env.PIE_SENHA_PERFIL ?? 'Pie-R3-Perfil!2026' },
  operador: { email: 'operador@pie.local', senha: process.env.PIE_SENHA_PERFIL ?? 'Pie-R3-Perfil!2026' },
  entregador: { email: 'entregador@pie.local', senha: process.env.PIE_SENHA_PERFIL ?? 'Pie-R3-Perfil!2026' },
} as const
export type Perfil = keyof typeof PERFIS

const API = process.env.PIE_API_URL ?? 'http://127.0.0.1:8000'

/** O que a jornada deixou de rastro: é a evidência anexada ao relatório. */
export interface Evidencia {
  console: string[]
  http: string[]
}

type Fixtures = {
  evidencia: Evidencia
  entrar: (perfil: Perfil) => Promise<void>
  acessibilidade: (nome: string) => Promise<void>
}

export const test = base.extend<Fixtures>({
  evidencia: async ({ page }, use, info) => {
    const ev: Evidencia = { console: [], http: [] }

    // A SPA chama `/novo/api/*` (base fixa do Vite) e o proxy de dev só cobre
    // `/api` (P2-D-18): aqui fazemos o papel do Nginx de produção.
    await page.context().route(/\/novo\/(api|sanctum)\//, async (route) => {
      const u = new URL(route.request().url())
      try {
        const resp = await route.fetch({ url: `${API}${u.pathname.replace(/^\/novo/, '')}${u.search}` })
        await route.fulfill({ response: resp })
      } catch {
        await route.abort()
      }
    })
    page.on('console', (m) => { if (m.type() === 'error') ev.console.push(m.text().slice(0, 300)) })
    page.on('pageerror', (e) => ev.console.push(`[pageerror] ${String(e).slice(0, 300)}`))
    page.on('response', async (r) => {
      if (r.status() >= 400 && r.url().includes('/api/')) {
        let corpo = ''
        try { corpo = (await r.text()).slice(0, 300) } catch { /* corpo indisponível */ }
        ev.http.push(`${r.status()} ${r.request().method()} ${new URL(r.url()).pathname} :: ${corpo}`)
      }
    })

    await use(ev)

    await info.attach('evidencia.json', { body: JSON.stringify(ev, null, 2), contentType: 'application/json' })
  },

  entrar: async ({ page, evidencia }, use) => {
    void evidencia // garante o proxy montado antes do login
    await use(async (perfil) => {
      const { email, senha } = PERFIS[perfil]
      await page.goto('login')
      await page.getByLabel('E-mail / usuário').fill(email)
      await page.getByLabel('Senha', { exact: true }).fill(senha)
      await page.getByRole('button', { name: /entrar/i }).click()
      await expect(page).not.toHaveURL(/login/)
    })
  },

  acessibilidade: async ({ page }, use, info) => {
    await use(async (nome) => {
      const r = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze()
      await info.attach(`axe-${nome}.json`, { body: JSON.stringify(r.violations, null, 2), contentType: 'application/json' })
    })
  },
})

export { expect }

/** Falha a jornada se ela deixou erro de console ou HTTP ≥ 500 — "parecia funcionar". */
export function semErroGrave(ev: Evidencia) {
  expect(ev.http.filter((h) => h.startsWith('5')), 'resposta 5xx durante a jornada').toEqual([])
  expect(ev.console.filter((c) => c.startsWith('[pageerror]')), 'exceção não tratada na página').toEqual([])
}

export async function recarregar(page: Page) {
  await page.reload()
  await page.waitForLoadState('networkidle')
}
