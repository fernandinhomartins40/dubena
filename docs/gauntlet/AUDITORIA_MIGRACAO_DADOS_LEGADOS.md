# Auditoria da Migração — Legado → ERP Novo

**Data:** 2026-08-14 · **Escopo:** código dos legados (ctrl-web, monitora, app gás em casa),
dump de teste (`C:\Users\fusea\Desktop\Banco Dubena`) e ETL/telas do erp-novo, app refatorado e app-entregador.
Contagens confirmadas **no banco de teste vivo** (dubena-pg/dubena-mysql).

> **ATUALIZAÇÃO 2026-08-15 — plano de correção IMPLEMENTADO.** Os 9 passos foram
> executados e validados no banco de teste; ver a seção "Implementação do plano"
> no fim deste documento com os números finais.

---

## Diagnóstico central

O "muita coisa não funciona" tem uma causa dominante e sistêmica, não um conjunto de bugs isolados:

1. **O espelho Oracle→Postgres cobre 43 de ~200 tabelas do legado** (`erp-novo/database/etl/espelhar_oracle.py`).
   Tudo que o ETL lê passa por esse espelho (schema `legado` do Postgres) — o que não foi espelhado simplesmente não existe para o ETL.
2. **Os migrators da "cauda longa" (F15) foram escritos contra um schema imaginado, não contra o dump real.**
   Leem tabelas com nomes que não existem nem no Oracle nem no espelho
   (`colaboradores` — o Oracle tem `COLABORADORS`; `promocoes` — o Oracle tem `PROMOCAOS`;
   `sorteionumeros`, `checklistexecucoes`, `app_devices`, `pagamentos_online`, `cartaotransacoes`,
   `gasdopovobeneficios`, `monitora_veiculos` — não existem em lugar nenhum).
3. **Toda falha é silenciosa.** O padrão `try { ... } catch (\Throwable) { return []; }` transforma
   "tabela não existe" em "0 linhas migradas com sucesso". E as invariantes (CountInvariant etc.)
   retornam `[]` quando o legado "não está disponível" — ou seja, a mesma condição que causa o
   problema desliga a checagem que o detectaria.
4. **Remodelagens que descartam dados reais** (detalhadas abaixo): UNIQUE em parcelas, rateio
   múltiplo colapsado, comodato sem itens, pós-venda sem questionário.

O núcleo do ETL (pedidos, notas, caixa, financeiro-títulos, clientes, geográfico, posições GPS) foi
**bem executado e confere com o dump**. O problema está no entorno: cadastros de apoio, RH, frota,
CRM, fiscal-config, cobrança-remessa, cheques, usuários — exatamente o que faz as telas "não funcionarem".

---

## P0 — quebra funcional imediata (origem → destino confirmados no banco)

| Domínio | Origem (dump) | Destino (erp-novo) | Causa |
|---|---|---|---|
| **Usuários** | `USERS` 74 | `users` **4** (seed) | **Nenhum migrator lê `users`** (o espelho até tem a tabela). `PedidosMigrator::anularFksInvalidas` então anula `atendente_user_id`/`entregador_user_id` dos **400.070 pedidos** — relatórios de entrega/comissão ficam vazios. |
| **Cadastros de apoio** | `BANCOS` 148, `CONTAMOVIMENTOTIPOS` 9, `TELEFONETIPOS` 3, `TIPOPESSOAS` 2, `SEGMENTOS` 4, `CLIENTECONTATOTIPOS/SITUACAOS` 3/3 | **todos 0** | `CadastrosApoioMigrator` lê 7 tabelas — **nenhuma está no MAPA do espelho**. Lookups de caixa, boleto e cliente ficam vazios. |
| **Contábil** | `PLANOCONTAS` 169, `CENTROCUSTOS` 110, `FINANCEIRORATEIOS` 442.477, `NFSITUACAOS` 595 | `planos_conta` 0, `centros_custo` 0, `financeirorateios` 0, `operacoes_fiscais` 0 | As 4 tabelas **estão no MAPA mas não existem no espelho local** — o `espelhar_oracle.py` não foi re-rodado após o commit c0adefb. DRE e classificação contábil zerados. |
| **RH / comissões** | `COLABORADORS` 81, `COLABORADORCOMISSAOS` 872, `COMISSAOEXCECOES` 288 | **todos 0** | `RhMigrator` lê `colaboradores`/`colaboradorcomissoes`/`colaboradorpontos`… — nomes errados e fora do espelho. Cálculo de comissão impossível. |
| **Frota** | `VEICULOS` 23, `VEICULOABASTECIMENTOS` 45, pneus, óleo | **todos 0** | Tabelas existem no Oracle com esses nomes, mas não foram espelhadas. |
| **CRM** | `POSVENDAPESQUISAS` 3.097 + `POSVENDAPESQUISARESPOSTAS` 16.345, `METAVENDAS` 96, `SORTEIOS` 20 | **todos 0** | `CrmMigrator` lê nomes errados/inexistentes. Além disso o modelo novo (`pos_vendas` com nota/canal) **não comporta** o questionário legado (perguntas + respostas) — remodelagem com perda. |
| **Avaliações do app** | `sgcm.pedidoavaliacoes` **21.907** | `pedido_avaliacoes` **0** | A correlação existe (96.656 pedidos com `apipedido_id`), os endereços do mesmo migrator entraram (31.878) — a etapa de avaliações falhou ou não rodou. Investigar `AppGasEmCasaMigrator::lerAvaliacoes`. |
| **Preço por condição (app)** | `sgcm.produtocondicaopagamentos` 34 | `produto_condicao_precos` **0** | Não migrado — catálogo do app sem preço por condição de pagamento. |
| **PIX / devices legados** | `PIXTRANSACTIONS` 4.961, `PIXPEDIDOS` 125, `ANDROIDS` 62 | `pix_cobrancas` 0, `app_devices` 0 | `MobileMigrator` lê `app_devices`/`pagamentos_online`, que **nunca existiram** no legado. |
| **MonitoraMigrator (N11)** | — | — | Lê `monitora_veiculos/cercas/ultima_posicao` na conexão do **Oracle** — é código morto que nunca migrou nada (o `MonitoraLegadoMigrator`, esse sim, funcionou). |

