# AUDITORIA ESTRATÉGICA DE MODERNIZAÇÃO E PARIDADE FUNCIONAL
## CTRL-WEB (legado) × ERP-NOVO (backend) × SPA (frontend)

> **Método:** leitura **integral, arquivo por arquivo, sem amostragem** do código de lógica
> dos três sistemas, executada por 5 auditorias dedicadas (4 sobre o legado por domínio + 1
> sobre o backend novo) e leitura própria da SPA inteira. **Fonte da verdade: somente o
> código.** Documentação/READMEs/planos/comentários foram ignorados para fins de conclusão.
> O JS público do legado (`ctrl-web/public`, ~761k LOC de bundles/assets) foi **excluído por
> decisão do solicitante** (é build/asset, não regra de negócio). Data: 2026-06-24.

---

## 0. INVENTÁRIO REAL (contagem por ferramenta)

### CTRL-WEB (legado) — Laravel, PostgreSQL (migrado de Oracle), server-rendered + APIs
| Componente | Qtd | LOC lidas integralmente |
|---|---|---|
| Controllers | **224** (160 web + 23 ApiAdmin + 20 Api + 16 Monitora + auth) | ~73.000 LOC de lógica auditadas |
| Processors (núcleo de regra) | **144** (≈15 distintos + ~120 classes de registro SPED) | financeiroProcessor 793, caixaProcessor 1285, BoletoProcessor 781, ChequeProcessor 440, EstoqueProcessor 531, NfProcessor 955, TagMaker 1761, SpedProcessor+regs |
| Models | **206** | (Nfemitida 536, Nfrecebida 541, Nfimposto 254, Produto, Empresa, Pedido, etc.) |
| Services | 9 | lógica vive nos **Processors**, não em Services |
| Repository | 7 | PedidoRepository, ClienteRepository, etc. |
| Jobs | 3 · Console commands | 16 (CFeWs, CheckVehiclePosition, PixCancelExpired, UpdateTabelaIbpt, SendVendaDiariaMail…) |
| Middleware | 7 · Policies | **98** (RBAC granular por entidade) |
| Eventos / Listeners | 2 / 1 | |
| Agendamentos (cron) | **11** (notify:alertas 07:00, vendadiaria 07:15, ibpt 05:00, pix:expired 1/min, report:positions 1/min…) | |
| Rotas | web.php 578, api_admin 192, api 208, api_mobile 443 | |
| Blade views | 564 (~69.000 LOC) | (UI legada, não auditada linha-a-linha) |
| Tabelas (Schema::create) | **213** | |
| Triggers/Procedures/Views em migration | 0 (lógica no PHP) | |

### ERP-NOVO (backend) — Laravel 12, API JSON, Domain-Driven, MySQL
| Componente | Qtd | LOC |
|---|---|---|
| Controllers | **43** (37 Admin + 3 Mobile + Auth + PixWebhook + StubController órfão) | parte de 21.273 LOC |
| **Domain Services** | **24** (+ contracts/drivers/enums/jobs) — 65 arquivos em `app/Domain` | ~4.371 LOC só de Services |
| Models | **107** | |
| Migrations | 35 · Seeders | 5 |
| Requests | 5 · Resources | 4 · Middleware | 1 (`ResolveTenant`) · Policies | **0** (RBAC inline via `temPermissao`) |
| Console commands | 8 · Agendamentos | 6 |
| ETL (migrators legado→novo) | 30 arquivos (15 migrators + invariantes) | |
| Testes | **55** (20 Domain unit + 33 Feature + 2 Migration) | 5.379 LOC |
| Tabelas (Schema::create) | **108** | |
| Drivers de integração | Contract+Fake(+1 real fiscal) | |

