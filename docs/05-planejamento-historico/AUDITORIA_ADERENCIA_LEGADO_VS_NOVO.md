# Auditoria de Aderência — ctrl-web (legado) × erp-novo + SPA

> **Base da análise:** exclusivamente o código-fonte (controllers, models, migrations,
> services, rotas, processors, jobs, commands, componentes da SPA). Documentações,
> READMEs e specs foram **ignorados**. Datas/percentuais derivam de contagem real de código.

---

## 0. Sumário executivo

| Métrica | ctrl-web (legado) | erp-novo + SPA |
|---|---|---|
| Controllers | **224** | 31 |
| Controllers da API consumida pela SPA (ApiAdmin) | **23** | ~20 (admin) |
| Models | **203** | 63 |
| Tabelas (migrations `create`) | **212** | **~55** (negócio) + infra |
| Processors (regras de negócio) | 10 | 0 (regra migrou p/ Services) |
| Services de domínio | — | **23** |
| Commands (cron/automação) | 18 | 3 |
| Jobs de fila | 3 | 3 |
| Relatórios (`Report*Controller`) | **~35** | 4 (Query Services) |
| Módulos de frontend (features SPA) | — | 12 |

**Veredito (detalhado na Etapa 11):** o erp-novo **cobre o núcleo transacional**
(cliente, produto, estoque, pedido, financeiro, caixa, cheque, fiscal NF-e, cobrança,
satélites, GPS, mobile) com regra de negócio reescrita e testada, **mas NÃO substitui
o legado hoje**. Faltam: módulos inteiros (RH/colaborador, frota, SPED, NF recebida,
pós-venda, promoção, metas, comissões, checklist, sorteio), ~31 dos 35 relatórios, e
há **divergência de contrato entre SPA e backend** em vários endpoints (a SPA foi
trazida do legado e ainda chama rotas que o erp-novo não expõe).

**Aderência funcional estimada: ~35–45%** (núcleo forte; cauda longa ausente).

---

## Etapa 1 — Inventário do sistema legado (ctrl-web)

### 1.1 Superfície de controllers (224 arquivos)
Agrupados por domínio (nomes reais do código):

