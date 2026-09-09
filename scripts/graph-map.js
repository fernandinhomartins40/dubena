#!/usr/bin/env node
/**
 * Destila graphify-out/graph.json em um mapa curto da arquitetura.
 *
 * Por que existe: ler o grafo inteiro (5 MB) ou os arquivos afetados (~160 KB)
 * custa caro em contexto. Este script extrai só o que responde as perguntas
 * frequentes -- onde mexer, o que e hub, o que atravessa fronteira -- em ~2 KB,
 * que cabem no CLAUDE.md e ficam disponiveis sem nenhuma leitura de arquivo.
 *
 * Uso:
 *   node scripts/graph-map.js            # imprime o mapa
 *   node scripts/graph-map.js --json     # saida estruturada
 *   node scripts/graph-map.js --write    # reescreve a secao gerada do CLAUDE.md
 *   node scripts/graph-map.js --check    # falha se o CLAUDE.md estiver desatualizado
 */
const fs = require('fs')
const path = require('path')

const GRAPH = path.join(__dirname, '..', 'graphify-out', 'graph.json')

if (!fs.existsSync(GRAPH)) {
  console.error('graph.json nao encontrado. Rode `graphify update .` primeiro.')
  process.exit(1)
}

const g = JSON.parse(fs.readFileSync(GRAPH, 'utf8'))
const nodes = g.nodes || []
const links = g.links || []

const norm = (s) => (s || '').split(String.fromCharCode(92)).join('/')
// Uma "area" e a maior unidade util do projeto. Em monorepos e o app/pacote
// (apps/web, packages/database); em repo de app unico e o diretorio de topo
// (src, tests). GRAPH_MAP_GROUPS ajusta os prefixos que agrupam por subpasta.
const GROUPS = (process.env.GRAPH_MAP_GROUPS || 'apps,packages,services,libs,modules')
  .split(',').map((x) => x.trim()).filter(Boolean)
const areaOf = (f) => {
  const p = norm(f)
  if (!p.includes('/')) return null
  const parts = p.split('/')
  return GROUPS.includes(parts[0]) && parts.length > 1 ? parts[0] + '/' + parts[1] : parts[0]
}

const fileById = new Map(nodes.map((n) => [n.id, norm(n.source_file)]))
const nodeById = new Map(nodes.map((n) => [n.id, n]))

// 1. Peso de cada area
const byArea = new Map()
for (const n of nodes) {
  const a = areaOf(n.source_file)
  if (a) byArea.set(a, (byArea.get(a) || 0) + 1)
}

// 2. Grau de cada no (hubs = onde uma mudanca se propaga)
const degree = new Map()
for (const e of links) {
  degree.set(e.source, (degree.get(e.source) || 0) + 1)
  degree.set(e.target, (degree.get(e.target) || 0) + 1)
}

// 3. Arestas que cruzam fronteira de app/pacote
const cross = new Map()
for (const e of links) {
  const a = areaOf(fileById.get(e.source))
  const b = areaOf(fileById.get(e.target))
  if (a && b && a !== b) {
    const k = `${a} -> ${b}`
    cross.set(k, (cross.get(k) || 0) + 1)
  }
}

// 3b. Indice por diretorio: onde cada coisa mora, na granularidade que cabe
// no contexto. Um indice de todos os 1742 nos custaria ~93 KB (~23k tokens);
// por diretorio custa ~1,4 KB e responde a mesma pergunta ("onde mexo?").
const byDir = new Map()
for (const n of nodes) {
  const f = norm(n.source_file)
  if (!f.includes('/')) continue
  const d = f.split('/').slice(0, -1).join('/')
  byDir.set(d, (byDir.get(d) || 0) + 1)
}

// 4. Hubs por area: o simbolo mais conectado de cada app
const hubsByArea = new Map()
for (const [id, d] of degree) {
  const n = nodeById.get(id)
  if (!n) continue
  const a = areaOf(n.source_file)
  if (!a) continue
  const cur = hubsByArea.get(a)
  if (!cur || d > cur.d) hubsByArea.set(a, { label: n.label, d, file: norm(n.source_file), loc: n.source_location })
}

