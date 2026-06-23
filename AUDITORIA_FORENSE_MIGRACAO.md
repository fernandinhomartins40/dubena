# Auditoria Forense de Migração — ctrl-web × erp-novo × SPA

> **Método:** análise baseada **exclusivamente no código executável**. Nenhuma documentação,
> README, comentário de planejamento ou relatório anterior foi usado como fonte de verdade.
> Toda conclusão abaixo é rastreável a arquivo, classe, método e linha reais.
> **Data:** 2026-06-22 · **Escopo:** `ctrl-web/` (legado), `erp-novo/` (backend novo + SPA React).

---

## 0. Índice quantitativo (medido, não estimado)

Contagens obtidas varrendo o código-fonte (`find`/`grep` sobre `app/`, `database/`, `routes/`).

### 0.1 ctrl-web (legado)

| Artefato | Qtd | Evidência |
|---|---:|---|
| Arquivos PHP (sem vendor) | **2.509** | `find . -name '*.php' -not -path './vendor/*'` |
| LOC em `app/` | **139.048** | `find app -name '*.php' -exec cat \| wc -l` |
| LOC em `database/migrations` | **25.079** | idem migrations |
| Controllers (Http/Controllers) | **164** | `app/Http/Controllers` |
| Controllers (Api) | **82** | `app/Api/**` |
| Controllers (ApiAdmin) | **25** | `app/ApiAdmin/**` |
| **Controllers (total)** | **271** | soma |
| Services | 9 | `app/Services` |
| **Processors** | **144** | `app/Processors` (lógica de negócio pesada) |
| Models (Eloquent) | **203** | `grep -rl "extends Model" app` |
| Repositories | 7 | `app/Repository` |
| Monitora (GPS) | 44 | `app/Monitora` |
| Commands | 17 | `app/Console` |
| Jobs | 3 | `app/Jobs` |
| Policies | 98 | `app/Policies` |
| Requests | 20 | `app/Http/Requests` |
| Middleware | 7 | `app/Http/Middleware` |
| Enums | 6 | `app/Enums` |
| Helpers | 9 | `app/Helpers` |
| Migrations | **624** | `database/migrations` |
| Tabelas criadas (`Schema::create`) | **213** | grep migrations |

### 0.2 erp-novo (backend novo)

| Artefato | Qtd | Evidência |
|---|---:|---|
| LOC em `app/` | **11.673** | `find app -name '*.php' -exec cat \| wc -l` |
| LOC em `database/migrations` | **1.669** | idem |
| Controllers | **31** | `app/Http/Controllers` |
| Domain Services (classes `*Service`) | **24** | `app/Domain/**` |
| Models | **63** | `app/Models` |
| Commands | **4** | EtlRun, CutoverCheck, MonitoraSyncPositions, NotifyAlertas |
| Jobs | **0** | `app/Jobs` inexistente |
| Requests | 5 | `app/Http/Requests` |
| Resources | 4 | `app/Http/Resources` |
| Middleware | 1 | `app/Http/Middleware` |
| Policies | 0 | — |
| Migrations | **23** | `database/migrations` |
| Tabelas criadas | **69** | grep migrations |
| Seeders | 2 | DeployAdminSeeder + DatabaseSeeder |
| Tests | 32 arq / 2.950 LOC | `tests/` |

### 0.3 SPA React (em `erp-novo/frontend`)

| Artefato | Qtd | Evidência |
|---|---:|---|
| Features | **12** | `src/features/*` (auth, cadastros, clientes, dashboard, empresas, estoque, financeiro, fiscal, geografico, pedidos, produtos, satelites) |
| Endpoints distintos chamados | **75** | extração de `features/*/api.ts` |

### 0.4 A razão que define tudo

```
LOC app/ legado : 139.048
LOC app/ novo   :  11.673   →   8,4% do volume de código de negócio
Processors      :    144 → 0  (a lógica fiscal/caixa/comissão do legado vive aqui)
Tabelas         :    213 → 69  (32%)
Controllers     :    271 → 31  (11%)
```