- **Vendas/Pedido:** Pedido, Pedidosituacao, Pedidooperacao, Pedidomotivoatraso, Vendaativa, VendaAtivaOcorrenciaTipos, Metavenda.
- **Cliente/CRM:** Cliente, ClienteSub, Clientecontato(+tipo/situacao), Clienteproduto, Segmento, Tipopessoa, Posvenda, Posvendacadastro, Maladireta, Promocao, Sorteio, Coupons/Cupons.
- **Produto/Estoque:** Produto, ProdutoCategoria, Produtoclasse, ProdutoConfig, Atualizarprecos, Estoque, Estoquefisico, Estoquerequisicao, Estoquesetor(+acerto), EstoqueTransferencias, Inventario, Unidademedida, Tipocombustivel.
- **Financeiro/Caixa:** Financeiro, FinanceiroGestao, Caixa, Conta, Cheque(+emitido/recebido), Descontocheque, Planoconta, Centrocusto, Condicaopagamento, Contamovimentotipo, Conciliacao, Importextrato, Banco, Layoutbanco.
- **Cobrança:** Boleto, BoletoPdf, Boletoremessa, Ocorrenciasremessas, Pix.
- **Fiscal:** Fiscal, Nfemitida, Nfrecebida, Nfweb, CupomFiscal, Impostonf, Nfcst, Nficms, Nfipi, Nfpis, Nfcofins, Nfclasstrib, Nfgrupofiscal, NfOperacao, Nfsituacao, ConfigNfcePedido, IBPT, Spedfiscal, Spedcontribuicao, Spedcreditos.
- **Convênio/Vale-gás/Comodato:** Conveniogbgestao, Fechamentoconvenio, ValeGas (+baixar/cancelar/consulta/venda), Valegasbaixar, Comodato, Comodatogestao, Fechamentomalote.
- **RH/Colaborador:** Colaborador, Colaboradorcomissoes, Colaboradorfamilia, Cargo, Recessos, Recessotipo, Tiporecessos, Turno, Tipoexame, Setorcolaboradores, Promotor.
- **Frota/Veículos:** Veiculo, Veiculoabastecimento, Veiculodocumento, Veiculoentradasaida, Veiculopneu, Veiculotrocaoleo, Veiculotipo.
- **GPS/Monitora:** Rastreamento, Cerca, Logcerca, Rota, Mcmm, módulo `app/Monitora`.
- **Documentos/Checklist:** Documento, Documentogestao, Documentotipo, Checklist, Cadastrochecklist, Evento, Feriado.
- **Empresa/Config/Auth:** Empresa, Empresabens, Empresaconfig, EmpresasGrupo, Config, Configuracoesgerais, GeneralConfig, ConfigUser, User(s), Role, Auth, Passport, Password, Secret.
- **Geográfico:** Cidade, Bairro, Rua, Regiao, Endereco, Setor.
- **Relatórios (~35):** ReportCaixa, Reportclientes(+aniversariantes), Reportcolaborador, Reportcomissoes, Reportcomodato, Reportconvenio, ReportEntregas, Reportestoque, ReportFinanceiro, Reportlogs(+senha), Reportmovimentacao, Reportnfemitidas, Reportnfrecebida(s), Reportpromocoes, Reportpromotor, Reportquestionarios, ReportResumoVendas, Reportvalegas, Reportveiculos, Reportvendapdv, ReportVendas, Reportvendasmalote, Dashboard, Dashboardgerencial, Inconsistencia.
- **Apps/Integração:** Api, App, Android, Appgiro, Appnotification, Appvideo, Nfweb, OauthClient, Notificacao(es).

### 1.2 Regras de negócio (processors — 10)
`caixaProcessor`, `financeiroProcessor`, `EstoqueProcessor`, `ChequeProcessor`,
`NfProcessor`, `NfeImpostoProcessor`, `SpedProcessor`, `BoletoProcessor`,
`MobileAppProcessor`, `androidProcessor`.

### 1.3 Automação (commands — 18)
CFeWs, CheckInconsistencies, CheckVehiclePosition, DeleteNotificacoes, Notificacao,
NotifyApp, OrderStatus, PixCancelExpired, ProcessIbptFiles, SendDocumentosVencidosMail,
SendRememberMail, SendVendaDiariaMail, UpdateTabelaIbpt, SyncPosicoesSGCasa,
UpdateClientsLocation, MigrateApiModule, MigrateMonitoraModule, Inspire.

### 1.4 Jobs de fila (3)
ProcessAppVideo, ProcessPixPedido, Job base.

### 1.5 APIs
- **ApiAdmin** (23 controllers) — consumida pela SPA.
- **Api** (20 controllers, OAuth/Passport) — app mobile cliente/entregador.
- **Monitora** — GPS/SGCasa.

### 1.6 Banco
**212 tabelas** distintas criadas em migrations.

---

## Etapa 2 — Inventário do erp-novo (backend)

### 2.1 Controllers (31)
Admin: Auth, Empresa, EmpresaConfig, Regiao, CadastroApoio, Geo, Cliente,
ClienteTelefone, ClienteSubrecurso, Produto, Setor, Estoque, Pedido, Financeiro,
FinanceiroCadastro, Caixa, Cheque, Boleto, Pix, PixWebhook, Convenio, ValeGas,
Comodato, NotaFiscal, ConfigFiscal, Monitora, Relatorio.
Mobile: AppAuth, AppCliente, AppEntregador.

### 2.2 Services de domínio (23)
ClienteService, ProdutoService, EstoqueService, PedidoService, FinanceiroService,
CaixaService, ChequeService, BoletoService, PixService, CalculoImpostoService,
FiscalService, CalculoParcelasService, NumeroSequencialService, ConvenioFechamentoService,
ValeGasService, ComodatoService, MonitoraService, MonitoraSyncService,
PagamentoOnlineService, PedidoMobileService, PushService, RelatorioService, CadastroApoioService.

