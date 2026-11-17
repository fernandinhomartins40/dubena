import type { Page } from '@playwright/test'
import { expect, PERFIS, type Perfil } from '../support/fixtures'

/**
 * Apoio das medições de produto (Agente G, Rodada 3).
 *
 * `entrarDevagar`: o mesmo login da fixture `entrar`, com espera maior. Com
 * sete agentes na mesma máquina e o `php artisan serve` do Windows, o login
 * medido levou 11–16 s; a fixture desiste em 10 s e a medição morreria antes
 * de começar. O tempo de login entra no registro, não esconde o problema.
 */
export async function entrarDevagar(page: Page, perfil: Perfil): Promise<number> {
  const { email, senha } = PERFIS[perfil]
  const t0 = Date.now()
  await page.goto('login')
  await page.getByLabel('E-mail / usuário').fill(email)
  await page.getByLabel('Senha', { exact: true }).fill(senha)
  await page.getByRole('button', { name: /entrar/i }).click()
  await expect(page).not.toHaveURL(/login/, { timeout: 90_000 })
  return Date.now() - t0
}

/**
 * Contador de passos da análise de tarefa (protocolo §7.1).
 *
 * "Passo" = uma ação deliberada do usuário: clicar, digitar um campo que não
 * vem preenchido, escolher numa lista, confirmar. Cada passo registra também
 * a tela em que aconteceu, para contar trocas de tela.
 */
export class Tarefa {
  passos: { n: number; tipo: 'clique' | 'digitar' | 'escolher' | 'tecla'; o_que: string; tela: string; ms: number }[] = []
  private t0 = Date.now()
  constructor(private page: Page, public nome: string) {}
  private tela() { return new URL(this.page.url()).pathname.replace(/^\/novo\/app/, '') || '/' }
  registrar(tipo: Tarefa['passos'][number]['tipo'], o_que: string) {
    this.passos.push({ n: this.passos.length + 1, tipo, o_que, tela: this.tela(), ms: Date.now() - this.t0 })
  }
  resumo() {
    const telas = new Set(this.passos.map((p) => p.tela))
    return {
      tarefa: this.nome,
      passos: this.passos.length,
      cliques: this.passos.filter((p) => p.tipo === 'clique' || p.tipo === 'escolher').length,
      campos_digitados: this.passos.filter((p) => p.tipo === 'digitar').length,
      telas: [...telas],
      ms_total: Date.now() - this.t0,
      detalhe: this.passos,
    }
  }
}
