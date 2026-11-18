#!/usr/bin/env node
/**
 * Costura entre camadas — rotas do Laravel × chamadas da SPA e dos apps.
 *
 * Responde às duas perguntas que nenhum índice responde (ver CLAUDE.md,
 * "Fronteira HTTP"):
 *
 *  1. CHAMADA SEM ROTA — a tela ou o app chama um caminho que o backend não tem.
 *     É 404 garantido em runtime, por mais que o componente "funcione" no teste.
 *  2. ROTA SEM CONSUMIDOR — o backend implementou e ninguém chama. É o sintoma
 *     de funcionalidade feita isolada: endpoint pronto, tela que nunca chegou.
 *
 * Também acusa MÉTODO DIVERGENTE (a tela faz PUT, a rota é POST).
 *
 * Limites, de propósito explícitos: chamada montada com caminho em variável
 * (`api.get(url)`) não é vista — por isso "rota sem consumidor" é CANDIDATO e
 * precisa de confirmação por leitura. Um segmento dinâmico na chamada casa com
 * qualquer segmento da rota (comparação conservadora: prefere não acusar).
 *
 * Uso: node scripts/pie-costura.mjs [--json]
 * Fonte das rotas: erp-novo/database/api-manifest.json (rode `php artisan
 * api:manifest` antes se mexeu em rota).
 */
import { readFileSync, readdirSync, statSync } from 'node:fs'
import { join, relative } from 'node:path'
import { fileURLToPath } from 'node:url'

const RAIZ = join(fileURLToPath(new URL('.', import.meta.url)), '..')