### 2.3 ETL (migração de dados) — presente
Migrators: Estados, Empresas, CadastrosApoio, Geografico, Clientes, Produtos, Estoque,
Pedidos, Financeiro, Caixa, Cobranca, Satelites, Fiscal, Mobile, Monitora.
Invariantes: Count, Sum, Integrity, Balance (Σ movimentos = saldo). Comando `cutover:check`.

### 2.4 Tabelas de negócio (~55)
grupos, empresas, empresa_configs, estados, cidades, bairros, ruas, regioes,
segmentos/tipopessoas/telefonetipos/bancos/contamovimentotipos (apoio), clientes (+telefones,
interacoes, dependentes, precos), produtos (+origens, classes, unidades), setores,
estoquesaldos/historico/fechamentos, pedidos (+itens, situacoes, operacoes, historico),
financeiros (+parcelas, rateios), planos_conta, centros_custo, contas/contamovimentos/
contafechamentos, cheques, boletos (+ocorrencias), remessas_cnab, pix_cobrancas,
notas_fiscais (+itens), config_fiscais, convenios (+fechamentos), vale_gas, comodatos,
app_devices, pagamentos_online, monitora_* (veiculos, posicoes, ultima_posicao, cercas, rotas),
roles/permissions (RBAC), sequencias.

### 2.5 O que JÁ foi migrado (núcleo)
Cadastros base, geográfico, clientes, produtos, **estoque com saldo auditável**,
pedidos com **máquina de estados**, **financeiro a pagar/receber**, **caixa/cheque
(saldo derivável)**, **fiscal NF-e/NFC-e (cálculo de imposto + emissão via driver)**,
boleto/PIX (gates), convênio/vale-gás/comodato, mobile (app cliente/entregador),
monitora GPS, relatórios básicos (vendas/financeiro/DRE/estoque-baixo).

---

## Etapa 3 — Inventário da SPA

### 3.1 Features (12)
auth, cadastros, clientes, dashboard, empresas, estoque, financeiro, fiscal,
geografico, pedidos, produtos, satelites.

### 3.2 Endpoints consumidos pela SPA (extraídos de `api.ts`)
A SPA foi **trazida do legado** e ainda reflete o contrato da ApiAdmin legada. Consome,
entre outros: `/clientes*`, `/produtos*`, `/produto-config/*`, `/produtos-precos/*`,
`/estoque*`, `/financeiro/*` (lançamentos, planos-conta, centros-custo, **dre**,
**conciliacao**), `/caixa/*`, `/cheques/*`, `/boletos*`, `/pix/config`, `/empresas/*`
(+`certificado`, `nfce-token`, `config/testar-email`), `/grupos*`, `/colaboradores/*`,
`/veiculos/*` (+abastecimentos, pneus, trocas-oleo), `/fiscal/nfe*`, `/fiscal/operacoes*`,
`/fiscal/malha*`, `/fiscal/sped`, `/satelites/*`, `/vale-gas*`, `/geo/*`, `/pedidos*`.

> A SPA tem telas de **colaboradores, veículos, satélites, fiscal-operações, sped,
> conciliação, malha fiscal, produto-config, produtos-precos** que o erp-novo **não expõe**.

---

## Etapa 4 — Matriz de aderência (funcionalidade × backend × SPA)

Legenda Status: ✅ Completo · 🟡 Parcial · 🔴 Ausente · ⚠️ Comportamento divergente

