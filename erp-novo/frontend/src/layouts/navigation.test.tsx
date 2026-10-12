import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { describe, it, expect, beforeEach } from 'vitest'
import { ModuleFinder, RouteTrail } from './ModuleFinder'
import { prefixosDeOutroItem, NAV_ITENS, secoesDoMenu, SECAO_FIXA } from './AppShell'

beforeEach(() => localStorage.clear())
const items = [{ label: 'Pedidos', to: '/pedidos', group: 'Operações' }, { label: 'Clientes', to: '/clientes', group: 'Cadastros' }]

describe('Indicador de item ativo', () => {
  it('marca como exato todo item cujo caminho é prefixo de outro do menu', () => {
    // O bug: `/clientes/revisoes` começa com `/clientes`, então sem `end` os
    // DOIS itens acendiam ao mesmo tempo — visto em produção na sidebar.
    const exatos = prefixosDeOutroItem([
      { to: '/clientes' }, { to: '/clientes/revisoes' }, { to: '/pedidos' },
    ])
    expect(exatos.has('/clientes')).toBe(true)
    expect(exatos.has('/clientes/revisoes')).toBe(false)
    expect(exatos.has('/pedidos')).toBe(false)
  })

  it('protege o menu real: nenhum par prefixo/filho fica sem marcação exata', () => {
    const exatos = prefixosDeOutroItem(NAV_ITENS)
    const pares = NAV_ITENS.filter((a) => NAV_ITENS.some((b) => b.to !== a.to && b.to.startsWith(a.to + '/')))
    // Guardião precisa provar que varreu algo, não só que passou.
    expect(NAV_ITENS.length).toBeGreaterThan(20)
    for (const p of pares) expect(exatos.has(p.to)).toBe(true)
  })

  it('cada item do menu aponta para um caminho único', () => {
    const vistos = NAV_ITENS.map((i) => i.to)
    expect(new Set(vistos).size).toBe(vistos.length)
  })
})

/**
 * O menu foi reorganizado por tarefa (11/10/2026) com a regra de NÃO eliminar
 * nada. Esta é a lista de antes — destino, permissão e recurso contratado. Se
 * uma reorganização futura sumir com um item ou trocar a permissão dele, o
 * teste reprova: rótulo e grupo podem mudar, o acesso não.
 */
const MENU_PRESERVADO = [
  '/ - -', '/acessos usuario.view -', '/alcadas venda.alcada -', '/alertas alerta.view -', '/auditoria auditoria.view -',
  '/bens bem.view -', '/cartoes cartao.view -', '/central logistica.view -', '/central-vendas venda.view -',
  '/checklists checklist.view -', '/clientes cliente.view -', '/clientes/revisoes cliente.edit -', '/colaboradores colaborador.view -',
  '/comodatos comodato.view -', '/configuracoes grupo.view -', '/convenios convenio.view -', '/cupons-fiscais cupomfiscal.view -',
  '/documentos documento.view -', '/empresas empresa.view -', '/estoque estoque.view -', '/financeiro financeiro.view -', '/fiscal fiscal.view -',
  '/gas-do-povo gasdopovo.view -', '/geografico cliente.view -', '/mcmm mcmm.view -', '/metas meta.view -', '/missoes missao.view -',
  '/monitora monitora.view monitora', '/pedidos pedido.view -', '/pos-venda posvenda.view -', '/produtos produto.view -',
  '/promocoes promocao.view crm', '/relatorios relatorio.view relatorios_avancados', '/sorteios sorteio.view crm',
  '/vale-gas valegas.view -', '/veiculos veiculo.view frota',
]

describe('Menu por tarefa', () => {
  it('nenhum item sumiu nem trocou de permissão ao reorganizar', () => {
    const atual = NAV_ITENS.map((i) => `${i.to} ${i.permission ?? '-'} ${i.feature ?? '-'}`).sort()
    expect(atual).toEqual([...MENU_PRESERVADO].sort())
  })

  it('leigo acha pelo que conhece, não pelo nome interno', async () => {
    const user = userEvent.setup()
    const itens = [
      { label: 'Financeiro', to: '/financeiro', group: 'Dinheiro', dica: 'Contas a pagar e receber, caixa e boletos', palavras: 'boleto pix baixa' },
      { label: 'Central de entregas', to: '/central', group: 'Entregas', dica: 'Fila de entregas', palavras: 'logistica entregador motoboy' },
    ]
    render(<MemoryRouter><ModuleFinder items={itens} userKey="erp.1" onNavigate={() => {}} /></MemoryRouter>)
    const busca = screen.getByRole('textbox', { name: 'Buscar módulos' })

    await user.type(busca, 'boleto')
    expect(screen.getByRole('link', { name: /Financeiro/ })).toBeVisible()
    expect(screen.queryByRole('link', { name: /Central de entregas/ })).not.toBeInTheDocument()

    // Palavras fora de ordem e sem acento: "pagar conta" acha "Contas a pagar".
    await user.clear(busca)
    await user.type(busca, 'pagar conta')
    expect(screen.getByRole('link', { name: /Financeiro/ })).toBeVisible()

    await user.clear(busca)
    await user.type(busca, 'logística')
    expect(screen.getByRole('link', { name: /Central de entregas/ })).toBeVisible()
  })
})