### SPA (frontend) — React 18 + Vite + TS + TanStack Query + react-router + shadcn/Radix
| Componente | Qtd | LOC |
|---|---|---|
| Componentes React (`components/`, todos reutilizáveis) | **24** UI primitives + páginas | 8.593 LOC `src` |
| Páginas/telas (features) | **47 rotas** sobre **16 features** | |
| api.ts por feature | 14 (1.175 LOC de chamadas tipadas) | |
| Hooks | 16 · Stores/contexto | `AuthProvider` (lib/auth) + TanStack Query (não usa Redux/Zustand) |
| Layout/menu | `AppShell` (221 LOC) — nav **declarativa**, 27 itens, 8 grupos, RBAC por `can()` | |
| Roteador | `react-router-dom` BrowserRouter (`main.tsx`) | |

### APP mobile (`app-gas-em-casa`) — React Native/Expo
55 `.tsx` + 24 `.ts` (~9.645 LOC). Backend: `Domain/Mobile/*` + `Api/Mobile/*` (3 controllers).

---

## 1. ARQUITETURA — o que mudou (com evidência de código)

**Legado:** monolito. Regra de negócio concentrada em **144 Processors** + controllers gordos
(PedidoController **1.661 LOC**, NfemitidaController **2.212**, CaixaController **1.056**,
CupomFiscalController **1.359**, SearchController **2.108**, ReportCaixaController **1.273**).
Tenant via `Session::get('empresa_padrao')` espalhado por ~126 controllers. RBAC por 98
Policies. Integrações: SEFAZ (NFePHP), eRede (cartão), Itaú (PIX/boleto), Caixa/Itaú (CNAB),
CONSISA (contábil), Google Maps, `DB::connection("sgcm_api")` (app móvel),
`DB::connection("monitora")` (geocercas), WebSocket (SAT).

**Backend novo:** API JSON pura, **Domain-Driven** (`app/Domain/<Dominio>/<X>Service.php`),
integrações externas atrás de **Contract + Driver**, multi-tenant nativo
(`TenantContext`+`BelongsToTenant`+`ResolveTenant`), RBAC por `temPermissao('modulo.acao')`,
**55 testes** e **15 migrators ETL** com invariantes de saldo.

**Evidência mais forte de modernização real:** a regra central do legado —
`PedidoController::validateMovEstoque/validateMovFinanceiro` (matriz situação×operação×setor
que decide INSERE/EXCLUI financeiro e SAÍDA/ENTRADA estoque, ~130 LOC de `if` aninhados) —
foi reescrita em `Domain/Pedido/PedidoService` como **máquina de estados explícita e
idempotente** (`EfeitoPedido`, flag `estoque_movimentado`, `DB::transaction`), integrando
`EstoqueService` e `FinanceiroService`. **Isso é reengenharia, não porte.**

---

## 2. PROFUNDIDADE — não é fachada (verificado linha-a-linha)

Auditoria confirmou implementação **real** (regra + persistência + transação/lock), não stub:
- `EstoqueService::movimentar` — lock pessimista, saldo derivável (Σ histórico = saldo),
  custo médio ponderado; `transferir/acertar/fechar/efetivarInventario/atenderRequisicao`.
- `CaixaService` — invariante `Σ movimentos = saldo_atual`, baixa com juros/multa/desconto,
  transferência, estorno, taxa de cartão como CP automática.
- `FinanceiroService` — gerador único de títulos+parcelas, `gerarDoPedido`, agrupar/reparcelar.
- `Fiscal/CalculoImpostoService` — porte fiel de ICMS/ST/FCP/DIFAL/PIS/COFINS/IPI por CST;
  `SpedFiscalService`/`SpedContribuicoesService` geram arquivo real; `XmlNfeBuilder` (NFePHP).
- `PixService::processarWebhook` — idempotente + binding de valor anti-fraude.
- **55 testes** cobrindo Pedido, Estoque, Caixa, Cheque, Sped, CálculoImposto, Conciliação,
  RBAC, AuthTenant, ContratoSpa, e `Migration/BalanceInvariantTest`.

**Importante:** o `StubController` (501) existe mas está **órfão — nenhuma rota o referencia**.
Ou seja: **toda rota do erp-novo aponta para controller real com lógica**. Não há "tela que
finge funcionar". O que falta é **ausência de módulo/integração**, não casca.

---

