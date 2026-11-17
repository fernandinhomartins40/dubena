import { defineConfig } from '@playwright/test'
import base from '../playwright.config'

/**
 * Mesma configuração do harness, só com prazos maiores: com sete agentes no
 * mesmo `php artisan serve` por máquina, o login levou 25 s na Rodada 3 e a
 * espera padrão de 10 s reprovava a jornada pelo ambiente, não pela aplicação.
 * O relatório HTML fica desligado para não disputar a pasta com outros agentes.
 */
export default defineConfig({
  ...base,
  testDir: '.',
  timeout: 180_000,
  expect: { timeout: 45_000 },
  reporter: [['line']],
})