describe('Seções do menu', () => {
  const todos = NAV_ITENS

  it('o dia a dia vem primeiro, aberto e na ordem de uso', () => {
    const [primeira, ...resto] = secoesDoMenu(todos, '/', {})
    expect(primeira.grupo).toBe(SECAO_FIXA)
    expect(primeira.colapsado).toBe(false)
    expect(primeira.itens.map((i) => i.to)).toEqual(['/', '/pedidos', '/central', '/clientes', '/financeiro', '/estoque', '/alertas'])
    // O resto começa recolhido: é o que deixa o menu curto.
    expect(resto.length).toBeGreaterThan(5)
    expect(resto.every((s) => s.colapsado)).toBe(true)
  })

  it('a seção da página aberta fica aberta, mesmo que a pessoa a tenha recolhido', () => {
    const secoes = secoesDoMenu(todos, '/sorteios/12', { Relacionamento: true })
    expect(secoes.find((s) => s.grupo === 'Relacionamento')?.colapsado).toBe(false)
    expect(secoes.find((s) => s.grupo === 'Administração')?.colapsado).toBe(true)
  })

  it('lembra a escolha de abrir uma seção', () => {
    expect(secoesDoMenu(todos, '/', { Administração: false }).find((s) => s.grupo === 'Administração')?.colapsado).toBe(false)
  })

  it('com poucos itens nada recolhe — o entregador não perde o pouco que tem', () => {
    const poucos = todos.filter((i) => ['/', '/central', '/comodatos', '/missoes'].includes(i.to))
    expect(secoesDoMenu(poucos, '/', {}).every((s) => !s.colapsado)).toBe(true)
  })

  it('nenhum item se perde ao montar as seções', () => {
    const montados = secoesDoMenu(todos, '/', {}).flatMap((s) => s.itens.map((i) => i.to)).sort()
    expect(montados).toEqual(todos.map((i) => i.to).sort())
  })
})

describe('Navegação orientada a tarefas', () => {
  it('busca sem acento e persiste favoritos somente para a pessoa atual', async () => {
    const user = userEvent.setup()
    const view = render(<MemoryRouter><ModuleFinder items={items} userKey="erp.1" onNavigate={() => {}} /></MemoryRouter>)
    await user.type(screen.getByRole('textbox', { name: 'Buscar módulos' }), 'operacoes')
    expect(screen.getByRole('link', { name: 'Pedidos' })).toBeVisible()
    await user.click(screen.getByRole('button', { name: 'Adicionar Pedidos aos favoritos' }))
    await user.clear(screen.getByRole('textbox', { name: 'Buscar módulos' }))
    expect(screen.getByText('Favoritos')).toBeVisible()
    view.rerender(<MemoryRouter><ModuleFinder items={items} userKey="erp.2" onNavigate={() => {}} /></MemoryRouter>)
    expect(screen.queryByRole('link', { name: 'Pedidos' })).not.toBeInTheDocument()
  })

  it('não mostra favorito cuja permissão foi removida', () => {
    localStorage.setItem('erpnovo.ui.favorites.erp.1', JSON.stringify(['/financeiro']))
    render(<MemoryRouter><ModuleFinder items={items} userKey="erp.1" onNavigate={() => {}} /></MemoryRouter>)
    expect(screen.queryByRole('link')).not.toBeInTheDocument()
  })

  it('a trilha não mostra a seção que é só atalho', () => {
    const itens = [{ label: 'Financeiro', to: '/financeiro', group: SECAO_FIXA }, { label: 'Cartões', to: '/cartoes', group: 'Dinheiro' }]
    const { unmount } = render(<MemoryRouter initialEntries={['/financeiro']}><RouteTrail items={itens} semGrupo={[SECAO_FIXA]} /></MemoryRouter>)
    expect(screen.getByRole('navigation', { name: 'Você está em' })).toHaveTextContent(/^Início\/Financeiro$/)
    unmount()
    render(<MemoryRouter initialEntries={['/cartoes']}><RouteTrail items={itens} semGrupo={[SECAO_FIXA]} /></MemoryRouter>)
    expect(screen.getByRole('navigation', { name: 'Você está em' })).toHaveTextContent(/^Início\/Dinheiro\/Cartões$/)
  })

  it('mostra caminho de retorno ao cadastro ao abrir detalhe', () => {
    render(<MemoryRouter initialEntries={['/clientes/42']}><RouteTrail items={items} /></MemoryRouter>)
    expect(screen.getByRole('link', { name: 'Clientes' })).toHaveAttribute('href', '/clientes')
    expect(screen.getByText('Detalhes')).toHaveAttribute('aria-current', 'page')
  })
})