> O erp-novo não é uma reescrita incompleta de "quase lá": é **o núcleo transacional
> reconstruído com qualidade arquitetural alta**, cercado por uma vasta cauda de
> funcionalidades legais/operacionais **ainda inexistentes**.

---

## 1. Análise de implementação real por fluxo (rastreamento de código)

Para cada fluxo, segui: **Controller → Service/Processor → Banco → efeitos colaterais**.

### 1.1 Fluxo PEDIDO (venda — coração do ERP)

**Legado:** `app/Http/Controllers/PedidoController.php` — **1.661 linhas**, 47 métodos.
Métodos-chave lidos:
- `setMovimentaEstoqueFinanceiro()` (l.61): a decisão de mover estoque/financeiro vem de
  **`config->pedidoOp->movimentaestoque/movimentafinanceiro`** — uma **matriz configurável
  no banco** (operação × setor), não hardcoded.
- `validateMovFinanceiro()` (l.808-929): decide `INSERE`/`EXCLUI` financeiro cruzando
  situação × condição de pagamento × vale-gás (`condicao->tipo == '5'`) × troca de setor
  com operação diferente; **estorna pagamento online** (`MobileAppProcessor::estornarPagamentoOnline`,
  conexão `sgcm_api`); bloqueia se parcela já baixada; trata financeiro paralelo
  **"Gás do Povo"** (`financeiroentregagp_id`).
- `validateMovEstoque()` (l.939): SAÍDA/ENTRADA/revert+insert conforme transição e troca de setor.
- `createNF()`/`transmitirNF()` (l.1306/1326): emissão fiscal acoplada ao pedido.
- `gerarFinanceiroParcelasEntregaGasdoPovo()` (l.1621): parcelamento dedicado do Gás do Povo.

**Novo:** `app/Domain/Pedido/PedidoService.php` — **193 linhas**.
- Máquina de estados explícita (`EfeitoPedido`: PENDENTE/CONCLUIDO/CANCELADO).
- `aplicarEfeito()` (l.110): CONCLUIDO sempre baixa estoque + `financeiro->gerarDoPedido()`;
  CANCELADO sempre devolve + estorna. **Hardcoded** — não lê matriz de operação.
- `gerarDoPedido()` (FinanceiroService l.78): **ignora a condição de pagamento** —
  sempre `numParcelas=1`, sem cartão/NSU/autorização, sem vale-gás, sem Gás do Povo.

**Veredito PEDIDO — PARCIAL/DIVERGENTE.** Evidências de regras **não migradas**:
| Regra (legado) | Status novo | Prova |
|---|---|---|
| Movimentação por matriz operação×setor (`movimentaestoque/financeiro`) | ❌ ausente | `grep movimentaestoque erp-novo/app` → 0 |
| Parcelamento por condição de pagamento | ❌ ausente | `gerarDoPedido` força `numParcelas=1` |
| Cartão (autorização/NSU) | ❌ ausente | `grep cartaoautorizacao\|cartaonsu` → 0 |
| Estorno de pagamento online ao cancelar | ❌ ausente | `grep transacaoonline\|estornarPagamentoOnline` → 0 |
| Gás do Povo (financeiro paralelo + parcelas) | ❌ só flag | `gasdopovo` existe **apenas como coluna** booleana/preço |
| Vale-gás como condição de pagamento | ⚠️ parcial | há `ValeGasService`, mas não integrado ao fluxo de pedido como condição |

### 1.2 Fluxo CAIXA / CHEQUE (risco contábil)

**Legado:** `app/Processors/caixaProcessor.php` — **1.285 linhas**.
Métodos lidos: `abrirCaixa`, `fecharCaixa`, `validarBaixaTitulos`, `receberCaixa`,
**`createCPCartao`** (l.580 — gera contas a pagar de cartão/taxa de adquirente),
`transferirCaixa`, **`movimentarCaixaFechado`** (l.899 — lançamento retroativo em caixa fechado),
`baixarTitulos` (baixa em lote), `estornarCaixa/Parcela/Transferencia`, e
`isUserAllowed/Estornar/Transfer/Fechado` (l.298-346 — **4 verificações de permissão por operação**).
`app/Processors/ChequeProcessor.php` — **440 linhas**: encontro de contas
(`insertChequeEncontrocontas`), `transfereCaixaChequeRecebido` (troco/adiantamento),
`estornarChequeRecebido`, devolução.