const result = {
  commit: g.built_at_commit || null,
  totals: { nodes: nodes.length, edges: links.length },
  areas: [...byArea.entries()].sort((a, b) => b[1] - a[1]),
  crossBoundary: [...cross.entries()].sort((a, b) => b[1] - a[1]),
  hubs: [...hubsByArea.entries()].sort((a, b) => b[1].d - a[1].d),
  // O indice e lido em TODA sessao, entao o limite real e orcamento de bytes,
  // nao numero de nos. O limiar fixo de 5+ do kit original foi calibrado para
  // um projeto de 1.742 nos; aqui sao 10.780, e 5+ renderia 191 diretorios
  // (~7 KB) — metade do CLAUDE.md inteiro para responder "onde isso mora?".
  // Sobe o corte ate caber no orcamento; sempre mantem os maiores, que sao os
  // que respondem a pergunta. GRAPH_MAP_KB ajusta (padrao 3 KB).
  dirs: (() => {
    const all = [...byDir.entries()].sort((a, b) => b[1] - a[1])
    const orcamento = Number(process.env.GRAPH_MAP_KB || 3) * 1024
    const custo = (l) => l.reduce((a, [d]) => a + d.length + 8, 0)
    if (custo(all) <= orcamento) return all
    for (const lim of [5, 10, 15, 20, 25, 30, 40, 60, 80, 120]) {
      const podado = all.filter(([, n]) => n >= lim)
      if (custo(podado) <= orcamento) return podado
    }
    return all.slice(0, 40)
  })(),
}

if (process.argv.includes('--json')) {
  console.log(JSON.stringify(result, null, 2))
  process.exit(0)
}

// --- Renderizacao da secao gerada do CLAUDE.md ---------------------------
// Delimitada por marcadores para que o texto escrito a mao (convencoes,
// decisoes, divida conhecida) nunca seja sobrescrito por uma regeneracao.
const BEGIN = '<!-- graph-map:begin -->'
const END = '<!-- graph-map:end -->'
const TICK = String.fromCharCode(96)

function render() {
  const code = (t) => TICK + t + TICK
  const L = []
  L.push(BEGIN)
  L.push('<!-- Gerado por scripts/graph-map.js. Nao editar a mao: rode `node scripts/graph-map.js --write`. -->')
  L.push('')
  L.push(`Grafo: ${result.totals.nodes} nos, ${result.totals.edges} arestas (commit ${code((result.commit || '?').slice(0, 8))}).`)
  L.push('')
  L.push('| Area | Nos | Hub (maior propagacao de mudanca) |')
  L.push('|---|---|---|')
  const hubMap = new Map(result.hubs)
  for (const [a, n] of result.areas) {
    const h = hubMap.get(a)
    const hub = h ? `${code(h.label)} (${h.d} arestas) - ${code(h.file + ':' + String(h.loc || '').replace('L', ''))}` : '-'
    L.push(`| ${code(a)} | ${n} | ${hub} |`)
  }
  L.push('')
  L.push('**Acoplamento entre areas:**')
  if (result.crossBoundary.length === 0) {
    L.push('- nenhum - as areas sao silos independentes.')
  } else {
    for (const [k, n] of result.crossBoundary) L.push(`- ${code(k)}: ${n} arestas`)
  }
  L.push('')
  // O rotulo diz o corte REAL aplicado, nao um numero fixo: quem le precisa
  // saber que a lista e parcial, e a partir de quanto, para nao concluir que
  // um diretorio ausente nao existe.
  const menor = result.dirs.length ? result.dirs[result.dirs.length - 1][1] : 0
  const total = [...byDir.entries()].length
  const podou = result.dirs.length < total
  L.push(`**Onde as coisas moram**${podou ? ` (${result.dirs.length} de ${total} diretorios; corte em ${menor}+ nos)` : ''}:`)
  L.push('')
  L.push(TICK.repeat(3))
  for (const [d, n] of result.dirs) L.push(`${String(n).padStart(4)}  ${d}`)
  L.push(TICK.repeat(3))
  L.push('')
  L.push(END)
  return L.join('\n')
}

const CLAUDE_MD = path.join(__dirname, '..', 'CLAUDE.md')
const wantWrite = process.argv.includes('--write')
const wantCheck = process.argv.includes('--check')

if (wantWrite || wantCheck) {
  const md = fs.readFileSync(CLAUDE_MD, 'utf8')
  const i = md.indexOf(BEGIN)
  const j = md.indexOf(END)
  if (i === -1 || j === -1) {
    console.error('Marcadores graph-map:begin / graph-map:end nao encontrados no CLAUDE.md.')
    process.exit(1)
  }
  const next = md.slice(0, i) + render() + md.slice(j + END.length)

  if (wantCheck) {
    if (next !== md) {
      console.error('CLAUDE.md desatualizado em relacao ao grafo.')
      console.error('Rode: node scripts/graph-map.js --write')
      process.exit(1)
    }
    console.log('CLAUDE.md esta atualizado.')
    process.exit(0)
  }

  if (next === md) {
    console.log('CLAUDE.md ja estava atualizado.')
  } else {
    fs.writeFileSync(CLAUDE_MD, next)
    console.log('CLAUDE.md atualizado.')
  }
  process.exit(0)
}

console.log(render())