/** Camadas consumidoras: pasta, prefixo da baseURL e padrões de chamada. */
const CAMADAS = [
  {
    nome: 'spa',
    pasta: 'erp-novo/frontend/src',
    padroes: [
      { re: /\bapi\.(get|post|put|patch|delete)(?:<[^>()]*>)?\(\s*([`'"])(.*?)\2/g, prefixo: 'api/admin', metodo: 1, caminho: 3 },
      { re: /\bsaApi\.(get|post|put|patch|delete)(?:<[^>()]*>)?\(\s*([`'"])(.*?)\2/g, prefixo: 'api/superadmin', metodo: 1, caminho: 3 },
    ],
  },
  ...['app-gas-em-casa', 'app-entregador'].map((app) => ({
    nome: app,
    pasta: `${app}/src`,
    padroes: [
      { re: /PrepareRequest\(\s*([`'"])(.*?)\1\s*(?:,\s*["'](GET|POST|PUT|PATCH|DELETE)["'])?/g, prefixo: 'api', metodo: 3, caminho: 2 },
      { re: /enviarOuEnfileirar(?:<[^>()]*>)?\(\s*([`'"])(.*?)\1\s*,\s*["'](POST|PUT|PATCH|DELETE)["']/g, prefixo: 'api', metodo: 3, caminho: 2 },
      // Multipart (foto, assinatura) sempre sai como POST.
      { re: /PrepareForm(?:<[^>()]*>)?\(\s*([`'"])(.*?)\1()/g, prefixo: 'api', metodo: 3, caminho: 2, metodoFixo: 'POST' },
      // Multipart pela fila offline (concluir, ocorrência): também sempre POST.
      { re: /enviarFormularioOuEnfileirar(?:<[^>()]*>)?\(\s*([`'"])(.*?)\1()/g, prefixo: 'api', metodo: 3, caminho: 2, metodoFixo: 'POST' },
    ],
  })),
]

const arquivos = (dir) =>
  readdirSync(dir).flatMap((n) => {
    if (n === 'node_modules' || n.startsWith('.')) return []
    const p = join(dir, n)
    if (statSync(p).isDirectory()) return arquivos(p)
    return /\.(ts|tsx)$/.test(n) && !/\.test\.tsx?$/.test(n) ? [p] : []
  })

/** Caminho → segmentos normalizados; '*' = dinâmico. */
const segmentos = (caminho) =>
  caminho
    // `${x ? `?a=${x}` : ''}` no fim: a regex de captura para na aspa interna e
    // sobra um `${` aberto. É sufixo de query string, não segmento de caminho.
    .replace(/\$\{[^}]*$/, '')
    .split('?')[0]
    .replace(/\$\{[^}]*\}/g, '*')
    .replace(/\{[^}]+\}/g, '*')
    .split('/')
    .filter(Boolean)
    .map((s) => (s.includes('*') ? '*' : s))

const casa = (rota, chamada) =>
  rota.length === chamada.length && rota.every((s, i) => s === '*' || chamada[i] === '*' || s === chamada[i])

// ── rotas ─────────────────────────────────────────────────────────────
const manifesto = JSON.parse(readFileSync(join(RAIZ, 'erp-novo/database/api-manifest.json'), 'utf8'))
const rotas = manifesto
  .map((linha) => {
    const [metodo, uri] = linha.split(' ')
    return { metodo, uri, seg: segmentos(uri), usada: false }
  })
  .filter((r) => r.uri.startsWith('api/'))

// ── chamadas ──────────────────────────────────────────────────────────
const chamadas = []
for (const camada of CAMADAS) {
  for (const arq of arquivos(join(RAIZ, camada.pasta))) {
    const texto = readFileSync(arq, 'utf8')
    for (const p of camada.padroes) {
      for (const m of texto.matchAll(p.re)) {
        const bruto = m[p.caminho]
        if (/^https?:/.test(bruto)) continue // host externo (Google etc.)
        const caminho = `${p.prefixo}/${bruto.replace(/^\/+/, '')}`
        const linha = texto.slice(0, m.index).split('\n').length
        chamadas.push({
          camada: camada.nome,
          metodo: (p.metodoFixo ?? m[p.metodo] ?? 'GET').toUpperCase(),
          caminho,
          seg: segmentos(caminho),
          onde: `${relative(RAIZ, arq).replaceAll('\\', '/')}:${linha}`,
        })
      }
    }
  }
}

// ── cruzamento ────────────────────────────────────────────────────────
const semRota = []
const metodoDivergente = []
for (const c of chamadas) {
  const mesmoCaminho = rotas.filter((r) => casa(r.seg, c.seg))
  const exata = mesmoCaminho.filter((r) => r.metodo === c.metodo)
  exata.forEach((r) => (r.usada = true))
  if (exata.length > 0) continue
  if (mesmoCaminho.length > 0) metodoDivergente.push({ ...c, existe: mesmoCaminho.map((r) => r.metodo).join('/') })
  else semRota.push(c)
}

const consumiveis = /^api\/(admin|superadmin|app\/v1)\//
const semConsumidor = rotas.filter((r) => !r.usada && consumiveis.test(r.uri))

const resultado = {
  rotas: rotas.length,
  chamadas: chamadas.length,
  porCamada: Object.fromEntries(CAMADAS.map((c) => [c.nome, chamadas.filter((x) => x.camada === c.nome).length])),
  semRota,
  metodoDivergente,
  semConsumidor: semConsumidor.map(({ metodo, uri }) => ({ metodo, uri })),
}

if (process.argv.includes('--json')) {
  console.log(JSON.stringify(resultado, null, 2))
} else {
  console.log(`# Costura entre camadas\n`)
  console.log(`Rotas no manifesto: ${resultado.rotas} | chamadas encontradas: ${resultado.chamadas} ${JSON.stringify(resultado.porCamada)}\n`)
  console.log(`## Chamada sem rota (404 garantido): ${semRota.length}`)
  semRota.forEach((c) => console.log(`- ${c.metodo} ${c.caminho}  <- ${c.onde}`))
  console.log(`\n## Método divergente: ${metodoDivergente.length}`)
  metodoDivergente.forEach((c) => console.log(`- ${c.metodo} ${c.caminho} (rota aceita ${c.existe})  <- ${c.onde}`))
  console.log(`\n## Rota sem consumidor visível (CANDIDATO — confirmar por leitura): ${semConsumidor.length}`)
  semConsumidor.forEach((r) => console.log(`- ${r.metodo} ${r.uri}`))
}

// Guardião contra varredura vazia: um padrão quebrado daria "nada a acusar".
if (resultado.chamadas < 200) {
  console.error(`\nERRO: só ${resultado.chamadas} chamadas encontradas — os padrões de busca quebraram.`)
  process.exit(2)
}
process.exit(semRota.length > 0 ? 1 : 0)
