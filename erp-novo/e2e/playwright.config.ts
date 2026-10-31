import { defineConfig, devices } from '@playwright/test'
import path from 'node:path'

/**
 * Jornadas por perfil contra a aplicação REAL: API Laravel como `erp_app` no
 * Postgres com RLS + SPA servida pelo Vite. Nada aqui é mock — o que a tela
 * promete é conferido no banco (ver support/db.ts).
 *
 * Variáveis (ver README.md): PIE_SPA_URL, PIE_API_URL, PIE_DB, PIE_CHROMIUM.
 */
const chromium = process.env.PIE_CHROMIUM
  ?? path.join(process.env.LOCALAPPDATA ?? '', 'ms-playwright', 'chromium-1248', 'chrome-win64', 'chrome.exe')

export default defineConfig({
  testDir: '.',
  testMatch: '**/*.spec.ts',
  // O `php artisan serve` do Windows atende uma requisição por vez: paralelo
  // só mede fila, não a aplicação.
  workers: 1,
  timeout: 60_000,
  expect: { timeout: 10_000 },
  reporter: [['list'], ['html', { open: 'never', outputFolder: 'relatorio' }]],
  outputDir: 'resultados',
  use: {
    baseURL: process.env.PIE_SPA_URL ?? 'http://localhost:5173/novo/app/',
    locale: 'pt-BR',
    timezoneId: 'America/Sao_Paulo',
    screenshot: 'on',
    trace: 'retain-on-failure',
    launchOptions: { executablePath: chromium },
  },
  projects: [
    { name: 'desktop', use: { viewport: { width: 1366, height: 768 } } },
    { name: 'celular', use: { ...devices['Pixel 7'], launchOptions: { executablePath: chromium } } },
  ],
})