## P1 — perdas parciais e históricos deixados para trás

| Item | Origem | Destino | Observação |
|---|---|---|---|
| Parcelas financeiras | 475.000 | 449.772 (**−25.228**) | UNIQUE `(financeiro_id, numero)` no schema novo descarta parcelas reais que o legado repetia (renegociações). Somatórios de contas a receber não batem. |
| Histórico de situação de pedido | `PEDIDOSITUACAOHISTORICOS` **2.077.510** | `pedidosituacaohistorico` **0** | A tabela destino existe e ficou vazia. Timeline de pedidos (inclusive no app-entregador) sem passado. |
| Pedidos de fechamento de convênio | `CONVENIOFECHAMENTOPEDIDOS` 28.260 | `convenio_fechamento_pedidos` **0** | Fechamentos migrados (3.717) mas sem os pedidos que os compõem. |
| Itens de comodato | `COMODATOITEMS` 1.344 | — | `SatelitesMigrator` grava todo comodato com "produto padrão, qtde 1" — o vasilhame real se perdeu. |
| Cheques | `CHEQUERECEBIDOS` 1.232 + históricos 3.467 + vínculos 1.387 | `cheques` **0** | Tela nova existe (`ChequeController`), dados não. |
| Fechamentos de caixa | `CONTAFECHAMENTOS` 6.051 | `contafechamentos` **0** | — |
| Fechamentos de estoque | `ESTOQUEFECHAMENTOSETORS` 10.865 + 105 | `estoquefechamentos` **0** | — |
| Remessa CNAB | `BOLETOREMESSAS` 1.603, `BOLETOREMESSAFINANCEIROS` 21.383, `OCORRENCIASREMESSAS` 238 | `remessas_cnab` **0** | — |
| Config fiscal | `NFIMPOSTOS` 61, `NFIMPOSTOESTADOS` 31, `NFGRUPOFISCALS` 23, `NFCESTS` 1.498, `PRODUTOLEIIMPOSTOS` **317.763**, NFICMS/NFPIS/NFCOFINS/NFIPI/NFCLASTRIB | `config_fiscais`/`malha_fiscal`/`ibpt_aliquotas` **0** | Sem regras de imposto migradas, **emissão de NF-e do tenant migrado não tem base tributária**. |
| Boletos | 21.544 → 21.132; históricos 69.158 → 68.227 | −412 / −931 | Filtro por FK; verificar descartes. |
| Parcelas de condição de pagto | 88 | 27 | Dedup do `ComplementosMigrator` colapsou 61 parcelas. |
| Preço por cliente | `CLIENTEPRODUTOS` 1.386 | `clienteprecos` **0** | Preço negociado por cliente se perdeu. |
| Notificações internas | `NOTIFICACOES` 733 + `NOTIFICACAOUSERS` 5.822 | — | — |
| Telefonia (disk-gás) | `LIGACOESTELEFONICAS` 13.214, `BINAS`, `MONITORAMENTOCHAMADAS` | — | Funcionalidade descontinuada? Decidir explicitamente. |
| Geofence histórico | `LOGCERCAS` 39.929 | — | — |
| Monitora | `devices` 31, `veiculotipos` | `monitora_veiculo_tipos` 0 | Posições (16,1 mi) e veículos/cercas OK. |
| App sgcm | `cupons` 3, `transacoesonline` 260, `videos`, `feriados` | — | Histórico de pagamento online do app não veio. |

