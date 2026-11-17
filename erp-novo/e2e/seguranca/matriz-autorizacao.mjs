#!/usr/bin/env node
/**
 * Matriz de autorização automatizada (PIE R3, Agente F) — BOLA/BFLA na API inteira.
 *
 * Por que um runner HTTP e não um teste PHP: a fronteira que se quer medir é a
 * de produção inteira — Sanctum → ResolveTenant → envelope → RLS como `erp_app`
 * → RBAC no controller. Um teste de feature em sqlite pularia a RLS, e um teste
 * PHP no Postgres não atravessaria o servidor HTTP real nem o throttle.
 *
 * O que faz:
 *  1. prepara os atores que faltam (revenda B, usuário só da empresa 2,
 *     consumidores do app) com `preparar-revenda-b.sql`, pelo OWNER;
 *  2. lê TODAS as rotas do `database/api-manifest.json` e casa cada uma com a
 *     action e o middleware do `route:list`;
 *  3. para cada rota com parâmetro, resolve um id REAL da revenda A (empresa 1);
 *     para escrita, CLONA a linha pelo owner e mira o clone — escrita só em
 *     objeto criado pelo próprio runner;
 *  4. dispara a requisição com cada ator e compara com o esperado:
 *       - sem token → 401; token de app em /api/admin → 403;
 *       - admin da revenda B (com e sem `X-Empresa-Id` de A) → nunca 2xx;
 *       - admin só da empresa 2 → nunca 2xx em objeto da empresa 1;
 *       - papéis de A → 2xx só se o papel tem a permissão que o controller exige;
 *     e, nas listas sem id, procura dado de A na resposta de B (vazamento);
 *  5. grava a matriz (JSON + Markdown) e sai com código 1 se houver violação
 *     ou se o piso de rotas varridas não for atingido.
 *
 * Uso (API já no ar, como `erp_app`, apontando para PIE_DB):
 *   PIE_DB=pie_r3_f PIE_API_URL=http://127.0.0.1:8216 node e2e/seguranca/matriz-autorizacao.mjs
 *
 * NUNCA rode contra banco com dado real nem contra a homologação: o runner
 * cria usuários, emite tokens e clona linhas.
 */
import { execFileSync } from 'node:child_process'
import { createHash, randomBytes } from 'node:crypto'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const AQUI = path.dirname(fileURLToPath(import.meta.url))
const RAIZ = path.resolve(AQUI, '..', '..') // erp-novo
// Várias URLs separadas por vírgula = várias instâncias do mesmo backend/banco
// (o servidor embutido do PHP atende uma requisição por vez).
const APIS = (process.env.PIE_API_URL ?? 'http://127.0.0.1:8216').split(',').map((x) => x.trim()).filter(Boolean)
const API = APIS[0]
const BANCO = process.env.PIE_DB
const PSQL = process.env.PIE_PSQL
  ?? path.join(process.env.LOCALAPPDATA ?? '', 'dubena-tools', 'pg15x', 'pgsql', 'bin', 'psql.exe')
const PHP = process.env.PIE_PHP
  ?? path.join(process.env.LOCALAPPDATA ?? '', 'Microsoft', 'WinGet', 'Packages',
    'PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe', 'php.exe')
const SAIDA = process.env.PIE_SAIDA ?? path.join(AQUI, 'resultado')
const DOC = process.env.PIE_DOC // opcional: grava a matriz em Markdown aqui também
// Piso: o manifesto tem ~617 rotas. Se o runner varrer menos que isto, algo
// quebrou na leitura (manifesto vazio, route:list falhou) e o verde seria falso.
const PISO_ROTAS = Number(process.env.PIE_PISO_ROTAS ?? 560)
const SOMENTE = process.env.PIE_SOMENTE ? new RegExp(process.env.PIE_SOMENTE) : null

if (!BANCO) {
  console.error('Defina PIE_DB com o banco do agente (clone do pie_r3_base). Nunca o base nem dado real.')
  process.exit(2)
}
if (/^(pie_r3_base|erp_teste|erp|erp_novo|postgres)$/.test(BANCO)) {
  console.error(`Recusado: ${BANCO} não é banco descartável de agente.`)
  process.exit(2)
}

