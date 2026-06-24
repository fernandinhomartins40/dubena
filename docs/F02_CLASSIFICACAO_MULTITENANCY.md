# F02 — Classificação Multi-Tenancy dos 107 Models

> Fonte: inventário programático dos models em `erp-novo/app/Models` (trait + colunas).
> Define o escopo de cada tabela para aplicar `BelongsToTenant`/`BelongsToGrupo` e RLS.
> Data: 2026-06-24.

## Legenda
- **EMPRESA** — escopado por `empresa_id` (trait `BelongsToTenant` + RLS por empresa).
- **GRUPO** — escopado por `grupo_id` (trait `BelongsToGrupo`; RLS por grupo — opcional, menor risco).
- **GLOBAL** — sem escopo de tenant (tabelas de plataforma/geografia nacional).
- **FILHA** — herda o tenant do pai; recebe `empresa_id` denormalizado + `BelongsToTenant`.

---

## EMPRESA (trait já presente) — OK, sem ação
Caixa/Cheque, Caixa/Conta, Caixa/ContaFechamento, Caixa/ContaMovimento (F00.5),
Cliente/Cliente, Cobranca/Boleto, Cobranca/PixCobranca, Cobranca/RemessaCnab,
Crm/ChecklistExecucao, Crm/MetaVenda, Crm/PosVenda, Estoque/EstoqueFechamento,
Estoque/EstoqueHistorico, Estoque/EstoqueInventario, Estoque/EstoqueRequisicao,
Estoque/EstoqueSaldo, Estoque/Setor, Financeiro/Financeiro, Fiscal/NfRecebida,
Fiscal/NotaFiscal, Frota/Veiculo, Gestao/CupomFiscal, Gestao/Documento, Gestao/EmpresaBem,
Gestao/Mcmm, Mobile/PagamentoOnline, Monitora/Cerca, Monitora/Veiculo,
Pagamento/CartaoTransacao, Pagamento/GasDoPovoBeneficio, Pedido/Pedido, Produto/Produto,
Rh/Colaborador, Satelite/Comodato, Satelite/Convenio, Satelite/ConvenioFechamento,
Satelite/ValeGas. **(37 models)**

## EMPRESA-FILHA — adicionar `empresa_id` (backfill do pai) + `BelongsToTenant`
| Model | Tabela | Pai (backfill via) |
|---|---|---|
| Cliente/ClienteDependente | clientedependentes | clientes (cliente_id) |
| Cliente/ClienteInteracao | clienteinteracoes | clientes (cliente_id) |
| Cliente/ClientePreco | clienteprecos | clientes (cliente_id) |
| Cliente/ClienteTelefone | clientetelefones | clientes (cliente_id) |
| Financeiro/FinanceiroParcela | financeiroparcelas | financeiros (financeiro_id) |
| Financeiro/FinanceiroRateio | financeirorateios | financeiros (financeiro_id) |
| Pedido/PedidoItem | pedidoitens | pedidos (pedido_id) |
| Pedido/PedidoSituacaoHistorico | pedidosituacaohistorico | pedidos (pedido_id) |
| Fiscal/NotaItem | nota_itens | notas_fiscais (nota_fiscal_id) |
| Fiscal/NfRecebidaItem | nf_recebida_itens | nf_recebidas (nf_recebida_id) |
| Estoque/EstoqueInventarioItem | estoque_inventario_itens | estoque_inventarios (estoque_inventario_id) |
| Crm/ChecklistResposta | checklist_respostas | checklist_execucoes (checklist_execucao_id) |
| Frota/VeiculoAbastecimento | veiculo_abastecimentos | veiculos (veiculo_id) |
| Frota/VeiculoPneu | veiculo_pneus | veiculos (veiculo_id) |
| Frota/VeiculoTrocaOleo | veiculo_trocas_oleo | veiculos (veiculo_id) |
| Rh/ColaboradorExame | colaborador_exames | colaboradores (colaborador_id) |
| Rh/ColaboradorPonto | colaborador_pontos | colaboradores (colaborador_id) |
| Rh/ColaboradorTurno | colaborador_turnos | colaboradores (colaborador_id) |
| Rh/ComissaoExcecao | comissao_excecoes | colaboradores (colaborador_id) |
| Produto/ProdutoOrigem | produtoorigens | produtos (produto_id) |

## EMPRESA-FILHA — já tem `empresa_id`, só falta trait
Cobranca/BoletoOcorrencia (boleto_ocorrencias), Crm/ChecklistPergunta (checklist_perguntas),
Crm/SorteioNumero (sorteio_numeros), Gestao/CupomFiscalItem (cupom_fiscal_itens),
Monitora/Posicao (monitora_posicoes), Monitora/UltimaPosicao (monitora_ultima_posicao),
Rh/ColaboradorComissao (colaborador_comissoes), Rh/ColaboradorFamilia (colaborador_familias),
Rh/ColaboradorRecesso (colaborador_recessos).