**Novo:** `app/Domain/Caixa/CaixaService.php` — **231 linhas** + `ChequeService.php` — **75 linhas**.
- Arquitetura **superior**: saldo auditável (Σ `contamovimentos.valor` = `conta.saldo_atual`),
  `movimentar()` com `lockForUpdate`, estorno por movimento inverso (mantém histórico).

**Veredito CAIXA — PARCIAL, com risco.** Evidências:
| Regra (legado) | Status novo | Prova |
|---|---|---|
| Bloqueio de movimento em caixa fechado | ❌ **ausente** | `CaixaService::movimentar()` (l.35-56) **nunca checa `conta->fechado`** |
| CP/taxa de cartão na baixa (`createCPCartao`) | ❌ ausente | `grep createCPCartao\|cartao` → 0 |
| Baixa de títulos em lote (`baixarTitulos`) | ❌ ausente | novo só baixa 1 parcela (`baixarParcela`) |
| Permissões por operação de caixa (`isUserAllowed*`) | ❌ ausente | nenhuma policy/gate de caixa |
| Cheque: encontro de contas / troco / devolução | ❌ ausente | ChequeService = CRUD + `mudarSituacao` |

### 1.3 Fluxo FISCAL (obrigação legal — maior risco)

**Legado:** `app/Processors/Nfe/` — **8.729 LOC / 18 arquivos** + `app/Processors/Sped/` —
**10.523 LOC / 119 arquivos**. Total **~19.250 LOC fiscais**.
- `CalculoImposto.php` (737) + `NfeImpostoProcessor.php` (1.001) + `IcmsBase.php` (940):
  tratam (contagem de ocorrências no código) **ICMS(161), IBS(74), CBS(59), COFINS(57),
  PIS(58), IPI(70), FCP(37), ISS(28), MVA(3), diferimento** — inclui já a **reforma
  tributária IBS/CBS**. `isSimples()`, `getAliqNac/Mun/Est` (IBPT por origem).
- `TagMaker.php` (1.761) + `MakeXml.php` (513): **geração do XML** da NF-e.
- `SefazEvento.php` (917): eventos SEFAZ (carta de correção, cancelamento, inutilização).
- `Sped/` (119 arquivos Reg*): geração de SPED Fiscal/Contribuições/Créditos.

**Novo:** `app/Domain/Fiscal/` — **395 LOC / 7 arquivos**.
- `CalculoImpostoService.php` (64): **`base * aliq / 100`** para ICMS/PIS/COFINS/IPI.
  Comentário do próprio código admite: *"simplificadas para o núcleo testável; ST/FCP/
  diferimento entram por extensão"*.
- `FiscalService.php` (172): monta/emite/cancela nota, mas `emitir()` chama um **driver**.
- `Drivers/FakeSefazDriver.php` (40): **gera chave de 44 dígitos e protocolo FICTÍCIOS**.

**Veredito FISCAL — NÃO MIGRADO (esqueleto).** Evidências (grep em `erp-novo/app`):
| Componente | Status | Prova |
|---|---|---|
| ICMS-ST / DIFAL / FCP-ST / MVA / redução | ❌ ausente | `grep -i "icmsst\|difal\|substituicao"` → 0 |
| IBS / CBS (reforma) | ❌ ausente | `grep -i "ibs\|cbs"` → 0 |
| IBPT (aliq nac/est/mun) | ❌ ausente | `grep -i ibpt` → 0 |
| Geração de XML (TagMaker/MakeXml) | ❌ ausente | `grep -i "makexml\|tagmaker\|gerarXml"` → 0 |
| Comunicação real com SEFAZ | ❌ **FAKE** | único driver é `FakeSefazDriver` (protocolo fictício) |
| Assinatura digital / uso do certificado A1 | ❌ ausente | sem upload nem uso de certificado |
| SPED (Fiscal/Contribuições) | ❌ ausente | 10.523 LOC no legado → 0 real no novo |
| DANFE / inutilização / carta de correção | ❌ ausente | `grep -i "danfe\|inutiliza"` → 0 |