## P2 — paridade funcional (código, não dados)

- **Relatórios:** legado tem ~25 controllers de relatório; `RelatorioController` novo expõe 13.
  Sem contraparte direta: entregas por entregador, promotor, questionários pós-venda, venda PDV,
  malote, veículos, logs de senha, NF emitidas/recebidas (listões), clientes geral.
- **Gestão de conteúdo do app** (`APPVIDEOS`, `APPNOTIFICATIONS`, `ANDROIDMENSAGEMS`): sem tela nova.
- **SPED Fiscal/Contribuições, conciliação OFX, IBPT:** reescritos no novo (Domain/Fiscal, Domain/Financeiro) ✓.
- **Apps:** o app refatorado é superset de telas do legado (ganhou carrinho, seleção de revenda,
  perfil-dados) ✓. O que "não funciona" nos apps vem do backend/dados acima (avaliações vazias,
  preços por condição ausentes, timeline sem histórico), não das telas.

## O que está certo (confirmado)

`pedidos` 400.070 ✓ · `pedidoitens` 406.883 ✓ · `notas_fiscais` 241.021/241.024 ✓ ·
`nota_itens` 254.305/254.308 ✓ · `contamovimentos` 410.417 ✓ · `financeiros` (títulos) 443.714 ✓ ·
`clientes` 44.349 + 22.208 criados do app ✓ · `estoquehistorico` 506.891 ✓ · `vale_gas` 23.588 ✓ ·
`monitora_posicoes` 16.113.791 ✓ · geográfico com dedup/IBGE bem resolvido ✓ ·
convênio remodelado conscientemente ✓.

---

## Plano de correção recomendado (em ordem)

1. **Re-rodar `espelhar_oracle.py`** — já traz `planocontas`, `centrocustos`, `financeirorateios`,
   `nfsituacaos` que estão no MAPA e faltam no espelho local.
2. **Ampliar o MAPA do espelho** (~30 tabelas com dados): `COLABORADORS` + comissões/exceções,
   `VEICULOS` + filhas, os 7 cadastros de apoio, `PEDIDOSITUACAOHISTORICOS`,
   `CONVENIOFECHAMENTOPEDIDOS`, `COMODATOITEMS`, cheques, `CONTAFECHAMENTOS`,
   fechamentos de estoque, remessas de boleto, `PIXTRANSACTIONS`, `CLIENTEPRODUTOS`,
   pós-venda (5 tabelas), `METAVENDAS`, `SORTEIOS`, config fiscal (NF*), `NOTIFICACOES`, `CARGOS`,
   `SETORCOLABORADORES`, `ANDROIDS`.
3. **Criar `UsersMigrator`** (users + `EMPRESA_USER`) rodando **antes** de `PedidosMigrator`,
   e re-migrar pedidos para recuperar atendente/entregador.
4. **Corrigir os nomes de tabela dos migrators F15** (Rh, Frota, Crm, Gestao, Pagamento, Mobile)
   contra o schema REAL; apagar `MonitoraMigrator` (morto).
5. **Matar a falha silenciosa:** tabela-fonte ausente = erro no `MigrationResult` (não `[]`);
   invariantes devem **falhar** quando a origem não está disponível, não desligar.
6. **Decidir as remodelagens com perda:** parcelas duplicadas (25.228 — renumerar em vez de
   descartar?), rateio múltiplo (colapsado para 1), comodato-itens, questionário de pós-venda.
7. **Investigar avaliações do app** (21.907 → 0 apesar de correlação válida).
8. **Popular a malha fiscal** do tenant migrado (migrar NF*/`PRODUTOLEIIMPOSTOS` ou reconfigurar) antes de emitir NF-e.
9. Repovoar `produto_condicao_precos` a partir do `sgcm.produtocondicaopagamentos`.

*Gerado por auditoria de código + verificação empírica no banco de teste em 2026-08-14.*

---

# Implementação do plano (2026-08-15)

Os 9 passos foram executados e validados no banco de teste. Suíte completa: **679 testes verdes**.

## O que mudou no código

