import { useTheme } from '@/lib/theme'
import { ResponsiveSidebar } from '@/layouts/ResponsiveSidebar'
import { ModuleFinder, RouteTrail } from '@/layouts/ModuleFinder'
import { usePreference } from '@/lib/usePreference'
import { useState, type ReactNode } from 'react'
import { NavLink, useNavigate } from 'react-router-dom'
import {
  LayoutDashboard, Users, MapPin, LogOut, Menu as MenuIcon,
  Moon, Sun, ChevronLeft, ChevronDown, Building2, Package, Wallet, Warehouse, FileText,
  UserCog, Truck, Flame, FileBarChart, ShoppingCart, Bike, Compass,
  MessageSquareHeart, Tag, Gift, Target, ListChecks, BadgePercent,
  Receipt, ArrowLeftRight, FolderArchive, Building,
  CreditCard, HandCoins, PackageCheck, Handshake, Navigation, Settings, ShieldCheck, History, Merge,
  ShieldAlert,
} from 'lucide-react'
import { useAuth } from '@/lib/auth'
import { cn } from '@/lib/cn'
import { EmpresaSwitcher } from './EmpresaSwitcher'
import { BotaoNovo } from './BotaoNovo'
import { useNomeDoTenant, useTituloDoTenant } from '@/lib/titulo'
import {
  DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem,
  DropdownMenuLabel, DropdownMenuSeparator, Tooltip, Button,
} from '@/components/ui'

interface NavItem {
  label: string
  to: string
  icon: ReactNode
  permission?: string
  /**
   * Recurso CONTRATADO exigido (F2-03). Distinto de `permission`: aquela diz o
   * que o usuario pode, esta diz o que a empresa comprou. Sem o filtro, o menu
   * mostraria o modulo e o clique voltaria 402.
   */
  feature?: string
  group: string
  /** Uma linha dizendo para que serve — na busca e ao passar o mouse. */
  dica?: string
  /** Sinônimos que um leigo digitaria na busca. */
  palavras?: string
}