> **O erp-novo NÃO emite documento fiscal válido.** O fluxo "emite" trocando o estado da
> nota para AUTORIZADA com dados fabricados pelo driver fake. Em produção isso é inviável.

### 1.4 AUTOMAÇÃO (jobs / cron)

**Legado** (`app/Console/Kernel.php`): **10 tarefas agendadas** — `notify:alertas` (diário),
`vendadiaria:send`, `notify:delete`, `ibpt:update`, `remembermail:send`, `order:send`
(a cada minuto), `pix:expired` (a cada minuto), `documentosvencidosmail:send`,
`notify:inconsistencies`, `report:positions` (GPS, a cada minuto). + Jobs assíncronos:
`ProcessAppVideo`, `ProcessPixPedido`.

**Novo** (`routes/console.php`): **2 tarefas** — `notify:alertas`, `monitora:sync-positions`.
**0 jobs assíncronos**.

**Veredito AUTOMAÇÃO — PARCIAL.** Não migrados: PIX expirado, IBPT diário, e-mails
(venda diária / remember / documentos vencidos), `order:send`, inconsistências, vídeo, PIX assíncrono.

---

## 2. Auditoria de banco de dados (uso real)

- **Legado:** 213 tabelas (`Schema::create`). **Novo:** 69 tabelas.
- A nomenclatura difere (novo: `snake_case` singular → `clientes`, `setores`, `produtos`),
  então parte das "faltantes" são apenas renomeações. Após normalização manual, os
  **domínios inteiros sem qualquer tabela equivalente** no novo são:

| Domínio ausente no banco novo | Tabelas legado (exemplos) |
|---|---|
| **RH / Colaborador** | colaboradors, colaboradorcomissaos, colaboradorexames, colaboradorferias, colaboradorfamilias, cargos, recessos, recessotipos, turnos, comissaoexcecoes |
| **Frota / Veículos** | veiculos, veiculoabastecimentos, veiculopneus, veiculotrocaoleos, veiculodocumentos, veiculoentradasaidas, tipocombustivels |
| **Cheque completo** | chequeemitidos, chequerecebidos, chequeemitidoencontrocontas, chequerecebidoencontrocontas, chequerecebidotransferencias, chequesituacaohistoricos |
| **SPED** | spedfiscals, spedcontribuicaos, spedcontribuicoescreditos, creditopiscofins |
| **NF recebida (entrada)** | nfrecebidas, nfrecebidaitems, nfrecebidaparcelas, nfrecebidavolumes |
| **Conciliação bancária** | contaextratoconfigs, layoutbancos, bancolayoutretornos, ocorrenciasremessas |
| **Pós-venda / CRM** | posvendas, posvendapesquisas, posvendaperguntas, vendaativas, vendaativaocorrencias |
| **Checklist** | checklists, checklistforms, checklistperguntas, checklistpesquisas, checklistrespostas (7 tabelas) |
| **Promoção / Sorteio / Metas** | promocaos, clientepromocaos, sorteios, metavendas, motivonaovendas |
| **Cupom fiscal (SAT/CFe)** | cuponsfiscais, cuponsfiscaisitens, cupomfiscalparcela, nfcests, nfclastribs |
| **Condição de pagamento** | condicaopagamentos, condicaopagamentoparcelas, cliente_condicaopagamento |
| **Centro de custo / plano** | centrocustos (novo tem `centros_custo`, mas sem rateio completo) |
| **Bancos / Agências** | bancos, agencias, agenciatelefones |
| **Menu / RBAC dinâmico** | menus, menuusers, menu_user, logsenhas |
| **Inventário / MCMM** | inventarios, inventarioitems, mcmms, mcmmhistoricoentradas/saidas |
| **Documentos / Depreciação** | documentos, documentotipos, empresabems, empresabemdepreciacaos |