// ---------------------------------------------------------------------------
// Banco (owner, só para preparar e resolver ids)
// ---------------------------------------------------------------------------
function sql(texto) {
  const out = execFileSync(PSQL, ['-h', '127.0.0.1', '-p', process.env.PIE_DB_PORT ?? '55432', '-U', 'postgres',
    '-d', BANCO, '-At', '-F', '\t', '-v', 'ON_ERROR_STOP=1', '-c', texto], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] })
  return out.split(/\r?\n/).filter((l) => l !== '').map((l) => l.split('\t'))
}
function sqlTentar(texto) {
  try { return sql(texto) } catch { return null }
}
function um(texto) {
  const r = sqlTentar(texto)
  return r && r[0] ? r[0][0] : null
}

function preparar() {
  execFileSync(PSQL, ['-h', '127.0.0.1', '-p', process.env.PIE_DB_PORT ?? '55432', '-U', 'postgres', '-d', BANCO,
    '-v', 'ON_ERROR_STOP=1', '-q', '-f', path.join(AQUI, 'preparar-revenda-b.sql')], { stdio: ['ignore', 'ignore', 'inherit'] })
}

/** Token Sanctum emitido pelo owner, com ability de app (o login do app exige vínculo de colaborador). */
function emitirToken(tipo, id, abilities) {
  const plano = randomBytes(30).toString('hex').slice(0, 40)
  const hash = createHash('sha256').update(plano).digest('hex')
  const tid = um(`insert into personal_access_tokens (tokenable_type, tokenable_id, name, token, abilities, created_at, updated_at)
    values ('${tipo}', ${id}, 'matriz-autorizacao', '${hash}', '${JSON.stringify(abilities)}', now(), now()) returning id`)
  return `${tid}|${plano}`
}

// ---------------------------------------------------------------------------
// HTTP
// ---------------------------------------------------------------------------
const espera = (ms) => new Promise((r) => setTimeout(r, ms))

async function chamar(metodo, uri, token, extraHeaders = {}, base = API) {
  const headers = { Accept: 'application/json', 'Content-Type': 'application/json', ...extraHeaders }
  if (token) headers.Authorization = `Bearer ${token}`
  for (let tentativa = 0; tentativa < 6; tentativa++) {
    let res
    try {
      res = await fetch(`${base}/${uri}`, {
        method: metodo, headers, body: ['GET', 'HEAD', 'DELETE'].includes(metodo) ? undefined : '{}',
        redirect: 'manual', signal: AbortSignal.timeout(60_000),
      })
    } catch (e) {
      if (tentativa === 5) return { status: 0, corpo: String(e), tipo: '' }
      await espera(1000)
      continue
    }
    if (res.status === 429) {
      const ra = Number(res.headers.get('retry-after') ?? 10)
      await espera((ra + 1) * 1000)
      continue
    }
    const corpo = await res.text()
    return { status: res.status, corpo, tipo: res.headers.get('content-type') ?? '' }
  }
  return { status: 429, corpo: '', tipo: '' }
}

async function login(email, senha, rota = 'api/login') {
  const r = await fetch(`${API}/${rota}`, {
    method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password: senha }),
  })
  const j = await r.json().catch(() => ({}))
  if (!j.token) throw new Error(`login ${email} falhou: ${r.status} ${JSON.stringify(j).slice(0, 200)}`)
  return j.token
}

// ---------------------------------------------------------------------------
// Rotas: manifesto × route:list × permissão exigida pelo controller
// ---------------------------------------------------------------------------
function carregarRotas() {
  const manifesto = JSON.parse(fs.readFileSync(path.join(RAIZ, 'database', 'api-manifest.json'), 'utf8'))
  const lista = JSON.parse(execFileSync(PHP, ['artisan', 'route:list', '--json', '--path=api'], { cwd: RAIZ, encoding: 'utf8', maxBuffer: 64 << 20 }))
  const porChave = new Map()
  for (const r of lista) {
    for (const m of r.method.split('|')) if (m !== 'HEAD') porChave.set(`${m} ${r.uri}`, r)
  }
  return manifesto.map((k) => {
    const [metodo, uri] = k.split(' ')
    const r = porChave.get(k)
    return { metodo, uri, action: r?.action ?? '', middleware: r?.middleware ?? [] }
  })
}

