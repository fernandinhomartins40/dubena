import { mkdirSync, writeFileSync } from 'node:fs'
import path from 'node:path'
import { test, expect, PERFIS, type Perfil } from '../support/fixtures'
import { entrarDevagar } from './apoio'

/**
 * Protocolo §4.3 — menu × permissão × licença × backend, MEDIDO.
 *
 * Para cada perfil do painel: lê do `/me` as permissões e os recursos do plano,
 * decide quais itens do menu ele enxerga (a mesma regra do AppShell) e abre
 * CADA rota do menu — as que ele vê e as que não vê. Registra toda resposta
 * 401/402/403/5xx que a tela disparou.
 *
 * O que se procura é divergência entre as camadas:
 *  - item VISÍVEL cuja tela leva 403/402 → o menu promete o que o backend nega;
 *  - item OCULTO cuja tela abre com dado → porta lateral (a rota não guarda).
 *
 * Não grava nada: só navega. A saída vai para `resultados/produto/` (ignorado
 * pelo git) e é consolidada no G_INVENTARIO_CRUZADO.md.
 */

/** Espelho de `NAV` em AppShell.tsx (to, permissão, recurso de licença). */
const MENU: { to: string; label: string; perm?: string; feature?: string }[] = [
  { to: '/', label: 'Início' },
  { to: '/alertas', label: 'Avisos e pendências', perm: 'alerta.view' },
  { to: '/relatorios', label: 'Relatórios', perm: 'relatorio.view', feature: 'relatorios_avancados' },
  { to: '/pedidos', label: 'Pedidos', perm: 'pedido.view' },
  { to: '/clientes', label: 'Clientes', perm: 'cliente.view' },
  { to: '/clientes/revisoes', label: 'Clientes duplicados', perm: 'cliente.edit' },
  { to: '/central-vendas', label: 'Central de Vendas', perm: 'venda.view' },
  { to: '/alcadas', label: 'Limites de desconto', perm: 'venda.alcada' },
  { to: '/central', label: 'Central de entregas', perm: 'logistica.view' },
  { to: '/comodatos', label: 'Comodatos', perm: 'comodato.view' },
  { to: '/missoes', label: 'Missões de campo', perm: 'missao.view' },
  { to: '/monitora', label: 'Rastreamento (GPS)', perm: 'monitora.view', feature: 'monitora' },
  { to: '/geografico', label: 'Endereços (cidades e ruas)', perm: 'cliente.view' },
  { to: '/produtos', label: 'Produtos', perm: 'produto.view' },
  { to: '/estoque', label: 'Estoque', perm: 'estoque.view' },
  { to: '/mcmm', label: 'Movimentação de material', perm: 'mcmm.view' },
  { to: '/bens', label: 'Patrimônio (bens)', perm: 'bem.view' },
  { to: '/financeiro', label: 'Financeiro', perm: 'financeiro.view' },
  { to: '/cartoes', label: 'Cartões', perm: 'cartao.view' },
  { to: '/vale-gas', label: 'Vale-Gás', perm: 'valegas.view' },
  { to: '/gas-do-povo', label: 'Gás do Povo', perm: 'gasdopovo.view' },
  { to: '/convenios', label: 'Convênios', perm: 'convenio.view' },
  { to: '/fiscal', label: 'Notas fiscais', perm: 'fiscal.view' },
  { to: '/cupons-fiscais', label: 'Cupons fiscais (SAT)', perm: 'cupomfiscal.view' },
  { to: '/pos-venda', label: 'Pós-venda', perm: 'posvenda.view' },
  { to: '/promocoes', label: 'Promoções', perm: 'promocao.view', feature: 'crm' },
  { to: '/sorteios', label: 'Sorteios', perm: 'sorteio.view', feature: 'crm' },
  { to: '/metas', label: 'Metas de venda', perm: 'meta.view' },
  { to: '/colaboradores', label: 'Colaboradores', perm: 'colaborador.view' },
  { to: '/veiculos', label: 'Veículos', perm: 'veiculo.view', feature: 'frota' },
  { to: '/checklists', label: 'Checklists', perm: 'checklist.view' },
  { to: '/documentos', label: 'Documentos', perm: 'documento.view' },
  { to: '/empresas', label: 'Empresas', perm: 'empresa.view' },
  { to: '/acessos', label: 'Usuários e permissões', perm: 'usuario.view' },
  { to: '/auditoria', label: 'Histórico de ações', perm: 'auditoria.view' },
  { to: '/configuracoes', label: 'Configurações', perm: 'grupo.view' },
  // Fora do menu, mas roteadas: entram para medir a guarda.
  { to: '/satelites', label: '(rota sem item) Satélites' },
  { to: '/seguranca', label: '(rota sem item) Segurança' },
]