## GRUPO — aplicar `BelongsToGrupo` (têm grupo_id, sem trait)
Apoio/Agencia, Apoio/Banco, Apoio/ContaMovimentoTipo, Apoio/Feriado, Apoio/TelefoneTipo,
Apoio/TipoPessoa, Apoio/Transportadora, Crm/Checklist (tem G), Crm/Promocao (tem G),
Crm/Sorteio (tem G), Financeiro/CentroCusto (tem G), Financeiro/CondicaoPagamento (tem G),
Financeiro/PlanoConta (tem G), Fiscal/MalhaFiscal (tem G), Fiscal/OperacaoFiscal (tem G),
Frota/TipoCombustivel (tem G), Frota/VeiculoTipo (tem G), Geografico/Bairro (tem G),
Geografico/Cidade (tem G), Geografico/Rua (tem G), Pedido/PedidoOperacao (tem G),
Pedido/PedidoSituacao (tem G), Produto/ProdutoClasse (tem G), Produto/UnidadeMedida (tem G),
Regiao (tem G), Rh/Cargo (tem G), Apoio/CadastroApoio (base, tem G),
Apoio/ClienteContatoSituacao*, Apoio/ClienteContatoTipo*, Apoio/EstadoCivil*, Apoio/Profissao*, Apoio/Segmento*.
\* Estes têm `grupo_id` na tabela (CadastroApoio base) embora o fillable não liste — herdam de CadastroApoio.

## GRUPO-tabela-de-config-empresa
EmpresaConfig (empresa_configs, tem empresa_id) → **EMPRESA** (1:1 com empresa).
ConfigFiscal (config_fiscais, empresa_id) → **EMPRESA**.

## GLOBAL — sem escopo (não recebem trait)
Estado (estados — UFs nacionais), Empresa (empresas — a própria entidade-tenant, escopada por grupo no controller),
Grupo (grupos — a rede), User (users — pertence a empresa/grupo mas é a identidade; escopo no RBAC),
Permission (permissions — catálogo global), Role (roles — papéis; empresa_id usado no pivot),
Mobile/AppDevice (app_devices — vinculado ao token do usuário).

---

## Resumo de ação
- **+20 tabelas-filhas** recebem `empresa_id` (migration com backfill do pai).
- **+9 models-filhos** já com `empresa_id` recebem só o trait.
- **+~32 models** de grupo recebem `BelongsToGrupo`.
- **GLOBAL**: 6 models permanecem sem escopo (intencional).
- **RLS** habilitado nas tabelas EMPRESA (inclui filhas após denormalização).

---

## RESULTADO REAL DA EXECUÇÃO (F02 implementada — 2026-06-24)

A triagem inicial por `grep` super/subestimou alguns casos; os números abaixo refletem
o que foi efetivamente aplicado, verificado por `Schema::hasColumn` em runtime:

- **`BelongsToGrupo` já estava 100%**: os cadastros de apoio herdam o trait da base
  abstrata `CadastroApoio`; os demais models de grupo já o tinham. **0 alterações** aqui.
- **`BelongsToTenant` aplicado a 29 models-filhos** que não tinham (clientes/* filhos,
  financeiro parcelas/rateios, pedido itens/histórico, nota/nf itens, estoque inventário
  itens, checklist respostas, veículo abastecimento/pneu/óleo, colaborador
  exames/pontos/turnos/família/recessos/comissão, comissão exceções, produto origens,
  boleto ocorrências, cupom itens, monitora posições/última, colaborador comissões).
- **2 leafs revertidos para SEM trait** (ChecklistPergunta, SorteioNumero): são folhas de
  pais grupo-scoped, sem coluna de tenant — isolados pelo pai.
- **Migration `..._f02_empresa_id_em_tabelas_filhas`**: adiciona `empresa_id` + backfill do
  pai a **26 tabelas-filhas** (idempotente; só age se a coluna faltar).
- **Herança de tenant na criação** (`BelongsToTenant::empresaIdDoPai` + `$tenantParent`):
  filhas criadas SEM tenant ativo (ETL/jobs/seed/testes) herdam o `empresa_id` do pai.
- **RLS Postgres** (`..._f02_habilitar_rls_postgres`): policy `tenant_isolation` em **64
  tabelas** escopadas (`empresa_id = current_setting('app.empresa_id')` OU var não setada
  → CLI/admin vê tudo, espelhando o global scope). NO-OP em sqlite/mysql.
- **`ResolveTenant`** faz `set_config('app.empresa_id', <id>)` por requisição (pgsql).
- **Uploads**: certificado A1 segregado em subpasta `certificados/empresa_<id>/` (disco privado).
- **Cache por-tenant**: **N/A** — a aplicação não usa cache de dados (0 chamadas `Cache::`/`remember`).
- **Jobs/crons**: já tenant-corretos (iteram `Empresa` ou agregam por `empresa_id`); os
  crons globais de saúde (inconsistências) funcionam sob RLS pela cláusula de var não setada.
- **Testes**: `FaseF02CrossTenantTest` (6 casos) + suíte completa **267 passed / 0 falhas**.