> **Matriz tabela→código:** o novo cobre o núcleo transacional (clientes, produtos,
> estoque com saldo/histórico, pedidos+itens, financeiro+parcelas+rateios, contas+movimentos,
> cheques básicos, notas+itens, convênios/vale-gás/comodato, monitora/GPS, mobile).
> **O banco NÃO suporta a operação completa** do legado — faltam ~12 domínios.

---

## 3. Auditoria de integração SPA × backend (contrato real)

Cruzamento programático: **75 endpoints** extraídos de `features/*/api.ts` × **96 rotas**
declaradas em `routes/api.php`/`web.php`.

### 3.1 Resultado: 36 de 75 endpoints (48%) NÃO existem no backend

| Tela / feature da SPA | Endpoint chamado | Backend | Classificação |
|---|---|---|---|
| Fiscal (NF-e) | `fiscal/nfe`, `.../transmitir`, `.../cancelar` | ❌ (backend usa `notas*`) | **NÃO FUNCIONA** |
| Fiscal (operações/malha/SPED) | `fiscal/operacoes`, `fiscal/malha/:t`, `fiscal/sped` | ❌ | **NÃO FUNCIONA** |
| Satélites — Colaboradores | `colaboradores`, `.../familia`, `.../recessos`, `.../comissoes` | ❌ | **NÃO FUNCIONA** |
| Satélites — Veículos | `veiculos`, `.../abastecimentos`, `.../pneus`, `.../trocas-oleo` | ❌ | **NÃO FUNCIONA** |
| Satélites — outros | `satelites/relatorios\|monitoramento\|integracoes` | ❌ | **NÃO FUNCIONA** |
| Empresas — Grupos | `grupos`, `grupos/:id` | ❌ | **NÃO FUNCIONA** |
| Empresas — Certificado/NFC-e | `empresas/:id/certificado`, `.../nfce-token`, `.../testar-email` | ❌ | **NÃO FUNCIONA** |
| Financeiro — DRE | `financeiro/dre` | ⚠️ existe em `relatorios/dre` | **QUEBRA (path)** |
| Financeiro — Conciliação | `financeiro/conciliacao` | ❌ | **NÃO FUNCIONA** |
| Produtos — config | `produto-config/classes\|unidades` | ❌ | **NÃO FUNCIONA** |
| Produtos — preços em lote | `produtos-precos/preview\|aplicar` | ❌ | **NÃO FUNCIONA** |
| Produtos — estoque | `produtos/:id/estoque` | ❌ | **NÃO FUNCIONA** |
| Estoque — requisições/fechamentos | `estoque/requisicoes`, `estoque/fisico/:id/efetivar`, `estoque/fechamentos/abrir` | ❌ | **NÃO FUNCIONA** |
| Cheques recebidos (edição) | `cheques/recebidos/:id` (PUT/DELETE) | ❌ | **QUEBRA parcial** |

**Telas que FUNCIONAM** (endpoint existe): Clientes, Pedidos (CRUD/kanban/situações),
Produtos (CRUD), Estoque (saldos/entrada/saída/acerto/transferências), Financeiro
(lançamentos/planos/centros), Caixa (contas/abrir/fechar/baixar/transferir), Boletos,
PIX (config), Convênios, Vale-gás, Comodatos, Geográfico, Cadastros de apoio,
Empresas (CRUD/config), Monitora.

### 3.2 RBAC quebrado fim-a-fim (evidência)

- `AuthController` `/me` retorna **apenas `support`** (l.53) — **NÃO retorna `roles`/`permissions`**.
- A SPA (`auth.tsx` l.48-49) lê `u.roles`/`u.permissions` → **sempre vazios**.
- `can()` (auth.tsx l.119-123): se não for `support`, `permissions.includes(...)` → sempre `false`.
- **Consequência:** apenas usuário `support=true` enxerga qualquer coisa; RBAC real não opera.