const cacheFonte = new Map()
/** Permissão da PRIMEIRA chamada de autorização do método (convenção do repositório: primeira linha). */
function permissaoExigida(action) {
  const [classe, metodo] = action.split('@')
  if (!classe || !metodo) return null
  const arq = path.join(RAIZ, classe.replace(/^App\\/, 'app\\').replace(/\\/g, path.sep) + '.php')
  if (!cacheFonte.has(arq)) cacheFonte.set(arq, fs.existsSync(arq) ? fs.readFileSync(arq, 'utf8') : '')
  const src = cacheFonte.get(arq)
  const ini = src.search(new RegExp(`function\\s+${metodo}\\s*\\(`))
  if (ini < 0) return null
  const resto = src.slice(ini + 10)
  const fim = resto.search(/\n\s{4}(public|protected|private)\s+function\s/)
  const corpo = fim < 0 ? resto : resto.slice(0, fim)
  const m = corpo.match(/autorizar(?:NaEmpresa|Recurso)?\(\s*\$request,\s*['"]([a-z0-9_.]+)['"]/i)
    ?? corpo.match(/temPermissao\(\s*['"]([a-z0-9_.]+)['"]/i)
  if (m) return m[1]
  if (/autorizar(?:NaEmpresa|Recurso)?\(\s*\$request,\s*["']?\{?\$/.test(corpo) || /autorizar\(\s*\$request,\s*\$/.test(corpo)) return 'DINAMICA'
  return null
}

// ---------------------------------------------------------------------------
// Resolução de parâmetros → id real da revenda A (empresa 1 / grupo 1)
// ---------------------------------------------------------------------------
// segmento anterior ao parâmetro → [tabela, escopo]. Escopo `grupo` é
// configuração da rede: o usuário só da empresa 2 (mesma rede) PODE vê-la.
const TABELAS = {
  'alcadas': ['alcada_descontos', 'empresa'], 'alertas': ['alertas', 'empresa'],
  'missoes/atribuicoes': ['missao_atribuicoes', 'empresa'], 'missoes/evidencias': ['missao_evidencias', 'empresa'],
  'missoes': ['missoes', 'empresa'], 'bens': ['empresa_bens', 'empresa'],
  'boletos/remessas': ['remessas_cnab', 'empresa'], 'cobranca/remessas': ['remessas_cnab', 'empresa'], 'boletos': ['boletos', 'empresa'],
  'caixa/movimentos': ['contamovimentos', 'empresa'], 'caixa': ['contas', 'empresa'],
  'central-vendas/solicitacoes': ['pedido_solicitacoes', 'empresa'], 'entregador/solicitacoes': ['pedido_solicitacoes', 'empresa'],
  'central/pedidos': ['pedidos', 'empresa'], 'central/entregadores': ['users', 'usuario-entregador'],
  'checklists': ['checklists', 'grupo'], 'cheques': ['cheques', 'empresa'],
  'superadmin/cidades': ['cidades_plataforma', 'global'], 'cidades': ['cidades', 'grupo'],
  'clientes/revisoes': ['cliente_revisoes', 'empresa'], 'clientes': ['clientes', 'empresa'],
  'dependentes': ['clientedependentes', 'empresa'], 'interacoes': ['clienteinteracoes', 'empresa'], 'telefones': ['clientetelefones', 'empresa'],
  'colaboradores': ['colaboradores', 'empresa'], 'comissoes': ['colaborador_comissoes', 'empresa'],
  'familia': ['colaborador_familias', 'empresa'], 'recessos': ['colaborador_recessos', 'empresa'],
  'comodatos/vigilancia': ['clientes', 'empresa'], 'comodatos/vinculos': ['produtos', 'empresa'],
  'comodatos': ['comodatos', 'empresa'], 'contratos': ['comodato_contratos', 'empresa'],
  'convenios': ['convenios', 'empresa'], 'cupons-fiscais': ['cupons_fiscais', 'empresa'],
  'departamentos': ['departamentos', 'empresa'], 'documentos': ['documentos', 'empresa'],
  'superadmin/empresas': ['empresas', 'global'], 'empresas': ['empresas', 'empresa-propria'],
  'estoque/fechamentos': ['estoquefechamentos', 'empresa'], 'estoque/fisico': ['estoque_inventarios', 'empresa'],
  'estoque/requisicoes': ['estoque_requisicoes', 'empresa'],
  'financeiro/centros-custo': ['centros_custo', 'grupo'], 'financeiro/contas': ['contas', 'empresa'],
  'extrato-regras': ['conta_extrato_regras', 'empresa'], 'financeiro/lancamentos': ['financeiros', 'empresa'],
  'financeiro/planos-conta': ['planos_conta', 'grupo'],
  'fiscal/inventarios': ['inventarios_fiscais', 'empresa'], 'fiscal/nf-entrada': ['nf_recebidas', 'empresa'],
  'fiscal/nfe': ['notas_fiscais', 'empresa'], 'fiscal/operacoes': ['operacoes_fiscais', 'grupo'],
  'entregador/fiscal/notas': ['notas_fiscais', 'empresa'], 'notas': ['notas_fiscais', 'empresa'],
  'franqueados': ['colaboradores', 'empresa'], 'gasdopovo': ['gasdopovo_beneficios', 'empresa'],
  'grupos': ['grupos', 'grupo-proprio'], 'mcmm': ['mcmms', 'empresa'], 'metas': ['meta_vendas', 'empresa'],
  'monitora/cercas': ['monitora_cercas', 'empresa'], 'monitora/veiculos': ['monitora_veiculos', 'empresa'],
  'papeis': ['roles', 'grupo'], 'condicoes': ['permission_conditions', 'empresa'],
  'pedidos/situacoes': ['pedidosituacoes', 'grupo'], 'pos-vendas': ['pos_vendas', 'empresa'],
  'produto-config/classes': ['produtoclasses', 'grupo'], 'produto-config/unidades': ['unidadesmedida', 'grupo'],
  'produtos': ['produtos', 'empresa'], 'promocoes': ['promocoes', 'grupo'], 'regioes': ['regioes', 'grupo'],
  'seguranca/sessoes': ['personal_access_tokens', 'sessao'], 'setores-org': ['setores_org', 'empresa'],
  'setores': ['setores', 'empresa'], 'sorteios': ['sorteios', 'grupo'], 'taxas-entrega': ['taxas_entrega', 'empresa'],
  'telefonia/chamadas': ['telefonia_chamadas', 'empresa'], 'unidades': ['unidades', 'empresa'],
  'usuarios': ['users', 'usuario'], 'vale-gas': ['vale_gas', 'empresa'], 'veiculos': ['veiculos', 'empresa'],
  'pneus': ['veiculo_pneus', 'empresa'], 'trocas-oleo': ['veiculo_trocas_oleo', 'empresa'],
  'logradouros/importacoes': ['importacoes_logradouro', 'grupo'], 'enderecos': ['cliente_enderecos', 'consumidor2'],
  'app/v1/pedidos': ['pedidos', 'consumidor2'], 'entregador/pedidos': ['pedidos', 'empresa'],
  'migracoes': ['migracoes', 'global'], 'planos': ['planos', 'global'],
}
// Parâmetros que não são id de linha: valores válidos fixos.
const LITERAIS = {
  '{tipo}': { 'cadastros': ['segmentos', 'cargos', 'condicoes-pagamento'], 'lookups': ['segmentos'], 'fiscal/malha': ['icms'] },
  '{entidade}': { 'geo': ['bairros'], 'auditoria/registro': ['cliente'] },
  '{slug}': { 'relatorios': ['vendas-por-produto'] },
  '{cep}': { 'geo/cep': ['85010000'] },
  '{chave}': { 'limite': ['usuarios'], 'override': ['crm'] },
}
// Tabela de linha para os parâmetros por TIPO (cadastros/{tipo}/{id} etc.).
const TABELA_DO_TIPO = { segmentos: ['segmentos', 'grupo'], cargos: ['cargos', 'grupo'], 'condicoes-pagamento': ['condicaopagamentos', 'grupo'], bairros: ['bairros', 'grupo'], icms: ['nf_impostos', 'empresa'], cliente: ['clientes', 'empresa'] }

let IDS = {} // preenchido em main (usuários, consumidores)

function filtroDeEscopo(tabela, escopo, cols) {
  if (escopo === 'empresa-propria') return 'id = 1'
  if (escopo === 'grupo-proprio') return 'id = 1'
  if (escopo === 'usuario') return `id = ${IDS.gerente}`
  if (escopo === 'usuario-entregador') return `id = ${IDS.entregador}`
  if (escopo === 'sessao') return `tokenable_type = 'App\\Models\\User' and tokenable_id = ${IDS.gerente}`
  if (escopo === 'consumidor2') {
    return tabela === 'pedidos' ? `cliente_id = ${IDS.cliente2}` : `cliente_id = ${IDS.cliente2}`
  }
  if (escopo === 'global') return 'true'
  if (cols.includes('empresa_id')) return 'empresa_id = 1'
  if (cols.includes('grupo_id')) return 'grupo_id = 1'
  return 'true'
}

const cacheCols = new Map()
function colunas(tabela) {
  if (!cacheCols.has(tabela)) {
    const r = sqlTentar(`select attname from pg_attribute where attrelid = 'public.${tabela}'::regclass and attnum > 0 and not attisdropped and attgenerated = ''`)
    cacheCols.set(tabela, r ? r.map((x) => x[0]) : [])
  }
  return cacheCols.get(tabela)
}

/** id existente de A; com `pai` (coluna, valor) restringe ao filho daquele pai. */
function idDeA(tabela, escopo, pai) {
  const cols = colunas(tabela)
  if (!cols.length) return null
  let onde = filtroDeEscopo(tabela, escopo, cols)
  if (pai && cols.includes(pai[0])) onde += ` and ${pai[0]} = ${pai[1]}`
  return um(`select id from ${tabela} where ${onde} order by id limit 1`)
}

/**
 * Clona a linha pelo owner para a escrita mirar um objeto do próprio runner.
 * Colunas únicas que costumam colidir recebem sufixo; se ainda assim o insert
 * falhar (constraint), a rota fica "sem clone" e a escrita não é exercida.
 */
function clonar(tabela, id) {
  if (['empresas', 'grupos', 'planos', 'migracoes', 'users', 'cidades_plataforma'].includes(tabela)) return null
  const cols = colunas(tabela).filter((c) => c !== 'id')
  if (!cols.length) return null
  const sel = cols.map((c) => (['email', 'cpf', 'cnpj', 'codigo', 'chave', 'token', 'uuid', 'numero', 'nosso_numero', 'slug'].includes(c)
    ? `case when ${c} is null then null else left(${c}::text || '-c' || floor(random()*1e6)::text, 64) end` : c)).join(', ')
  return um(`insert into ${tabela} (${cols.join(', ')}) select ${sel} from ${tabela} where id = ${id} returning id`)
}

/**
 * Monta a URI concreta. Para cada parâmetro: o segmento anterior decide a
 * tabela; parâmetros filhos ficam restritos ao pai já resolvido.
 */
function resolver(rota, escrita) {
  const partes = rota.uri.split('/')
  const valores = []
  let escopo = null
  let tabelaFinal = null
  let pai = null
  let tipoAtual = null
  for (let i = 0; i < partes.length; i++) {
    const p = partes[i]
    if (!p.startsWith('{')) continue
    const ant = partes.slice(0, i).join('/')
    // literal?
    const lits = LITERAIS[p]
    if (lits) {
      const chave = Object.keys(lits).find((k) => ant.endsWith(k))
      if (chave) { tipoAtual = lits[chave][0]; partes[i] = tipoAtual; valores.push(tipoAtual); continue }
    }
    let alvo = null
    if (tipoAtual && TABELA_DO_TIPO[tipoAtual]) alvo = TABELA_DO_TIPO[tipoAtual]
    else {
      const cands = Object.keys(TABELAS).filter((k) => ant.endsWith(k)).sort((a, b) => b.length - a.length)
      if (cands.length) alvo = TABELAS[cands[0]]
    }
    if (!alvo) return { erro: `sem mapa para ${p} em ${ant}` }
    const [tabela, esc] = alvo
    const fkPai = pai ? [pai.fk, pai.id] : null
    let id = idDeA(tabela, esc, fkPai)
    if (!id) return { erro: `sem linha em ${tabela} para A` }
    const ultimo = !partes.slice(i + 1).some((x) => x.startsWith('{'))
    if (escrita && ultimo) {
      const c = clonar(tabela, id)
      if (!c) return { erro: `sem clone de ${tabela}` }
      id = c
    }
    partes[i] = id
    valores.push(id)
    escopo = esc
    tabelaFinal = tabela
    pai = { fk: fkDoPai(tabela), id }
  }
  // Só parâmetros literais (tipo, entidade, slug, cep): a rota é uma LISTA,
  // não um objeto de A — mede-se vazamento no corpo, não o status.
  return { uri: partes.join('/'), escopo, tabela: tabelaFinal, valores, soLiteral: tabelaFinal === null }
}

function fkDoPai(tabela) {
  const mapa = { clientes: 'cliente_id', colaboradores: 'colaborador_id', veiculos: 'veiculo_id', comodatos: 'comodato_id',
    contas: 'conta_id', roles: 'role_id', convenios: 'convenio_id', pedidos: 'pedido_id', papeis: 'role_id' }
  return mapa[tabela] ?? 'id_pai_inexistente'
}

// ---------------------------------------------------------------------------
// Detecção de vazamento em lista (revenda B / empresa 2)
// ---------------------------------------------------------------------------
function marcasDeA(corpo, alvo) {
  const achados = []
  let json = null
  try { json = JSON.parse(corpo) } catch { /* CSV/PDF: só texto */ }
  const visitar = (o, cam) => {
    if (achados.length > 3 || o === null || typeof o !== 'object') return
    for (const [k, v] of Object.entries(o)) {
      if (alvo.empresas.length && k === 'empresa_id' && alvo.empresas.includes(Number(v))) achados.push(`${cam}${k}=${v}`)
      else if (alvo.grupos.length && k === 'grupo_id' && alvo.grupos.includes(Number(v))) achados.push(`${cam}${k}=${v}`)
      else if (typeof v === 'object') visitar(v, `${cam}${k}.`)
    }
  }
  if (json) visitar(json, '')
  for (const s of alvo.sentinelas) if (s && corpo.includes(s)) achados.push(`texto:"${s.slice(0, 12)}…"`)
  return achados
}

// ---------------------------------------------------------------------------
// Expectativa e veredito
// ---------------------------------------------------------------------------
const NEGA = new Set([401, 403, 404, 402, 405, 409, 423])

function veredito(esperado, status, metodo) {
  const ok2 = status >= 200 && status < 300
  if (status === 0 || status >= 500) return 'ERRO'
  if (esperado === '401') return status === 401 ? 'OK' : ok2 ? 'VIOLACAO' : status === 403 ? 'OK' : 'DIVERGE'
  if (esperado === 'nega') {
    if (ok2) return 'VIOLACAO'
    if (NEGA.has(status)) return 'OK'
    if (status === 422) return 'VALIDA-ANTES' // validou antes de autorizar/buscar: não vazou, mas a ordem está errada
    return 'DIVERGE'
  }
  if (esperado === 'permite') {
    if (ok2 || (status === 422 && metodo !== 'GET')) return 'OK'
    // GET de relatório/consulta sem os filtros obrigatórios: passou da
    // autorização (senão seria 403) e parou na validação.
    if (status === 422) return 'OK-EXIGE-FILTRO'
    if (status === 403) return 'NEGA-INDEVIDO'
    return 'DIVERGE'
  }
  return 'INFO'
}

// ---------------------------------------------------------------------------
// Principal
// ---------------------------------------------------------------------------
async function main() {
  fs.mkdirSync(SAIDA, { recursive: true })
  preparar()

  const ids = Object.fromEntries(sql(`select email, id from users where email in ('admin@pie.local','gerente@pie.local','operador@pie.local','entregador@pie.local','adminb@pie.local','emp2@pie.local','consumidor1@pie.local','consumidor2@pie.local')`)
    .map(([e, i]) => [e.split('@')[0], Number(i)]))
  IDS = { ...ids, cliente2: Number(um(`select id from clientes where user_id = ${ids.consumidor2}`)) }

  const PERFIL = 'Pie-R3-Perfil!2026'
  const SENHA_B = 'Pie-R3-RevendaB!2026'
  const tok = {
    admin: await login('admin@pie.local', 'Pie-R3-Admin!2026'),
    gerente: await login('gerente@pie.local', PERFIL),
    operador: await login('operador@pie.local', PERFIL),
    entregador: await login('entregador@pie.local', PERFIL),
    adminB: await login('adminb@pie.local', SENHA_B),
    emp2: await login('emp2@pie.local', SENHA_B),
    appEntregador: emitirToken('App\\Models\\User', ids.entregador, ['role:entregador']),
    appCliente: emitirToken('App\\Models\\User', ids.consumidor1, ['role:cliente']),
    superadmin: emitirToken('App\\Models\\Saas\\PlatformAdmin', Number(um(`select id from platform_admins order by id limit 1`)), ['superadmin']),
  }

  const permsPorPapel = {}
  for (const [ator, papel] of [['admin', 'Administrador'], ['gerente', 'Gerente'], ['operador', 'Operador'], ['entregador', 'Entregador']]) {
    permsPorPapel[ator] = new Set(sql(`select p.chave from permissions p join permission_role pr on pr.permission_id = p.id join roles r on r.id = pr.role_id where r.grupo_id = 1 and r.nome = '${papel}'`).map((x) => x[0]))
  }

  const sentinelas = [
    ...sql(`select razao_social from empresas where grupo_id = 1`).map((x) => x[0]),
    ...sql(`select nome from clientes where empresa_id = 1 and length(nome) > 8 order by id limit 5`).map((x) => x[0]),
    ...sql(`select cpf from clientes where empresa_id = 1 and cpf is not null and length(cpf) = 11 order by id limit 3`).map((x) => x[0]),
  ]
  const alvoB = { empresas: [1, 2], grupos: [1], sentinelas }
  const alvoEmp2 = { empresas: [1], grupos: [], sentinelas: [] }

  const rotas = carregarRotas().filter((r) => !SOMENTE || SOMENTE.test(`${r.metodo} ${r.uri}`))
  const linhas = []
  let varridas = 0
  const pulos = []

  let proxima = 0
  const trabalhador = async (base) => {
  while (proxima < rotas.length) {
    const rota = rotas[proxima++]
    const { metodo, uri } = rota
    let temParam = uri.includes('{')
    const escrita = metodo !== 'GET'
    const publica = !rota.middleware.some((m) => /Authenticate|auth:|plataforma/.test(m))
    const area = uri.startsWith('api/admin') ? 'admin' : uri.startsWith('api/superadmin') ? 'superadmin'
      : uri.startsWith('api/app') ? 'app' : uri.startsWith('api/legado') ? 'legado' : 'outra'
    if (/(^|\/)(login|logout|refresh|cadastro)$|pabx\/chamada|pix\/webhook|api\/health/.test(uri) || publica) {
      linhas.push({ metodo, uri, ator: '-', esperado: '-', status: '-', veredito: 'PULADA', nota: publica ? 'pública/sessão' : 'sessão' })
      pulos.push(`${metodo} ${uri} (pública/sessão)`)
      varridas++
      continue
    }
    let alvo = { uri, escopo: null }
    if (temParam) {
      alvo = resolver(rota, escrita)
      if (alvo.soLiteral) temParam = false
      if (alvo.erro) {
        // Sem objeto de A ainda dá para medir o que não depende do objeto: anônimo e token de app.
        alvo = { uri: uri.replace(/\{[^}]+\}/g, '999999999'), escopo: null, semObjeto: alvo.erro }
      }
    }
    varridas++
    const perm = permissaoExigida(rota.action)
    const atores = []
    atores.push(['anonimo', null, {}, '401'])

    if (area === 'admin') {
      atores.push(['app-entregador', tok.appEntregador, {}, 'nega'])
      if (!alvo.semObjeto) {
        if (temParam || !escrita) {
          atores.push(['adminB', tok.adminB, {}, temParam ? 'nega' : 'lista'])
          // O header de empresa fora do alcance é IGNORADO por desenho (ResolveTenant):
          // na lista, a prova é não aparecer dado de A; no objeto, não ser 2xx.
          atores.push(['adminB+empresa1', tok.adminB, { 'X-Empresa-Id': '1' }, temParam ? 'nega' : 'lista'])
        }
        const emp2Pode = !temParam || ['grupo', 'grupo-proprio', 'global'].includes(alvo.escopo)
        if (temParam || !escrita) atores.push(['emp2', tok.emp2, {}, temParam ? (emp2Pode ? 'info' : 'nega') : 'lista-emp2'])
        for (const papel of ['admin', 'gerente', 'operador', 'entregador']) {
          let esp = 'info'
          if (perm && perm !== 'DINAMICA') esp = permsPorPapel[papel].has(perm) ? 'permite' : 'nega'
          // Escrita autorizada não é exercida (não é teste funcional e mexeria em dado de A).
          if (escrita && esp !== 'nega') continue
          atores.push([papel, tok[papel], {}, esp])
        }
      }
    } else if (area === 'superadmin') {
      atores.push(['admin-painel', tok.admin, {}, 'nega'], ['app-cliente', tok.appCliente, {}, 'nega'])
      if (!escrita) atores.push(['superadmin', tok.superadmin, {}, 'info'])
    } else if (area === 'app') {
      if (uri.includes('/entregador/')) atores.push(['app-cliente', tok.appCliente, {}, 'nega'])
      else if (!alvo.semObjeto && temParam && alvo.escopo === 'consumidor2') {
        atores.push(['consumidor1(pedido do 2)', tok.appCliente, {}, 'nega'])
        atores.push(['adminB', tok.adminB, {}, 'nega'])
      } else if (!escrita) atores.push(['app-cliente', tok.appCliente, {}, 'info'], ['adminB', tok.adminB, {}, temParam ? 'nega' : 'lista'])
    } else if (area === 'legado') {
      atores.push(['adminB', tok.adminB, {}, 'info'])
    }

    for (const [ator, token, hdr, esperado] of atores) {
      const r = await chamar(metodo, alvo.uri, token, hdr, base)
      let v
      let nota = alvo.semObjeto ? `sem objeto (${alvo.semObjeto})` : ''
      if (esperado === 'lista' || esperado === 'lista-emp2') {
        const marcas = r.status >= 200 && r.status < 300 ? marcasDeA(r.corpo, esperado === 'lista' ? alvoB : alvoEmp2) : []
        v = r.status >= 500 ? 'ERRO' : marcas.length ? 'VIOLACAO' : 'OK'
        if (marcas.length) nota = `dado de A na resposta: ${marcas.slice(0, 3).join(', ')}`
      } else {
        v = veredito(esperado, r.status, metodo)
        // 2xx em objeto de A sem NENHUM dado de A no corpo (lista vazia, null):
        // a fronteira filtrou, só não recusou. Não é vazamento; fica registrado.
        if (v === 'VIOLACAO' && metodo === 'GET' && !marcasDeA(r.corpo, ator === 'emp2' ? alvoEmp2 : alvoB).length && /^\{"data":(\[\]|null)/.test(r.corpo)) v = 'VAZIO-200'
      }
      if (v === 'ERRO' && r.corpo) nota = (nota ? nota + '; ' : '') + r.corpo.replace(/\s+/g, ' ').slice(0, 160)
      if (v === 'VIOLACAO' && !nota) nota = r.corpo.replace(/\s+/g, ' ').slice(0, 160)
      linhas.push({ metodo, uri, alvo: alvo.uri, ator, perm: perm ?? '', esperado, status: r.status, veredito: v, nota })
    }
  }
  }
  await Promise.all(APIS.map((b) => trabalhador(b)))
  linhas.sort((a, b) => (a.uri + a.metodo).localeCompare(b.uri + b.metodo))

  // ---------------------------------------------------------------- saída
  const cont = {}
  for (const l of linhas) cont[l.veredito] = (cont[l.veredito] ?? 0) + 1
  const resumo = { data: new Date().toISOString(), banco: BANCO, api: API, rotas_manifesto: rotas.length, rotas_varridas: varridas, requisicoes: linhas.filter((l) => l.status !== '-').length, vereditos: cont }
  fs.writeFileSync(path.join(SAIDA, 'matriz.json'), JSON.stringify({ resumo, linhas }, null, 1))

  const md = []
  md.push(`<!-- gerado por erp-novo/e2e/seguranca/matriz-autorizacao.mjs em ${resumo.data} -->`)
  md.push(`Banco \`${BANCO}\` · API ${API} · rotas no manifesto: ${rotas.length} · varridas: ${varridas} · requisições: ${resumo.requisicoes}`)
  md.push('')
  md.push('| Veredito | Qtde |', '|---|---|', ...Object.entries(cont).sort().map(([k, n]) => `| ${k} | ${n} |`))
  md.push('')
  const graves = linhas.filter((l) => ['VIOLACAO', 'ERRO', 'VALIDA-ANTES', 'NEGA-INDEVIDO', 'DIVERGE'].includes(l.veredito))
  md.push('## Anomalias (tudo o que não é OK/INFO/PULADA)', '', '| Método | Rota | Ator | Permissão | Esperado | Obtido | Veredito | Nota |', '|---|---|---|---|---|---|---|---|')
  for (const l of graves) md.push(`| ${l.metodo} | \`${l.uri}\` | ${l.ator} | ${l.perm} | ${l.esperado} | ${l.status} | **${l.veredito}** | ${String(l.nota).replace(/\|/g, '/')} |`)
  md.push('', '## Matriz completa', '', '| Método | Rota | Ator | Esperado | Obtido | Veredito |', '|---|---|---|---|---|---|')
  for (const l of linhas) md.push(`| ${l.metodo} | \`${l.uri}\` | ${l.ator} | ${l.esperado} | ${l.status} | ${l.veredito} |`)
  fs.writeFileSync(path.join(SAIDA, 'matriz.md'), md.join('\n'))
  if (DOC) fs.writeFileSync(DOC, md.join('\n'))

  console.log(JSON.stringify(resumo, null, 1))
  for (const l of graves.filter((x) => x.veredito === 'VIOLACAO' || x.veredito === 'ERRO')) {
    console.log(`${l.veredito} ${l.metodo} ${l.uri} [${l.ator}] esperado=${l.esperado} obtido=${l.status} ${l.nota}`)
  }
  if (!SOMENTE && varridas < PISO_ROTAS) {
    console.error(`Piso não atingido: ${varridas} < ${PISO_ROTAS} rotas varridas.`)
    process.exit(1)
  }
  process.exit((cont.VIOLACAO ?? 0) > 0 ? 1 : 0)
}

main().catch((e) => { console.error(e); process.exit(2) })