| Funcionalidade (legado) | ERP-NOVO | SPA (tela) | Status |
|---|---|---|---|
| Login / Auth | Sim (token+cookie) | Sim | ✅ |
| Empresa + Grupo + troca de tenant | Sim | Sim | ✅ |
| Empresa: certificado A1, nfce-token, testar-email | **Não** | Sim (chama endpoint) | 🔴 backend ausente |
| Cadastros de apoio (7 tipos) | Sim | Sim | ✅ |
| Cadastros de apoio (legado tem ~40) | Parcial (7) | Parcial | 🟡 |
| Geográfico (cidade/bairro/rua) | Sim | Sim | ✅ |
| Cliente + sub-relações | Sim | Sim | ✅ |
| Produto | Sim | 🟡 (`/produto-config/*`, `/produtos-precos/*` ausentes no backend) | 🟡 |
| Estoque (saldo, mov., fechamento) | Sim (auditável) | 🟡 (rotas divergentes: `/estoque/fechamentos/abrir`, `/estoque/fisico/{id}/efetivar`, `/estoque/requisicoes` ausentes) | 🟡 |
| Pedido / Vendas (máquina de estados) | Sim | Sim | ✅ |
| Financeiro a pagar/receber | Sim | Sim | ✅ |
| Financeiro: **conciliação**, **DRE** (rota), importação extrato/OFX | **Não** (DRE existe em `/relatorios/dre`, não em `/financeiro/dre`) | Sim (chama `/financeiro/dre`, `/financeiro/conciliacao`) | ⚠️/🔴 |
| Caixa / Conta / Cheque | Sim | Sim | ✅ |
| Boleto / CNAB | Sim (gate) | Sim | ✅ (gate homolog) |
| PIX | Sim (gate) | Sim | ✅ (gate homolog) |
| Fiscal NF-e/NFC-e | Sim (emitir/cancelar) | ⚠️ SPA chama `/fiscal/nfe`, backend expõe `/notas` | ⚠️ contrato divergente |
| Fiscal: operações, malha fiscal, **SPED**, **NF recebida**, IBPT | **Não** | Sim (telas chamam) | 🔴 |
| Convênio / Vale-gás / Comodato | Sim | 🟡 (telas em `satelites`; rotas parciais) | 🟡 |
| Monitora GPS | Sim | 🟡 (sem feature dedicada na SPA) | 🟡 |
| Mobile (app cliente/entregador) | Sim (API) | n/a (apps externos) | ✅ backend |
| **Colaborador / RH** (comissões, família, recessos, cargo, turno) | **Não** | Sim (telas chamam `/colaboradores/*`) | 🔴 |
| **Frota / Veículos** (abastecimento, pneu, troca óleo, documento) | **Não** (só monitora_veiculos) | Sim (telas chamam `/veiculos/*`) | 🔴 |
| **Pós-venda / Promoção / Sorteio / Mala-direta / Metas** | **Não** | Parcial | 🔴 |
| **Checklist / Documentos / Eventos / Feriados** | **Não** (documentos parcial) | Não | 🔴 |
| **Relatórios (~35)** | 🟡 (4: vendas, financeiro, DRE, estoque-baixo) | 🟡 (dashboard) | 🔴 (cauda) |
| Dashboard gerencial | 🟡 (resumo simples) | 🟡 | 🟡 |
| Cron jobs (18 no legado) | 🟡 (2: notify:alertas, monitora:sync) | n/a | 🟡 |

---

## Etapa 5 — Regras de negócio

### Migradas corretamente (com baseline/testes)
- **Estoque:** saldo derivável de movimentos (`Σ estoquehistorico = estoquesaldos`),
  custo médio ponderado, transferência atômica, acerto, fechamento encadeado. (testes baseline)