interface Linha { to: string; label: string; visivel: boolean; negadas: string[]; telaSemAcesso: boolean; erro5xx: string[] }

const PAINEL: Perfil[] = ['admin', 'gerente', 'operador']

for (const perfil of PAINEL) {
  test(`matriz do menu — ${perfil}`, async ({ page, evidencia }, info) => {
    test.skip(info.project.name !== 'desktop', 'a matriz é a mesma no celular')
    test.setTimeout(40 * 60_000)
    void evidencia

    await entrarDevagar(page, perfil)
    // O `/me` com o token da sessão: é a mesma fonte que o AppShell usa.
    const token = await page.evaluate(() => localStorage.getItem('erpnovo_token') ?? sessionStorage.getItem('erpnovo_token'))
    const API = process.env.PIE_API_URL ?? 'http://127.0.0.1:8000'
    const me = await (await page.request.get(`${API}/api/me`, { headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' } })).json()
    const u = me.data ?? me.user ?? me
    const perms: string[] = u.permissions ?? []
    const features: string[] | undefined = u.features
    const suporte = !!u.is_support
    const pode = (p?: string) => !p || suporte || perms.includes(p)
    const temRecurso = (f?: string) => !f || !features || features.includes(f)

    const linhas: Linha[] = []
    for (const item of MENU) {
      const negadas: string[] = []
      const erro5xx: string[] = []
      const ouvir = (r: import('@playwright/test').Response) => {
        const p = new URL(r.url()).pathname
        if (!p.includes('/api/')) return
        if ([401, 402, 403].includes(r.status())) negadas.push(`${r.status()} ${r.request().method()} ${p.replace(/^\/novo/, '')}`)
        if (r.status() >= 500) erro5xx.push(`${r.status()} ${r.request().method()} ${p.replace(/^\/novo/, '')}`)
      }
      page.on('response', ouvir)
      await page.goto(item.to.replace(/^\//, ''))
      await page.waitForLoadState('networkidle', { timeout: 45_000 }).catch(() => {})
      await page.waitForTimeout(400)
      page.off('response', ouvir)
      const telaSemAcesso = await page.getByText(/sem acesso|não tem permissão|sem permissão/i).first().isVisible().catch(() => false)
      linhas.push({ to: item.to, label: item.label, visivel: pode(item.perm) && temRecurso(item.feature), negadas: [...new Set(negadas)], telaSemAcesso, erro5xx: [...new Set(erro5xx)] })
    }

    const dir = path.join(__dirname, '..', 'resultados', 'produto')
    mkdirSync(dir, { recursive: true })
    writeFileSync(path.join(dir, `menu-${perfil}.json`), JSON.stringify({ perfil, email: PERFIS[perfil].email, features: features ?? null, linhas }, null, 2))
    await info.attach(`menu-${perfil}.json`, { body: JSON.stringify(linhas, null, 2), contentType: 'application/json' })

    // A medição é o produto; o teste só reprova o que é inequívoco: 5xx.
    expect(linhas.flatMap((l) => l.erro5xx), 'tela do menu com resposta 5xx').toEqual([])
  })
}