1. **Espelho ampliado** — `espelhar_oracle.py` foi de 47 para **112 tabelas** no MAPA
   (RH, frota, apoio, históricos, cheques, remessas, PIX, CRM completo, malha fiscal,
   notificações). Re-espelhado: batch 1 = 555.537 linhas, batch 2 = 2.395.063 (histórico
   de pedidos **2.077.510 íntegro**; `produtoleiimpostos` 317.520/317.763 — 243 linhas
   descartadas por formato no sqlplus, reportadas alto). Container Oracle recuperado via
   snapshot (`dubena-ora-snap` → `dubena-ora2`, entrypoint manual; `ORA_CONTAINER` no env).
2. **`UsersMigrator` (novo)** — users + `empresa_user` antes de pedidos; senhas bcrypt
   preservadas; sem papel (atribuir no RBAC). `PedidosMigrator` agora depende de `users`.
3. **Migrators F15 reescritos contra o schema REAL** (Rh, Frota, Crm, Mobile) com as
   diferenças de modelagem resolvidas: vínculo user↔colaborador é reverso
   (`users.colaborador_id`); flag `entregador` derivada dos pedidos; parentesco/tipo de
   exame são FK→texto; férias→recessos; pós-venda preserva o questionário na observação;
   `ANDROIDS`→`app_devices`. `MonitoraMigrator` (morto) apagado.
4. **Falha silenciosa eliminada** — `CountInvariant`/`SumInvariant` agora FALHAM quando a
   conexão está de pé e a tabela-fonte não existe (nome errado/fora do MAPA); migrators
   principais retornam aviso explícito quando a fonte falta. `phpunit.xml` isola as
   conexões legadas (testes nunca enxergam o espelho real — era a causa de um OOM).
5. **Remodelagens com perda revertidas** — parcelas duplicadas são **renumeradas** (as
   25.228 entraram; 475.000/475.000); rateios múltiplos preservados integralmente em
   `financeirorateios` (442.477/442.477) além do principal nas FKs do título; comodato
   usa os itens reais (produto do maior item, quantidade somada — 973/975 com item);
   parcelas de condição de pagamento remapeadas para a condição canônica.
6. **`FiscalConfigMigrator` (novo)** — catálogos legados → `malha_fiscal` (1.091 registros
   nos tipos que a tela consome; aba CEST adicionada no frontend). As matrizes de alíquota
   (`nfimpostos`/`nfimpostoestados`/`produtoleiimpostos`) seguem SEM destino modelado —
   aviso alto no migrator: modelar antes do cutover da emissão de NF-e.
7. **`FiscalMigrator`** ganhou itens de NF de entrada (2.319) e cartas de correção (83).
8. **`CobrancaMigrator`** ganhou PIX legado → `pix_cobrancas` (4.961; status do PSP
   mapeado ao vocabulário do destino) e remessas CNAB → `remessas_cnab` (1.603).
9. **`ComplementosMigrator`** ganhou timeline de pedidos, pedidos de fechamento de
   convênio (vigente vence o refeito), fechamentos de caixa/estoque, cheques (situação
   FK→texto) e preço por cliente (renegociado vence). **`FinanceiroMigrator`** corrigido:
   checava destino com nome errado (`planocontas` vs `planos_conta`) e anulava a
   religação contábil.
10. **`AppGasEmCasaMigrator`** ganhou preço por condição, transações online e cupons→promoções;
    avaliações protegidas contra empresa nula.

## Números finais (legado → destino, banco de teste)

| Entidade | Origem | Destino | |
|---|---|---|---|
| Pedidos com atendente | 400.070 | **400.070 (100%)** | ✓ |
| Timeline de pedidos | 2.077.510 | 2.077.510 | ✓ |
| Parcelas financeiras | 475.000 | 475.000 (25.228 renumeradas) | ✓ |
| Rateios contábeis | 442.477 | 442.477 (+442.121 títulos religados) | ✓ |
| Colaboradores / comissões / exceções | 81 / 872 / 288 | 81 / 872 / 288 | ✓ |
| Veículos / bancos / devices | 23 / 148 / 62 | 23 / 148 / 62 | ✓ |
| Pós-venda (pesquisas c/ questionário) | 3.097 | 3.097 | ✓ |
| Cheques / fech. caixa / fech. estoque | 1.232 / 6.051 / 10.865 | 1.232 / 6.051 / 10.865 | ✓ |
| PIX / remessas CNAB | 4.961 / 1.603 | 4.961 / 1.603 | ✓ |
| NF entrada itens / cartas de correção | 2.319 / 83 | 2.319 / 83 | ✓ |
| Avaliações do app | 21.907 | 21.905 (2 sem pedido) | ✓ |
| Pedidos de fechamento de convênio | 28.260 | 28.218 (42 re-fechamentos substituídos) | ✓ |
| Preço por cliente | 1.386 | 1.385 (1 renegociado) | ✓ |
| Preço por condição (app) | 34 | 23 (11 são cadastros de teste de 2019, `ativo=0`) | ✓ |
| Malha fiscal (catálogos) | — | 1.091 | ✓ |
| Matriz de tributação (`nfimpostos`) | 61 | 61 | ✓ |
| Matriz por UF (`nfimpostoestados`) | 31 | 31 | ✓ |
| Produto × operação fiscal | 21 | 21 | ✓ |
| IBPT / Lei 12.741 (`produtoleiimpostos`) | 317.520 | 317.520 | ✓ |

