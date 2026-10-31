import { execFileSync } from 'node:child_process'
import path from 'node:path'

/**
 * Prova de persistência: relê pelo OWNER o que a tela disse que gravou.
 *
 * "Salvou" na tela é o que o usuário vê; a linha no banco é o que vale. Pela
 * conexão do runtime a RLS esconderia parte do que se quer conferir, por isso
 * a leitura é como `postgres`, só leitura, no banco do agente (PIE_DB).
 */
const PSQL = process.env.PIE_PSQL
  ?? path.join(process.env.LOCALAPPDATA ?? '', 'dubena-tools', 'pg15x', 'pgsql', 'bin', 'psql.exe')

export function consultar(sql: string): string[][] {
  const banco = process.env.PIE_DB
  if (!banco) throw new Error('Defina PIE_DB com o banco do agente (ex.: pie_r3_a).')
  const saida = execFileSync(PSQL, ['-h', '127.0.0.1', '-p', process.env.PIE_DB_PORT ?? '55432', '-U', 'postgres',
    '-d', banco, '-At', '-F', '\t', '-v', 'ON_ERROR_STOP=1', '-c', `begin read only; ${sql}; commit;`], { encoding: 'utf8' })
  return saida.split(/\r?\n/).filter((l) => l && l !== 'BEGIN' && l !== 'COMMIT').map((l) => l.split('\t'))
}
