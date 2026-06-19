# PRD DE IMPLEMENTAÇÃO — Cadastros de Apoio (consolidado) · auditado

> CRUDs simples (index/store/update/destroy) que, no MAPA_NAVEGACAO_ALVO, em sua maioria
> deixam de ser telas isoladas e viram ABAS de "Configurações" do módulo dono.
> Auditado: controllers em app/Http/Controllers + tabelas. Cada um é escopo grupo/empresa,
> validação de descrição obrigatória + unicidade no escopo (padrão comum).

## CADASTROS E DESTINO (de-para — reorganização)
| Cadastro (controller / rota) | Campos principais | Destino no app novo |
|---|---|---|
| Segmento (segmento.index) | descricao, ativo, grupo_id | **Clientes → Configurações → aba Segmentos** |
| Tipopessoa (tipopessoa.index) | descricao, tipopessoacadastro(F/J), ativo | **Clientes → Configurações → aba Tipos de Pessoa** |
| Telefonetipo (telefonetipo.index) | descricao, celular, ativo | **Clientes → Configurações → aba Tipos de Telefone** |
| ClienteContatoTipo (clientecontatotipo) | descricao, ativo | **Clientes → Config → Tipos de Contato** |
| ClienteContatoSituacao (clientecontatosituacao) | descricao, ativo | **Clientes → Config → Situações de Contato** |
| Promocao (promocao.index) | descricao, período, prêmio | **Clientes → aba/seção Promoções** |
| Produtoclasse (produtoclasse.index) | descricao, tipo(G/R), ativo | **Produtos → Configurações → aba Classes** |
| Unidademedida (unidademedida.index) | descricao, sigla, ativo | **Produtos → Configurações → aba Unidades** |
| Banco (banco.index) | codigo, nome | **Cadastros → Bancos** (ou Financeiro → Config) |
| Layoutbanco (layoutbancos.index) | layout de cobrança | **Financeiro → Config → Layouts de cobrança** |
| Condicaopagamento (config) | descricao, tipo, dias, parcelas, taxa | **Financeiro → Config → Condições de pagamento** |
| Contamovimentotipo (contamovimentotipo) | descricao, pagarreceber | **Financeiro → Config → Tipos de movimento** |
| Setor (setor.index) | descricao, cidade/bairro, grupo/empresa | **Cadastros → Setores** (geográfico/operacional) |
| Cargo (cargo.index) | descricao | **RH → Config → Cargos** |
| Estadocivil / Parentesco / Tipoexame | descricao, ativo | **RH → Config** (abas) |
| Tipocombustivel / Veiculotipo / Tipodocumento | descricao | **Frota → Config** (abas) |
| Recessotipo / Motivonaovenda / Pedidomotivoatraso / Pedidooperacao / Pedidosituacao / OcorrenciatipoVA | cadastros de apoio de operação | **dentro do módulo dono (Vendas/Operações → Config)** |

## REGRAS COMUNS (auditadas — padrão dos cadastros)
- index lista por grupo/empresa; store define grupo_id = empresa_padrao.grupo_id.
- Validação: `descricao` required; **unicidade** descricao no escopo (grupo ou empresa) — vários têm
  `getXIguais`/unique. Manter na API.
- `ativo` smallint default 1; destroy trata FK (erro amigável, não 500).
- **Pedidosituacao** (máquina de estados) e **Pedidooperacao** (movimentaestoque/financeiro) NÃO são
  apoio trivial — são CONFIG CRÍTICA lida pelo Pedido; auditar à parte no IMPL_VENDAS.

## PÁGINA-ALVO (UX)
- Cada "Configurações" (de Cliente/Produto/Financeiro/RH/Frota) é UMA página com abas, cada aba um
  CRUD simples (DataTable + form modal). Reduz dezenas de itens de menu a poucas páginas de config.
- Cadastros "de primeira classe" (Banco, Setor) podem ter página própria no grupo Cadastros.

## API ADMIN
- Endpoints CRUD genéricos por cadastro (/config/<cadastro>) com escopo + unicidade.
- Muitos já têm lookup (/lookups/*) criado na S2b — reusar; o CRUD adiciona create/update/delete.

## DoD
1. Todo cadastro de apoio do legado tem destino (aba de Config ou página) — de-para acima 100%.
2. CRUD com validação+unicidade+escopo; destroy com FK amigável.
3. Nenhuma função perdida (conferir contra a árvore de menus do legado).
4. Testes dos CRUDs principais + suíte verde.
