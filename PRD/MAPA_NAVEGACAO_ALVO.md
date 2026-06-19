# MAPA DE NAVEGAÇÃO-ALVO + DE-PARA (legado → SPA React)

> Define COMO o sistema será REORGANIZADO. Princípio: **reorganizar ≠ eliminar** — toda
> função do legado tem destino no app novo (página + aba/seção). Objetivo: páginas mais
> completas, melhor visão das informações/operações, UX intuitiva. Auditado da árvore real
> de `menus` (5 raízes, 217 nós) + rotas + controllers.

## DIAGNÓSTICO (o que está desorganizado no legado)
- 5 menus-raiz (Cadastros/Operações/Financeiros/Ferramentas/Relatórios) com 3 níveis de
  submenu → o usuário se perde; muitas telas isoladas que pertencem ao mesmo contexto.
- Ex. de dispersão: "Tipos de Telefones", "Tipos de Contatos", "Segmentos", "Tipos de Pessoas"
  são TELAS separadas, mas são configurações do contexto CLIENTE. CSTs (ICMS/IPI/PIS/COFINS),
  grupo fiscal, situação NF, IBPT estão soltos em "Administração" mas formam a MALHA FISCAL.
  "Contas a Receber/Pagar", "Lançamento de Receita/Despesa", "Caixas", "Cheques", "Boletos",
  "PIX", "Conciliação" são 12+ telas do mesmo domínio FINANCEIRO.

## NAVEGAÇÃO-ALVO (sidebar do SPA — grupos e PÁGINAS)
> Cada PÁGINA é completa (lista + ficha em abas + sub-recursos + ações). "Config" agrupa os
> cadastros de apoio antes dispersos.

### Grupo CADASTROS
- **Clientes** (página completa) ← cliente.index + telas de apoio como ABAS/CONFIG:
  Segmentos, Tipos de Pessoas, Tipos de Telefones, Tipos de Contatos, Situações de Contatos,
  Promoções. (Hoje: 7 telas separadas → 1 página + tela de "Configurações de Cliente".)
- **Produtos** ← produto.index + Classes de Produtos + Unidades de Medida + Atualização de
  Preços (como AÇÃO na lista) — antes 4 telas.
- **Geográfico** ← Cidades/Bairros/Ruas/Regiões numa página com abas.
- **Fornecedores** (mesma base de Clientes, filtro fornecedor) — visão dedicada.

### Grupo VENDAS / OPERAÇÕES
- **Pedidos** (painel + ficha) ← pedido.index, monitoramento, venda ativa (aba), promover.
- **Vale-Gás** ← Vale Gás (venda/consulta/baixar/cancelar) numa página com abas de status.
- **Pós-Venda / Checklists** ← posvenda + checklist + cadastros de apoio.
- **Sorteios / MCMM** ← satélites.

### Grupo ESTOQUE
- **Estoque** ← Estoques (saldos/requisição/transferência/acerto/inventário/fechamento) — abas.

### Grupo FINANCEIRO (consolidar as 12+ telas)
- **Lançamentos** ← Contas a Receber + Contas a Pagar + Lançamento de Receita/Despesa
  (uma página com filtros, não 4 telas).
- **Caixa/Tesouraria** ← Caixas + Fechamento de Malotes.
- **Cheques** ← Cheques Emitidos + Recebidos (abas).
- **Boletos / PIX** ← Boletos + Baixa do PIX + remessas/retornos.
- **Conciliação** ← Conciliação Contábil + Importação Extrato + Importação Cartão.
- **Plano/Centro de Contas** (config) + **Fechamento Mensal Gerencial**.

### Grupo FISCAL
- **NF-e / NFC-e** (emissão/status) ← Gerais>NFe, nfemitida, nfrecebida.
- **Malha Fiscal** (config unificada) ← Grupo Fiscal + CST ICMS/IPI/PIS/COFINS + Situação NF +
  IBPT + Operações fiscais. (Hoje ~8 telas dispersas em "Administração" → 1 página com abas.)
- **SPED** ← Spedfiscal + Spedcontribuição + créditos.

### Grupo RH
- **Colaboradores** (ficha) ← Colaboradores + Cargos + comissões + recessos + família/exames (abas).

### Grupo FROTA
- **Veículos** (ficha por veículo) ← Veículos + Gestão de Veículos (abastecimento/óleo/pneu/
  entrada-saída/documento) — abas.

### Grupo RELATÓRIOS
- **Central de Relatórios** ← todos os report.* (Administrativo/Gestão/Financeiros/Operacionais/
  Vendas + Vale Gás/Checklists/Dashboard) numa área com filtros e export.

### Grupo ADMINISTRAÇÃO
- **Usuários & Papéis** ← Usuários + Tipo de Usuário + Definir Papéis (RBAC).
- **Empresas** ← Empresas + Grupos de Empresas + Configurações da Empresa + Senha Mestra (abas).
- **Configurações Gerais / Integrações** ← Configurações Gerais, Androids, App Gás em Casa,
  Layout de Cobranças, Ocorrências de Remessas.

## DE-PARA (garantia: NADA some) — formato
> Cada IMPL_<modulo>.md DEVE conter a tabela de-para das telas legadas que consolida, ex.:

| Tela legada (menu / rota) | Destino no app novo |
|---|---|
| cliente.index | Página Clientes (lista + ficha) |
| segmento.index | Clientes → Configurações → aba Segmentos |
| telefonetipo.index | Clientes → Configurações → aba Tipos de Telefone |
| clientecontatotipo/situacao.index | Clientes → Configurações → abas Tipos/Situações de Contato |
| promocao.index | Clientes → aba/seção Promoções |
| produto.index / produtoclasse / unidademedida / atualizarprecos | Página Produtos (+ Config + ação) |
| nficms/nfipi/nfpis/nfcofins/nfsituacao/ibpt/grupofiscal | Página Malha Fiscal (abas) |
| contasreceber/contaspagar/financeiro.create* | Página Lançamentos (filtros) |
| ... (completar por módulo ao auditar cada IMPL) | ... |

## REGRA PARA OS IMPL_<modulo>.md
Cada PRD de implementação ganha a seção **"Reorganização/UX"**:
1. Quais telas legadas ele CONSOLIDA (de-para; nada perdido).
2. Como ficam agrupadas (página + abas/seções/ações/config).
3. Que visão NOVA a página dá (ex.: ver pedidos+financeiro do cliente na própria ficha).
4. Funções "escondidas" do legado que passam a ficar acessíveis/visíveis.

> Este mapa é o CONTRATO de navegação. Os IMPL_* herdam o agrupamento daqui — assim o
> resultado é coerente (uma função não vai para 2 lugares diferentes) e completo.