// Navegação DECLARATIVA (sem menu-no-banco), organizada por TAREFA.
//
// Antes os grupos eram a estrutura interna do sistema ("Operações", "Gestão",
// "CRM") e vários rótulos eram jargão ("MCMM", "Geográfico", "Alçadas"): quem
// não conhecia o sistema não sabia onde procurar. Agora o grupo responde "o
// que eu quero fazer?" e o rótulo diz o que a tela faz. Destino, permissão e
// recurso contratado de cada item NÃO mudaram — o teste de navegação confere.
// `dica` aparece na busca e ao passar o mouse; `palavras` são os sinônimos que
// um leigo digitaria ("boleto", "entregador", "nota").
const NAV: NavItem[] = [
  { label: 'Início', to: '/', icon: <LayoutDashboard size={18} />, group: 'Início', dica: 'O que pede atenção hoje', palavras: 'dashboard painel hoje resumo' },
  { label: 'Avisos e pendências', to: '/alertas', icon: <ShieldAlert size={18} />, permission: 'alerta.view', group: 'Início', dica: 'Problemas que a equipe precisa resolver', palavras: 'alertas central problemas' },
  { label: 'Relatórios', to: '/relatorios', icon: <FileBarChart size={18} />, permission: 'relatorio.view', feature: 'relatorios_avancados', group: 'Início', dica: 'Vendas, financeiro e DRE por período', palavras: 'dre faturamento graficos' },

  { label: 'Pedidos', to: '/pedidos', icon: <ShoppingCart size={18} />, permission: 'pedido.view', group: 'Vendas e clientes', dica: 'Lançar e acompanhar cada venda', palavras: 'venda vender kanban balcao telefone' },
  { label: 'Clientes', to: '/clientes', icon: <Users size={18} />, permission: 'cliente.view', group: 'Vendas e clientes', dica: 'Cadastro, endereço e histórico de compras', palavras: 'cadastro consumidor fornecedor' },
  { label: 'Clientes duplicados', to: '/clientes/revisoes', icon: <Merge size={18} />, permission: 'cliente.edit', group: 'Vendas e clientes', dica: 'Cadastros parecidos para juntar ou descartar', palavras: 'revisar revisao duplicidade cadastros a revisar' },
  { label: 'Central de Vendas', to: '/central-vendas', icon: <BadgePercent size={18} />, permission: 'venda.view', group: 'Vendas e clientes', dica: 'Pedidos do campo, pós-venda e missões do atendente', palavras: 'atendente solicitacoes campo' },
  { label: 'Limites de desconto', to: '/alcadas', icon: <Tag size={18} />, permission: 'venda.alcada', group: 'Vendas e clientes', dica: 'Quanto cada perfil pode dar de desconto', palavras: 'alcadas alcada aprovacao desconto' },

  { label: 'Central de entregas', to: '/central', icon: <Bike size={18} />, permission: 'logistica.view', group: 'Entregas', dica: 'Fila de entregas e quem leva cada pedido', palavras: 'logistica entregador rota motoboy distribuicao' },
  { label: 'Comodatos', to: '/comodatos', icon: <PackageCheck size={18} />, permission: 'comodato.view', group: 'Entregas', dica: 'Vasilhames emprestados aos clientes', palavras: 'vasilhame emprestimo botijao contrato' },
  { label: 'Missões de campo', to: '/missoes', icon: <Compass size={18} />, permission: 'missao.view', group: 'Entregas', dica: 'Panfletagem e prospecção na hora ociosa', palavras: 'missoes prospeccao panfleto' },
  { label: 'Rastreamento (GPS)', to: '/monitora', icon: <Navigation size={18} />, permission: 'monitora.view', feature: 'monitora', group: 'Entregas', dica: 'Veículos no mapa, rotas e cercas', palavras: 'monitora gps mapa rastreador frota' },
  { label: 'Endereços (cidades e ruas)', to: '/geografico', icon: <MapPin size={18} />, permission: 'cliente.view', group: 'Entregas', dica: 'Cidades, bairros, ruas e regiões de entrega', palavras: 'geografico bairro rua cidade regiao cep' },

  { label: 'Produtos', to: '/produtos', icon: <Package size={18} />, permission: 'produto.view', group: 'Produtos e estoque', dica: 'Cadastro e preços', palavras: 'botijao gas agua preco catalogo' },
  { label: 'Estoque', to: '/estoque', icon: <Warehouse size={18} />, permission: 'estoque.view', group: 'Produtos e estoque', dica: 'Saldos, entradas, saídas e inventário', palavras: 'saldo inventario deposito armazem' },
  { label: 'Movimentação de material', to: '/mcmm', icon: <ArrowLeftRight size={18} />, permission: 'mcmm.view', group: 'Produtos e estoque', dica: 'Controle de movimentação de material (MCMM)', palavras: 'mcmm material' },
  { label: 'Patrimônio (bens)', to: '/bens', icon: <Building size={18} />, permission: 'bem.view', group: 'Produtos e estoque', dica: 'Bens da empresa e depreciação', palavras: 'bens imobilizado ativo depreciacao' },

  { label: 'Financeiro', to: '/financeiro', icon: <Wallet size={18} />, permission: 'financeiro.view', group: 'Dinheiro', dica: 'Contas a pagar e receber, caixa e boletos', palavras: 'contas pagar receber caixa boleto pix baixa' },
  { label: 'Cartões', to: '/cartoes', icon: <CreditCard size={18} />, permission: 'cartao.view', group: 'Dinheiro', dica: 'Vendas no cartão e conciliação', palavras: 'credito debito maquininha' },
  { label: 'Vale-Gás', to: '/vale-gas', icon: <Flame size={18} />, permission: 'valegas.view', group: 'Dinheiro', dica: 'Consultar, imprimir e dar baixa em vales', palavras: 'vale gas de bolso' },
  { label: 'Gás do Povo', to: '/gas-do-povo', icon: <HandCoins size={18} />, permission: 'gasdopovo.view', group: 'Dinheiro', dica: 'Benefícios do programa', palavras: 'beneficio voucher programa social' },
  { label: 'Convênios', to: '/convenios', icon: <Handshake size={18} />, permission: 'convenio.view', group: 'Dinheiro', dica: 'Faturamento mensal de empresas conveniadas', palavras: 'fechamento empresa conveniada' },

  { label: 'Notas fiscais', to: '/fiscal', icon: <FileText size={18} />, permission: 'fiscal.view', group: 'Notas fiscais', dica: 'NF-e, NFC-e e SPED', palavras: 'fiscal nota nfe nfce sped imposto' },
  { label: 'Cupons fiscais (SAT)', to: '/cupons-fiscais', icon: <Receipt size={18} />, permission: 'cupomfiscal.view', group: 'Notas fiscais', dica: 'Emitir e consultar cupom fiscal', palavras: 'sat cfe cupom' },

  { label: 'Pós-venda', to: '/pos-venda', icon: <MessageSquareHeart size={18} />, permission: 'posvenda.view', group: 'Relacionamento', dica: 'Ligar para saber se o cliente ficou satisfeito', palavras: 'satisfacao crm contato' },
  { label: 'Promoções', to: '/promocoes', icon: <Tag size={18} />, permission: 'promocao.view', feature: 'crm', group: 'Relacionamento', dica: 'Campanhas de desconto', palavras: 'crm campanha' },
  { label: 'Sorteios', to: '/sorteios', icon: <Gift size={18} />, permission: 'sorteio.view', feature: 'crm', group: 'Relacionamento', dica: 'Campanhas com prêmio e o palco do sorteio', palavras: 'crm premio' },
  { label: 'Metas de venda', to: '/metas', icon: <Target size={18} />, permission: 'meta.view', group: 'Relacionamento', dica: 'Meta de cada vendedor no mês', palavras: 'metas objetivo vendedor' },

  { label: 'Colaboradores', to: '/colaboradores', icon: <UserCog size={18} />, permission: 'colaborador.view', group: 'Equipe e frota', dica: 'Funcionários, cargos e comissões', palavras: 'rh funcionario entregador vendedor' },
  { label: 'Veículos', to: '/veiculos', icon: <Truck size={18} />, permission: 'veiculo.view', feature: 'frota', group: 'Equipe e frota', dica: 'Frota, abastecimento e manutenção', palavras: 'frota caminhao moto abastecimento oleo pneu' },
  { label: 'Checklists', to: '/checklists', icon: <ListChecks size={18} />, permission: 'checklist.view', group: 'Equipe e frota', dica: 'Roteiros de conferência (abertura, veículo…)', palavras: 'conferencia verificacao' },
  { label: 'Documentos', to: '/documentos', icon: <FolderArchive size={18} />, permission: 'documento.view', group: 'Equipe e frota', dica: 'Alvarás, licenças e contratos com vencimento', palavras: 'alvara licenca contrato vencimento' },

  { label: 'Empresas', to: '/empresas', icon: <Building2 size={18} />, permission: 'empresa.view', group: 'Administração', dica: 'Dados das empresas e filiais', palavras: 'filial cnpj' },
  { label: 'Usuários e permissões', to: '/acessos', icon: <ShieldCheck size={18} />, permission: 'usuario.view', group: 'Administração', dica: 'Quem entra no sistema e o que pode fazer', palavras: 'acessos usuario senha papel perfil permissao' },
  { label: 'Histórico de ações', to: '/auditoria', icon: <History size={18} />, permission: 'auditoria.view', group: 'Administração', dica: 'Quem mudou o quê, e quando', palavras: 'auditoria log trilha' },
  { label: 'Configurações', to: '/configuracoes', icon: <Settings size={18} />, permission: 'grupo.view', group: 'Administração', dica: 'Ajustes do sistema e cadastros de apoio', palavras: 'parametros preferencias' },
]