## Pendências conscientes — TODAS FECHADAS (2026-08-15)

As três pendências que restavam foram implementadas. O que se descobriu ao fazê-lo
mudou o diagnóstico de duas delas:

- **Matrizes de alíquota fiscais** — ✅ resolvido, com uma **correção de premissa**:
  `produtoleiimpostos` (317.520 linhas) *não* é matriz de tributação. É a tabela do
  **IBPT** (Lei 12.741/2012, o "De olho no imposto"): carga tributária aproximada por
  UF × NCM, para o rodapé do cupom. E o destino dela **já existia** (`ibpt_aliquotas`,
  lida pelo `IbptService`) — só nunca fora populada, porque o `ibpt:atualizar` depende
  de um CSV externo. Migrada 1:1 pelo `IbptMigrator`.

  A matriz de tributação de verdade é `nfimpostos` + `nfimpostoestados` (61 + 31
  linhas), e essa realmente não tinha destino. Agora tem: `nf_impostos` /
  `nf_imposto_estados` (migration `matriz_tributacao`), carregadas pelo
  `MatrizTributariaMigrator` e consumidas pela nova `ResolucaoTributariaService` —
  porte fiel do `ImpostoDB` legado, com as quatro decisões que ele toma: PJ ×
  consumidor final, interno × interestadual, recusa a faturar sem regra da UF (erra
  em vez de tributar errado) e CFOP 5xxx→6xxx. O `FiscalService` deixou de usar
  CST 00 / 18% / CFOP 5102 fixos.

  Duas descobertas no caminho, ambas corrigidas:
  - `produtos.grupo_fiscal_id` **não havia sido migrado**. A matriz é indexada por
    operação × grupo fiscal; sem essa coluna, 25 dos 26 produtos seriam tributados
    pela regra errada. Coluna criada e populada.
  - 9 regras apontavam para operações fiscais absorvidas pelo dedup do commit
    `c16b4a6` (66→57 por UNIQUE grupo+descrição). Em vez de descartá-las, o migrator
    as **redireciona** pela mesma chave (grupo, descrição) — zero descartes.

- **Usuários migrados sem papel** — ✅ resolvido. O legado não tem RBAC por papel (a
  permissão vive em `menuusers`, menu × ação, que não está no espelho), mas o papel é
  derivável e foi **validado contra os dados**: `support=1` → Administrador; e o
  significado de `tipo_id` foi confirmado cruzando com os 400 mil pedidos — dos 26
  usuários com `tipo_id=21`, 23 aparecem como entregador e nenhum como atendente.
  Resultado: 74/74 usuários com papel (31 Entregador, 34 Operador, 12 Administrador,
  1 Gerente), **zero sem papel**.

- **11 preços por condição do app** — ✅ resolvido, e **não eram perda de migração**.
  São de março/2019, apontam para `produtoimportacoes` 1-3 (erp_id 5 e 8 — produtos
  que nunca existiram neste ERP; os reais são 50, 98, 297...), estão com `ativo='0'`
  e foram cadastrados pelo usuário 1 da implantação: registros de **teste
  desativados**. Os 23 preços vivos migram todos. O descarte, que antes era um
  `continue` silencioso, agora é contado e reportado com o diagnóstico.

Segue como backlog de produto (P2 da auditoria): relatórios/telas sem contraparte.

### O que ainda depende de decisão de negócio

- A tabela IBPT é **mensal** e a migrada é a versão `18.2.C` (vigência out/2018 a
  jan/2019, a última que o legado carregou). Antes do cutover fiscal, rodar
  `ibpt:atualizar` com o CSV da vigência corrente.
- Itens sem regra na matriz continuam caindo no padrão histórico (CST 00 / 18%) — o
  comportamento anterior, mantido para não quebrar quem ainda não cadastrou a
  tributação. No dump atual, só 6 produtos têm vínculo produto×operação no legado.