- **Caixa:** `Σ contamovimentos = conta.saldo_atual`, baixa com juros/multa/desconto,
  transferência, estorno (reabre parcela). (baseline #1)
- **Pedido:** máquina de estados explícita (PENDENTE/CONCLUIDO/CANCELADO) decide
  baixa/devolução de estoque + geração/estorno de financeiro, com idempotência.
- **Financeiro:** gerador único; Σ parcelas = valor; agrupamento/reparcelamento (enum).
- **Fiscal:** cálculo de imposto portado (ICMS/PIS/COFINS/IPI) com casos de ouro;
  numeração sob lock (anti-duplicidade).
- **Parcelas:** última parcela absorve o centavo.

### Ausentes (sem código no erp-novo)
- **SPED** Fiscal/Contribuições/Créditos (legado: `SpedProcessor` + controllers).
- **NF de entrada (recebida)** + importação de XML.
- **Cálculo de comissões** de colaborador.
- **Conciliação bancária / OFX** (`Importextrato`, `Conciliacao`).
- **IBPT** (atualização de tabela de impostos — `UpdateTabelaIbpt`, `ProcessIbptFiles`).
- **Fechamento de malote / vendas mensais por malote.**
- **Regras de RH** (recessos, família, comissões, turnos).
- **Regras de frota** (abastecimento/consumo, troca de óleo por km, pneus).

### Simplificadas / divergentes
- **Cadastros de apoio:** legado tem ~40 tipos; erp-novo expõe 7.
- **EmpresaConfig:** legado tem ~106 colunas planas; erp-novo usa colunas
  estruturais + JSON `dados` (intencional, mas **certificado A1 e CSC ainda não
  têm endpoint** que a SPA espera).
- **DRE:** existe como relatório (`/relatorios/dre`) mas **não** no path
  `/financeiro/dre` que a SPA chama → quebra na tela.

---

## Etapa 6 — Telas e UX (cobertura SPA × backend)

| Tela (SPA) | Backend pronto | Observação |
|---|---|---|
| Login | ✅ | token+cookie |
| Dashboard | 🟡 | resumo limitado; sem dashboard gerencial |
| Empresas (+config) | 🟡 | falta certificado, nfce-token, testar-email |
| Cadastros de apoio | ✅ | 7 tipos |
| Geográfico | ✅ | |
| Clientes (+abas) | ✅ | histórico depende de Pedidos (ok) |
| Produtos | 🟡 | `produto-config/*` e `produtos-precos/*` sem backend |
| Estoque | 🟡 | requisições/fisico/efetivar/fechamentos-abrir ausentes |
| Pedidos (kanban) | ✅ | |
| Financeiro | 🟡 | conciliação e `/financeiro/dre` ausentes |
| Fiscal | ⚠️ | SPA usa `/fiscal/nfe*`; backend expõe `/notas*` (contrato diferente) |
| Satélites (colaborador, veículo, vale-gás) | 🔴 | colaborador/veículo sem backend |

**Telas do legado SEM equivalente na SPA:** ~35 relatórios, SPED, NF recebida,
pós-venda, promoção, sorteio, mala-direta, metas, checklist, eventos, conciliação,
gestões (FinanceiroGestao, Comodatogestao, Conveniogbgestao, Documentogestao,
Vendasmensaisgestao, Fechamentomensalgestao), Dashboardgerencial.

---

## Etapa 7 — Integração Frontend × Backend (gaps de contrato)

### 7.1 Endpoints que a SPA chama e o backend NÃO expõe (quebram a tela)
- `/produto-config/classes`, `/produto-config/unidades` (a SPA gerencia classes/unidades aqui;
  backend só tem cadastro implícito). 🔴
- `/produtos-precos/aplicar`, `/produtos-precos/preview`, `/produtos/{id}/estoque`. 🔴
- `/estoque/fechamentos/abrir`, `/estoque/fisico/{id}/efetivar`, `/estoque/requisicoes`,
  `/estoque/{rota}` (genérico). 🔴 (backend tem `/estoque/fechamentos`, `/estoque/entrada|saida`)
- `/financeiro/dre`, `/financeiro/conciliacao`. 🔴 (DRE está em `/relatorios/dre`)
- `/empresas/{id}/certificado`, `/empresas/{id}/nfce-token`, `/empresas/{id}/config/testar-email`. 🔴
- `/grupos`, `/grupos/{id}`. 🔴 (não há GrupoController)
- `/colaboradores/*` (lista, comissões, família, recessos). 🔴
- `/veiculos/*` (lista, abastecimentos, pneus, trocas-oleo). 🔴
- `/fiscal/nfe`, `/fiscal/nfe/{id}/transmitir|cancelar`, `/fiscal/operacoes*`,
  `/fiscal/malha*`, `/fiscal/sped`. ⚠️/🔴 (backend usa `/notas*` e `/fiscal/config`)
- `/satelites/integracoes`, `/satelites/monitoramento`, `/satelites/relatorios`. 🔴
- `/cheques/recebidos/{id}` (PUT/DELETE em recebidos): backend tem `/cheques/{id}`. ⚠️

### 7.2 Endpoints do backend SEM uso pela SPA (cobertura ociosa)
- `/notas`, `/notas/emitir`, `/notas/{id}/cancelar`, `/fiscal/config` (a SPA fala `/fiscal/nfe*`).
- `/monitora/*` (a SPA não tem feature de monitora dedicada).
- `/comodatos*`, `/convenios*` (parcialmente; SPA usa `/satelites/*` e `/vale-gas`).
- `/caixa/transferencias`, `/caixa/movimentos/{id}/estornar` (SPA pode não acionar).
- `/estoque/entrada`, `/estoque/saida` (SPA usa rotas diferentes).
- `/relatorios/*` (SPA chama outros paths).

### 7.3 Autenticação / autorização
- ✅ Auth funcional (token Bearer + cookie SPA stateful), validado em homologação.
- ✅ RBAC por `temPermissao('modulo.acao')` com bypass `support`.
- 🟡 SPA espera `/me` com `{roles, permissions}`; backend retorna `{user, tenant}` —
  o adapter normaliza, mas **roles/permissions vêm vazios** (a SPA cai no bypass `is_support`).

### 7.4 CRUD / paginação / filtros / upload / download
- ✅ CRUD + paginação (`{data, meta}`) em clientes/produtos/pedidos/geo/financeiro.
- 🔴 **Upload** (certificado A1, foto, anexos) — sem endpoint no erp-novo.
- 🔴 **Download/Export** de relatórios (PDF/Excel/CNAB/DANFE/SPED) — sem geração de arquivo.

---

## Etapa 8 — Banco de dados (legado × novo)

| Aspecto | Legado | erp-novo |
|---|---|---|
| Tabelas | **212** | ~55 (negócio) + infra |
| Tipo de schema | string-BR (Oracle helpers), arrays posicionais | **limpo** (decimal/boolean nativo, FK declaradas) |
| Saldo | incremental | **derivável de movimentos** (auditável) |
| Migração de dados | — | ETL com invariantes (`cutover:check`) |

### Domínios do legado SEM tabela no erp-novo (evidência por migration)
comissões, checklist, sorteio, posvenda, promoção, metas (metavenda), recessos,
turnos, feriados, eventos, inconsistências, SPED (sped*), NF recebida (nfrecebida),
IBPT, layout banco, importação de extrato, documentos-gestão completa, colaborador-RH
(família/comissão/cargo/exame), frota completa (abastecimento/pneu/óleo/documento de veículo),
mala-direta, posvenda-questionário.

> **Relacionamentos quebrados / órfãos:** não há banco novo populado em produção
> (homologação tem só admin+teste). Integridade é validada **no ETL** (IntegrityInvariant),
> não no banco vazio atual.

---

## Etapa 9 — Geração de dados de teste (seeds)

**Estado atual:** o erp-novo tem apenas `DeployAdminSeeder` (admin+empresa) e
factories de teste unitário. **Não há seed abrangente** cobrindo todas as ~55 tabelas
de negócio. Veja `PLANO_SEEDS.md` (gerado) com o desenho dos seeds por domínio
respeitando FKs e a ordem topológica do ETL (estados→empresas→geografico→cadastros→
clientes→produtos→estoque→pedidos→financeiro→caixa→cobranca→fiscal→satelites→mobile→monitora).

Cobertura-alvo dos seeds: grupos/empresas/usuários/RBAC; geográfico; clientes (+telefones/
convênio/preços); produtos (+origens/classes/unidades); setores+saldos+histórico;
pedidos concluídos/cancelados (gerando estoque+financeiro); financeiro (parcelas/rateios);
contas+movimentos+cheques; boletos+pix; notas fiscais; convênio/vale-gás/comodato;
devices+pagamentos; veículos+posições GPS.

---

## Etapa 10 — Testabilidade

| Módulo | Testável já | Depende de |
|---|---|---|
| Auth/Tenant/RBAC | ✅ | seed admin (existe) |
| Cadastros, Geográfico, Cliente, Produto | ✅ | seeds de apoio |
| Estoque, Pedido, Financeiro, Caixa, Cheque | ✅ | seeds + produtos/clientes |
| Fiscal NF-e (emissão) | 🟡 | driver SEFAZ real (gate homolog) |
| Boleto/CNAB, PIX | 🟡 | credenciais bancárias (gate) |
| Mobile (app) | 🟡 | apps externos / gateway Rede |
| Monitora GPS | 🟡 | SGCasa (gate externo) |
| Relatórios | 🟡 | volume de dados (seeds) |

**Para homologar:** popular ambiente com seeds (Etapa 9) → validar fluxos núcleo →
configurar gates (SEFAZ/banco/PIX/Rede/SGCasa) em homologação.

---

## Etapa 11 — Relatório final (respostas objetivas)

1. **O ERP-NOVO substitui totalmente o ctrl-web?** ❌ **Não.** Cobre o núcleo
   transacional, mas faltam módulos inteiros, ~31 relatórios e há divergências de
   contrato SPA×backend.
2. **Percentual de aderência:** **~35–45%** funcional. O núcleo crítico
   (estoque/financeiro/caixa/fiscal/pedido) está ~80% pronto **no backend**; a cauda
   (RH, frota, SPED, NF recebida, relatórios, pós-venda, etc.) está ~0–10%.
3. **Funcionalidades que faltam:** RH/colaborador (comissões, família, recessos,
   cargo, turno), frota/veículos (abastecimento, pneu, óleo, documento), SPED, NF
   recebida, IBPT, conciliação/OFX, pós-venda, promoção, sorteio, mala-direta, metas,
   checklist, eventos, malote, ~31 relatórios, dashboards gerenciais, uploads, exports.
4. **Telas que faltam:** colaboradores, veículos, SPED, NF recebida, conciliação,
   malha fiscal, fiscal-operações, produto-config, produtos-precos, ~35 relatórios,
   gestões (Financeiro/Comodato/Convenio/Documento/Vendasmensais).
5. **Regras não migradas:** SPED, comissões, conciliação bancária, IBPT, NF de
   entrada, regras de RH/frota, fechamento de malote.
6. **Erros de integração SPA×backend:** **Sim** — múltiplos endpoints que a SPA
   chama não existem no backend (Etapa 7.1) e contratos divergentes (fiscal `/nfe`
   vs `/notas`; `/financeiro/dre` vs `/relatorios/dre`).
7. **Endpoints sem utilização:** Sim (Etapa 7.2): `/notas*`, `/monitora/*`,
   `/relatorios/*`, parte de caixa/estoque.
8. **Funcionalidades sem backend:** Sim — colaboradores, veículos, produto-config,
   produtos-precos, certificado, grupos, conciliação, SPED, NF recebida, satélites.
9. **Funcionalidades sem frontend:** Sim — monitora GPS, notas fiscais (`/notas`),
   comodato/convênio (parcial), estorno de caixa.
10. **Banco preparado para operação?** 🟡 Schema novo é sólido e auditável, mas
    cobre ~55/212 domínios; **falta o ETL rodar contra dump real** (hoje vazio).
11. **Seeds cobrem todos os cenários?** ❌ Não — só admin. Plano em `PLANO_SEEDS.md`.
12. **Pronto para homologação?** 🟡 **Parcial** — núcleo sim (com seeds); módulos
    ausentes e gaps de contrato impedem homologação ponta-a-ponta da SPA.
13. **Pronto para produção?** ❌ **Não.**

---

## Lista priorizada de pendências

### 🔴 CRÍTICO (bloqueia uso da SPA / cutover)
1. **Gaps de contrato SPA×backend** (Etapa 7.1): alinhar rotas (fiscal `/nfe`↔`/notas`,
   `/financeiro/dre`, produto-config, estoque) — senão as telas quebram.
2. **GrupoController + endpoints de Empresa** (certificado A1, nfce-token, testar-email).
3. **Módulo Colaborador/RH** (a SPA tem telas e chama `/colaboradores/*`).
4. **Módulo Frota/Veículos** (`/veiculos/*`).
5. **Seeds abrangentes** (Etapa 9) + **rodar ETL contra dump real** e `cutover:check` verde.
6. **`/me` com roles/permissions reais** (hoje vazio; SPA depende de support-bypass).

### 🟠 ALTO
7. **Fiscal: NF recebida + SPED + IBPT** (obrigação legal).
8. **Conciliação bancária / OFX.**
9. **Relatórios essenciais** (vendas detalhada, financeiro, caixa, comissões, NF) +
   **export PDF/Excel** e **upload** (certificado/anexos).
10. **Cadastros de apoio restantes** (de 7 para ~40).
11. **Cron jobs faltantes** (16 dos 18): IBPT, documentos vencidos, remember mail,
    venda diária, inconsistências, pix cancel expired, order status.

### 🟡 MÉDIO
12. Pós-venda, promoção, sorteio, mala-direta, metas, checklist, eventos.
13. Dashboards gerenciais; "gestões" (Financeiro/Comodato/Convenio/Documento).
14. Feature de Monitora GPS na SPA (backend pronto).
15. Estoque: requisições, inventário/efetivar, abertura de fechamento.

### 🟢 BAIXO
16. Relatórios raros (aniversariantes, questionários, logs de senha, vendas malote).
17. Limpeza de endpoints ociosos / consolidação de contratos.

---

## Estimativa de esforço (ordem de grandeza, 1 dev sênior)

| Bloco | Esforço |
|---|---|
| Alinhar contratos SPA×backend (núcleo) + `/me` permissões | 2–3 semanas |
| Grupos + Empresa (certificado/nfce/email) + uploads | 1–2 semanas |
| Colaborador/RH (com comissões) | 3–4 semanas |
| Frota/Veículos | 2–3 semanas |
| Fiscal: NF recebida + SPED + IBPT (portar) | 4–6 semanas |
| Conciliação/OFX | 1–2 semanas |
| Relatórios (35) + export/PDF/Excel | 4–6 semanas |
| Cadastros de apoio restantes + cron jobs | 1–2 semanas |
| Pós-venda/promoção/sorteio/mala-direta/metas/checklist | 3–5 semanas |
| Seeds + ETL contra dump real + validação invariantes | 1–2 semanas |
| **Total estimado para paridade completa** | **~6–9 meses** |

> Para **homologação do núcleo** (sem a cauda): alinhar contratos + seeds +
> grupos/empresa ≈ **4–6 semanas**.

---

## Conclusão

O erp-novo é uma **fundação sólida e bem-arquitetada** (regra em Services, saldo
auditável, ETL com invariantes, gates isolados), mas **hoje não substitui o ctrl-web**.
É um **núcleo transacional ~35–45% aderente**, com a cauda longa (RH, frota, fiscal
completo, relatórios, módulos satélite secundários) ainda por implementar, e com
**divergências de contrato** entre a SPA (trazida do legado) e o backend novo que
precisam ser resolvidas antes de qualquer homologação ponta-a-ponta da interface.