// Ordem fixa das seções: o dia a dia primeiro, a administração por último.
const ORDEM_GRUPOS = ['Início', 'Vendas e clientes', 'Entregas', 'Produtos e estoque', 'Dinheiro', 'Notas fiscais', 'Relacionamento', 'Equipe e frota', 'Administração']

/**
 * Itens cujo caminho é prefixo do caminho de OUTRO item do menu — para eles o
 * NavLink precisa de `end`, senão os dois acendem juntos. Derivado do NAV em
 * vez de escrito à mão: item novo entra e a regra continua valendo sozinha.
 */
export function prefixosDeOutroItem(itens: { to: string }[]): Set<string> {
  return new Set(itens.filter((a) => itens.some((b) => b !== a && b.to.startsWith(a.to + '/'))).map((a) => a.to))
}

const PREFIXO_DE_OUTRO = prefixosDeOutroItem(NAV)

/** Exportado para o teste conferir a regra contra o menu REAL, não uma cópia. */
export const NAV_ITENS = NAV.map((i) => ({ to: i.to, label: i.label, group: i.group, permission: i.permission, feature: i.feature }))

export function AppShell({ children }: { children: ReactNode }) {
  const { user, logout, can, hasFeature } = useAuth()

  // F9-06 — a aba do navegador mostra o nome de quem esta logado, nao o da
  // primeira revenda. Fica no shell porque e o unico ponto por onde toda tela
  // autenticada passa.
  useTituloDoTenant()
  // O menu mostra a empresa de quem está logado. Antes era "Dubena" fixo no
  // código — a primeira revenda servida a todas, como o título da aba já fora.
  const nomeEmpresa = useNomeDoTenant()

  const navigate = useNavigate()
  // `open` = sidebar recolhida/expandida no DESKTOP (md+).
  const userKey = `erp.${user?.id ?? 'anon'}`
  const [open, setOpen] = usePreference(`erpnovo.ui.sidebar.${userKey}`, true)
  // `mobileOpen` = drawer da sidebar ABERTO no MOBILE (overlay).
  const [mobileOpen, setMobileOpen] = useState(false)
  const [scopeRevision, setScopeRevision] = useState(0)
  const { dark, toggleDark } = useTheme()
  const [compact, setCompact] = usePreference(`erpnovo.ui.compact.${userKey}`, false)



  // No mobile a sidebar é overlay e está sempre "expandida" (mostra rótulos).
  const expandida = open || mobileOpen

  const visiveis = NAV.filter(
    (i) => (!i.permission || can(i.permission)) && (!i.feature || hasFeature(i.feature)),
  )
  const presentes = Array.from(new Set(visiveis.map((i) => i.group)))
  const grupos = [
    ...ORDEM_GRUPOS.filter((g) => presentes.includes(g)),
    ...presentes.filter((g) => !ORDEM_GRUPOS.includes(g)),
  ]
  // Seções recolhidas (colapsadas) — por padrão todas abertas.
  const [recolhidos, setRecolhidos] = usePreference<Record<string, boolean>>(`erpnovo.ui.groups.${userKey}`, {})
  const toggleGrupo = (g: string) => setRecolhidos((r) => ({ ...r, [g]: !r[g] }))
  const iniciais = (user?.name ?? '?').split(' ').map((s) => s[0]).slice(0, 2).join('').toUpperCase()

  return (
    <div data-density={compact ? "compact" : "comfortable"} className="flex h-full">
      <a href="#conteudo-principal" className="sr-only focus:not-sr-only focus:absolute focus:z-[100] focus:rounded focus:bg-card focus:p-3">Ir para o conteúdo</a>
      <ResponsiveSidebar open={mobileOpen} onOpenChange={setMobileOpen} expanded={open}>
        <div className={cn('flex h-16 shrink-0 items-center gap-2.5', expandida ? 'px-4' : 'justify-center px-0')}>
          <div aria-hidden className="grid size-9 shrink-0 place-items-center rounded-lg bg-sidebar-accent font-black text-white shadow-md shadow-black/30">{(nomeEmpresa ?? 'E').trim().charAt(0).toUpperCase()}</div>
          {expandida && <span className="truncate text-[17px] font-bold tracking-wide text-white" title={nomeEmpresa ?? undefined}>{nomeEmpresa ?? 'ERP'}</span>}
        </div>
        {expandida && <ModuleFinder items={visiveis} userKey={userKey} onNavigate={() => setMobileOpen(false)} />}
        {/* Ritmo vertical: o respiro que separa GRUPOS (mt-6) é maior que o
            que separa ITENS do mesmo grupo (gap-0.5). É essa diferença — não
            uma linha divisória — que agrupa as coisas aos olhos. */}
        <nav aria-label="Módulos" className={cn('flex-1 overflow-y-auto pb-4', expandida ? 'px-2 pt-2' : 'px-2 pt-3')}>
          {grupos.map((g, gi) => {
            const colapsado = expandida && recolhidos[g]
            return (
            <div key={g} className={cn(gi > 0 && (expandida ? 'mt-6' : 'mt-4 border-t border-white/10 pt-4'))}>
              {expandida && (
                <button
                  type="button"
                  onClick={() => toggleGrupo(g)}
                  aria-expanded={!colapsado}
                  // O título de seção recua em peso e cor para o item de menu
                  // poder liderar: 10px, tracking largo, cor esmaecida. Antes
                  // os dois brigavam pelo mesmo destaque.
                  className="group/sec mb-1 flex w-full items-center gap-1.5 rounded px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-sidebar-foreground/60 transition-colors hover:text-sidebar-foreground"
                >
                  <ChevronDown size={12} className={cn('shrink-0 transition-transform duration-200', colapsado && '-rotate-90')} />
                  <span className="truncate">{g}</span>
                </button>
              )}
              <div className={cn('flex flex-col', expandida ? 'gap-0.5' : 'gap-1')}>
              {!colapsado && visiveis.filter((i) => i.group === g).map((i) => {
                const link = (
                  <NavLink
                    key={i.to}
                    to={i.to}
                    title={expandida ? i.dica : undefined}
                    // `end` em todo item cujo caminho é prefixo de outro do
                    // menu: sem isso, `/clientes/revisoes` acendia o indicador
                    // de "Clientes" TAMBÉM, e dois itens apareciam ativos ao
                    // mesmo tempo. Só quem tem subrota própria (abas internas,
                    // detalhe de registro) casa por prefixo.
                    end={i.to === '/' || PREFIXO_DE_OUTRO.has(i.to)}
                    onClick={() => setMobileOpen(false)}
                    className={({ isActive }) =>
                      cn(
                        // O ativo era um bloco laranja sólido, que competia com
                        // os botões de ação pela mesma cor. Vira barra lime +
                        // texto branco: 14,26:1 e 16,56:1 sobre a sidebar,
                        // contra 5,51:1 de antes — e devolve o laranja à ação.
                        'group relative flex h-9 items-center rounded-md text-[13px] outline-none transition-colors',
                        'focus-visible:ring-2 focus-visible:ring-destaque focus-visible:ring-offset-2 focus-visible:ring-offset-sidebar',
                        expandida ? 'gap-2.5 px-3' : 'justify-center px-0',
                        isActive
                          ? 'bg-white/[0.11] font-semibold text-white'
                          : 'text-sidebar-foreground hover:bg-white/[0.055] hover:text-white',
                      )
                    }
                  >
                    {({ isActive }) => (
                      <>
                        {/* A barra lime fica FORA do fluxo e ancorada na borda
                            da sidebar; como ícone e rótulo não se deslocam
                            entre ativo e inativo, a lista não "pula". */}
                        {isActive && (
                          <span aria-hidden className="absolute -left-2 top-1/2 h-5 w-[3px] -translate-y-1/2 rounded-r-full bg-destaque" />
                        )}
                        {/* Caixa fixa do ícone: todos ocupam a mesma largura,
                            então os rótulos alinham numa coluna só. */}
                        <span className={cn('grid size-[18px] shrink-0 place-items-center transition-colors', isActive ? 'text-destaque' : 'text-current')}>
                          {i.icon}
                        </span>
                        {expandida && <span className="truncate">{i.label}</span>}
                      </>
                    )}
                  </NavLink>
                )
                // Tooltip só faz sentido no modo recolhido do desktop.
                return expandida ? link : <Tooltip key={i.to} label={i.dica ? `${i.label} — ${i.dica}` : i.label}>{link}</Tooltip>
              })}
              </div>
            </div>
            )
          })}
        </nav>
      </ResponsiveSidebar>

      {/* Conteúdo */}
      <div className="flex-1 flex flex-col min-w-0">
        {/* Header */}
        <header className="h-16 shrink-0 bg-card border-b border-border flex items-center justify-between px-3 sm:px-4">
          <div className="flex items-center gap-2 sm:gap-3 min-w-0">
            {/* Mobile: abre/fecha o drawer. Desktop: recolhe/expande a sidebar. */}
            <Button
              variant="ghost"
              size="icon"
              className="md:hidden"
              onClick={() => setMobileOpen((v) => !v)}
              id="abrir-menu" aria-expanded={mobileOpen} aria-label="Abrir menu"
            >
              <MenuIcon size={18} />
            </Button>
            <Button
              variant="ghost"
              size="icon"
              className="hidden md:inline-flex"
              onClick={() => setOpen((v) => !v)}
              aria-label="Recolher menu"
            >
              {open ? <ChevronLeft size={18} /> : <MenuIcon size={18} />}
            </Button>
            <EmpresaSwitcher onScopeChange={() => setScopeRevision((value) => value + 1)} />
          </div>
          <div className="flex items-center gap-2">
            <BotaoNovo />
            <Button variant="ghost" size="icon" onClick={toggleDark} aria-label={dark ? "Ativar tema claro" : "Ativar tema escuro"}>
              {dark ? <Sun size={18} /> : <Moon size={18} />}
            </Button>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <button className="flex items-center gap-2.5 rounded-md px-2 py-1.5 hover:bg-secondary transition-colors">
                  <div className="grid size-9 place-items-center rounded-full bg-primary text-primary-foreground text-sm font-semibold">{iniciais}</div>
                  <div className="text-right leading-tight hidden sm:block">
                    <p className="text-sm font-medium">{user?.name}</p>
                    <p className="text-xs text-muted-foreground">{user?.roles.join(', ') || (user?.is_support ? 'Suporte' : '')}</p>
                  </div>
                </button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel>{user?.email}</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem onClick={() => setCompact((value) => !value)}>{compact ? 'Usar tabelas confortáveis' : 'Usar tabelas compactas'}</DropdownMenuItem>
                <DropdownMenuItem onClick={() => navigate('/seguranca')}>
                  <ShieldCheck /> Segurança
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem destructive onClick={async () => { await logout(); navigate('/login') }}>
                  <LogOut /> Sair
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </header>

        <main id="conteudo-principal" tabIndex={-1} className="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8"><RouteTrail items={visiveis} /><div key={`${user?.id}:${user?.empresa_id}:${scopeRevision}`}>{children}</div></main>
      </div>
    </div>
  )
}