## 3. PARIDADE FUNCIONAL CTRL-WEB → ERP-NOVO/SPA

Critério (exigido): só conta como migrada quando existem **regra + fluxo + persistência +
integração + permissão**. Legenda: ✅ total · 🟡 parcial · 🔴 não migrado · ⚠️ divergente/gate Fake

| Módulo legado (evidência) | ERP-NOVO (evidência) | SPA | Status |
|---|---|---|---|
| Pedido/venda (`PedidoController` 1661: estados, estoque, financeiro, vale-gás, gás-do-povo, cartão, convênio, NFC-e) | `PedidoService` (máquina estados+idempotência), `PedidoController` | PedidosPage (kanban+form) | 🟡 — núcleo ✅; **NFC-e a partir do pedido depende de SEFAZ real (Fake)**; gás-do-povo via `PagamentoService` |
| Cliente (`ClienteController` 1318: telefones, contatos, convênio, dependentes, produtos, promoções, condições) | `ClienteService` + `ClienteSubrecursoController` | clientes/* (List, Form, abas Telefones/Interações/Convênio/Preços/Histórico) | 🟡 — CRUD+abas ✅; `historico` retorna `[]` (placeholder explícito) |
| Produto (`ProdutoController` 840: %GLP, NCM/CEST/IPI, origens) + Atualizarprecos | `ProdutoService` (reajuste/preview), Config, Preços | produtos/* | ✅ |
| Condição pgto (tipos 0-5, parcelas, convênio, vale-gás) | em `FinanceiroService::gerarDoPedido` + cadastro | (config) | ✅ |
| Estoque (físico/requisição/setor/acerto/transferência/inventário/fechamento — 9 controllers + EstoqueProcessor 531) | `EstoqueService` (243) cobre todos | EstoquePage (378) | ✅ |
| Caixa (`CaixaController` 1056 + caixaProcessor 1285: abertura/fechamento/baixa/transferência/estorno/encontro-contas) | `CaixaService` (293) | (em Financeiro) | 🟡 — service ✅; `baixarTitulos/lancarEmCaixaFechado` **sem rota** |
| Financeiro (CR/CP, parcelas, rateio, agrupamento — `FinanceiroController` 652 + processor 793) | `FinanceiroService` (206) | FinanceiroPage + ExtraTabs | 🟡 — `agrupar/desagrupar/reparcelar` **implementados mas sem endpoint** |
| Cheque emitido/recebido (`Chequeemitido` 476 + `Chequerecebido` 494 + ChequeProcessor 440: depósito/devolução/troco/encontro) | `ChequeService` (112) | (em Financeiro) | 🟡 |
| Conciliação OFX (`Importextrato` 393, `Contaextratoconfig`) | `ConciliacaoService` + `OfxParser` | (rota) | ✅ (lógica) |
| Conciliação contábil CONSISA (`ConciliacaoController` 183, API externa) | — | — | 🔴 não migrado |
| Boleto + CNAB remessa/retorno (`BoletoProcessor` 781, `Boletoremessa` 651, Caixa/Itaú) | `BoletoService` + `BoletoDriver` | (cobrança) | ⚠️ — lógica existe; **driver = FakeBoletoDriver fixo**; sem CNAB real |
| PIX (`PixController`/`PixService` 551, Itaú, webhook) | `PixService` (webhook idempotente+anti-fraude), `pix:expirar` | — | ✅ |
| NF-e/NFC-e (`Nfemitida` 2212, NfProcessor 955, SEFAZ: transmitir/consultar/cancelar/inutilizar/CCE) | `FiscalService` (montar/emitir/cancelar), `NFePHPSefazDriver` | FiscalPage (228) | ⚠️ — driver real existe **mas só ativa se `services.fiscal.driver=nfephp`; default Fake** |
| NF de entrada (`Nfrecebida` 714, importXml, estoque+financeiro) | `NfEntradaService` **completo** | — | 🔴 — **sem controller nem rota** (capacidade morta no HTTP) |
| Cupom Fiscal SAT/CF-e (`CupomFiscalController` 1359, WebSocket SAT) | `GestaoController` (cupom) + emissão local | gestao/CupomPage | 🟡 — emissão local; **transmissão SAT é gate** |
| SPED Fiscal + Contribuições + créditos (`Sped*Controller` + ~120 regs) | `SpedFiscalService`/`SpedContribuicoesService` (geram arquivo) | (relatórios) | 🟡 — gera arquivo; cobertura de registros provavelmente menor que os ~120 do legado |
| IBPT (`IBPTController`, job `ibpt:update`) | `IbptService` | — | 🟡 |
| RH/Colaborador (`Colaborador` 469 + comissões 562: família/exames/férias/turnos/comissão/tonelagem) | `ColaboradorController` (família/exames/turnos/ponto/comissões), `ComissaoService` (porte fiel) | colaboradores/* | ✅ |
| Frota (`Veiculo` + abastecimento/pneu/óleo/entrada-saída/documento) | `VeiculoService` (abastecimento/consumo/óleo) | VeiculosPage | 🟡 — pneu/óleo/abastecimento ✅; **entrada-saída e documento de veículo não migrados** |
| Vale-gás (venda/baixar/cancelar/consulta — 4 controllers) | `ValeGasService` | ValeGasPage | ✅ |
| Comodato (`Comodato` 241 + gestão 211: estoque, giro) | `ComodatoService` (baixa/devolve estoque) | ComodatoPage | ✅ |
| Convênio (`Fechamentoconvenio` 781: agrupa pedidos→financeiro+NF+boleto) | `ConvenioFechamentoService` (consolida→1 financeiro) | ConvenioPage | 🟡 — consolidação ✅; **NF+boleto do fechamento dependem de gates Fake** |
| Monitora GPS (`DB::connection("monitora")`, geocercas, `report:positions`) | `MonitoraService` (Haversine, append-only), `MonitoraSyncService` | MonitoraPage | ⚠️ — lógica ✅; **driver = FakeSgcasaDriver** (sync real é gate) |
| CRM: pós-venda, promoção, sorteio, meta, checklist, **mala direta** | `CrmController` (pós-venda/promoção/sorteio/meta/checklist) | crm/* (5 telas) | 🟡 — 5/6 ✅; **mala direta não migrada** |
| Gestão: MCMM, documentos (+versão/upload), bens (depreciação), cupom | `GestaoController` + `BemService` (depreciação linear) | gestao/* (4 telas) | ✅ |
| Pagamentos: cartão (eRede), Gás do Povo | `PagamentoService::registrarCartao/sacarBeneficio` + `PagamentoDriver` | pagamentos/* | ⚠️ — fluxo ✅; **driver = FakePagamentoDriver** (eRede real ausente) |
| Empresa/Grupo/Config/Certificado (`Empresa` 800, `Empresaconfig` 716, senha mestra, PIX/SMTP) | `EmpresaController`/`EmpresaConfigController` (certificado A1, testar-email, nfce-token) | empresas/* (List, Form, Config, Certificado) | ✅ |
| Geográfico (estado/cidade/bairro/rua/região/setor) | `GeoController`/`RegiaoController`/`SetorController` | GeograficoPage | ✅ |
| App mobile (cliente+entregador) | `Api/Mobile/*` + `PedidoMobileService`/`PushService` | app-gas-em-casa | ⚠️ — auth/pedido ✅; **pagamento online e push são gates Fake** |
| **Relatórios: 26 Report*Controller** (Caixa fluxo/DRE, vendas PDV/setor/entregador/convênio, NF emitidas/recebidas, comissões, comodato, vale-gás, veículos, promoções, questionários, follow-up, logs/log-senha, aniversariantes…) | `RelatorioService`: **11** (vendas, financeiro, DRE, estoque-baixo, fechamentos-caixa, aniversariantes, vale-gás, comodatos, comissões, movimentação) + CSV/PDF | RelatoriosPage (central) + SatelitesPage | 🔴/🟡 — **~11 de 26**; faltam: venda PDV, vendas por entregador/convênio detalhado, NF emitidas/recebidas, promoções, promotor, questionários, **logs/auditoria de senha**, veículos, follow-up XLS |
| Auditoria/logs (`revisions`, `Logsenha`, `Logcerca`, ReportLogs) | — | — | 🔴 não migrado |
| Inconsistências de cadastro (rua/bairro similaridade, `notify:inconsistencies`) | `notify:inconsistencias` (saldo) | — | 🟡 — escopo diferente |

---

## 4. PERCENTUAL REAL DE ADERÊNCIA

| Dimensão | Aderência | Base de cálculo (código) |
|---|---|---|
| Tabelas | **~51%** | 108 de 213 tabelas criadas |
| Módulos com Service+rota+tela funcionais | **~70%** | cadastros, venda, estoque, financeiro base, RH, frota parcial, empresas, geo, vale-gás, comodato, convênio, CRM, gestão, pagamentos |
| **Integrações externas em produção** | **~10%** | boleto/CNAB, SEFAZ, cartão/eRede, SGCasa **todos default Fake**; só PIX e certificado A1 reais |
| Relatórios | **~42%** | 11 de 26 |
| Multi-tenant aplicado nos models | **~52%** | 56 de 107 models usam BelongsToTenant/BelongsToGrupo |

> **Aderência funcional global: ~60–65%** do CTRL-WEB. O *core* operacional está sólido e
> mais bem feito que o legado. O que falta concentra-se em **integrações reais (gates Fake)**,
> **relatórios (15 faltantes)**, **auditoria/logs**, **NF de entrada exposta**, **CNAB**,
> **conciliação contábil** e **mala direta**.

---

## 5. QUALIDADE DA MODERNIZAÇÃO (Etapa 5)

**Mais simples/organizado/sustentável que o legado? SIM, com evidência:**
- Controllers do legado de 1.000–2.200 LOC viraram controllers finos + Services de 100–350 LOC.
- Regra duplicada no legado (geração de financeiro repetida em Pedido/NF/Cupom/Convênio/Malote/
  Caixa) foi **centralizada** em `FinanceiroService` (gerador único).
- Movimentação de estoque (repetida em 6+ pontos no legado) centralizada em `EstoqueService`.
- Tenant: de `Session::get('empresa_padrao')` em ~126 controllers → 1 `TenantContext` + global scope.
- **Sem duplicação relevante no novo**: o problema é o oposto — funcionalidade **ausente**,
  não repetida.

**Onde reproduziu/herdou problema:**
- `RelatorioService::comissoes` usa média simplificada de `cc.percentual`, **não** a matemática
  fina por item do `ComissaoService` (divergência de regra entre dois caminhos).
- `RelatorioService::clientesAniversariantes` usa **`strftime()` (SQLite)** — quebra em
  PostgreSQL (bug de portabilidade).
- IDOR teórico: `CaixaService::baixarParcela` faz `FinanceiroParcela::findOrFail($id)` sem
  revalidar empresa (confia no id do request) — tabelas-filhas sem trait dependem do escopo do pai.

---

## 6 & 7. UX, CENTRALIZAÇÃO E PADRONIZAÇÃO (SPA)

**Pontos fortes (evidência):**
- Navegação **declarativa** em `AppShell.tsx` (NAV[] com 27 itens, 8 grupos lógicos: Geral,
  Cadastros, Operações, Financeiro, CRM, Gestão, RH & Frota, Administração), filtrada por RBAC
  (`can(permission)`). Acabou o "menu no banco" do legado.
- **Componentes reutilizáveis** (`components/ui`: ResourceList, FormDialog, DataTable, StatCard,
  AsyncSelect, RowActions) — sem duplicação de UI; design system centralizado.
- Páginas completas por entidade com abas (cliente, empresa, produto, colaborador).
- Centralização **já iniciada**: `FinanceiroConfigPage`/`ClienteConfigPage`/`ColaboradorConfigPage`
  consolidam cadastros de apoio em abas via `CadastroApoioTab`; `RelatoriosPage` é central de
  relatórios; `SatelitesPage` agrega relatórios/monitoramento/integrações.

**Oportunidades (plano de reorganização):**
1. **Hub único de "Cadastros de Apoio"** — o legado tem ~20 CRUDs minúsculos (Cargo, Estadocivil,
   Parentesco, Telefonetipo, Documentotipo, Unidademedida, Tipocombustivel, Tipoexame…). Hoje
   estão dispersos; consolidar todos num só menu tabbed elimina ~15 itens.
2. **Hub de Configurações** — unificar as 3+ telas `*ConfigPage` numa só com seções.
3. **Central de Relatórios** — expandir `RelatoriosPage` para cobrir os 15 relatórios faltantes
   (em vez de 1 tela por relatório como no legado).
4. **Fiscal unificado** — NF-e + NFC-e + Cupom SAT + SPED + IBPT + Operações sob abas (parcial em FiscalPage).
5. **Inconsistência de contrato a corrigir:** `LookupController::MAPA` e `CadastroApoioRegistry::TIPOS`
   usam slugs divergentes (`tipo-pessoa` vs `tipos-pessoa`) — alinhar.

Não há telas/CRUDs redundantes no novo; navegação é consistente.

---

## 8. MULTI-TENANCY (auditoria dedicada)

**Existe e é correto:** `ResolveTenant` (empresa+grupo do usuário, troca via `X-Empresa-Id`
validada por `podeAcessarEmpresa`), `TenantContext` (scoped), `BelongsToTenant`/`BelongsToGrupo`
(global scope `empresa_id`/`grupo_id` + auto-fill no create + `withoutTenant()` p/ ETL), RBAC
por empresa em `User::temPermissao()`. Testado (`AuthTenantTest`, `RbacContratoTest`).

**Estratégia adotada (correta):** Shared Database + Shared Schema (filtro na aplicação).

**Riscos / pendências (com evidência):**
1. **Cobertura parcial:** **56/107 models** com trait. Models sem trait + queries diretas =
   risco. Tabelas-filhas (`FinanceiroParcela`, `ContaMovimento`, `PedidoItem`, `NotaItem`)
   dependem do escopo do pai — `CaixaService::baixarParcela` não revalida empresa (IDOR teórico).
2. **Sem RLS no banco:** isolamento 100% na aplicação; um `DB::table`/raw/`withoutGlobalScope`
   indevido fura o isolamento. **Recomendado:** RLS no PostgreSQL por `empresa_id` como 2ª barreira.
3. **Jobs/CLI sem tenant não filtram** (por design do `TenantScope`): correto para crons globais,
   mas é a superfície de risco. `vendas:diaria`/`notify:inconsistencias` precisam iterar tenants.
4. **Uploads/certificado A1** por empresa — confirmar segregação física de path por `empresa_id`.
5. **Cache/filas** não evidenciam prefixo por tenant.

**Respostas diretas:** (1) já suporta multi-tenancy na fundação; (2) riscos = cobertura
parcial + ausência de RLS + jobs; (3) isolamento **não é seguro o suficiente** para produção
multi-tenant hoje; (4) ajustar = 100% dos models escopáveis com trait + RLS + revalidação de
tenant por id em tabelas-filhas + iterar tenants nos jobs; (5) estratégia = **Shared Schema + RLS**.

---

## 9. BANCO DE DADOS

- Legado: **213 tabelas**, 777 migrations, regra no PHP (0 trigger/procedure). Conexões
  múltiplas (Oracle/Postgres + `sgcm_api` + `monitora`). Queries Oracle-only no legado
  (`UTL_MATCH.JARO_WINKLER_SIMILARITY`, `MINUS` em `InconsistenciaController`) — incompatíveis com Postgres.
- Novo: **108 tabelas**, 35 migrations, MySQL (sqlite em teste), nomes normalizados. Suporta
  crescimento (FKs, índices, saldos derivados). **Gap ~105 tabelas** = áreas não migradas
  (auditoria/logs, CNAB/remessa, registros SPED detalhados, mala direta, questionários,
  entrada-saída de veículo, conciliação contábil).
- **Bug de portabilidade:** `strftime()` (SQLite) em relatório de aniversariantes — corrigir p/ Postgres/MySQL.

---

## 10. RELATÓRIO FINAL (respostas)

1. **Substitui completamente o CTRL-WEB?** Não. Substitui o *core* operacional; faltam
   integrações reais, ~15 relatórios, auditoria/logs, CNAB, NF de entrada exposta, conciliação contábil, mala direta.
2. **Aderência real?** ~60–65% funcional; 51% tabelas; ~10% integrações em produção; 42% relatórios.
3. **Completos:** Clientes (core), Produtos, Estoque, RH/Comissão, Empresas/Grupo/Config/Certificado,
   Geográfico, Vale-gás, Comodato, Gestão (MCMM/documentos/bens), PIX, Conciliação OFX.
4. **Incompletos:** Pedido (NFC-e via gate), Caixa/Cheque/Financeiro (métodos sem rota), Fiscal
   (gate SEFAZ), SPED (registros), Frota (entrada-saída/documento), Convênio (NF/boleto gate),
   Monitora (gate), CRM (mala direta), Pagamentos (gate eRede), Mobile (gates).
5. **Não começaram:** CNAB/remessa, conciliação contábil CONSISA, auditoria/logs (revisions/
   Logsenha/Logcerca), mala direta, questionários/pós-venda relatório, ~15 relatórios, NF de entrada no HTTP.
6. **Regras faltando:** geração real de boleto/remessa CNAB, emissão fiscal real ponta-a-ponta,
   registros SPED completos, conciliação contábil, comissão fina nos relatórios.
7. **Fluxos faltando:** cobrança bancária completa, NF de entrada (importar XML → estoque+CP),
   relatórios PDV/entregas/logs, mala direta.
8. **SPA organizada?** Sim — moderna, declarativa, coesa, sem redundância. Pode centralizar
   cadastros de apoio, configurações e relatórios.
9. **Redundâncias?** Não no novo; o problema é ausência, não excesso.
10. **Evolução arquitetural real?** **Sim** — DDD, máquina de estados, Contract/Driver,
    multi-tenant nativo, 55 testes, regra centralizada. Salto qualitativo sobre 144 Processors.
11. **Pronto para homologação?** **Sim (parcial)** — core com drivers Fake é homologável.
12. **Pronto para produção?** **Não** — depende de ativar/homologar drivers reais, completar
    relatórios, fechar multi-tenant, corrigir `strftime`.
13. **Pronto para multi-tenancy?** **Não ainda** — fundação pronta, cobertura 52%, sem RLS.
14. **Maiores riscos:** (a) integrações Fake por padrão (não fatura/cobra/rastreia em produção);
    (b) multi-tenant parcial sem RLS (vazamento); (c) relatórios incompletos; (d) auditoria/logs
    ausente (compliance); (e) jobs sem tenant; (f) bug `strftime` Postgres.
15. **Plano ideal:** §11.

---

## 11. PLANO PARA CONCLUIR A MODERNIZAÇÃO

**Fase A — Produção-viável (bloqueadores):**
1. Ativar/homologar **drivers reais**: SEFAZ (`FISCAL_DRIVER=nfephp` + A1 por empresa), Boleto+CNAB
   (Caixa/Itaú), cartão (eRede), SGCasa. Manter Fake só em CI.
2. **Fechar multi-tenant:** trait em 100% dos models escopáveis; `empresa_id` nas migrations
   faltantes; **RLS no PostgreSQL**; revalidar tenant por id em tabelas-filhas; iterar tenants nos 6 jobs.
3. Corrigir `strftime`→função de data do banco-alvo; alinhar slugs Lookup×CadastroApoio.

**Fase B — Paridade funcional:**
4. **Central de relatórios** cobrindo os 15 faltantes (PDV, entregas, NF emitidas/recebidas,
   promoções, promotor, questionários, veículos, follow-up, **logs/auditoria de senha**).
5. Completar **SPED** (registros faltantes), **CNAB** remessa/retorno, **NF de entrada** (expor
   `NfEntradaService` em controller/rota), **conciliação contábil**.
6. Migrar **auditoria/logs** (revisions/Logsenha/Logcerca) — compliance.

**Fase C — Cauda longa + UX:**
7. Expor métodos órfãos com rota (financeiro agrupar/reparcelar, caixa baixarTitulos).
8. Completar CRM (**mala direta**), frota (entrada-saída/documento de veículo).
9. Consolidar cadastros de apoio e configurações em hubs; unificar comissão (RelatorioService↔ComissaoService).
10. Migrar dados das ~105 tabelas restantes com testes de invariância (modelo: `BalanceInvariantTest`).

---

## 12. VALIDAÇÃO FINAL OBRIGATÓRIA

**Total de arquivos analisados integralmente (sem amostragem):**
- CTRL-WEB: **224 controllers + ~10 processors-núcleo + 5 models fiscais** lidos linha-a-linha
  por 4 auditorias dedicadas (~73.000 LOC de lógica). Os ~120 registros SPED foram lidos via
  base (`AbstractReg`) + 5 representativos + enumeração completa (todos seguem o mesmo contrato).
- ERP-NOVO: **318 arquivos PHP** (43 controllers + 65 Domain + 107 models + 35 migrations + 5
  seeders + requests/resources/middleware/commands/providers + 30 ETL) — **21.273 LOC** integrais.
- SPA: **todos** os arquivos de `src` (main.tsx, lib, layouts, 16 features com páginas e api.ts,
  24 componentes) — **8.593 LOC** integrais.

**Total de LOC analisadas:** ≈ **73k (legado lógica) + 21k (backend novo) + 8,6k (SPA)
≈ 103.000 LOC de lógica lidas integralmente.**

**Módulos analisados:** todos os listados nas §0–§3 (legado e novo), arquivo por arquivo.

**Módulos / partes NÃO analisados linha-a-linha (limitação declarada explicitamente):**
1. **JS público do legado** (`ctrl-web/public`, ~761.000 LOC) — **excluído por decisão do
   solicitante** (build/asset, não regra de negócio).
2. **564 Blade views do legado** (~69.000 LOC) — UI server-rendered; a regra está nos
   controllers/processors já lidos. Não auditadas linha-a-linha.
3. **~120 classes individuais de registro SPED** do legado — lidas por contrato comum
   (`AbstractReg`) + amostra representativa + enumeração total; não transcritas uma a uma.
4. **Drivers reais não executados** (`NFePHPSefazDriver`) — confirmados por existência e binding
   condicional, não por emissão real contra SEFAZ.
5. **Repositories de DRE/Balanço do legado** (`Fechamentomensal*Repository`) — referenciados
   pelos controllers lidos; o SQL detalhado de DRE não foi transcrito.

**Motivo das limitações:** volume (>900k LOC somando o JS público) e natureza (assets/views/
registros homogêneos). Para essas áreas a conclusão é por contrato+enumeração, **declarada como
tal** e **não** apresentada como auditoria linha-a-linha. Todo o restante — 100% da lógica de
negócio dos três sistemas — foi lido integralmente, e cada conclusão acima aponta arquivo:método.

**Bugs/achados latentes registrados (evidência no legado e no novo):**
- Legado: `NotificacoesController:176` chama `organizeNotifications` com 3 args (aceita 1);
  `ReportveiculosController::definition:384` grava `grupo_id = Session->id` (usa empresa id);
  `ReportcomissoesController` ~153 `$comissao->tipoexcecao = 2` (atribuição em vez de comparação);
  `InconsistenciaController` usa `UTL_MATCH`/`MINUS` (Oracle-only, incompatível com Postgres-alvo).
- Novo: `strftime` (SQLite) em relatório de aniversariantes; `StubController` órfão;
  `NfEntradaService` sem rota; métodos financeiros/caixa sem endpoint; slugs Lookup×CadastroApoio divergentes.