---

## 4. Auditoria de seeds

- **Existem 2 seeders** no novo: `DeployAdminSeeder` (1 grupo + 1 empresa + 1 admin) e
  `DatabaseSeeder` (orquestrador). **Nenhuma tabela de negócio é populada.**
- **A homologação está praticamente vazia** (só admin + usuário de teste).
- Não há cobertura para: cenários válidos/inválidos/extremos, volume de carga, nem
  relacionamentos completos. Plano de seeds detalhado já existe em `PLANO_SEEDS.md`
  (fora do escopo de "verdade do código", mas referenciado como pendência).

**Veredito SEEDS — INSUFICIENTE para homologação completa.**

---

## 5. Respostas às 13 perguntas (com evidência)

| # | Pergunta | Resposta | Evidência |
|---|---|---|---|
| 1 | O erp-novo substitui o ctrl-web? | **NÃO** | 8,4% do LOC de negócio; fiscal fake; 12 domínios sem banco |
| 2 | Percentual REAL de aderência? | **~30–40%** (núcleo); **~10–15%** se contar a cauda legal/operacional | LOC 8,4%, tabelas 32%, controllers 11%, fiscal ~2% |
| 3 | Módulos completos? | **Nenhum 100%.** Próximos: Cliente, Produto, Estoque, Convênio/Vale-gás/Comodato, Monitora/GPS | Services + rotas + Resources existem e fecham fluxo |
| 4 | Parcialmente migrados? | Pedido, Financeiro, Caixa/Cheque, Fiscal (cálculo), Boleto/PIX, Empresa | seções 1.1–1.4 |
| 5 | Não começaram? | RH/Colaborador, Frota/Veículos, SPED, NF recebida, Conciliação/OFX, Pós-venda/CRM, Checklist, Promoção/Sorteio/Metas, Cupom SAT/CFe, Inventário, Documentos/Bens, Menu dinâmico | seção 2 |
| 6 | Regras faltando? | Matriz operação×estoque/financeiro; parcelamento por condição; cartão/NSU; Gás do Povo; bloqueio caixa fechado; CP de cartão; cheque-troco/encontro de contas; ICMS-ST/DIFAL/FCP/IBS/CBS/IBPT; SPED; comissão | seções 1.1–1.3 |
| 7 | Integrações faltando? | SEFAZ real (só fake), assinatura/certificado, SPED, conciliação OFX, estorno de pagamento online, e-mail (SMTP testar) | seções 1.3, 3.1 |
| 8 | Telas que não funcionam? | Fiscal NF-e/SPED/operações, Colaboradores, Veículos, Satélites, Grupos, Conciliação, DRE (path), produto-config, produtos-precos, estoque requisições/fechamentos, certificado/NFC-e | seção 3.1 |
| 9 | Endpoints que quebram fluxos? | **36 de 75 (48%)** retornam 404 / contrato divergente | seção 3.1 (cruzamento) |
| 10 | O banco suporta a operação? | **NÃO** — 69/213 tabelas; faltam ~12 domínios | seção 2 |
| 11 | Seeds permitem homologação completa? | **NÃO** — só admin/teste; nenhuma tabela de negócio populada | seção 4 |
| 12 | Pronto para **homologação**? | **NÃO** (parcial só do núcleo, e ainda com 48% de endpoints quebrados + RBAC inoperante + seeds vazias) | seções 3, 4 |
| 13 | Pronto para **produção**? | **NÃO** — fiscal não emite documento válido (driver fake), caixa sem trava de fechado, comissão/RH/frota ausentes | seções 1.2, 1.3 |

---

## 6. Prova de cobertura da auditoria (transparência)

**Arquivos efetivamente abertos e lidos linha-a-linha** (não apenas listados):
- `erp-novo/app/Domain/Pedido/PedidoService.php` (193 l.) — fluxo de pedido completo.
- `erp-novo/app/Domain/Financeiro/FinanceiroService.php` (métodos `gerarDoPedido`/`criar`/`reparcelar`).
- `erp-novo/app/Domain/Caixa/CaixaService.php` (231 l.) — todos os métodos.
- `erp-novo/app/Domain/Caixa/ChequeService.php` (assinaturas).
- `erp-novo/app/Domain/Fiscal/CalculoImpostoService.php` (64 l.) — algoritmo completo.
- `erp-novo/app/Domain/Fiscal/FiscalService.php` + `Drivers/FakeSefazDriver.php`.
- `erp-novo/frontend/src/lib/auth.tsx` (RBAC/login) + 10 `features/*/api.ts` (endpoints).
- `erp-novo/routes/api.php` + `console.php` (rotas e schedule).
- `ctrl-web/app/Http/Controllers/PedidoController.php` (mapa de 47 métodos + l.61-150, 808-967).
- `ctrl-web/app/Processors/caixaProcessor.php` (mapa de métodos) e `ChequeProcessor.php`.
- `ctrl-web/app/Processors/Nfe/Tributacao/CalculoImposto.php` (mapa + tributos por grep).
- `ctrl-web/app/Console/Kernel.php` (schedule).

**Análises por varredura (grep/find) — agregado, não por leitura individual:**
- 2.509 arquivos PHP do legado (contagem); 144 processors, 203 models, 271 controllers.
- 213 vs 69 tabelas (cruzamento de `Schema::create`).
- 75 endpoints da SPA × 96 rotas do backend (cruzamento automático).

### Módulos NÃO analisados linha-a-linha (limitação declarada)

Por **volume** (impraticável abrir ~160k LOC do legado individualmente nesta sessão), os
seguintes foram auditados **por amostragem dirigida + grep estrutural**, não lidos integralmente:
- **SPED** (119 arquivos / 10.523 LOC do legado) — confirmado ausente no novo por grep, mas
  a lógica interna dos Reg* não foi lida método-a-método.
- **TagMaker / MakeXml** (geração de XML, ~2.300 LOC) — confirmados ausentes; conteúdo não lido.
- **MobileAppProcessor** (1.056 LOC), **TagMaker** (1.761), **financeiroProcessor** (793),
  **BoletoProcessor** (781) — mapeados por assinatura/grep; fluxo interno não rastreado integralmente.
- **app-gas-em-casa/** (app mobile, fora do par ctrl-web×erp-novo) — não auditado.
- **271 controllers do legado** — auditados os de pedido/caixa/fiscal; os demais por nome+grep.

> **Conclusão metodológica:** as conclusões de **ausência** (fiscal real, SPED, RH, frota,
> conciliação, 36 endpoints) são **fortes** — baseadas em grep que retorna zero ocorrência no
> código novo. As conclusões de **divergência de regra** (pedido/caixa) são baseadas em
> **leitura direta** dos métodos relevantes nos dois lados. As conclusões sobre a **profundidade
> interna** dos processors fiscais legados (quão fiel seria um porte) exigiriam leitura
> linha-a-linha dos ~19k LOC fiscais — declarada aqui como pendente.

---

## 7. Veredito final

**O erp-novo é um núcleo transacional bem construído (saldo/estoque auditáveis, locks,
máquina de estados, testes) — mas cobre ~30–40% do legado no melhor caso e NÃO está pronto
nem para homologação plena nem para produção.** Os três bloqueios mais graves, por código:

1. **Fiscal é fake** (`FakeSefazDriver`) e simplificado (`base*aliq`) — sem XML, SPED, ST,
   DIFAL, IBS/CBS, IBPT, certificado. **Bloqueio legal absoluto para produção.**
2. **48% dos endpoints da SPA não existem** + **RBAC inoperante** (`/me` sem roles/permissions)
   — a maioria das telas novas quebra ou só funciona como `support`.
3. **Banco e seeds incompletos** — 12 domínios sem tabela; homologação vazia.

Próximo passo natural (não executado — auditoria é o entregável): alinhar o contrato
SPA×backend (destrava telas do núcleo) e tratar o fiscal como porte 1:1 do legado, não reescrita.
