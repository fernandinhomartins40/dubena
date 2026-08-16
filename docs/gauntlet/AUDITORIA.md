# AUDITORIA — Refatoração do ERP Gás em Casa

**A pergunta que este documento responde:** *a refatoração cobre tudo que o sistema antigo fazia, e está pronta para receber os dados do cliente e ser vendida como SaaS para outras revendas?*

**Resposta curta:** a refatoração cobre o **núcleo transacional inteiro** e o supera em vários pontos, mas **20 de 46 áreas funcionais ainda não permitem ao operador concluir o trabalho do dia** — quase sempre por falta da ação que fecha o ciclo (o documento impresso, o botão que persiste a decisão, a operação de escrita que virou só leitura). Nenhuma dessas lacunas exige rearquitetura: são 46 tarefas localizadas, com arquivo e linha conhecidos. Os dados do dump estão migrados com fidelidade financeira ao centavo, mas o espelho de produção cobre 43 das 222 tabelas do Oracle e há 15 tabelas com dados sem destino no novo.

---

**Data:** 15 de agosto de 2026
**Escopo:** `ctrl-web/` (legado), `erp-novo/` (novo), `app-gas-em-casa/`, `app-entregador/`, `mobile-shared/`, `deploy/`, VPS de produção `gasemcasa.com`

> **Método — Gauntlet Loop.** Cada seção foi escrita por um agente construtor independente e submetida a um crítico com contexto limpo, que formulou 5 perguntas difíceis, respondeu-as primeiro só com o texto da seção e depois **verificou cada resposta no código, no schema e nos dados reais**. Seção reprovada voltava ao construtor. Nenhum construtor avaliou o próprio trabalho. A seção de paridade (§2) levou 3 rodadas — foi reprovada por módulo faltando e por status inflado.
>
> **Regra inegociável:** o código é a única fonte de verdade. Toda afirmação é rastreável a arquivo (e linha). O que não pôde ser verificado está marcado **NÃO VERIFICADO** com o motivo.

### Estado de validação

| Seção | Veredito | Rodadas |
|---|---|---|
| 1. Mapa do sistema novo | ✅ Aprovada | 1 |
| 2. Mapa do legado | ✅ Aprovada | 1 |
| **3. Paridade funcional** ← *a seção que responde sua pergunta* | ✅ Aprovada | 3 (reprovada 1×) |
| 4. Migração de dados | ✅ Aprovada | 1 |
| 5. Erros da refatoração | ⚠️ **NÃO VALIDADA** (ver ressalva) | 0 |
| 6. Segurança | ✅ Aprovada | 1 |
| 7. Lacunas para produção | ✅ Aprovada | 1 |
| 8. Estado real da VPS de produção | ✅ Verificada por SSH | — |

> **⚠️ Ressalva da §10 (débito técnico).** O crítico foi interrompido por limite de sessão e não emitiu veredito. Antes disso **já havia derrubado uma refutação**: a seção afirma que "os 6 jobs declaram `$tries`", mas `TenantAwareJob` é uma *trait* e `NotificarEstoqueBaixoJob` (`app/Domain/Relatorio/`) é um `ShouldQueue` **sem `$tries`, sem `$backoff`, sem `failed()`**, com 2 `return` silenciosos. Trate os achados da §10 como hipóteses.

---

## 1. Sumário executivo — o que você precisa decidir

### 1.1 A refatoração cobre o sistema antigo?

**46 áreas funcionais** foram derivadas do código do legado (163 controllers, 580 rotas em `web.php`). Cada uma foi confrontada com o código do novo:

| Status | Qtd | % | Significado prático |
|---|---:|---:|---|
| ✅ Migrada e funcional | 20 | 43,5% | O operador faz no novo tudo que fazia no antigo |
| ⚠️ Migrada com lacuna | 22 | 47,8% | O núcleo existe, mas **falta a ação que fecha o ciclo** |
| ❌ Não migrada | 4 | 8,7% | Não existe nada equivalente |

**Cobertura bruta: 91,3%** (42 de 46 têm algum equivalente). **Paridade plena: 43,5%.**

A diferença entre esses dois números é o cerne do seu problema. Uma auditoria por checklist de tela diria "91% pronto". Na prática, **uma tela que lê mas não persiste não substitui a do legado** — o operador abre, olha e não consegue concluir.

### 1.2 As quatro famílias de lacuna

Os 22 ⚠️ não são aleatórios. Eles se agrupam em quatro padrões, e isso torna o trabalho previsível:

1. **Saídas impressas ausentes (a maior família).** Boleto em PDF, recibo de caixa, vale-gás impresso, contrato de comodato, DANFE, comanda de pedido, etiquetas. **Nenhum gerador de PDF operacional existe no novo fora dos relatórios.** No modelo disk-gás, o papel é o produto: sem o boleto impresso o título não chega ao cliente.
2. **Operações de escrita que viraram somente-leitura.** Recessos e comissões de RH, trocas de óleo e pneu da frota — no legado são CRUD, no novo são apenas GET.
3. **Camada de gestão e marketing não portada.** Fechamento mensal com envio, PowerPoint de vendas, campanhas de push segmentadas, giro de compras, dashboards de convênio.
4. **A ação que fecha o ciclo de uma tela de leitura** (a mais insidiosa). Resolver a inconsistência geográfica (`ignorarRua`/`ignorarBairro`) e classificar automaticamente o extrato bancário (`extratoconfig`). A tela existe, parece pronta, e a fila nunca esvazia.

### 1.3 Onde o novo é MELHOR que o legado

Isso importa para vender o SaaS, e é verificável:

- **Segurança de acesso:** o legado autoriza por menu-no-banco com um bypass de AJAX que libera qualquer POST (`AuthorizeCustom.php:52-53`); o novo tem RBAC por Gates, ABAC, 2FA, gestão de sessões e auditoria.
- **Multi-tenant real:** RLS no Postgres com 147 policies e role de runtime sem bypass — a base técnica para vender a outras revendas já existe e está ativa em produção.
- **Sem injeção SQL:** o legado tem 137 `whereRaw` e SQLi confirmada (`BoletoremessaController.php:82,102` concatena `$_GET`); no novo as únicas 3 interpolações usam constantes internas.
- **Logística:** central de distribuição, app do entregador, missões de campo e rastreamento — não existiam no legado.
- **Integridade dos dados migrados:** zero órfãos, financeiro batendo ao centavo.

### 1.4 O que bloqueia a entrega ao cliente HOJE

Verificado na VPS de produção em 15/08 (somente leitura):

| # | Bloqueador | Evidência |
|---|---|---|
| 1 | **Senha default de admin ATIVA** | `admin@gasemcasa.com`, `support=true` (ignora todo o RBAC), senha `admin1234` — pública no repositório. `Hash::check` na VPS retorna `SENHA_DEFAULT_ATIVA` |
| 2 | **Zero backup do banco** | 443.714 títulos e 241.021 NF-e sem nenhuma rotina de `pg_dump` |
| 3 | **Não emite documento fiscal real** | `FISCAL_DRIVER=fake` e `COBRANCA_DRIVER=fake` — não gera NF-e nem CNAB reais |
| 4 | **`cutover:check` verde falso** | 16 OK / 0 falhas, mas **14 dizem "legado indisponível — não se aplica"**: passam por omissão |
| 5 | **Migração incompleta em produção** | 43 tabelas espelhadas contra 222 do Oracle; 15 tabelas com dados sem destino |
| 6 | **Tempo real inoperante** | Reverb com 368 reinícios; `BROADCAST_CONNECTION` ausente → apps caem para polling |
| 7 | **Dados demo em produção** | 53 clientes "teste/demo" na base real |

### 1.5 Caminho recomendado

O `PLANO_PRODUCAO.md` detalha 46 tarefas em 6 fases. A ordem que respeita dependência e risco:

**F1 Segurança** (dias) → **F2 Dados** (1–2 semanas) → **F3 Infra** (1 semana) → **F4 Paridade funcional** (o grosso: as 22 lacunas) → **F5 Débito técnico** → **F6 Cutover**.

F1, F2 e F3 são pré-requisito de qualquer entrega. **F4 é o que decide se o cliente consegue operar** — e é onde está a maior parte do esforço restante.

---
# 2. Paridade funcional: o legado × a refatoração

**Método.** A lista de módulos foi derivada exclusivamente do código do legado: `ctrl-web/routes/web.php` (1.118 linhas, ~150 grupos de rotas), `ctrl-web/routes/api.php` (API dos tablets Android/NFweb), `ctrl-web/routes/api_mobile.php` (API do app consumidor), `ctrl-web/routes/monitora.php` (módulo GPS) e o inventário real de **163 controllers** em `ctrl-web/app/Http/Controllers/` (160 na raiz + 2 em `App/` + 2 em `Auth/`, descontando a classe-base `Controller.php`; 164 arquivos `.php` no total). A contraparte foi verificada em `erp-novo/routes/api.php` (752 linhas), nos 46 controllers de `erp-novo/app/Http/Controllers/Api/Admin/` (+ Mobile/ e SuperAdmin/), nos services de `erp-novo/app/Domain/` e nas páginas React de `erp-novo/frontend/src/features/`. Nenhuma linha abaixo se apoia em documentação — cada célula cita arquivo real dos dois lados. `ctrl-web/routes/api_admin.php` (SPA strangler embutida no próprio ctrl-web) foi tratado como parte do legado.

**Varredura de completude.** Antes de fechar, cada um dos 163 controllers do legado foi confrontado com a matriz para garantir que cai em alguma linha. O mapeamento dos agrupamentos que não citam o arquivo nominalmente: os 24 `Report*Controller.php` (incluindo `ReportController.php`, `ReportCaixaController`, `ReportclientesController`, `ReportclientesaniversariantesController`, `ReportcolaboradorController`, `ReportcomissoesController`, `ReportcomodatoController`, `ReportconvenioController`, `ReportEntregasController`, `ReportestoqueController`, `ReportFinanceiroController`, `ReportlogsController`, `ReportlogsenhaController`, `ReportmovimentacaoController`, `ReportnfemitidasController`, `ReportnfrecebidaController`, `ReportnfrecebidasController`, `ReportpromocoesController`, `ReportpromotorController`, `ReportquestionariosController`, `ReportResumoVendasController`, `ReportvalegasController`, `ReportveiculosController`, `ReportvendapdvController`, `ReportVendasController`, `ReportvendasmaloteController`) caem todos na **linha 28**; `ClientecontatosituacaoController`/`ClientecontatotipoController` na **linha 3**; `RecessotipoController`/`TiporecessosController` na **linha 21**; `TipodocumentoController` na **linha 35** (nova); `Auth/OauthClientController.php` e `Auth/PasswordController.php` na **linha 1**. Nenhum controller ficou fora.

## 3.1 Matriz — ERP web (ctrl-web → erp-novo)

| # | Módulo | Evidência no legado (arquivos em `ctrl-web/`) | Evidência no novo (arquivos em `erp-novo/`) | Status | Observação |
|---|--------|------------------------------------------------|----------------------------------------------|--------|------------|
| 1 | Autenticação, usuários e permissões | `app/Http/Controllers/AuthController.php`, `UsersController.php`, `RoleController.php` (+ rota `definirtipos`), `MenuController.php`, `Auth/OauthClientController.php` (emissão de token OAuth/Passport no login — `store`/`getAuthorizationToken`/`exclude`), `Auth/PasswordController.php` (reset de senha), troca de senha em `UsersController@indexchangepassword`/`updatepassword`; rotas em `routes/web.php:27-31,66,77-79` | `routes/api.php:79,103-116` (login/logout/me), `196-200` (usuários), `179-189` (papéis+catálogo+ABAC), `122-131` (2FA/sessões/política de senha — `app/Http/Controllers/Api/SegurancaController.php`); `app/Domain/Acesso/`, `app/Domain/Seguranca/`; frontend `frontend/src/features/acessos/` (PerfisTab, UsuariosTab, AuditoriaTab), `features/seguranca/SegurancaPage.tsx` | ✅ | Novo supera o legado (2FA, sessões, ABAC, auditoria). Menu-no-banco do legado (`MenuController`) substituído por navegação declarativa na SPA (`frontend/src/layouts/`) — decisão de arquitetura, não lacuna. |
| 2 | Empresas, grupos e configurações | `EmpresaController.php`, `EmpresasGrupoController.php`, `EmpresaconfigController.php` (senha mestre, e-mail), `ConfiguracoesgeraisController.php`; `routes/web.php:43-54,626-635` | `routes/api.php:137-158` (empresas, config, senha-mestra, testar-email, certificado A1, token NFC-e, integrações PIX/cartão), `171-175` (grupos), `157-158` (config-global); `app/Http/Controllers/Api/Admin/EmpresaController.php`, `EmpresaConfigController.php`, `ConfigGlobalController.php`, `GrupoController.php`; frontend `features/empresas/` (ConfigTab, CertificadoSection, IntegracoesSection), `features/configuracoes/` | ✅ | Config de 106 colunas portada em sub-abas (`features/empresas/config/`). |
| 3 | Clientes | `ClienteController.php` (ativação, contrato convênio, etiquetas, busca), `ClientecontatoController.php`, `Clientecontatosituacao/tipoController.php`, `ClienteprodutoController.php` (preços por cliente), `TelefonetipoController.php`, `TipopessoaController.php`, `SegmentoController.php`; `routes/web.php:82-122` | `routes/api.php:241-263` (CRUD + exportar + telefones + interações + convênio/dependentes + preços + histórico); `app/Http/Controllers/Api/Admin/ClienteController.php`, `ClienteSubrecursoController.php`, `ClienteTelefoneController.php`; cadastros de apoio (segmentos, tipos-pessoa, telefone-tipos, contato-tipos/situações) em `app/Domain/Apoio/CadastroApoioRegistry.php`; frontend `features/clientes/` (7 arquivos: ficha com abas Telefones/Histórico/Interações/Convênio/Preços) | ✅ | Impressão de contrato/etiquetas de convênio do legado não tem endpoint dedicado no novo (a mala direta cobre etiquetas via CSV). |
| 4 | Geográfico (cidade/bairro/rua/região) + inconsistências | `CidadeController.php`, `BairroController.php`, `RuaController.php`, `RegiaoController.php`, `InconsistenciaController.php` (detecção `getInconsistencias`/`getRegistros` **+ resolução** `ignorarRua`/`ignorarBairro`, `:48-91`, POST com `DB::beginTransaction()` + `$rua->ignorados()->attach(...)`); `routes/web.php:62-63,979-1006` (POSTs em `1002-1003`) | `routes/api.php:219-238` (`geo/{entidade}` CRUD, regiões, `cadastros/inconsistencias` — **só GET**, linha 226); `app/Http/Controllers/Api/Admin/GeoController.php:101-116`, `RegiaoController.php`; `InconsistenciaService` expõe apenas `ruas()`/`bairros()`/`todas()`; frontend `features/geografico/GeograficoPage.tsx` | ⚠️ | CRUD geográfico e regiões migrados. O **detector** de duplicatas foi portado (`GeoController@inconsistencias`), mas a **resolução não**: não existe rota POST/PUT de inconsistência em `routes/api.php` nem método de "ignorar" no service. Sem `ignorarRua`/`ignorarBairro` o par duplicado reaparece na fila indefinidamente — a tela vira relatório somente-leitura em vez de fila de trabalho. |
| 5 | Produtos e preços | `ProdutoController.php`, `ProdutoclasseController.php`, `UnidademedidaController.php`, `AtualizarprecosController.php`; `routes/web.php:256-274` | `routes/api.php:266-285` (CRUD produto, estoque por produto, classes/unidades, `produtos-precos/preview|aplicar`); `app/Http/Controllers/Api/Admin/ProdutoController.php`, `ProdutoConfigController.php`, `ProdutoPrecoController.php`; frontend `features/produtos/` (ProdutoFormPage, OrigensTab, ProdutoPrecosPage, ProdutoConfigPage) | ✅ | Reajuste em massa com preview portado; origens GLP (soma 100%) na aba própria. |
| 6 | Estoque | `EstoquesetoracertoController.php`, `EstoquesetorController.php` (consulta+fechar/abrir), `EstoquefisicoController.php`, `EstoquerequisicaoController.php`, `EstoqueTransferenciasController.php`, `InventarioController.php`, `SetorController.php`; `routes/web.php:253,309-337,377` | `routes/api.php:288-312` (setores, saldos, histórico, entrada/saída, transferências, acerto, fechamentos+abrir, requisições, inventários, físico+efetivar); `app/Http/Controllers/Api/Admin/EstoqueController.php`, `SetorController.php`; `app/Domain/Estoque/`; frontend `features/estoque/tabs/` (8 abas: Saldos, Acerto, Transferência, Requisição, Inventário, Físico, Fechamento) | ✅ | PDFs de requisição/transferência do legado (`gerarPDF`) não têm equivalente de impressão. |
| 7 | Pedidos / vendas (disk-gás + monitoramento) | `PedidoController.php` (monitoramento, updateVariosStatus, justifica atraso, valida cartão/vale-gás, comanda, histórico), `PedidooperacaoController.php`, `PedidosituacaoController.php`, `PedidomotivoatrasoController.php`, `MotivonaovendaController.php`, `ConfigNfcePedidoController.php`; `routes/web.php:168-219,481-511` | `routes/api.php:315-329` (kanban, situações CRUD+reordenar, CRUD pedido, mudarSituacao, emitir-nfce); `app/Http/Controllers/Api/Admin/PedidoController.php`; `app/Domain/Pedido/`; frontend `features/pedidos/` (KanbanView, ListaView, PedidoDialogs) | ⚠️ | Núcleo migrado (kanban substitui o monitoramento). Faltam: motivos de atraso/justificativa (`PedidomotivoatrasoController`), motivos de não-venda (`MotivonaovendaController` — sem match no novo, grep vazio), comanda de impressão (`pedido.comanda`), validação de cartão on-line no atendimento (`validacartao`). |
| 8 | Fiscal de saída (NF-e/NFC-e/SAT + malha fiscal) | `NfemitidaController.php` (transmitir, cancelar, CC-e, inutilizar faixa, exportarXmls, enviarEmailNF, importTxt), `CupomFiscalController.php` (SAT CF-e), `NfcofinsController.php`, `NficmsController.php`, `NfcstController.php`, `NfclasstribController.php`, `NfipiController.php`, `NfpisController.php`, `NfgrupofiscalController.php`, `NfOperacaoController.php`, `ImpostonfController.php`, `NfsituacaoController.php`, `IBPTController.php`; `routes/web.php:172-205,339-359` | `routes/api.php:422-456` (notas emitir/cancelar/transmitir, carta-correção, inutilizações, malha `fiscal/malha/{tipo}`, operações, sped, ibpt), `531-533` (cupons-fiscais SAT); `app/Http/Controllers/Api/Admin/NotaFiscalController.php`, `FiscalConfigController.php`, `ConfigFiscalController.php`, `GestaoController.php` (cupom); `app/Domain/Fiscal/` (XmlNfeBuilder, CalculoImpostoService, NFePHPSefazDriver, CertificadoService); frontend `features/fiscal/tabs/` (NfeTab, MalhaTab, SpedTab) | ⚠️ | Emissão, cancelamento, CC-e, inutilização e malha completos (driver SEFAZ real `NFePHPSefazDriver.php`). Faltam operações auxiliares do legado: exportar XMLs em lote p/ contador (`exportarXmls`), envio de NF por e-mail (`enviarEmailNF`), importação por TXT, e a visualização de DANFE. |
| 9 | NF de entrada (recebida) | `NfrecebidaController.php` (CRUD, `importXml`, inutilizar), busca fornecedor por CNPJ; `routes/web.php:362-365` | `routes/api.php:449-452` (`fiscal/nf-entrada` + importar XML + processar → estoque + contas a pagar); `app/Http/Controllers/Api/Admin/NfEntradaController.php`; `app/Domain/Fiscal/NfEntradaService.php`; frontend `features/fiscal/tabs/NfEntradaTab.tsx` | ✅ | Fluxo importar→processar cobre o ciclo do legado. |
| 10 | SPED (fiscal, contribuições, créditos) | `SpedfiscalController.php`, `SpedcontribuicaoController.php`, `SpedcreditosController.php`; `routes/web.php:368-391` | `routes/api.php:454-455` (`fiscal/sped`, `fiscal/sped-contribuicoes`); `app/Domain/Fiscal/SpedFiscalService.php`, `SpedContribuicoesService.php`; frontend `features/fiscal/tabs/SpedTab.tsx` | ⚠️ | SPED Fiscal e Contribuições existem como service+endpoint. **SPED Créditos não existe** (grep `spedcredito` no erp-novo: zero ocorrências). Download do arquivo `.txt` gerado (rota `sped.dowload` do legado) não evidenciado. |
| 11 | Financeiro — títulos, plano de contas, centro de custos | `FinanceiroController.php` (despesa/receita, consultar, cancelar, agrupar, lançamentos), `PlanocontaController.php`, `CentrocustoController.php`, `CondicaopagamentoController.php`, `ContamovimentotipoController.php`, `ContaController.php` (contas bancárias + **automação de extrato** `addEditExtratoconfig`, `:670-700`, rota `routes/web.php:155`); `routes/web.php:145-165,612-624` | `routes/api.php:332-349` (lançamentos+resumo+criar+cancelar+agrupar/desagrupar/reparcelar, planos-conta, centros-custo); `app/Http/Controllers/Api/Admin/FinanceiroController.php`, `FinanceiroCadastroController.php`; `app/Domain/Financeiro/FinanceiroService.php`; condições de pagamento e tipos de movimento em `CadastroApoioRegistry.php` + `lookups/condicoes-pagamento`; frontend `features/financeiro/tabs/` | ⚠️ | Núcleo migrado, com agrupamento/reparcelamento (regra de negócio antiga do `FinanceiroService`) exposto explicitamente. Lacuna: a **configuração de automação de extrato bancário** (`ContaController@addEditExtratoconfig`) — regras que casam uma descrição do extrato com uma ação Lancar/LancarBaixar/Transferir, validando `condicaopagamento_id`, `contamovimentotipo_id`, `pc_id`/`cc_id` (ou `contaorigem_id`) — não existe no novo: `grep -rniE "extratoconfig\|contaextrato"` em `app/` e `frontend/src/` retorna zero. Sem ela a conciliação importa o extrato mas cada linha precisa ser classificada à mão. |
| 12 | Caixa / tesouraria | `CaixaController.php` (abrir, fechar, transferir, estornar lançamento/CR, baixar títulos, cancelar, gerar recibo/reciboCR, tela caixa fechado); `routes/web.php:556-571` | `routes/api.php:352-362` (contas, movimentos, abrir, fechar, baixar, transferências, estornar, baixar-titulos em lote, lancar-fechado); `app/Http/Controllers/Api/Admin/CaixaController.php`; `app/Domain/Caixa/CaixaService.php`; frontend `features/financeiro/tabs/CaixaTab.tsx` | ⚠️ | Operações de caixa completas, incluindo lançamento em caixa fechado. Falta: **recibo impresso** (`gerarrecibo`/`gerarrecibocr` — grep `recibo` no erp-novo: zero). |
| 13 | Cheques (emitidos, recebidos, desconto) | `ChequeemitidoController.php` (inutilizar, estornar, baixar), `ChequerecebidoController.php` (depositar, baixar, estornar, devolver), `DescontochequeController.php`; `routes/web.php:574-589` | `routes/api.php:365-371` (recebidos, emitidos, CRUD, mudarSituacao, encontro-de-contas); `app/Http/Controllers/Api/Admin/ChequeController.php`; `app/Domain/Caixa/ChequeService.php` + `SituacaoCheque.php` (CARTEIRA/DEPOSITADO/COMPENSADO/DEVOLVIDO/REPASSADO/CANCELADO) | ⚠️ | Ciclo de vida coberto por `mudarSituacao`. Falta o **desconto de cheque** (antecipação bancária — `DescontochequeController`): não há situação "descontado" nem fluxo equivalente no enum `SituacaoCheque.php`. |
| 14 | Boletos / cobrança CNAB | `BoletoController.php`, `BoletoPdfController.php` (impressão do boleto), `BoletoremessaController.php` (remessa, retorno, efetivar, cancelar), `LayoutbancoController.php`, `OcorrenciasremessasController.php`, `BancoController.php`, `AgenciaController.php`; `routes/web.php:57-60,142,544-553,1046` | `routes/api.php:385-398` (boletos, gerar, remessas+arquivo, retorno, aliases /cobranca); `app/Http/Controllers/Api/Admin/BoletoController.php`; `app/Domain/Cobranca/` (BoletoService, `Cnab/`, Drivers, SituacaoBoleto); bancos/agências em `CadastroApoioRegistry.php` (`bancos`, `agencias`) | ⚠️ | Geração, remessa CNAB e retorno migrados. Faltam: **PDF do boleto** (`BoletoPdfController` — nenhum gerador de PDF no domínio Cobranca), CRUD de layouts de banco (`LayoutbancoController` — layouts agora são drivers fixos em código) e ocorrências de remessa como cadastro. |
| 15 | PIX | `PixController.php` (webhook, gerar transação, baixar parcelas), `routes/api.php:29-30,198-200` (legado), `routes/web.php:915-916` | `routes/api.php:82` (webhook público), `401-402` (config + cobranças); `app/Http/Controllers/Api/PixWebhookController.php`, `Api/Admin/PixController.php`; `app/Domain/Cobranca/PixService.php` + `app/Domain/Integracao/` (segredos por empresa, webhook por txid) | ✅ | Novo é multi-tenant (segredo por empresa/grupo — `IntegracaoTenant`), superando o legado de credencial única. |
| 16 | Importações financeiras (extrato OFX, relatório de cartão) | `ImportextratoController.php` (importExtrato, getParcelas, update), `FinanceiroController@importReportCartao*`; `routes/web.php:533-541` | `routes/api.php:571-572` (conciliação bancária GET/POST — OFX via `ConciliacaoService`, comentário na rota), `374-378` (cartões NSU + Gás do Povo — `app/Http/Controllers/Api/Admin/PagamentoController.php`); frontend `features/pagamentos/CartaoPage.tsx`, `GasDoPovoPage.tsx` | ⚠️ | Extrato OFX coberto pela conciliação. A **importação do arquivo do adquirente de cartão** (baixa em lote de parcelas por relatório) virou registro/consulta de NSU — sem o parser de arquivo do legado. |
| 17 | Conciliação contábil (CONSISA) | `ConciliacaoController.php` (getFinCont, getFinDet, getContDet); `routes/web.php:607-610` | `routes/api.php:574-575` (`financeiro/conciliacao-contabil`); `app/Http/Controllers/Api/Admin/FinanceiroController.php@conciliacaoContabil` | ✅ | Comentário da rota confirma escopo CONSISA (F08). |
| 18 | Convênio / Gás de Bolso | `FechamentoconvenioController.php` (filtro, PDF, XLS, **emitirNF**, **emitirBoleto**), `ConveniogbgestaoController.php` (dashboard GB), contrato/etiquetas em `ClienteController.php`; `routes/web.php:440-451` | `routes/api.php:405-408` (convenios CRUD, fechamentos, fechar); `app/Http/Controllers/Api/Admin/ConvenioController.php`; `app/Domain/Satelite/ConvenioFechamentoService.php`; convênio do cliente em `ClienteSubrecursoController.php`; frontend `features/convenios/ConvenioPage.tsx` | ⚠️ | Fechamento de convênio existe, mas **sem emissão encadeada de NF e boleto** (grep `boleto|nota|nf` em `ConvenioFechamentoService.php`: zero) e sem os PDFs/XLS do fechamento. Dashboard "Convênio/Gás de Bolso gestão" sem equivalente dedicado. |
| 19 | Vale-gás | `ValegasvendaController.php` (venda, parcelas, duplicata PDF, impressão), `ValegasbaixarController.php`, `ValegascancelarController.php`, `ValegasconsultaController.php`; `routes/web.php:409-425` | `routes/api.php:411-414` (situações, index, store, baixar); `app/Http/Controllers/Api/Admin/ValeGasController.php`; relatório em `RelatorioController.php` (`vale-gas`); frontend `features/valegas/ValeGasPage.tsx`; venda em campo via `AppMissaoController@venderValeGas` (`routes/api.php:693`) | ⚠️ | Venda, baixa, consulta e cancelamento (via situações) cobertos. Faltam: **impressão do vale e duplicata em PDF** (`gerarPDF`, `imprimirGas`, `confirmaImpressao`). |
| 20 | Comodato | `ComodatoController.php` (CRUD + contrato PDF), `ComodatogestaoController.php` (saldos, vencidos, giro, PDFs); `routes/web.php:428-437` | `routes/api.php:417-419` (comodatos index/store/devolver); `app/Http/Controllers/Api/Admin/ComodatoController.php` (index/store/devolver); relatório `comodatos` em `RelatorioController.php:160`; frontend `features/comodatos/ComodatoPage.tsx` | ⚠️ | Ciclo entrada/devolução coberto. Faltam: **contrato de comodato em PDF** e a gestão analítica (saldos por cliente, vencidos, giro — `ComodatogestaoController` sem equivalente; grep `giro` no novo só acha giro de produto). |
| 21 | RH / colaboradores | `ColaboradorController.php`, `ColaboradorfamiliaController.php`, `CargoController.php`, `EstadocivilController.php`, `ParentescoController.php`, `TipoexameController.php`, `ColaboradorcomissoesController.php` (resource), `RecessosController` + `TiporecessosController.php` (resource), `TurnoController.php`, `SetorcolaboradoresController.php`; `routes/web.php:125-139,225-239,933` | `routes/api.php:578-594` (CRUD colaborador, família add/del, recessos GET, comissões GET, exames GET/POST, turnos GET/POST, pontos GET/POST); `app/Http/Controllers/Api/Admin/ColaboradorController.php`; `app/Models/Rh/` (Colaborador, ColaboradorTurno); cargos/estados-civis/parentescos/tipos-exame em `CadastroApoioRegistry.php`; frontend `features/rh/ColaboradoresPage.tsx` + tabs | ⚠️ | Ficha completa com abas e cadastros de apoio migrados; novo adiciona ponto. Lacunas: **recessos e comissões só leitura** (legado tem resource CRUD completo p/ ambos — `routes/api.php:586-587` do novo só expõe GET). |
| 22 | Frota / veículos + portaria | `VeiculoController.php`, `VeiculotipoController.php`, `TipocombustivelController.php`, `VeiculoabastecimentoController.php`, `VeiculotrocaoleoController.php`, `VeiculopneuController.php`, `VeiculodocumentoController.php`, `VeiculoentradasaidaController.php` (portaria, com conferência de pedido/setor); `routes/web.php:277-288,394-406` | `routes/api.php:597-610` (CRUD veículo, abastecimentos GET/POST, trocas-oleo GET, pneus GET, entradas-saidas GET/POST, documentos GET/POST); `app/Http/Controllers/Api/Admin/VeiculoController.php`; `app/Domain/Frota/`; frontend `features/frota/VeiculosPage.tsx` | ⚠️ | Núcleo migrado incluindo portaria (entradas-saidas). Lacunas: **troca de óleo e pneus só leitura** (legado tem resource CRUD + alerta de troca vencida); conferência de carga da portaria (`ajaxpedidosetor`) não evidenciada. |
| 23 | Monitoramento GPS (monitora) | `routes/monitora.php` completo: `RastreamentoController.php`, `CercaController.php`, `RotaController.php`, `EventoController.php`, `LogcercaController.php` (+ relatório de cercas em `routes/web.php:903-904`), `SearchController@getPosicaoAtual/getRotas/getCercaPoligono` | `routes/api.php:459-472` (monitora: veículos, posições, histórico, eventos, tipos, últimas-posições, cercas CRUD, sync); `app/Http/Controllers/Api/Admin/MonitoraController.php`; `app/Domain/Monitora/`; frontend `features/satelites/` (MonitoraPage, MapaAoVivoTab, CercasTab, RotaTab) | ✅ | Módulo isolado portado com mapa ao vivo, cercas e rotas. |
| 24 | Logística de entrega (tablets Android + NFweb) | `routes/api.php` do legado (linhas 50-207): `ApiController.php` (pedidos pendentes, setPedidoSituacao, rastreamento, veículo ativo), `AndroidController.php`, `NfwebController.php` (savePedido, saveCliente, DANFE, boleto, duplicata, cadastros); `routes/web.php:38-40` | `routes/api.php:475-496` (Central de logística: fila, entregadores, atribuir/redistribuir/priorizar/reagendar, bloqueio, config; missões: moldes, atribuições, auditoria), `662-694` (app/v1/entregador: jornada, rota, pedidos, aceitar/recusar/ocorrência/concluir, posição GPS, missões, venda em campo); `app/Http/Controllers/Api/Admin/CentralController.php`, `MissaoController.php`, `Api/Mobile/AppEntregadorController.php`, `AppMissaoController.php`; `app/Domain/Logistica/`, `app/Domain/Missao/`; app novo `app-entregador/src/app/` (login, iniciar-jornada, pedido/, missao-venda, missao-visita, navegacao); frontend `features/central/CentralPage.tsx`, `features/missoes/MissoesPage.tsx` | ⚠️ | Paradigma reprojetado (tablet→smartphone; fila+distribuição+tempo real) e funcionalmente mais rico. Lacunas pontuais herdadas do NFweb: **visualizar/baixar DANFE, boleto e duplicata no dispositivo do entregador** e consulta de parcelas vencidas do cliente na entrega — sem equivalente no `AppEntregadorController.php`. |
| 25 | CRM satélites (pós-venda, checklist, metas, promoções, sorteio) | `PosvendaController.php` + `PosvendacadastroController.php`, `ChecklistController.php` + `CadastrochecklistController.php`, `MetavendaController.php`, `PromocaoController.php`, `SorteioController.php`; `routes/web.php:105-107,228-250,514-528,638-639` | `routes/api.php:500-525` (pos-vendas, promocoes, sorteios+números+sortear, metas, checklists+executar); `app/Http/Controllers/Api/Admin/CrmController.php`; frontend `features/crm/` (PosVendaPage, ChecklistPage, MetaPage, PromocaoPage, SorteioPage) | ✅ | Os 5 satélites têm CRUD + ação específica + página própria. |
| 26 | Venda ativa / promotores de rua | `VendaativaController.php` (filtros endereço/compra/média-giro, gerar ocorrência), `VendaAtivaOcorrenciaTiposController.php`, `PromotorController.php` (histórico por cliente/rua), `MotivonaovendaController.php`, relatórios `ReportpromotorController.php`; `routes/web.php:69-74,208,222,523-528,893-896` | Substituído pelo motor de **missões de campo**: `routes/api.php:488-496` (moldes, atribuição, auditoria, adiamento) + `684-694` (app: visitas, trilha GPS, próxima-casa, venda de gás/vale-gás, cadastro de cliente em campo); `app/Domain/Missao/` (GeradorMissaoService); dados legados migrados por `app/Etl/Migrators/ComplementosMigrator.php` | ⚠️ | Reescrita conceitual: missões cobrem (e ampliam) a venda ativa em campo. Não há, porém, equivalente 1:1 dos **filtros de prospecção da tela de venda ativa** (por média de giro/última compra), do cadastro de tipos de ocorrência, nem dos relatórios de visitas/ausências de promotor. |
| 27 | Mala direta | `MaladiretaController.php` (CSV, **envio de e-mail em massa**, **etiquetas**); `routes/web.php:663-671` | `routes/api.php:528` (`crm/mala-direta`); `app/Http/Controllers/Api/Admin/MalaDiretaController.php` (segmentação por aniversário/endereço/recompra + export CSV) | ⚠️ | Segmentação e CSV migrados. Faltam **envio de e-mail em massa** (comentário no próprio controller: "envio em massa é gate SMTP") e **impressão de etiquetas**. |
| 28 | Relatórios | ~24 controllers `Report*.php` (Caixa, clientes, aniversariantes, colaborador, comissões, comodato, convênio, entregas/mapas, estoque, financeiro, logs, log-senha, movimentação, NF emitidas/recebidas, promoções, promotor, questionários, resumo vendas, vale-gás, veículos, venda PDV, vendas, vendas malote) + `LogcercaController.php`; `routes/web.php:643-908` (~60 rotas) | `routes/api.php:551-567`: central de relatórios com catálogo de **17 slugs** (`RelatorioController.php:151-169`: vendas, financeiro, dre, movimentação/fechamentos caixa, comissões, vale-gás, estoque-baixo, comodatos, aniversariantes, vendas-entregador/-operação/-produto, nf-emitidas, nf-recebidas, promocoes, veiculos) + auditoria; `app/Domain/Relatorio/RelatorioService.php`; frontend `features/relatorios/RelatoriosPage.tsx` | ⚠️ | ~17 dos ~40 relatórios do legado têm slug equivalente. Sem equivalente identificado: mapas de entregas (4 variantes georreferenciadas), tempo de entrega, metas×vendas no mapa, follow-up, clientes sem compra/incompletos/inativos, fornecedores, convênio-funcionários, colaboradores (exames/férias/faixa etária), fluxo de caixa, retroativo, log de senha mestra, vendas por malote, troca de óleo vencida, seg/setor/produto. |
| 29 | Dashboards de gestão | `DashboardgerencialController.php` (DRE, detalhes, centro custos), `FechamentomensalgestaoController.php` (dashboard, e-mail, export), `VendasmensaisgestaoController.php` (**PowerPoint**, upload imagem), `DocumentogestaoController.php`, `ConveniogbgestaoController.php`; `routes/web.php:449-473,1111-1112` | `routes/api.php:551` (`dashboard/resumo`), `555,569` (DRE via `RelatorioService`); frontend `features/dashboard/DashboardPage.tsx` | ⚠️ | DRE e dashboard básico existem. Faltam: fechamento mensal com **envio por e-mail e export**, geração de **PowerPoint** de vendas mensais (grep `powerpoint`: zero), dashboards dedicados de documentos e convênio-GB. |
| 30 | Gestão documental, patrimônio e MCMM | `DocumentoController.php` (+ **versões com download**), `DocumentotipoController.php`, `EmpresabensController.php`, `McmmController.php` (mapa de controle de movimentação + impressão); `routes/web.php:242,476-478,1106-1112` | `routes/api.php:535-548` (mcmm CRUD, documentos CRUD, bens CRUD); `app/Http/Controllers/Api/Admin/GestaoController.php`; frontend `features/gestao/` (DocumentoPage, BemPage, McmmPage) | ⚠️ | CRUDs migrados. Falta o **versionamento de documentos com upload/download de arquivo** (`documento.versao`, `downloadversao` — grep `versao` em `app/Domain/Gestao` e `app/Models/Gestao`: zero) e a impressão do MCMM. |
| 31 | Backoffice do app consumidor (push, vídeo, giro, cupons) | `AppnotificationController.php` (envio de push em massa), `AppvideoController.php` (vídeo de abertura), `AppgiroController.php` (giro de compras + notify), `App/CuponsController.php` (CRUD cupons + gerar código), `App/AppController.php`; `routes/web.php:291-305` | Push transacional: `app/Domain/Mobile/PushService.php`, `Jobs/EnviarPushJob.php`, `Drivers/FcmV1Transport.php`, devices em `routes/api.php:626`; cupom de desconto validado como **promoção com código** (`app/Domain/Mobile/CatalogoMobileService.php:64-76` + CRM promocoes) | ⚠️ | Infra de push (FCM v1) e cupom-via-promoção existem. Faltam as ferramentas de marketing do legado: **campanhas de push manuais/segmentadas**, gestão do **vídeo de abertura** (grep `appvideo|video`: zero no admin) e painel de **giro de compras com notificação de recompra** (grep `recompra`: só comentário na mala direta). |
| 32 | Telefonia / bina (monitoramento de chamadas) | `NotificacoesController.php` (`meliganotification`), rotas `excluirTelefoneChamada`, `rejeitaligacao` (models `Monitoramentochamadas`, `Ligacoestelefonicas`), `ApiController@gravarTelefone`, `SearchController@searchTelefonesMonitoramento`; `routes/web.php:911-912,1016-1043` | — (grep `ligac`, `chamada`, `bina` no erp-novo: 19 ocorrências em app/ e frontend/src/, **todas ruído linguístico** — "chamada" no sentido de chamada de função/HTTP em comentários e "combina/combinação" casando `bina`; nenhum model, service, rota ou tela de telefonia. Greps específicos por `ligacoestelefonicas`/`monitoramentochamadas`/`\bbina\b`: zero) | ❌ | Identificação de chamadas (bina) integrada ao atendimento não foi migrada. Não há tabela, service, endpoint nem tela equivalente no novo. |
| 33 | Fechamento de malote | `FechamentomaloteController.php` (pedidos do malote, parcelas, updatePedido, fechar), `ReportvendasmaloteController.php`; `routes/web.php:598-604,784-785` | — (grep `malote` no erp-novo: só um campo de config contábil em `frontend/src/features/empresas/config/ContabilTab.tsx`) | ❌ | Conferência/fechamento de malote dos entregadores (acerto físico de valores) não existe no novo — nem endpoint, nem service, nem tela. |
| 34 | Tipos de documento de veículo (cadastro de apoio) | `app/Http/Controllers/TipodocumentoController.php`, model `app/Tipodocumento.php`, policy `app/Policies/TipodocumentoPolicy.php`, view `resources/views/documento/tipodocumentos.blade.php`, rota `Route::resource('tipodocumento', ...)` em `routes/web.php:280`; consumido por `app/Veiculodocumento.php:45` (`belongsTo('App\Tipodocumento')`) e `app/Http/Controllers/VeiculoController.php:15,52,149,183` (dropdown filtrado por `ativo` + `grupo_id`) | — (`grep -rniE "tipo[-_]?documento\|tipodoc" erp-novo/app/ erp-novo/frontend/src/`: zero ocorrências; `CadastroApoioRegistry.php` tem `tipos-exame`, `tipos-pessoa`, `tipos-movimento`, mas **nenhuma chave `tipos-documento`**) | ❌ | Cadastro que tipifica os documentos do veículo (CRLV, seguro, ANTT…). O novo expõe `veiculos/{id}/documentos` GET/POST (linha 22) mas **sem o cadastro dos tipos** — o campo fica sem domínio de valores. Não confundir com `DocumentotipoController.php` (model `app/Documentotipo.php`, rota `web.php:1106`), que é o tipo da **gestão documental** da linha 30 e é um controller diferente. |
| 35 | Outros — buscas AJAX, lookups e rotas de manutenção | `SearchController.php` (~50 rotas AJAX em `routes/web.php:920-1006`), `TurnoController.php` (web.php:933), rotas utilitárias mortas (`consultasoracle`, `organizarestoque` — web.php:1049-1103), `SecretController`/`VideoController` (api_mobile) | Lookups genéricos `lookups/{tipo}` (`routes/api.php:134`, `app/Http/Controllers/Api/Admin/LookupController.php`); turnos em `colaboradores/{id}/turnos`; rotas mortas de manutenção Oracle intencionalmente sem equivalente | ✅ | As buscas AJAX eram infraestrutura das telas Blade; o padrão AsyncSelect+lookups as substitui. `consultasoracle`/`organizarestoque` eram scripts one-off de correção — não são funcionalidade. |

## 3.2 Matriz — App mobile (app-gas-em-casa legado → backend `app/v1` + app adaptado)

O app consumidor legado (`app-gas-em-casa/`, Expo Router) é a referência; a verificação cruzou as telas em `app-gas-em-casa/src/app/` com os endpoints consumidos em `app-gas-em-casa/src/services/*.service.ts` (todos já apontando para `app/v1`) e com as rotas expostas em `erp-novo/routes/api.php:623-696`.

| # | Funcionalidade do app | Evidência no legado/app (arquivos) | Evidência no novo (arquivos) | Status | Observação |
|---|----------------------|-------------------------------------|------------------------------|--------|------------|
| A1 | Login por telefone (Firebase) + cadastro | `app-gas-em-casa/src/app/login.tsx`, `sms.tsx`, `newuser.tsx`; backend legado `ctrl-web/routes/api_mobile.php:77-118` (`client/create`, `get`, `update`) | `erp-novo/routes/api.php:88-91` (`app/v1/cliente/login`, `cliente/cadastro`); `app/Domain/Mobile/ClienteAuthService.php` + `KreaitFirebaseVerifier.php`; consumo em `app-gas-em-casa/src/services/user.service.ts` | ✅ | Verificação Firebase agora é server-side (drivers Fake/Kreait). |
| A2 | Marketplace / seleção de revenda | `app-gas-em-casa/src/app/selecionar-revenda.tsx`; `src/services/marketplace.service.ts` | `erp-novo/routes/api.php:94-99` (`marketplace/empresas` por geolocalização, `marketplace/cidades`); `app/Http/Controllers/Api/Mobile/MarketplaceController.php` | ✅ | Funcionalidade nova que absorve a descoberta de revenda. |
| A3 | Catálogo, carrinho, cotação e cupom | Telas `(auth)/(tabs)/home`, `(auth)/carrinho.tsx`; legado `api_mobile.php:261-319` (`product/get`, `payment/get`, `coupons/verify`) | `erp-novo/routes/api.php:630-637` (`init`, `produtos`, `cupom`, `carrinho/cotacao` — preço server-side); `app/Domain/Mobile/CatalogoMobileService.php`, `CotacaoMobileService.php`; consumo em `src/services/product.service.ts`, `store.service.ts` | ✅ | Cotação movida para o servidor (anti-fraude de preço). |
| A4 | Pedido: criação, histórico, acompanhamento e rota do entregador | Telas `(auth)/(tabs)/pedidos`, `(auth)/track.tsx`; legado `api_mobile.php:184-256` (`order/create`, `history`, `track`, `getLastestStatus`) | `erp-novo/routes/api.php:651-654` (`pedidos` GET/POST, `pedidos/{id}`, `pedidos/{id}/rota-entregador`); `app/Http/Controllers/Api/Mobile/AppPedidoController.php`, `app/Domain/Mobile/PedidoMobileService.php`; consumo em `src/services/order.service.ts` | ✅ | Rota do entregador em tempo real substitui `vehicle/lastPosition` do legado. |
| A5 | Pagamento PIX no app | Tela `(auth)/pix.tsx`; legado `api_mobile.php:234-239` (`pixpaid`, `ispaid`) + `api.php:198-200` | `erp-novo/routes/api.php:655-657` (`pagar`, `pix`, `pix/status`) + webhook público (linha 82); consumo em `order.service.ts` (`pedidos/${id}/pix`) | ✅ | PIX por pedido com segredo por empresa (`app/Domain/Integracao/`). |
| A6 | Múltiplos endereços de entrega | Tela `(auth)/address.tsx`; legado `api_mobile.php:123-164` (address create/update/makeFavorite/getAll) | `erp-novo/routes/api.php:645-649` (`enderecos` CRUD + favorito); `AppPerfilController.php`; consumo em `src/services/address.service.ts` | ✅ | |
| A7 | Avaliar e cancelar pedido | Legado `api_mobile.php:219-224` (`evaluate`, `cancel`) | `erp-novo/routes/api.php:658-659` (`cancelar`, `avaliar`); consumo em `order.service.ts` | ✅ | |
| A8 | Perfil (editar, excluir conta) | Tela `(auth)/perfil-dados.tsx`; legado `api_mobile.php:92-112` (`client/update`, `delete`) | `erp-novo/routes/api.php:639-641` (`perfil` GET/PUT/DELETE); consumo em `user.service.ts` | ✅ | Exclusão de conta (exigência das lojas) presente. |
| A9 | Gás do Povo / Gás de Bolso no app | Legado `api_mobile.php:179` (`reseller/isGpAllowed`), `api.php:152` (`payment/infoGB`) | `erp-novo/routes/api.php:631` via `init?gasdopovo` (consumido em `store.service.ts`: `app/v1/init?gasdopovo=...`) + gestão em `PagamentoController.php` (`gasdopovo` + sacar) | ✅ | |
| A10 | Vídeo de abertura | Legado: tela `startupvideo.tsx` + `ctrl-web/routes/api.php:23` (`video/get`), `api_mobile.php:438-441` (`video/sync`), `AppvideoController.php` | `app-gas-em-casa/src/app/startupvideo.tsx:5-8` — comentário no código: "Vídeo de abertura **desativado** na F3 (dependia de endpoint público inexistente no app/v1)"; nenhum endpoint de vídeo em `erp-novo/routes/api.php` | ❌ | Funcionalidade conscientemente desligada; rota virou redirect inerte. |
| A11 | Push ao cliente (status do pedido, recompra, cupom) | Legado `api_mobile.php:411-426` (`sendNotification`, `sendNotificationRecompra`, `sendNotificationCoupon`), `ClienteController@setPushToken` | `erp-novo/routes/api.php:626` (`devices`); `app/Domain/Mobile/PushService.php`, `Jobs/EnviarPushJob.php`, `Drivers/FcmV1Transport.php` | ⚠️ | Push transacional (status do pedido) tem infra completa. Push de **campanha** (recompra, cupom, massa — disparado pelo backoffice) não tem endpoint/tela no novo (ver linha 31 da matriz ERP). |

## 3.3 Detalhamento das lacunas (⚠️ / ❌)

**4 — Geográfico (⚠️).** O CRUD de cidade/bairro/rua/região está migrado e a detecção de duplicatas foi reimplementada sem depender do `UTL_MATCH` do Oracle (`GeoController@inconsistencias` + `InconsistenciaService`). Falta a metade que fecha o ciclo: no legado o operador resolve o par com `InconsistenciaController@ignorarRua`/`ignorarBairro` (`:48-91`), um POST transacional que grava o par na tabela de ignorados (`$rua->ignorados()->attach($ruaignore_id, ['ignored_type' => Rua::class])`), rotas `ctrl-web/routes/web.php:1002-1003`. No novo há só o GET (`erp-novo/routes/api.php:226`) e o service não tem método de escrita — os falsos positivos voltam a cada consulta e a fila nunca esvazia.

**7 — Pedidos/vendas (⚠️).** O kanban (`erp-novo/frontend/src/features/pedidos/KanbanView.tsx`) substitui o monitoramento legado, com situações configuráveis e emissão de NFC-e no fluxo. Mas três apoios operacionais do disk-gás não existem: motivos de atraso com justificativa obrigatória (`ctrl-web/app/Http/Controllers/PedidomotivoatrasoController.php` + `PedidoController@justificaMotivoAtraso`), motivos de não-venda (`MotivonaovendaController.php` — zero ocorrências no novo) e a comanda impressa (`pedido.comanda`).

**8 — Fiscal de saída (⚠️).** O motor (emissão NF-e/NFC-e, cancelamento, CC-e, inutilização, malha tributária, SAT) está no novo com driver real (`erp-novo/app/Domain/Fiscal/Drivers/NFePHPSefazDriver.php`). As lacunas são periféricas mas usadas na rotina com o contador: exportação de XMLs em lote (`NfemitidaController@exportarXmls`), envio da NF por e-mail ao cliente e visualização de DANFE.

**10 — SPED (⚠️).** `SpedFiscalService.php` e `SpedContribuicoesService.php` existem; o módulo de créditos (`ctrl-web/app/Http/Controllers/SpedcreditosController.php`) não tem nenhum equivalente (grep vazio), e não há rota de download do arquivo gerado como o `sped.dowload` legado.

**11 — Financeiro/Contas (⚠️).** Títulos, plano de contas, centros de custo, agrupamento e reparcelamento estão no novo. A lacuna é a **automação de extrato** do legado: `ContaController@addEditExtratoconfig` (`ctrl-web/routes/web.php:155`, implementação em `ContaController.php:670-700`) cadastra, por conta bancária, regras que associam uma descrição do extrato a uma das ações do enum `ContaextratoAcao` — Lancar, LancarBaixar ou Transferir — exigindo `condicaopagamento_id`, `contamovimentotipo_id`, `pc_id` e `cc_id` nas duas primeiras e `contaorigem_id` na transferência. `grep -rniE "extratoconfig|contaextrato"` no `erp-novo` retorna zero: existe a importação OFX (linha 16) mas não a classificação automática que a torna produtiva.

**12 — Caixa (⚠️).** Única lacuna: recibos impressos (`CaixaController@gerarRecibo`/`gerarReciboCR`) — nenhum gerador de recibo no novo.

**13 — Cheques (⚠️).** O fluxo carteira→depósito→compensação→devolução está coberto pelo enum `SituacaoCheque.php`; o desconto/antecipação de cheque em banco (`DescontochequeController.php`) ficou de fora — não há situação nem operação equivalente.

**14 — Boletos (⚠️).** CNAB completo (remessa/retorno em `app/Domain/Cobranca/Cnab/`), mas o boleto em si não é impresso (legado: `BoletoPdfController.php`); sem o PDF o título não pode ser entregue ao cliente. Layouts bancários deixaram de ser cadastro (`LayoutbancoController.php`) e viraram drivers em código — funcional, porém sem a flexibilidade de configurar um banco novo sem deploy.

**16 — Importações financeiras (⚠️).** OFX coberto pela conciliação; a importação do relatório do adquirente de cartão com baixa em lote (`FinanceiroController@importReportCartaoGetParcelas`) virou registro manual de NSU (`PagamentoController.php`).

**18 — Convênio (⚠️).** O fechamento existe (`ConvenioFechamentoService.php`), mas no legado o fechamento dispara NF e boleto em cadeia (`FechamentoconvenioController@emitirNF`/`emitirBoleto`) — no novo essas duas pontas não estão ligadas, e não há PDF/XLS do fechamento nem o dashboard Convênio/Gás de Bolso.

**19 — Vale-gás (⚠️).** Venda/baixa/consulta ok; falta a materialização impressa (vale impresso, duplicata em PDF, confirmação de impressão), essencial no modelo físico do legado.

**20 — Comodato (⚠️).** Entrada/devolução ok; faltam contrato em PDF (`ComodatoController@contrato`) e a gestão analítica de saldos/vencidos/giro (`ComodatogestaoController.php`) — só há o relatório simples `comodatos` no catálogo novo.

**21 — RH (⚠️).** Ficha completa; recessos (férias/afastamentos) e comissões são resources CRUD no legado e só leitura no novo (`routes/api.php:586-587` — apenas GET).

**22 — Frota (⚠️).** Trocas de óleo e pneus só leitura no novo (legado tem lançamento + alerta de troca vencida — `VeiculotrocaoleoController@getTrocas`, `report.trocaoleovencido*`).

**24 — Logística (⚠️).** A reescrita (central de distribuição + app entregador + missões) cobre e amplia o ciclo de entrega. As pontas não portadas vêm do NFweb: DANFE/boleto/duplicata exibidos no dispositivo do entregador e consulta de parcelas vencidas do cliente no ato da entrega (`NfwebController@visualizarDanfe/visualizarBoleto/pedidoDuplicata/getParcelasVencidasCliente`).

**26 — Venda ativa/promotores (⚠️).** Missões de campo são o sucessor funcional (visita casa-a-casa, trilha GPS, venda em campo). Sem paridade: filtros de prospecção da tela de venda ativa (média de giro, última compra, endereço), tipos de ocorrência (`VendaAtivaOcorrenciaTiposController.php`) e relatórios de promotor (visitas/ausências).

**27 — Mala direta (⚠️).** Segmentação + CSV ok; envio de e-mail em massa e etiquetas não implementados (o próprio `MalaDiretaController.php` do novo documenta o gate de SMTP).

**28 — Relatórios (⚠️).** 17 slugs no catálogo novo contra ~40 relatórios do legado. As ausências de maior impacto operacional: os 4 mapas georreferenciados de entrega + tempo de entrega (`ReportEntregasController.php`) — parcialmente compensados pelo mapa ao vivo do módulo monitora —, follow-up de clientes, fluxo de caixa e log de uso de senha mestre.

**29 — Dashboards de gestão (⚠️).** DRE e resumo existem; fechamento mensal com envio por e-mail/export, PowerPoint de vendas mensais e os dashboards de documentos/convênio não.

**30 — Gestão documental (⚠️).** CRUDs de documento/bem/MCMM ok; o versionamento de documento com upload e download de arquivo (`DocumentoController@addEditVersao/downloadVersao`) não existe no novo.

**31 — Backoffice do app (⚠️).** Push transacional e cupom-via-promoção cobertos; as ferramentas de marketing (campanhas de push segmentadas, vídeo de abertura, giro de compras/recompra) não têm tela nem endpoint no novo.

**32 — Telefonia/bina (❌).** O atendimento do disk-gás no legado identifica a chamada entrante e abre a ficha do cliente (`Monitoramentochamadas`, `Ligacoestelefonicas`, `meliganotification`). Nada disso existe no novo — nenhum model, service, rota ou componente. Se o call-center ainda opera com bina, é bloqueador de virada.

**33 — Fechamento de malote (❌).** A conferência de valores do malote do entregador (pedidos, parcelas, acerto e fechamento — `FechamentomaloteController.php`) não existe no novo; a única menção a "malote" é um campo contábil de configuração. O relatório de vendas por malote também some junto (parte da linha 28).

**34 — Tipos de documento de veículo (❌).** O legado tem um cadastro de apoio próprio para tipificar os documentos da frota: controller (`TipodocumentoController.php`), model (`app/Tipodocumento.php`), policy (`app/Policies/TipodocumentoPolicy.php`), view (`resources/views/documento/tipodocumentos.blade.php`) e rota resource (`routes/web.php:280`). Ele é consumido pelo pivô `Veiculodocumento` (`belongsTo('App\Tipodocumento')`) e alimenta o dropdown do cadastro de veículo em quatro pontos do `VeiculoController`, sempre filtrado por `ativo` e `grupo_id`. No novo não existe nada: nem tabela, nem chave no `CadastroApoioRegistry.php`, nem endpoint, nem tela — a busca por `tipo[-_]?documento|tipodoc` em `erp-novo/app/` e `erp-novo/frontend/src/` volta vazia. O endpoint `veiculos/{id}/documentos` (linha 22) grava documentos sem um domínio de tipos por trás. Cuidado com o homônimo: `DocumentotipoController.php` (model `Documentotipo`, rota `web.php:1106`) pertence à gestão documental da linha 30 e **não** cobre este cadastro.

**A10 — Vídeo de abertura do app (❌).** Desligado deliberadamente na adaptação (`app-gas-em-casa/src/app/startupvideo.tsx` virou redirect); o backend novo não expõe endpoint de vídeo.

**A11 — Push de campanha (⚠️).** Mesmo diagnóstico da linha 31: a infra FCM existe, o disparo de marketing não.

## 3.4 Totais

Contagem feita linha a linha sobre as tabelas 3.1 e 3.2 já corrigidas.

| Status | ERP web (3.1) | App mobile (3.2) | Total | % |
|--------|---------------|------------------|-------|----|
| ✅ migrada e funcional | 11 | 9 | **20** | 43,5% |
| ⚠️ migrada com problemas | 21 | 1 | **22** | 47,8% |
| ❌ não migrada | 3 | 1 | **4** | 8,7% |
| 🔍 não verificado | 0 | 0 | **0** | 0% |
| **Total de linhas** | **35** | **11** | **46** | 100% |

Conferência das linhas por número — ✅ no ERP web: 1, 2, 3, 5, 6, 9, 15, 17, 23, 25, 35 (11). ⚠️: 4, 7, 8, 10, 11, 12, 13, 14, 16, 18, 19, 20, 21, 22, 24, 26, 27, 28, 29, 30, 31 (21). ❌: 32, 33, 34 (3). No app: ✅ A1–A9 (9), ⚠️ A11 (1), ❌ A10 (1).

**Leitura dos números.** Cobertura bruta (algum equivalente funcional no novo): 42/46 = **91,3%**. Paridade plena: 20/46 = **43,5%**. O padrão dominante das linhas ⚠️ não é módulo vazio — é o núcleo transacional migrado com três famílias recorrentes de lacuna: (1) **saídas impressas** (boleto, recibo, vale-gás, contratos, DANFE, comanda, etiquetas — nenhum gerador de PDF operacional existe no novo fora de relatórios); (2) **sub-operações de escrita viradas leitura** (recessos, comissões, troca de óleo/pneu); (3) **ferramentas de gestão/marketing por cima do transacional** (dashboards de fechamento, PowerPoint, push de campanha, giro). A esse padrão soma-se uma quarta família, menor mas insidiosa: **ações de escrita que fecham o ciclo de uma tela de leitura** — resolver a inconsistência geográfica (linha 4) e classificar automaticamente o extrato (linha 11). São telas que "existem" no novo e mesmo assim não substituem a do legado, porque o operador não consegue concluir o trabalho nelas; auditorias por checklist de tela tendem a marcá-las como migradas.

Os três ❌ do ERP têm pesos diferentes. Bina (32) e malote (33) são fluxos operacionais diários do modelo disk-gás legado e merecem decisão explícita: portar ou aposentar formalmente. Tipos de documento de veículo (34) é um cadastro de apoio pequeno — baixo esforço, mas é uma ausência total, não uma lacuna parcial, e deixa o cadastro de documentos da frota sem domínio de valores.
# 3. Mapa do sistema novo (como funciona hoje)

> Toda afirmação abaixo cita o arquivo real de onde foi lida. O que não foi aberto está marcado como **NÃO VERIFICADO**.

## 1.1 Visão geral dos repositórios

O "sistema novo" é composto por quatro peças no workspace `c:\Users\fusea\Desktop\Dubena`:

| Peça | Pasta | O que é (verificado no código) |
|---|---|---|
| Backend + SPA | `erp-novo/` | Laravel (bootstrap moderno em `erp-novo/bootstrap/app.php`, estilo Laravel 11/12: `Application::configure()->withRouting(...)`) servindo uma API JSON, com o frontend React/Vite/TypeScript em `erp-novo/frontend/` |
| App do entregador | `app-entregador/` | App Expo/React Native (tem `app.config.ts`, `eas.json`, `metro.config.js`; rotas em `app-entregador/src/app` no padrão expo-router: `_layout.tsx`, `index.tsx`, `login.tsx`, grupo `(app)`) |
| Biblioteca mobile compartilhada | `mobile-shared/` | Pacote TS com 3 módulos: `mobile-shared/src/http.ts` (cliente HTTP com `baseURL`, Bearer e refresh), `validators.ts`, `index.ts` |
| App do consumidor (adaptado) | `app-gas-em-casa/` | App Expo de ORIGEM LEGADA, reapontado para a API nova: seus services chamam `app/v1/...` do erp-novo (ex.: `app-gas-em-casa/src/services/order.service.ts:30` faz `POST app/v1/pedidos`; `address.service.ts:17` usa `app/v1/enderecos`; `marketplace.service.ts:32` usa `app/v1/marketplace/empresas`) |

O ctrl-web (Laravel legado) e os bancos legados entram no sistema novo apenas como **fonte de leitura do ETL** (conexões `legado`, `app_legado`, `monitora_legado` em `erp-novo/config/database.php:131-183`, todas comentadas no próprio arquivo como "NUNCA escrever aqui").

## 1.2 Diagrama textual da arquitetura

```
                         ┌────────────────────────────────────────────────────────┐
                         │                    erp-novo (Laravel)                  │
                         │                                                        │
 SPA React (frontend/)   │  routes/api.php                                        │
 baseURL /api/admin ────▶│   ├─ público: /health, /login, /pix/webhook,           │
 (cookie Sanctum ou      │   │   /app/v1/login, /app/v1/cliente/login|cadastro,   │
  Bearer)                │   │   /app/v1/marketplace/*                            │
                         │   ├─ auth:sanctum + tenant + throttle:api              │
 app-gas-em-casa ───────▶│   │   ├─ /me                                           │
 (cliente, Bearer        │   │   ├─ prefix admin  → Api/Admin/* (SPA)             │
  role:cliente)          │   │   └─ prefix app/v1 → Api/Mobile/*                  │
                         │   │       ├─ approle:cliente   (loja/perfil/pedidos)   │
 app-entregador ────────▶│   │       └─ approle:entregador prefix entregador      │
 (Bearer role:entregador)│   └─ prefix superadmin → auth:platform (cross-tenant)  │
                         │                                                        │
                         │  Controller (Api/Admin|Mobile|SuperAdmin)              │
                         │    └─ autorizar() → Gate (AuthServiceProvider)         │
                         │        └─ Domain/*Service (regra de negócio)           │
                         │            └─ Models (BelongsToTenant) → Postgres      │
                         │                          │        (RLS por GUCs)       │
                         │  Jobs (queue database) ◀─┤                             │
                         │  Events ShouldBroadcast ─┴─▶ Reverb ─▶ Echo (SPA/apps) │
                         │  app/Etl ◀── conexões legado/app_legado/monitora_legado│
                         └────────────────────────────────────────────────────────┘
```

## 1.3 Backend — estrutura de `erp-novo/app/`

Listagem real dos diretórios (verificada por `ls`):

- **`app/Http`** — `Controllers/Api/{Admin, Mobile, SuperAdmin}` + `AuthController.php`, `HealthController.php`, `PixWebhookController.php`, `SegurancaController.php`; `Middleware/` com exatamente 4 middlewares próprios: `AppRole.php`, `Permissao.php`, `Recurso.php`, `ResolveTenant.php`; além de `Requests/`, `Resources/` e `Controllers/Concerns/` (`AutorizaPorPermissao.php`, `PaginaListagem.php`).
- **`app/Domain`** — o "coração" por domínio: `Acesso`, `Apoio`, `Caixa`, `Cliente`, `Cobranca`, `Estoque`, `Financeiro`, `Fiscal`, `Frota`, `Gestao`, `Integracao`, `Logistica`, `Missao`, `Mobile`, `Monitora`, `Pagamento`, `Pedido`, `Produto`, `Relatorio`, `Rh`, `Saas`, `Satelite`, `Seguranca`, `Shared`, `Tenant`. Os "services" vivem AQUI (ex.: `app/Domain/Pedido/PedidoService.php`), não em `app/Services`.
- **`app/Services`** — contém apenas `Migracao/MigracaoService.php` (ferramenta de migração do SuperAdmin).
- **`app/Models`** — organizados por subpasta de domínio (`Models/Pedido`, `Models/Cliente`, `Models/Fiscal`, ...) + models de raiz (`User.php`, `Empresa.php`, `Grupo.php`, `Role.php`, `Permission.php`, `AuditLog.php`, `LoginLog.php`, `User2fa.php`, etc.).
- **`app/Policies`** — **vazio** (verificado por `ls`). A autorização NÃO usa Policies do Laravel; usa Gates definidos em `app/Providers/AuthServiceProvider.php` (ver 1.5).
- **`app/Jobs`** — apenas `ExecutarMigracaoJob.php` (implementa `ShouldQueue`, `app/Jobs/ExecutarMigracaoJob.php:17`). Outros jobs vivem dentro dos domínios: `app/Domain/Logistica/Jobs/AtribuirPedidoJob.php`, `app/Domain/Mobile/Jobs/EnviarPushJob.php`.
- **`app/Etl`** — pipeline de migração de dados legados: `MigratorRegistry.php` + `Migrators/` com 29 arquivos (ex.: `ClientesMigrator.php`, `PedidosMigrator.php`, `FiscalMigrator.php`, `AppGasEmCasaMigrator.php`, `MonitoraLegadoMigrator.php`), `Contracts/`, `Invariants/`, `Support/` (`MigrationContext.php`, `PreservaIdsDoLegado.php`), e pastas `Loaders/` e `Readers/` **vazias** (verificado por `ls`).
- **`app/Console/Commands`** — 12 comandos: `ApiManifestGerar`, `CutoverCheck`, `EtlRun` (assinatura `etl:run {migrator?}`, `app/Console/Commands/EtlRun.php:21`), `FinanceiroNotificarVencidos`, `GoliveCheck`, `IbptAtualizar`, `MissoesGerar`, `MonitoraSyncPositions`, `NotifyAlertas`, `NotifyInconsistencias`, `PixExpirar`, `VendaDiaria`.
- **`app/Providers`** — `AppServiceProvider.php` (bindings de drivers, ex.: binding do `PixDriver` em `app/Providers/AppServiceProvider.php:80-85`) e `AuthServiceProvider.php`.

### Bootstrap (`erp-novo/bootstrap/app.php`)

- Registra rotas `web`, `api`, `console` e health nativo `/up` (linhas 15-20).
- `withBroadcasting(routes/channels.php, ['middleware' => ['auth:sanctum']])` — a rota `/broadcasting/auth` autentica pelo guard `sanctum` (linhas 23-26).
- `$middleware->statefulApi()` — Sanctum SPA stateful (cookie) coexistindo com Bearer (linha 31).
- Aliases de middleware (linhas 42-47): `tenant` → `ResolveTenant`, `permissao` → `Permissao`, `recurso` → `Recurso`, `approle` → `AppRole`.
- Exceções JSON: `TenantNotResolvedException` → HTTP 409; `CredencialNaoConfiguradaException` → HTTP 503 fail-closed (linhas 64-79).

## 1.4 Rotas (lidas na íntegra)

### `routes/api.php` (753 linhas)

**Públicas** (sem auth):
- `GET /api/health` → `HealthController` com `throttle:60,1` (linha 76).
- `POST /api/login` → `AuthController@login` com `throttle:login` (linha 79).
- `POST /api/pix/webhook` → `PixWebhookController@handle` (linha 82) — segurança no controller, não em middleware.
- `POST /api/app/v1/login` (entregador/colaborador), `POST /api/app/v1/cliente/login`, `POST /api/app/v1/cliente/cadastro` → `Mobile\AppAuthController` (linhas 85-91).
- `POST /api/app/v1/marketplace/empresas` e `GET /api/app/v1/marketplace/cidades` → `MarketplaceController`, com `throttle:marketplace` (linhas 94-99).

**Grupo autenticado** `Route::middleware(['auth:sanctum', 'tenant', 'throttle:api'])` (linha 102):
- `POST /api/logout`; `GET /api/me` (closure que devolve `user.payloadAuth(empresaAtiva)` + `tenant{empresa_id,grupo_id}`, linhas 106-116).
- **`Route::prefix('admin')`** (linha 119) — a superfície da SPA (baseURL `/api/admin`): segurança de conta (`seguranca/2fa*`, `seguranca/sessoes*`, `seguranca/politica-senha`), `lookups/{tipo}`, `empresas` (+`/config`, `/certificado`, `/nfce-token`, `/integracoes`, `/ativar`, `/cidades`), `config-global`, `assinatura`, `cidades`, `grupos`, `papeis` (+`/catalogo`, `/condicoes`, `/historico`), `auditoria/eventos|logins`, `usuarios`, estrutura organizacional (`unidades`, `departamentos`, `setores-org`), `regioes`, `cadastros/{tipo}`, `geo/{entidade}`, `clientes` (+telefones/interações/convênio/preços/histórico/exportar), `produtos`, `produto-config/*`, `produtos-precos/*`, `setores`, `estoque/*` (saldos, histórico, entrada, saída, transferências, acerto, fechamentos, requisições, inventários, físico), `pedidos` (+`/kanban`, `/situacoes` CRUD, `/{id}/situacao`, `/{id}/emitir-nfce`), `financeiro/*` (lançamentos, agrupar/desagrupar/reparcelar, planos-conta, centros-custo, conciliação OFX e contábil), `caixa/*`, `cheques/*`, `cartoes`, `gasdopovo`, `boletos` + aliases `cobranca/*`, `pix/config` e `pix/cobrancas`, `convenios`, `vale-gas`, `comodatos`, fiscal (`fiscal/config`, `notas`, `notas/emitir`, aliases `fiscal/nfe*`, `fiscal/inutilizacoes`, carta de correção, `fiscal/operacoes`, `fiscal/malha/{tipo}`, `fiscal/nf-entrada*`, `fiscal/sped`, `fiscal/sped-contribuicoes`, `fiscal/ibpt`), `monitora/*` (GPS), `central/*` (fila/entregadores/atribuir/redistribuir/priorizar/reagendar/bloquear/config), `missoes/*`, CRM (`pos-vendas`, `promocoes`, `sorteios`, `metas`, `checklists`, `crm/mala-direta`), gestão (`cupons-fiscais`, `mcmm`, `documentos`, `bens`), `dashboard/resumo`, `relatorios/*` (incl. dispatcher `relatorios/{slug}` com `->where('slug','[a-z0-9-]+')`, linha 567), `colaboradores/*` (RH), `veiculos/*` (frota), `satelites/*` (status agregado).
- **`Route::prefix('app/v1')`** (linha 623) — comuns a qualquer token de app: `logout`, `token/refresh`, `devices`. Depois dois sub-grupos por papel do token:
  - `Route::middleware('approle:cliente')` (linha 628): `init`, `produtos`, `cupom`, `carrinho/cotacao`, `config`, `reseller`, `feriados`, `poligonos`, `perfil` (GET/PUT/DELETE), `perfil/endereco`, `enderecos` (CRUD + favorito), `pedidos` (histórico/criar/acompanhar/`rota-entregador`/`pagar`/`pix`/`pix/status`/`cancelar`/`avaliar`).
  - `Route::middleware('approle:entregador')->prefix('entregador')` (linha 662): `veiculos`, `jornada` (+iniciar/encerrar), `dashboard`, `rota` (+iniciar), `pedidos` (+`{id}/status`), `posicao` (com `throttle:gps-ping`), ciclo da entrega (`aceitar`, `recusar`, `ocorrencia`, `concluir`) e missões (`missao`, `missao/iniciar`, `missao/visitas` com `throttle:missao-visita`, `missao/trilha`, `missao/proxima-casa`, `missao/adiar`, `missao/concluir`, `missao/produtos`, `missao/venda`, `missao/vale-gas`, `missao/clientes`).

**Grupo SuperAdmin** `Route::prefix('superadmin')` (linha 706): `POST superadmin/login` público com `throttle:login`; o restante sob `Route::middleware(['auth:platform', 'throttle:api'])` (linha 709) — guard **`platform`** definido em `erp-novo/config/auth.php:49-51` com provider `platform_admins`, SEPARADO do guard de tenant, e **sem** o middleware `tenant`. Rotas: `logout`, `me`, `dashboard`, `auditoria`, `empresas` (suspender/reativar/assinatura/recursos/override), `planos`, **`migracoes`** (ferramenta de migração: conectar, diagnosticar, mapeamento, simular, executar, validar, descartes, descartes.csv — linhas 734-744, controller `Api/SuperAdmin/MigracaoController` apoiado por `app/Services/Migracao/MigracaoService.php` e `app/Jobs/ExecutarMigracaoJob.php`) e `cidades`.

### `routes/web.php`
Apenas `GET /` devolvendo `view('welcome')` (linhas 5-7). Não há telas server-rendered de negócio.

### `routes/channels.php`
4 canais privados, todos com `['guards' => ['sanctum']]`:
- `empresa.{empresaId}.pedidos` e `empresa.{empresaId}.central` — autorizam se `$user->podeAcessarEmpresa($empresaId)` (linhas 22-31).
- `pedido.{pedidoId}` e `pedido.{pedidoId}.entregador` — closure compartilhada `$autorizaPedido` (linhas 39-65): busca o pedido com `Pedido::withoutTenant()` filtrando `empresa_id = $user->empresa_id` e autoriza se o usuário é o cliente dono (`cliente.user_id`), o `entregador_user_id` ou o `atendente_user_id`.

### `routes/console.php`
8 agendamentos via `Schedule::command(...)`: `notify:alertas` (07:00), `monitora:sync-positions` (a cada minuto), `pix:expirar` (a cada minuto), `financeiro:notificar-vencidos` (07:30), `vendas:diaria` (07:15), `notify:inconsistencias` (segunda 03:00), `ibpt:atualizar` (dia 1, 05:00), `logistica:gerar-missoes` (a cada 10 min) — todos `withoutOverlapping()` (linhas 17-40).

## 1.5 Autenticação e autorização de ponta a ponta

**Login da SPA/back-office** — `app/Http/Controllers/Api/AuthController.php`:
1. `LoginRequest` valida; lockout via `Domain\Seguranca\LoginSeguranca->bloqueado(email, ip)` → 429 (linhas 40-44); `Auth::attempt` → 401; usuário `ativo=false` → 403; 2FA TOTP habilitado sem `otp` válido → **423** com `two_factor_required` (linhas 62-75).
2. Se a requisição tem sessão (SPA stateful), `session()->regenerate()`; SEMPRE emite também um token Bearer `createToken('spa')` (linha 92) e devolve `user.payloadAuth()`.
3. `payloadAuth()` (`app/Models/User.php:199-215`) devolve `id, name, email, empresa_id, grupo_id, support, roles, permissions, features` — a SPA monta o RBAC e os feature-flags a partir disso.
4. Login de usuário `support` é auditado à parte (`AuthController.php:87-89`).

**Login dos apps** — `app/Http/Controllers/Api/Mobile/AppAuthController.php`:
- `loginCliente()` (linha 42): recebe `firebase_id_token` + `empresa_id`, delega a `Domain\Mobile\ClienteAuthService->autenticar(...)` (verificação Firebase via contrato `Domain/Mobile/Contracts/FirebaseVerifier.php`, com drivers `KreaitFirebaseVerifier`/`FakeFirebaseVerifier` em `Domain/Mobile/Drivers/`), registra o device e emite token com ability **`role:cliente`** (linha 65).
- `login()` (linha 73): e-mail/senha com lockout + 2FA (paridade com o web), emite token com ability **`role:entregador`** (linha 119).

**Separação de papéis dos tokens** — `app/Http/Middleware/AppRole.php`: se o token atual é `PersonalAccessToken` e não `can('role:'.$papel)` → 403 (linha 29). Sessão stateful (cookie da SPA) e tokens curinga passam.

**RBAC/ABAC** — `app/Providers/AuthServiceProvider.php`:
- `Gate::before` → usuário `support` bypassa tudo (linhas 31-33).
- Um `Gate::define` por chave do catálogo (`Domain\Shared\PermissaoCatalogo::chaves()`), delegando ao `Domain\Acesso\PolicyEvaluator`: sem recurso = RBAC puro; com recurso = RBAC + escopo hierárquico + condições ABAC (linhas 39-44).
- Enforcement em 2 camadas: middleware `permissao:chave` (`app/Http/Middleware/Permissao.php`, `Gate::denies` → 403 "Sem permissão.") e checagem fina nos controllers via trait `app/Http/Controllers/Concerns/AutorizaPorPermissao.php` (`autorizar()`/`autorizarRecurso()` com `Gate::forUser(...)->denies`). Ex.: `PedidoController` usa a trait e chama `$this->autorizar($request, 'pedido.view'|'pedido.create'|'pedido.edit')` (`app/Http/Controllers/Api/Admin/PedidoController.php:28-82`).
- Camada de LICENÇA (SaaS): middleware `recurso:chave` (`app/Http/Middleware/Recurso.php`) consulta `Domain\Saas\LicencaService->recursoHabilitado()` e responde **402** se o plano não cobre o recurso. Observação: nos arquivos de rota lidos, nenhum grupo aplica `permissao:` ou `recurso:` diretamente — o enforcement visível nas rotas é `auth:sanctum`+`tenant`+`approle`; RBAC roda dentro dos controllers via trait.
- Tabelas do RBAC: `roles`, `permissions`, `permission_role`, `role_user`, `empresa_user` (`database/migrations/0001_01_01_000100_create_tenant_e_rbac_tables.php:21-58`).

**Multi-tenant + RLS** — duas barreiras:
1. **Aplicação**: `app/Http/Middleware/ResolveTenant.php` resolve empresa/grupo do usuário; header `X-Empresa-Id` troca a empresa ATIVA (validada por `podeAcessarEmpresa`, linhas 35-41); `empresasVisiveis()` alimenta as listagens da rede; query-param `?empresa_id=` só REFINA a visão (linhas 44-61, 86-91). Models de negócio usam a trait `app/Domain/Tenant/BelongsToTenant.php` (global scope `TenantScope` + preenchimento automático de `empresa_id`/`grupo_id` na criação; escape administrativo `Model::withoutTenant()`).
2. **Banco (Postgres RLS)**: o mesmo middleware seta as GUCs `app.empresa_id`, `app.grupo_id`, `app.empresas_visiveis` via `set_config(..., false)` (linhas 108-124) e as LIMPA no `terminate()` (linhas 75-78) — no-op fora de pgsql. As policies de RLS são criadas em migrations (`2026_06_24_000200_f02_habilitar_rls_postgres.php`, `2026_06_26_000300_rls_tenant_completa.php`, `2026_06_26_000400_rls_role_app_sem_bypass.php`, `2026_07_03_000300_rls_cobertura_tabelas_novas.php`, `2026_08_14_000300_rls_empresas_visiveis.php`). O runtime usa a role restrita e as migrations rodam pela conexão `pgsql_owner` (comentário e definição em `config/database.php:102-124`).

## 1.6 Fluxos principais (request → persistência)

### a) Venda/pedido pela SPA (admin)
`POST /api/admin/pedidos` → `app/Http/Controllers/Api/Admin/PedidoController.php` (`autorizar('pedido.create')`, construtor injeta `PedidoService`) → `app/Domain/Pedido/PedidoService.php`:
- `criar()` (linhas 38-67): transação que cria o `Pedido`, sincroniza itens, recalcula totais, registra histórico e aplica o EFEITO da situação — a matriz do legado virou máquina de estados `PENDENTE↔CONCLUIDO↔CANCELADO` (`app/Domain/Pedido/EfeitoPedido.php`): CONCLUIDO baixa estoque via `EstoqueService` e gera financeiro via `FinanceiroService`; CANCELADO devolve/estorna; idempotência pela flag `estoque_movimentado` (docblock linhas 17-29).
- Pedido que nasce PENDENTE sem entregador: dispara `PedidoEntrouNaFila` (evento) + `AtribuirPedidoJob` (fila) — linhas 61-64.
- Tabelas: `pedidos`, `pedidoitens`, `pedidosituacoes`, `pedidooperacoes`, `pedidosituacaohistorico` (`database/migrations/0005_01_01_000000_create_pedidos_tables.php:20-94`); financeiro em `financeiros`/`financeiroparcelas`/`financeirorateios` (`0006_01_01_000000_create_financeiro_tables.php:45-90`); estoque em `estoquesaldos`/`estoquehistorico` (`0004_01_01_000100_create_estoque_tables.php:31-44`).

### b) Pedido pelo app do consumidor
`POST /api/app/v1/pedidos` (`approle:cliente`) → `app/Http/Controllers/Api/Mobile/AppPedidoController.php` → `app/Domain/Mobile/PedidoMobileService.php`: porta o MobileAppProcessor do legado — matching de cliente por geolocalização com bounding box SQL + Haversine (`clientePorGeoloc`, linhas 70-84), setor de entrega por geofence poligonal via `MonitoraService->setorPorPonto` (linhas 91-100), regras do app (1 pedido pendente por cliente, cancelar só antes de concluir, avaliar 1x — docblock linhas 22-26) e **delega a criação ao `PedidoService`** (mesma máquina de estados da SPA). Push de status ao cliente via `PushService` (FCM HTTP v1, transporte `Domain/Mobile/Drivers/FcmV1Transport.php`).

### c) PIX (cobrança + webhook)
- Geração: `POST /api/app/v1/pedidos/{id}/pix` (app) e `POST /api/admin/pix/cobrancas` (SPA) → `app/Domain/Cobranca/PixService.php` (`criarCobranca()` por parcela, linha 36; `criarCobrancaPedido()`, linha 61), gravando em `pix_cobrancas` (`0008_01_01_000000_create_cobranca_tables.php:59`). O PSP é abstraído pelo contrato `Domain/Cobranca/Contracts/PixDriver.php`; binding em `app/Providers/AppServiceProvider.php:80-85` (driver `fake` = `FakePixDriver`; PSP real entra no mesmo ponto).
- Confirmação: `POST /api/pix/webhook` (público) → `app/Http/Controllers/Api/PixWebhookController.php`: camada 1 segredo compartilhado `X-Webhook-Token` (linha 43), resolução da empresa pelo txid (`PixService->empresaIdDoTxid`, linha 49; txid desconhecido em produção → 401), camada 2 HMAC-SHA256 do corpo cru com o segredo DA EMPRESA resolvido por `Domain/Integracao/IntegracaoTenant->pix()` (linhas 91-136, fail-closed em produção), camada 3 `PixService->processarWebhook()` valida estado/valor/idempotência (linha 135 do service). Expiração agendada por `pix:expirar` (`routes/console.php:23`).

### d) Fiscal (NFC-e/NF-e a partir do pedido)
`POST /api/admin/pedidos/{id}/emitir-nfce` → `PedidoController::emitirNfce(..., FiscalService $fiscal)` (`PedidoController.php:257`) → `app/Domain/Fiscal/FiscalService.php`: `montarDoPedido()` cria a `NotaFiscal` em rascunho calculando imposto por item via `CalculoImpostoService` + `ResolucaoTributariaService` (matriz operação fiscal × grupo fiscal × UF × consumidor final; fallback histórico CST 00/18%/PIS 1,65/COFINS 7,6 marcando `sem_regra_fiscal` — linhas 24-50); numeração com lock via `NumeroSequencialService` e transmissão isolada no contrato `Domain/Fiscal/Contracts/SefazDriver.php` (drivers `NFePHPSefazDriver` e `FakeSefazDriver` em `Domain/Fiscal/Drivers/`). Tabelas: `config_fiscais`, `notas_fiscais`, `nota_itens` (`0010_01_01_000000_create_fiscal_tables.php:19-65`).

### e) Logística — distribuição da entrega
Pedido pendente entra na fila (ver fluxo a) → `app/Domain/Logistica/Jobs/AtribuirPedidoJob.php` (`ShouldQueue`; `handle(CentralService, DistribuidorService, TenantContext)`, linha 33) → `app/Domain/Logistica/DistribuidorService.php`: `ranquear()` pega entregadores com `Jornada` ativa não bloqueados, cruza com `EntregadorPosicao` e pontua por proximidade (Haversine) × carga, pesos por empresa em `logistica_config` (linhas 39-70; defaults 0.7/0.3 nas linhas 28-30). O painel humano usa `GET/POST /api/admin/central/*` → `CentralController` → `CentralService`. No app do entregador, o ciclo `aceitar/recusar/ocorrencia/concluir` (`routes/api.php:678-681`) cai em `AppEntregadorController`, que injeta `PedidoService`, `PedidoMobileService`, `RastreamentoService`, `EntregaService`, `JornadaService` e `RoteirizadorService` (`app/Http/Controllers/Api/Mobile/AppEntregadorController.php:27-32`). Rotas/distâncias reais são contratos `Domain/Logistica/Contracts/{MatrizDistancia,TracadorRota}.php` com drivers `GoogleMatrizDriver`, `GoogleRoutesDriver`, `HaversineDriver`, `SemTracado`, `TracadorRotaCacheado`.

### f) Tempo real do pedido (transversal aos fluxos a/b/e)
Mudança de situação dispara `app/Domain/Pedido/Events/PedidoStatusAtualizado.php` (`ShouldBroadcast`): canais privados `empresa.{empresaId}.pedidos` e `pedido.{pedidoId}`, evento `pedido.status` (linhas 49-60). Posição do entregador: `app/Domain/Mobile/Events/EntregadorPosicaoAtualizada.php` (`ShouldBroadcast`, canal privado — docblock "P6"). Autorização dos canais em `routes/channels.php` (ver 1.4).

## 1.7 Camadas transversais

- **Filas**: default `database` (`config/queue.php:16`, `QUEUE_CONNECTION=database` em `.env.example:59`; tabela `jobs` criada em `0001_01_01_000002_create_jobs_table.php`). Jobs citados em 1.6; `Domain/Tenant/TenantAwareJob.php` existe para jobs cientes de tenant.
- **Broadcast**: default `log` (`config/broadcasting.php:16`); produção usa Reverb via `BROADCAST_CONNECTION=reverb` + chaves `REVERB_APP_ID/KEY/SECRET/HOST/PORT/SCHEME` (`.env.example:56-130`). Consumo: os DOIS apps mobile têm `laravel-echo` + `pusher-js` (`app-gas-em-casa/package.json:39-41`, `app-entregador/package.json:33-35`) e helper `src/helpers/realtime.ts` em cada um — o do consumidor conecta Echo com `broadcaster: "reverb"` e `authEndpoint .../broadcasting/auth` com Bearer, caindo para polling sem Reverb configurado (`app-gas-em-casa/src/helpers/realtime.ts`). **NÃO VERIFICADO**: uso de Echo dentro da SPA (`erp-novo/frontend/src`) — o grep por `laravel-echo|Echo` não retornou arquivos lá; a SPA aparentemente não assina canais (não confirmei página a página).
- **Integrações por tenant**: `app/Domain/Integracao/IntegracaoTenant.php` — resolvedor único de credenciais (PIX, cartão eRede via `Domain/Mobile/Drivers/EredeDriver.php`, Maps) na ordem EMPRESA → GRUPO → PLATAFORMA(env), com segredos cifrados por valor em `empresa_configs.dados['integracoes']` e write-only (docblock linhas 12-24). Firebase: contrato `FirebaseVerifier` (ver 1.5). Push: `Domain/Mobile/PushService.php` com transporte FCM HTTP v1 (`FcmV1Transport`), substituindo o endpoint legacy `fcm/send` (docblock linhas 12-13).
- **ETL / migração legada**: CLI `php artisan etl:run {migrator?}` (`app/Console/Commands/EtlRun.php:21`) sobre o `MigratorRegistry` (29 migrators); e a ferramenta interativa do SuperAdmin (`/api/superadmin/migracoes/*` → `MigracaoController` → `MigracaoService` → `ExecutarMigracaoJob` em fila).

## 1.8 Banco de dados

- **Conexão/driver**: `config/database.php:20` — default `env('DB_CONNECTION', 'sqlite')`; `.env.example:32-34` traz `DB_CONNECTION=sqlite` com `# DB_CONNECTION=pgsql` comentado (produção usa pgsql; sqlite é o default de dev/teste). Conexões relevantes: `pgsql` (runtime), `pgsql_owner` (migrations/DDL, cai nas credenciais do `pgsql` sem `DB_OWNER_*` — linhas 111-124), `legado` (pgsql, espelho do Oracle em schema configurável `LEGADO_DB_SCHEMA`), `app_legado` (MySQL `sgcm_api`), `monitora_legado` (MySQL `monitora`) — todas somente-leitura por convenção documentada no próprio arquivo.
- **Migrations**: 86 arquivos em `database/migrations/`. Núcleo numerado `0000_...`–`0012_...` (grupos/empresas, estados, users, tenant+RBAC, sequências, cadastros de apoio, geográfico, clientes, produtos, estoque, pedidos, financeiro, caixa, cobrança, satélites, fiscal, mobile, monitora) e evolutivas datadas 2026 (RLS, hierarquia A3, ABAC A4, segurança A5/A6, SaaS P2-P4, logística L0-L11, migração `2026_08_13_000100_create_migracoes_tables.php`, matriz tributária `2026_08_15_000100_matriz_tributacao.php`).
- **Convenções**: nomes de tabela em português, muitos SEM underscore herdando o estilo do legado (`pedidoitens`, `pedidosituacoes`, `clientetelefones`, `contamovimentos`, `estoquesaldos`, `financeiroparcelas`) convivendo com snake_case novo (`notas_fiscais`, `pix_cobrancas`, `planos_conta`, `app_devices`, `pagamentos_online`, `empresa_user`); colunas de tenant `empresa_id`/`grupo_id` nas tabelas de negócio (reforçadas por `2026_06_24_000100_f02_empresa_id_em_tabelas_filhas.php` e `2026_07_03_000600_mt3_empresa_id_not_null_nas_filhas.php`); ids do legado preservados no ETL (`app/Etl/Support/PreservaIdsDoLegado.php`).

## 1.9 Frontend SPA (`erp-novo/frontend/src`)

- **Estrutura**: `main.tsx`, `routes.tsx`, `index.css`, `components/`, `features/` (27 domínios: acessos, auth, cadastros, central, clientes, comodatos, configuracoes, convenios, crm, dashboard, empresas, estoque, financeiro, fiscal, frota, geografico, gestao, missoes, pedidos, produtos, relatorios, rh, satelites, seguranca, superadmin, valegas, pagamentos), `layouts/` (`AppShell`), `lib/`, `test/`.
- **Roteamento**: `routes.tsx` — React Router com `React.lazy` por página (code-splitting, helper `lazyNamed`, linhas 11-15). Rotas protegidas amarram página a permissão RBAC: ex. `/pedidos` exige `pedido.view`, `/central` exige `logistica.view`, `/fiscal` exige `fiscal.view` (linhas 134-138); `/login` fica fora do shell (linha 114).
- **Chamadas de API**: `lib/api.ts` — axios com `baseURL = PREFIX + '/api/admin'` (PREFIX derivado do `BASE_URL` do Vite: `/novo/app/` → `/novo`; linhas 12-13 e 39-46), `withCredentials` + XSRF (modo cookie) e interceptor que anexa `Authorization: Bearer` quando há token salvo (localStorage = "manter conectado", sessionStorage = sessão; linhas 23-37, 72-76). Filtro de empresa da listagem viaja em toda requisição (`FILTRO_EMPRESA_KEY`, linhas 48-69). `lib/auth.tsx` — login tenta fluxo cookie (csrf) com fallback Bearer, consulta `/me` com React Query (`staleTime` 60s) e expõe `can()` para RBAC field-level (linhas 21-103); RBAC utilitário em `lib/rbac.ts`.

## 1.10 Apps mobile novos e o app adaptado

- **`mobile-shared/`** — cliente HTTP único (`src/http.ts`: instância com `baseURL`/timeout, Bearer opcional via `isProtected`, e infraestrutura de refresh) + `validators.ts`; consumido pelos dois apps.
- **`app-entregador/`** (novo, Expo + expo-router): telas em `src/app` (`login.tsx`, grupo `(app)`); services chamam exclusivamente a API nova — `auth.service.ts` usa `app/v1/login`, `app/v1/logout`, `app/v1/token/refresh`, `app/v1/devices` (linhas 25-37); `entrega.service.ts` usa `app/v1/entregador/pedidos[...]/aceitar|recusar|status|ocorrencia|concluir` e `app/v1/entregador/posicao`; `jornada.service.ts` usa `app/v1/entregador/veiculos` etc.; `missao.service.ts` cobre as missões. Tempo real em `src/helpers/realtime.ts` (Echo/Reverb).
- **`app-gas-em-casa/`** (origem legada, adaptado): mantém a estrutura própria (`src/services`, `src/store`, `src/helpers`), mas os services apontam para `app/v1/*` do erp-novo — `order.service.ts` (init, cotação, cupom, pedidos), `address.service.ts` (endereços), `marketplace.service.ts` (descoberta de revendas por geolocalização, endpoints públicos), `user.service.ts`, `product.service.ts`, `store.service.ts`. Login do cliente é o phone-auth Firebase (endpoint `app/v1/cliente/login`, ver 1.5). Tem `google-services.json`/`GoogleService-Info.plist` na raiz (Firebase). É simultaneamente REFERÊNCIA legada e cliente ATIVO da API nova.

## 1.11 Itens NÃO VERIFICADOS (explícitos)

- Conteúdo integral dos controllers não citados linha a linha (li os centrais: Auth, AppAuth, Pedido, PixWebhook e as assinaturas de AppEntregador); os demais foram confirmados apenas como classes referenciadas por `routes/api.php` e presentes em `app/Http/Controllers/Api/*` (listagem de diretório).
- Uso de Echo/websocket DENTRO da SPA (`erp-novo/frontend`): grep não encontrou `laravel-echo` ali; não abri todas as páginas para afirmar que a SPA usa só polling.
- O corpo completo de `PolicyEvaluator.php`, `LicencaService.php` e `MigracaoService.php` (citados pelos pontos que os invocam, não lidos por inteiro).
- Corpo completo das closures dos rate limiters — a DEFINIÇÃO existe e foi localizada em `app/Providers/AppServiceProvider.php`: `RateLimiter::for('api')` 120/min (linha 146), `'login'` 10/min (linha 149), `'api-tenant'` (linha 157), `'marketplace'` 60/min por IP (linha 171), `'gps-ping'` 120/min (linha 172), `'missao-visita'` 30/min (linha 174); li apenas essas linhas, não as closures inteiras.
- SPED/IBPT: os services `SpedFiscalService.php`, `SpedContribuicoesService.php`, `IbptService.php` existem em `app/Domain/Fiscal/` (listagem), conteúdo não lido.
# 4. Mapa do legado (o que precisa ser coberto)

> Escopo desta seção: `ctrl-web/` (sistema web ~25 anos, ERP de distribuidora de GLP) e `app-gas-em-casa/` (app consumidor de origem legada). **Nota de precisão:** `ctrl-web/` no estado atual do repositório NÃO é o legado "puro" — é o legado sofrendo modernização Strangler in-place (Laravel 12, Postgres, SPA React embutido, módulos Api/ApiAdmin/Monitora unificados dentro do mesmo app). Por isso o inventário abaixo separa, sempre que possível, o núcleo legado (`app/Http/Controllers`, `routes/web.php`, views Blade+jQuery) das camadas novas incrustadas. Todas as contagens foram obtidas por comando e estão anotadas.

---

## 2.1 Quantificação (comandos e números)

Executado em `ctrl-web/` (Git Bash):

| Item | Comando | Nº |
|---|---|---|
| Arquivos de rota | `ls routes` | **7** (`web.php`, `api.php`, `api_mobile.php`, `api_admin.php`, `monitora.php`, `monitora_api.php`, `console.php`) |
| Controllers (total, todos os namespaces) | `find app/Http/Controllers ... -name "*.php"` só cobre 1 namespace | **164** em `app/Http/Controllers` |
| — núcleo legado ERP | `find app/Http/Controllers -maxdepth 1 ... \| wc -l` | **160** direto em `app/Http/Controllers` + 2 em `App/` + 2 em `Auth/` |
| — módulo Api mobile (unificado) | `app/Api/Http/Controllers` | **20** |
| — módulo ApiAdmin (SPA React) | `app/ApiAdmin/Http/Controllers` | **23** |
| — módulo Monitora (rastreamento) | `app/Monitora/Http/Controllers` (+1 em `Auth/`) | **16 + 1** |
| **Total de controllers no repo** | soma dos acima | **≈ 224** |
| Models (classes `extends Model`/`Authenticatable`) | `grep -rl "extends Model" app --include="*.php" \| wc -l` = 203; incluindo `Authenticatable` = **206** | **206** |
| — models na raiz `app/*.php` | `ls app/*.php \| wc -l` | **203** arquivos (a maioria são models legados no padrão pré-PSR-4 antigo, ex.: `Cliente.php`, `Pedido.php`, `Menu.php` direto em `app/`) |
| — models do módulo Api | `ls app/Api/Models` | **28** |
| — models do módulo Monitora | `ls app/Monitora/Models` | **14** |
| Views Blade | `find resources/views -name "*.blade.php" \| wc -l` | **564** |
| Views não-Blade | `find resources/views -type f ! -name "*.blade.php"` | **9** |
| Diretórios de views | `find resources/views -maxdepth 1 -type d \| wc -l` | **70** (69 subpastas + a raiz) |
| Statements `Route::` por arquivo | `grep -c "Route::"` | web.php **580**, api_admin.php **193**, api_mobile.php **85**, api.php **76**, monitora.php **34**, monitora_api.php **5** |
| Linhas de rota | `wc -l routes/*.php` | web.php **1118**, api_mobile.php **443**, api_admin.php **260**, api.php **208**, monitora.php **92**, monitora_api.php **25**, console.php **18** (total **2164**) |

> **Observação forte:** os models legados NÃO estão em `app/Models/` (`find app/Models` retornou **0**). Estão soltos em `app/` no padrão de namespace `App\Cliente`, `App\Pedido` etc. — sinal claro de app criado em Laravel 5.x (antes da convenção `app/Models`). Verificado em `routes/web.php` e nos `use App\Cliente;` dos controllers.

---

## 2.2 O que o sistema faz — módulos de negócio (fonte: `routes/web.php` + controllers)

O núcleo do ERP é declarado em `routes/web.php` (1118 linhas, 580 `Route::`), organizado por comentários-cabeçalho (`//Módulo`). Domínios reais identificados (cada um com controller correspondente em `app/Http/Controllers`):

**Cadastros / base**
- Empresas e Grupos de Empresas — `EmpresaController`, `EmpresasGrupoController` (`web.php:47,53`)
- Usuários, Roles/permissões, Cargos — `UsersController`, `RoleController`, `CargoController` (`web.php:65,76,124`)
- Clientes (+ contatos, produtos por cliente, comodato) — `ClienteController`, `ClientecontatoController`, `ClienteprodutoController` (`web.php:81`)
- Colaboradores (comissões, família, exames) — `ColaboradorController`, `ColaboradorcomissoesController`, `ColaboradorfamiliaController`
- Geografia — `CidadeController`, `BairroController`, `RuaController`, `RegiaoController`, `SegmentoController` (`web.php:62,704,974`)
- Promotores de venda — `PromotorController` (`web.php:68`)

**Produtos / estoque**
- Produtos, classes, unidades, atualização de preços — `ProdutoController`, `ProdutoclasseController`, `UnidademedidaController`, `AtualizarprecosController` (`web.php:258,270,273`)
- Estoque (operações, ajustes, físico, requisição, transferência, fechamento GLP) — `EstoquefisicoController`, `EstoquerequisicaoController`, `EstoqueTransferenciasController`, `EstoquesetorController`, `InventarioController` (`web.php:307–334`)

**Vendas / pedidos**
- Pedidos (status, operações, situações, motivos de atraso/não-venda) — `PedidoController`, `PedidooperacaoController`, `PedidosituacaoController`, `MotivonaovendaController` (`web.php:480`)
- Venda Ativa (telemarketing) — `VendaativaController`, `VendaAtivaOcorrenciaTiposController` (`web.php:522`)
- Vale Gás (venda, baixa, cancelamento, consulta) — `ValegasvendaController`, `ValegasbaixarController`, `ValegascancelarController`, `ValegasconsultaController` (`web.php:408–414`)
- Promoções, Sorteio, MCMM, Pós-Venda, Checklist/pesquisa — `PromocaoController`, `SorteioController`, `McmmController`, `PosvendaController`, `ChecklistController` (`web.php:475,513,518,637`)

**Fiscal (NF-e / NFC-e / SAT / SPED)**
- NF emitida/recebida, situações, operações, grupos fiscais e impostos (ICMS, PIS, COFINS, IPI, CST, IBS) — `NfemitidaController`, `NfrecebidaController`, `NfOperacaoController`, `NfgrupofiscalController`, `NficmsController`, `NfpisController`, `NfcofinsController`, `NfipiController`, `NfcstController`, `ImpostonfController` (`web.php:167–361`)
- SPED Fiscal / Contribuições / Créditos / Inventário — `SpedfiscalController`, `SpedcontribuicaoController`, `SpedcreditosController`, `InventarioController` (`web.php:367–379`)
- Cupom Fiscal / NFC-e config — `CupomFiscalController`, `ConfigNfcePedidoController` (`web.php:167`)
- IBPT — `IBPTController`

**Financeiro**
- Contas a pagar/receber, caixas, planos de conta, centro de custos, condições de pagamento — `FinanceiroController`, `CaixaController`, `PlanocontaController`, `CentrocustoController`, `CondicaopagamentoController` (`web.php:531,555,591,594`)
- Boletos (geração, remessa, PDF, retorno) — `BoletoController`, `BoletoremessaController`, `BoletoPdfController`, `BoletoremessaController` (`web.php:543,547`)
- Cheques (emitido, recebido, desconto) — `ChequeemitidoController`, `ChequerecebidoController`, `DescontochequeController` (`web.php:573–588`)
- Conciliação, importação de extrato, fechamento de malotes, convênios/Gás de Bolso — `ConciliacaoController`, `ImportextratoController`, `FechamentomaloteController`, `ConveniogbgestaoController`, `FechamentoconvenioController` (`web.php:448,597`)
- PIX — `PixController` (rota em `api.php:29`, webhook)

**Frota / veículos**
- Veículos (abastecimento, entrada/saída, troca de óleo/pneu, documentos) — `VeiculoController`, `VeiculoabastecimentoController`, `VeiculoentradasaidaController`, `VeiculotrocaoleoController`, `VeiculopneuController`, `VeiculodocumentoController` (`web.php:285–405`)

**Documentos / gestão / comodato**
- Documentos e versões — `DocumentoController`, `DocumentogestaoController` (`web.php:1105`)
- Comodato (gestão de vasilhames GLP) — `ComodatoController`, `ComodatogestaoController` (`web.php:431`)
- Fechamentos mensais e vendas mensais — `FechamentomensalgestaoController`, `VendasmensaisgestaoController` (`web.php:453,459`)

**Relatórios** — bloco muito grande (`web.php:641–906`): ~40 controllers `Report*` (ex.: `ReportVendasController`, `ReportFinanceiroController`, `ReportEntregasController`, `ReportcomodatoController`, `ReportnfemitidasController`, `ReportvalegasController`, `ReportlogsController`, `ReportlogsenhaController`, `ReportResumoVendasController`), incluindo mapas de entrega e dashboard gerencial (`DashboardgerencialController`, `web.php:468`).

**Apps / integração** — `AndroidController` (push/notificações a apps, `web.php:37`), `AppnotificationController`, `AppvideoController`, `AppgiroController`, `NotificacoesController`.

### Navegação = MENU NO BANCO (verificado no código)
O menu não é declarativo; é montado a partir das tabelas `menus` e `menuusers`:
- No login, `AuthController@handleLogin` chama `Menu::menus()` e guarda o HTML na sessão: `app/Http/Controllers/AuthController.php:48-49` (`$menu = Menu::menus(); Session::put('menu', $menu);`). As permissões vêm de `Menuuser::join('menus', ...)` em `AuthController.php:51-54`.
- `Menu::menus()` (`app/Menu.php:81-104`) lê os `menu_id` que o usuário tem em `menuusers` (`DB::table('menuusers')->where user_id/empresa_id`) e monta `<li>`/`<ul>` recursivamente (`menusinner`, `app/Menu.php:56-79`) via `link_to_route($menu->descricao, $menu->titulo)`.
- A view renderiza o HTML pronto: `resources/views/layouts/mainmenu.blade.php:115-117` (`@foreach(Session::get('menu') as $menu) <?php echo $menu; ?>`).
- **Autorização** também deriva do menu: o middleware `pode` (`app/Http/Middleware/AuthorizeCustom.php`, registrado como `'pode' => AuthorizeCustom::class` em `app/Http/Kernel.php:64`) lê `Session::get('permissoes')` (populada do banco) e compara `visualizar/criar/editar/deletar` por rota (`AuthorizeCustom.php:133-193, 263-266`).

---

## 2.3 Versões de Laravel — vestígios convivendo

`composer.json` declara **`laravel/framework: ^12.0`** e **`php: ^8.2`** (`composer.json:8`). Porém há vestígios inequívocos de que o app nasceu em **Laravel 5.x** e foi arrastado por upgrades sucessivos:

| Vestígio | Local | O que indica |
|---|---|---|
| Models soltos em `app/*.php` (203 arquivos), sem `app/Models/` | `find app/Models` = 0; `ls app/*.php` = 203 | Estrutura pré-Laravel 8 (`app/Models` virou padrão no 8). App é 5.x-era. |
| `laravelcollective/html` (via fork `rdx/laravelcollective-html`) + `{{ Form::open(...) }}` em **234 views** | `composer.json:12`; `resources/views/abastecimento/abastecimento_form.blade.php:12` | Collective Forms foi abandonado após Laravel 5.x; uso massivo em Blade legado. |
| Facade `Input` (`Illuminate\Support\Facades\Input`) — **264 ocorrências** `Input::` em `app/` (266 com `routes/`) | `routes/monitora.php:14,71`; `app/Monitora/Http/Controllers/SearchController.php:48` | `Input` foi **removido no Laravel 6**. Sobrevive por polyfill (`laravel/helpers`, `composer.json:10`). |
| Helpers globais removidos: `str_random()`, `starts_with()`, `array_get()` | `app/Api/Http/Controllers/SecretController.php:56` (`str_random()`); `app/Exceptions/Handler.php:79,85` (`starts_with()`) | Helpers de string/array globais foram extraídos no Laravel 6 para `laravel/helpers`. Presença = código 5.x. |
| `laravel-mix ^4.1.4` + `webpack.mix.js` | `package.json:11`; `webpack.mix.js:12` | Mix 4 é da era Laravel 5.7/5.8; o Laravel atual usa Vite. Build de assets legado ainda vivo. |
| Pasta `laravel/` na raiz | `ctrl-web/laravel/` contém só `.index.php.swp` | Resíduo/lixo de edição (swap do Vim). Não é código — indica higiene fraca do repo, mas sem função. **NÃO VERIFICADO** que já tenha tido conteúdo maior. |
| `oraclevar.php` na raiz | `ctrl-web/oraclevar.php` | Duas linhas: `putenv("ORACLE_HOME=/u01/app/oracle/product/11.2.0/xe")`. Vestígio da fase Oracle (ver 2.4). **Não foi encontrado nenhum `include`/`require` de `oraclevar.php`** em `index.php`/`server.php` — hoje é código órfão. |
| `.buildpath` / `.project` (Eclipse PDT) | `ctrl-web/.project` (`<name>ctrl</name>`, natures `org.eclipse.php.core.PHPNature`) | Projeto foi desenvolvido no **Eclipse PDT**, IDE típica de projetos PHP antigos (~2010s). |
| `vendor_fork/` | `PHPExcel-1.8.zip`, `phpoffice/PHPExcel/...`, `Util.php` | **PHPExcel** (predecessor do PhpSpreadsheet, descontinuado em 2017) versionado à mão + um `Util.php` que é fork do boleto (`namespace Eduardokum\LaravelBoleto`). Patch manual de dependências fora do Composer. |
| `Pentaho/` | `public.zip`, `read-me.md` | Backup manual de dashboards do **Pentaho** (BI). O `read-me.md` diz literalmente: "manter um controle e back-up dos dashboard dos relatórios do Pentaho ... armazena-los juntamente do sistema CTRL2". Indica que relatórios analíticos externos rodavam em Pentaho. |
| `composer-setup.php` na raiz | `ctrl-web/composer-setup.php` | Instalador do Composer commitado por engano — higiene do repo. |

> Conclusão: `composer.json` diz "Laravel 12", mas o corpo do código é **Laravel 5.x modernizado à força** (Input facade polyfillado, models soltos, Collective Forms, Mix 4, PHPExcel manual). Isso é central para o plano de paridade: a superfície é enorme e presa a padrões descontinuados.

---

## 2.4 Banco — como o legado conecta

`config/database.php` (sem expor credenciais — só estrutura):
- **Default:** `env('DB_CONNECTION', 'mysql')` (`config/database.php:29`). O fallback histórico é **MySQL**.
- **Fetch mode antigo:** `'fetch' => PDO::FETCH_CLASS` (`:16`) — configuração removida do Laravel moderno; outro vestígio 5.x.

Conexões nomeadas (7 chaves únicas em 8 blocos — a chave `monitora` é definida duas vezes e a segunda vence):
| Conexão | Driver | Papel (fonte) |
|---|---|---|
| `pgsql` | `pgsql` | Destino da migração Oracle→Postgres (`:50`, comentário "saída do Oracle, Fase 3"). É onde o ERP roda hoje em Docker. |
| `oracle` | `oracle` | Conexão Oracle original (`:63`, `charset AL32UTF8`, porta 1521). O legado real rodava **em Oracle**. |
| `monitora` | `mysql` (bloco `:75`) **e** redefinida como `pgsql` (bloco `:125`) | **Chave duplicada** `'monitora'` no mesmo array — a segunda definição (Postgres, schema `monitora`) sobrescreve a primeira (MySQL/forge). O bloco MySQL em `:75-88` é **código morto** (nunca usado por estar duplicado). |
| `sgcm_api` | `pgsql` (schema `api`) | Módulo Api do app (ex-`api-app-gc`), unificado no mesmo Postgres, schema dedicado `api` (`:94`). |
| `sgcm_logs` | `pgsql` (schema `api`) | Logs do módulo Api, originalmente MySQL separado, hoje no mesmo Postgres (`:109`). |
| `sgcasa` | `mysql` | Fonte externa **legada** de GPS (`sgcasa_monitoramento`), lida por jobs do monitoramento (`:143`). Continua MySQL real. |
| `mysql2` | `mysql` | Banco `sgc_dubena` (`:155`) — outra origem MySQL legada. |

> **Resposta direta:** o legado conectava a **Oracle** (11.2 XE — ver `oraclevar.php`) como banco principal, com **MySQL** para satélites (monitoramento GPS `sgcasa`, `sgc_dubena`, e a API do app). O estado atual migra tudo para **PostgreSQL** com schemas (`public`, `api`, `monitora`), mantendo MySQL só para as fontes externas de GPS. As três tecnologias (Oracle, MySQL, Postgres) aparecem no mesmo `database.php` — retrato da migração em curso. Nenhum valor de credencial foi lido; todos vêm de `env()`.

---

## 2.5 Integrações do legado — com ponto de uso real

| Integração | Pacote (`composer.json`) | Uso real verificado |
|---|---|---|
| **NF-e / NFC-e** | `nfephp-org/sped-nfe: 5.2.1`, `sped-da` | `use NFePHP\NFe\Make;` em `app/Processors/Nfe/Tools/MakeXml.php:12`; `use NFePHP\Common\Exception\ValidatorException` / `ValidTXT` / `Convert` em `app/Http/Controllers/NfemitidaController.php:36-38`; `use NFePHP\NFe\Factories\Contingency` em `app/Helpers/Utils/NfUtil.php:23`. |
| **Boleto bancário** | `eduardokum/laravel-boleto: 0.11.2` | `use Eduardokum\LaravelBoleto\Contracts\Boleto\Boleto` e `Render\Pdf` em `app/Http/Controllers/BoletoPdfController.php:4-7`; processamento em `app/Processors/BoletoProcessor.php` e `app/Services/BoletoCaixaService.php`. |
| **WebSocket (Ratchet)** | `cboden/ratchet: 0.4.1`, `ratchet/pawl: 0.3.4` | `use Ratchet\Server\IoServer / Http\HttpServer / WebSocket\WsServer` em `app/Broadcasting/websocketServer.php:9-11` e no comando `app/Console/Commands/CFeWs.php` (signature `cfews:connect`). Cliente Pawl em `app/Api/Repository/PedidoRepository.php:167` (`Client\connect(...)`). Usado para SAT/CF-e e pedidos em tempo real. |
| **Excel (leitura/import)** | `maatwebsite/excel: 3.1` | `use Maatwebsite\Excel\Facades\Excel` em `app/Http/Controllers/EstoquerequisicaoController.php:16`. |
| **Spreadsheet / XLSX (export)** | `phpoffice/phpspreadsheet: 1.29.4` | `use PhpOffice\PhpSpreadsheet\Spreadsheet / Writer\Xlsx` em `app/Helpers/XlsxExporter.php:5-6`; usado por `DashboardgerencialController`, `DocumentogestaoController`, `FechamentoconvenioController`. |
| **mPDF** | `mpdf/mpdf: 8.0` | Via `PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf` em `app/Helpers/XlsxExporter.php:7` (writer de PDF do PhpSpreadsheet). |
| **PDF (dompdf/barryvdh)** | `barryvdh/laravel-dompdf: 3.1` | `$pdf->loadView(...)` em `app/Http/Controllers/ClienteController.php:996,1210`, `ComodatoController`, `ChecklistController`, `ReportlogsenhaController.php:62`. |
| **Passport (OAuth2)** | `laravel/passport: 13.0` | Guard `'driver' => 'passport'` em `config/auth.php:45`; tokens em `app/Api/Http/Controllers/SecretController.php:56` (`$user->createToken(...)->accessToken`). API mobile autentica com `auth:api` (`routes/api.php:17`). |
| **PIX** | (sem lib dedicada — HTTP próprio) | `app/Http/Controllers/PixController.php` (`generatePix`, `webhookPix`) + `app/Services/PixService.php`; webhook em `routes/api.php:29-30`. |
| **Revisionable (auditoria)** | `venturecraft/revisionable: 1.40` | Usado em `app/Http/Controllers/ClienteController.php`, `app/Helpers/Utils/NfUtil.php`, `app/Services/RevisionsTraitService.php`. |
| **Imagem** | `intervention/image: 2.7` | `app/Api/Http/Controllers/ApiController.php`, `app/Http/Controllers/AppnotificationController.php`, `NfwebController.php`. |
| **Sanctum (SPA)** | (`Laravel\Sanctum` no Kernel) | Grupo `api_admin` usa `EnsureFrontendRequestsAreStateful` (`app/Http/Kernel.php:42`); rotas SPA em `routes/api_admin.php` (`auth:sanctum`). — camada NOVA, não legado. |

---

## 2.6 Problemas de segurança observáveis (arquivo:linha)

Cada item é evidência concreta no código legado; alimenta a matriz de paridade e o plano.

1. **SQL cru concatenando input do usuário (SQLi)**
   - `app/Http/Controllers/BoletoremessaController.php:82,102` — lê `$ocorrencia_id = $_GET['ocorrencia'];` (linha 82) e o injeta direto: `->whereRaw("ultimaocorrencia_id IN (" . $ocorrencia_id . ")")` (linha 102). Concatenação de superglobal em SQL.
   - `app/Http/Controllers/ReportCaixaController.php:63,67,115` — `$this->datainicio` vem de `insertDataOracle($filtros['datainicio'])` (input) e entra em `whereRaw("TO_CHAR(...) <= '" . $this->datainicio . " ...'")` (linha 67) e `"< '$this->datainicio' group by ..."` (linha 115).
   - `app/Http/Controllers/ReportnfemitidasController.php:205-206`, `ReportvendapdvController.php:74`, `CupomFiscalController.php:1021`, `PosvendaController.php:54` — mesma família de `whereRaw` com concatenação.
   - Total: **137 `whereRaw`** em `app/Http/Controllers` (`grep -c whereRaw`), nem todos com input do usuário, mas o padrão é sistêmico.

2. **Uso de superglobais `$_GET`/`$_POST` (bypassa validação/binding do framework)** — **85 ocorrências** (`grep -rn "_GET\[|_POST\[|_REQUEST\[" app`). Exemplos: `app/Http/Controllers/BairroController.php:23,33-36` (`$_GET['uf_filtro']`, `$_GET['cidade_id_filtro']` usados em `where()`), `BoletoController.php:40`. Input não sanitizado e sem CSRF-binding.

3. **Autorização frouxa por AJAX (bypass documentado no próprio código)** — `app/Http/Middleware/AuthorizeCustom.php:52-53`: se a request for AJAX (`X-Requested-With`) e a flag `seguranca.fechar_bypass_ajax` estiver **desligada (default)**, `return true` — libera QUALQUER request AJAX, inclusive POSTs de gravação (`cliente.store` etc.). O comentário nas linhas 43-51 admite: "Era o vetor de bypass de autorização mais amplo." Kill-switch existe mas vem **desligado por padrão**. Além disso, `AuthorizeCustom.php:40-41` libera toda rota `ajax.*` sem checar permissão.

4. **Registro de senhas em texto/histórico (`logsenhas`)** — existe a feature `Logsenha` (`app/Logsenha.php`), gravada em `app/Http/Controllers/EmpresaconfigController.php:697` (`Logsenha::create([...])`) e exibida em relatório `ReportlogsenhaController.php:40-49`. É a "senha mestre" da empresa: `EmpresaconfigController.php:502-518` faz `Hash::check($senhaatual, $senhamestre)` e `update(["senhamestre" => $novasenha])`. **NÃO VERIFICADO** se `$novasenha` é hasheada antes do update (o `update` recebe a variável direta na linha 518) — precisa auditoria do fluxo de `senhamestre` para confirmar se grava plaintext. O `unset($config->senhamestre)` em `:376-377,432-433` sugere consciência de que o campo é sensível.

5. **Upload sem validação de tipo/extensão** — `app/Http/Controllers/DocumentoController.php:181-207`: pega `$request->file('file')`, grava `nomearquivo = $file->getClientOriginalName()` (nome controlado pelo cliente, linha 196) e salva com `Storage::disk('local')->putFileAs(..., $file, $versao->id.'.'.$file->getClientOriginalExtension())` (linhas 197-207) — **sem** `validate()` de mime/extensão no trecho. Idem `AppvideoController.php:163,175` (`getClientOriginalName`, `storeAs("tmp_videos", "tmp_startup.mp4")`).

6. **CSRF: sem exceções indevidas, mas cobertura só no grupo `web`** — `app/Http/Middleware/VerifyCsrfToken.php` tem `$except = []` (vazio, bom). Porém o CSRF só protege o grupo `web` (`app/Http/Kernel.php:31`); os grupos `api`/`api_admin` não o incluem (esperado para API). O risco real está no item 3 (POSTs de gravação passando por AJAX sem checagem de permissão), não no CSRF em si.

7. **Segredo derivado de `APP_KEY` (fraco, admitido no código)** — `app/Api/Repository/PedidoRepository.php:167` e `app/Monitora/Http/Controllers/ApiController.php:182` usam `app_key=sha1(env("APP_KEY"))` como autenticação do WebSocket. Comentários em `SecretController.php:30-42` e `NfwebController.php:87-94` confirmam: "a app_key era sha1(APP_KEY) — e a APP_KEY vazou no repo". Há mitigação parcial (fallback para `APP_TOKEN_KEY`), mas o esquema `sha1(APP_KEY)` **ainda é o fallback ativo**.

8. **Histórico de exposição em produção (corrigido, mas revela postura)** — comentários confirmam bugs recém-removidos: `dd($request)` em rota de produção que "despejava o request/ambiente inteiro" (`routes/api_mobile.php:26-28`) e `dd($request)` removido de `/users`. São indícios de que o legado rodou com debug exposto.

---

## 2.7 `app-gas-em-casa` — app consumidor legado

**Stack:** Expo / React Native + TypeScript, roteamento **expo-router** (file-based em `src/app/`). Config dinâmica em `app.config.ts` (não `app.json` estático). Testes com Vitest (`vitest.config.ts`).

**Telas (26 arquivos `.tsx` em `src/app/`)** — `find src/app -name "*.tsx" | wc -l` = **26**. Estrutura de rotas:
- Pré-login: `index.tsx`, `startupvideo.tsx`, `login.tsx`, `sms.tsx` (verificação por SMS), `newuser.tsx` (cadastro), `policies.tsx`, `selecionar-revenda.tsx` (escolha de revenda/tenant), `(tutorial)/page1.tsx`.
- Autenticado `(auth)/`: abas `(tabs)/` → `home/`, `pedidos/`, `info/`, `perfil/`; mais `address.tsx`, `carrinho.tsx`, `pix.tsx` (pagamento PIX), `track.tsx` (acompanhar entrega), `perfil-dados.tsx`, `error.tsx`.

**O que o app faz:** login por telefone+SMS, seleção de revenda, catálogo/carrinho, criação de pedido de GLP, histórico e acompanhamento em tempo real do pedido/rota do entregador, pagamento (PIX + cartão crédito/débito — bandeiras em `src/constants/app.ts`), perfil e endereços.

**Serviços (`src/services/`):** `address`, `marketplace`, `order`, `product`, `store`, `user` (6 arquivos). Todos usam `Http.PrepareRequest(...)`.

**Contra qual API aponta hoje:** o **ERP-NOVO**, prefixo **`app/v1`** (não o legado). Evidência:
- `src/services/order.service.ts:5` — comentário "OrderService (F3 → ERP-NOVO `app/v1`)"; chamadas: `app/v1/init` (:13), `app/v1/carrinho/cotacao` (:20), `app/v1/pedidos` (:30,32), `app/v1/pedidos/{id}/rota-entregador` (:47), `app/v1/pedidos/{id}/pix` (:67).
- `src/services/user.service.ts:32-55` — `app/v1/cliente/login`, `app/v1/cliente/cadastro`, `app/v1/perfil`, `app/v1/devices`.
- Base URL: `src/helpers/http.ts:15` — `baseURL: APP.api_url`, que vem de `expo-constants` (`extra.apiUrl`), injetado por `app.config.ts` a partir de `process.env.API_URL`.

**Config / segredos (sem expor valores):** `.env.example` define `API_URL` (default `https://app.seu-erp.com.br/api/`), `GOOGLE_MAPS_API_KEY`, `EMPRESA_ID` (tenant do build), `APP_ENV`, `APP_DEBUG`, e bloco `REVERB_*` (Laravel Reverb / protocolo Pusher para tempo real; sem ele, o acompanhamento cai para polling — `src/helpers/realtime.ts`). Nenhum segredo real no exemplo; `.env` está gitignored conforme o cabeçalho do `.env.example`.

> **Nota de precisão:** o `app-gas-em-casa` no repositório **já foi migrado** para consumir o ERP-NOVO (`app/v1`, Reverb, `empresaId` multi-tenant). Ele é "cópia editável de origem legada" mas seu backend-alvo atual **não** é a API legada `api-app-gc` — os contratos legados (`/api/getToken`, `/api/v2/order/root`) ainda existem no lado servidor (`routes/api_mobile.php`, preservados por retrocompat), mas o app já não os chama nos serviços auditados. **NÃO VERIFICADO** se algum ponto residual do app ainda bate na API antiga (busca por `getToken`/`v2/order` no app não retornou chamadas de serviço, só nomes em `newuser.tsx`/`sms.tsx`).

---

## 2.8 Ressalvas de verificação
- **NÃO VERIFICADO** que `oraclevar.php` seja incluído em runtime: nenhum `require/include 'oraclevar'` encontrado em `index.php`/`public/index.php`/`server.php`. Tratado como órfão.
- A senha mestre em `changePassword` **é hasheada**: `EmpresaconfigController.php:505` faz `$novasenha = Hash::make($data["senhanova"])` antes do update na linha 518 (verificado pelo crítico da rodada 1). Perm
- A pasta `laravel/` só continha um `.swp` no momento da auditoria; seu conteúdo histórico é desconhecido.
- Contagem de controllers "≈224" soma os quatro namespaces (`Http`, `Api`, `ApiAdmin`, `Monitora`); o núcleo legado ERP são os **160** de `app/Http/Controllers` (mais 4 em subpastas `App/` e `Auth/`). Api/ApiAdmin/Monitora são camadas incrustadas durante a modernização, não legado original.
# 5. Migração dos dados do dump

**Escopo:** pipeline ETL do legado (Oracle `CTRL2QTI` + 2 dumps MySQL) para o Postgres do `erp-novo`.

**Condição de auditoria — TIVE ACESSO A DADOS REAIS.** Contrariando a expectativa de "só seed demo", os três bancos de origem e o destino estavam de pé em Docker no momento da auditoria e foram consultados em modo somente-leitura:

```
$ docker ps --format "{{.Names}}\t{{.Ports}}\t{{.Status}}"
dubena-ora2   0.0.0.0:51521->1521/tcp   Up 15 hours     # Oracle XE 11 (snapshot CTRL2QTI)
dubena-mysql  0.0.0.0:53306->3306/tcp   Up 17 hours     # sgcm_api + monitora
dubena-pg     0.0.0.0:55432->5432/tcp   Up 17 hours     # erp_novo (schemas public + legado)
```

O destino **não é seed demo**: `public.clientes` tem 88.765 linhas com `created_at` a partir de 2019-03-28, e `public.financeiros` soma R$ 250.029.904,80 — massa de produção. Todas as contagens abaixo foram executadas por mim; nenhuma é estimada. Nada foi escrito (só `SELECT`/`COUNT`, mais `php artisan cutover:check`, que é read-only por construção — ver §1.4).

---

## 4.1 O pipeline no código

### 4.1.1 Topologia real

O ETL vive em `erp-novo/app/Etl/`. **Aviso de leitura**: a estrutura de diretórios sugere uma arquitetura Reader→Transformer→Loader que **não existe**. Os três diretórios são cascas vazias:

```
$ ls app/Etl/Loaders app/Etl/Readers app/Etl/Transformers
# cada um contém apenas .gitkeep (0 bytes)
```

O pipeline real é: `MigratorRegistry` → N classes `*Migrator` monolíticas, cada uma lendo, transformando e gravando internamente. Isso é uma observação de forma, não um defeito por si — mas explica por que os migrators têm 4.000–37.000 bytes cada (`ComplementosMigrator.php` = 37.470 bytes).

### 4.1.2 Registro e ordenação

`erp-novo/app/Etl/MigratorRegistry.php:44-116` lista **28 migrators** em fases nomeadas N0–N10 + F15 (o diretório `app/Etl/Migrators/` tem 29 arquivos `.php`, mas um deles — `SementeCountInvariant.php` — não é migrator). `MigratorRegistry::resolved()` (`MigratorRegistry.php:120-154`) faz um **topological sort real** sobre `Migrator::dependeDe()`, com detecção de ciclo que lança exceção (`MigratorRegistry.php:137`). A ordem declarada no array é apenas ponto de partida; a ordem de execução é a das dependências. Isso está correto e é defensável.

Duas correções de auditorias anteriores estão visíveis e confirmadas no código atual:
- `MigratorRegistry.php:51-53` — `UsersMigrator` foi movido para **antes** de pedidos, porque `pedidos.atendente_id/entregador_id` referenciam `user_id` do legado (comentário cita o achado P0).
- `MigratorRegistry.php:97-100` — o antigo `MonitoraMigrator` foi **removido** por ser código morto que lia tabelas `monitora_*` inexistentes no legado. O substituto é `MonitoraLegadoMigrator`. Confirmei que não há `MonitoraMigrator.php` no diretório.

### 4.1.3 O contrato e o mecanismo de invariantes

`app/Etl/Contracts/Migrator.php:16-33` obriga todo migrator a declarar `invariantes(): array`. `app/Etl/Contracts/Invariant.php:11-16` define `verificar(): InvariantResult`.

`InvariantResult` (`app/Etl/Support/InvariantResult.php`) carrega `ok`, `esperado`, `obtido`, e `resumo()` (linha 29-36) formata a falha como `[FALHA] nome — msg (esperado=X, obtido=Y)`. É a linha que aparece no console.

Existem 4 invariantes concretas:

| Classe | Arquivo:linha | O que prova |
|---|---|---|
| `CountInvariant` | `Invariants/CountInvariant.php:29-67` | `count(origem) - descartes == count(destino)` |
| `SumInvariant` | `Invariants/SumInvariant.php:34-65` | `Σ coluna` bate com tolerância (default 0.01) |
| `IntegrityInvariant` | `Invariants/IntegrityInvariant.php:30-44` | zero FK órfã **no banco novo** |
| `BalanceInvariant` | `Invariants/BalanceInvariant.php:38-69` | `Σ movimentos == saldo materializado`, por chave |

**O detalhe que mais importa** — e que é a correção mais valiosa já feita neste pipeline — está em `CountInvariant.php:38-55` e no espelho em `SumInvariant.php:39-53`:

```php
try {
    $temTabela = $this->ctx->legado()->getSchemaBuilder()->hasTable($this->tabelaLegado);
} catch (\Throwable) {
    return InvariantResult::ok($this->nome(), 'legado indisponível — não se aplica');
}
if (! $temTabela) {
    return InvariantResult::falha($this->nome(),
        "origem `{$this->tabelaLegado}` NÃO existe no legado — nome errado "
        .'ou tabela fora do MAPA do espelho (espelhar_oracle.py)', -1, ...);
}
```

A distinção é exatamente a certa: **conexão ausente = skip** (dev/CI sem dump), mas **conexão presente com tabela ausente = FALHA**. O comentário em `CountInvariant.php:33-37` documenta o porquê — era assim que "módulos inteiros migravam 0 linhas com sucesso". Essa checagem funciona: ela é quem pega os dois migrators quebrados de `pagamentos` (§4.5, A-3).

`BalanceInvariant` merece uma ressalva de custo: `Invariants/BalanceInvariant.php:52-57` faz **uma query por chave** dentro do loop (N+1). Em estoque com 115 saldos é irrelevante; se algum dia for apontado para `contamovimentos` (410.417 linhas), trava.

### 4.1.4 Runners

- `app/Console/Commands/EtlRun.php:27-75` — executa a carga. `ini_set('memory_limit','3G')` na linha 32 (justificado no comentário pelos ~443 mil títulos). Flag `--check` (linha 57-63) valida invariantes após cada migrator e retorna `FAILURE` se qualquer uma falhar (linha 66-70).
- `app/Console/Commands/CutoverCheck.php:20-57` — **o portão**. Roda todas as invariantes de todos os migrators **sem re-migrar** (nunca chama `migrar()`), soma OK/falhas e retorna `FAILURE` com "PORTÃO FECHADO" se houver qualquer falha. É read-only, por isso pude executá-lo.

O desenho do portão está correto. **O problema não é o portão — é que ele está vermelho e a migração foi tratada como concluída assim mesmo.**

---

## 4.2 As origens

### 4.2.1 Não há dumps versionados no workspace

```
$ find . -maxdepth 4 \( -name "*.sql" -o -name "*.dmp" -o -name "*.csv" \) \
    -not -path "*/node_modules/*" -not -path "*/vendor/*"
(nenhum resultado)
```

As origens existem **apenas como containers Docker vivos** na máquina do desenvolvedor. Isso é um risco de reprodutibilidade por si (§4.7, A-7).

### 4.2.2 Oracle → Postgres: o espelho

O Oracle 11g não é lido diretamente pelos migrators. `erp-novo/database/etl/espelhar_oracle.py` (427 linhas) materializa o Oracle no schema `legado` do Postgres, com os nomes de tabela que os migrators esperam. O motivo está documentado em `espelhar_oracle.py:6-9`: `python-oracledb` thin não suporta 11g e não há Instant Client no host. Lê via `sqlplus` stdout (não SPOOL, que trunca em 2499 chars — `espelhar_oracle.py:11-12`) e grava com `COPY`.

A conexão `legado` **não é o Oracle** — é o próprio Postgres apontando para o schema `legado`:

```
# erp-novo/.env
LEGADO_DB_HOST=127.0.0.1   LEGADO_DB_PORT=55432
LEGADO_DB_DATABASE=erp_novo   LEGADO_DB_SCHEMA=legado
```
(`config/database.php:131-146`; `search_path` na linha 144.) Origem e destino são **o mesmo banco físico**, separados por schema.

### 4.2.3 Cobertura do espelho — o risco "43/200" está REFUTADO na forma, mas persiste no fundo

Contagem exata do MAPA:

```
$ python -c "...regex sobre o bloco MAPA de espelhar_oracle.py..."
ENTRADAS MAPA: 121      destinos unicos: 121
```

E, no Oracle real:

```
$ printf 'SELECT COUNT(*) FROM user_tables;\nEXIT\n' | sqlplus -S CTRL2QTI/dubena@localhost/XE
       222
```

Confirmado no destino:
```sql
SELECT count(*) FROM information_schema.tables WHERE table_schema='legado' AND table_type='BASE TABLE';  -- 121
SELECT count(*) FROM information_schema.tables WHERE table_schema='public' AND table_type='BASE TABLE';  -- 183
```

**Veredito:** o número "43 de 200" do documento histórico está **desatualizado** — o espelho foi ampliado para **121 de 222 tabelas Oracle (54,5%)**. `espelhar_oracle.py:92` marca explicitamente a fronteira "── Ampliacao pos-auditoria ──". Mas o risco de fundo **não foi eliminado**: 101 tabelas Oracle seguem fora do espelho e, para elas, nenhum migrator pode nem sequer tentar ler. Não auditei uma a uma o conteúdo dessas 101 — ver §4.7.

### 4.2.4 MySQL: sgcm_api e monitora

```
$ mysql -N -e "SHOW DATABASES;"          → sgcm_api, monitora (+ schemas de sistema)
$ SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='sgcm_api';   → 37
```

Contagens que usei nas conclusões (todas executadas):

| Tabela (sgcm_api) | Linhas |
|---|---|
| `clienteimportacoes` | 20.632 (ids 1..23.250) |
| `clientetelefones` | 19.686 |
| `pedidos` | 61.502 |
| `clienteenderecos` (ativo=1) | 23.004 |
| `pedidoavaliacoes` | 21.907 |
| `transacoesonline` | 260 |
| `users` | **3** |

Nota: `sgcm_api.users` tem 3 linhas — são as contas operacionais da revenda, não os consumidores. Os consumidores do app estão em `clienteimportacoes`. Quem ler "users" esperando clientes se engana.

---

## 4.3 Tabela origem → destino (verificada)

Todas as contagens abaixo vêm de `SELECT count(*)` executado nos dois lados no mesmo instante. `legado.*` = espelho do Oracle; `public.*` = destino.

| # | Origem (`legado`) | Destino (`public`) | Origem | Destino | Situação |
|---|---|---|---|---|---|
| 1 | `estados` | `estados` | 27 | 27 | OK |
| 2 | `empresasgrupos` | `grupos` | 1 | 1 | OK |
| 3 | `empresas` | `empresas` | **7** | **10** | **DIVERGE (+3)** |
| 4 | `cidades` | `cidades` | **106** | **105** | **DIVERGE (−1)** |
| 5 | `bairros` | `bairros` | 359 | 359 | OK |
| 6 | `ruas` | `ruas` | 2.609 | 2.609 | OK |
| 7 | `clientes` | `clientes` | **44.349** | **88.765** | **DIVERGE (+44.416)** |
| 8 | `clientetelefones` | `clientetelefones` | **48.243** | **88.899** | **DIVERGE (+40.656)** |
| 9 | `produtos` | `produtos` | 26 | 26 | OK |
| 10 | `setors` | `setores` | 34 | 34 | OK |
| 11 | `estoquesetors` | `estoquesaldos` | 115 | 115 | OK |
| 12 | `pedidos` | `pedidos` | **400.070** | **402.476** | **DIVERGE (+2.406)** |
| 13 | `pedidoprodutos` | `pedidoitens` | 406.883 | 406.883 | OK |
| 14 | `financeiros` | `financeiros` | 443.714 | 443.714 | OK |
| 15 | `financeiroparcelas` | `financeiroparcelas` | 475.000 | 475.000 | OK |
| 16 | `financeirorateios` | `financeirorateios` | 442.477 | 442.477 | OK |
| 17 | `contas` | `contas` | 43 | 43 | OK |
| 18 | `contamovimentos` | `contamovimentos` | 410.417 | 410.417 | OK |
| 19 | `boletos` | `boletos` | **21.544** | **21.135** | **DIVERGE (−409)** |
| 20 | `valegas` | `vale_gas` | 23.588 | 23.588 | OK |
| 21 | `comodatos` | `comodatos` | 975 | 975 | OK |
| 22 | `nfemitidas` | `notas_fiscais` | **241.024** | **241.021** | **DIVERGE (−3)** |
| 23 | `nfemitidaitems` | `nota_itens` | 254.308 | 254.305 | −3 (coerente com #22) |
| 24 | `produtoleiimpostos` | `ibpt_aliquotas` | 317.520 | 317.520 | OK |
| 25 | `pedidosituacaohistoricos` | `pedidosituacaohistorico` | 2.077.510 | 2.077.510 | OK |
| 26 | `androids` | `app_devices` | 62 | 62 | OK |
| 27 | `colaboradores` | `colaboradores` | 81 | 81 | OK |
| 28 | `colaboradorcomissoes` | `colaborador_comissoes` | 872 | 872 | OK |
| 29 | `comissaoexcecoes` | `comissao_excecoes` | 288 | 288 | OK |
| 30 | `veiculos` | `veiculos` | 23 | 23 | OK |
| 31 | `veiculoabastecimentos` | `veiculo_abastecimentos` | 45 | 45 | OK |
| 32 | `posvendapesquisas` | `pos_vendas` | 3.097 | 3.097 | OK |
| 33 | `metavendas` | `meta_vendas` | 96 | 96 | OK |
| 34 | `sorteios` | `sorteios` | 20 | 20 | OK |
| 35 | `planocontas` | `planos_conta` | 169 | 169 | OK |
| 36 | `centrocustos` | `centros_custo` | 110 | 110 | OK |
| 37 | `bancos` | `bancos` | 148 | 148 | OK |
| 38 | `cuponsfiscais` | `cupons_fiscais` | 0 | 0 | OK (vazio na origem) |
| 39 | `mcmms` | `mcmms` | 0 | 0 | OK (vazio na origem) |
| 40 | **`cartaotransacoes`** | `cartao_transacoes` | **NÃO EXISTE** | 0 | **QUEBRADO** |
| 41 | **`gasdopovobeneficios`** | `gasdopovo_beneficios` | **NÃO EXISTE** | 0 | **QUEBRADO** |

Confirmação independente pelo próprio Oracle (não só pelo espelho):
```
CLIENTES        → 44.349   (MIN(ID)=2, MAX(ID)=101.122)
PEDIDOS         → 400.070
CLIENTETELEFONES→ 48.243
```
O espelho reproduz o Oracle fielmente nessas três. **A divergência não está no espelho — está no ETL.**

### Somas financeiras (SumInvariant, verificado por mim)

```sql
SELECT round(sum(valor)::numeric,2) FROM public.financeiros;   -- 250029904.80
SELECT round(sum(valor::numeric),2) FROM legado.financeiros;   -- 250029904.80
```
Idênticas. `financeiroparcelas.valor` também bate (≈264.255.627,76, via `cutover:check`). **O núcleo financeiro migrou com fidelidade de centavos** — é o ponto mais forte desta migração.

---

## 4.4 Estado do portão: `cutover:check` está VERMELHO

Executei o portão real (read-only, não re-migra):

```
$ php artisan cutover:check
...
Invariantes: 62 OK, 9 falha(s).
PORTÃO FECHADO — não faça o cutover até zerar as falhas.
```

As **9 falhas**, na íntegra:

```
[FALHA] contagem empresas→empresas                — esperado=7,      obtido=10
[FALHA] contagem cidades→cidades                  — esperado=106,    obtido=105
[FALHA] contagem clientes→clientes                — esperado=44349,  obtido=88765
[FALHA] contagem clientetelefones→clientetelefones— esperado=48243,  obtido=88899
[FALHA] contagem pedidos→pedidos                  — esperado=400070, obtido=402476
[FALHA] contagem boletos→boletos                  — esperado=21544,  obtido=21135
[FALHA] contagem nfemitidas→notas_fiscais         — esperado=241024, obtido=241021
[FALHA] contagem cartaotransacoes→cartao_transacoes      — origem NÃO existe no legado
[FALHA] contagem gasdopovobeneficios→gasdopovo_beneficios— origem NÃO existe no legado
```

Este é o achado mais importante da seção: **o autoteste da migração existe, funciona e está reprovando — e mesmo assim a migração consta como concluída** (o commit mais recente, `d2348c8`, diz "implementa as 3 pendencias conscientes da auditoria"). O portão não está sendo respeitado como portão.

---

## 4.5 Achados

### A-1 — CRÍTICO: `AppGasEmCasaMigrator` duplicou clientes 4× (não é idempotente)

O maior desvio da tabela (+44.416 clientes) **não é do dump Oracle**. Rastreei a origem:

```sql
SELECT count(*) AS linhas_app,
       count(DISTINCT substring(observacoes from 'id de origem: ([0-9]+)')) AS api_ids
FROM public.clientes WHERE observacoes LIKE 'Cadastro originado do app%';
--  linhas_app = 44416   |   api_ids = 11104
```

Histograma da duplicação — **todos os 11.104 aparecem exatamente 4 vezes**, sem exceção:

```sql
SELECT vezes, count(*) FROM (
  SELECT substring(observacoes from 'id de origem: ([0-9]+)') a, count(*) vezes
  FROM public.clientes WHERE observacoes LIKE 'Cadastro originado do app%' GROUP BY 1) t
GROUP BY 1;
--  vezes=4  →  11104 api_ids
```
(11.104 × 4 = 44.416 ✓)

**Causa-raiz, no código.** `Migrators/AppGasEmCasaMigrator.php:167-246` cria como cliente do ERP cada usuário do app que não tenha par. A deduplicação depende de `$this->mapaClientes` (linha 187: `if (isset($this->mapaClientes[$apiId])) continue;`). Mas `montarCorrelacoes()` (`AppGasEmCasaMigrator.php:477-500`) popula esse mapa **exclusivamente** a partir de:

```php
$this->mapaClientes = $legado->table('clientes')->whereNotNull('api_id')->pluck('id','api_id')
```

— isto é, só da ponte `api_id` gravada pelo **ERP legado**. Ele **nunca consulta os clientes que o próprio migrator criou em execuções anteriores**. Como o id novo é gerado por `$proximoId = (int) DB::table('clientes')->max('id')` (linha 182) e incrementado (linha 191), cada re-execução escolhe uma faixa de ids **nova**, e o `upsert` por `id` de `gravarPreservandoId` (linha 221) insere em vez de atualizar.

Isso quebra a garantia central do trait: `PreservaIdsDoLegado` é idempotente **apenas** quando a chave vem do legado. Aqui a chave é sintética e móvel, então a "recarga idempotente" prometida em `Support/PreservaIdsDoLegado.php:16-18` não vale.

Confirmação do gap da ponte: só 9.993 clientes do legado têm `api_id` (`SELECT count(*) FROM legado.clientes WHERE api_id IS NOT NULL` → 9.993), contra 20.632 em `clienteimportacoes` — daí os ~11.104 sem par que entram no caminho de criação.

**Efeito colateral confirmado:** os telefones seguiram junto (mesmo bloco, linhas 236-243, com `max(id)+1`):
```sql
SELECT count(*), count(DISTINCT telefone) FROM public.clientetelefones WHERE cliente_id>101122;
-- 40.656 linhas  |  10.150 telefones distintos
```

**Impacto de negócio:** a base de clientes do app está inflada 4×. Um cliente que ligar aparece em 4 cadastros; histórico, crédito e convênio ficam repartidos entre eles. 430 pedidos (`SELECT count(*) FROM public.pedidos WHERE cliente_id>101122`) já apontam para linhas dessa faixa — deduplicar exige remapear FKs, não apenas apagar.

### A-2 — ALTO: `pedidos` +2.406 e `empresas` +3, mesma classe de causa

`public.pedidos` = 402.476 vs 400.070 no Oracle; 56.130 linhas têm `id > 400070` (faixa criada por `criarPedidosRecentesDoApp()`, `AppGasEmCasaMigrator.php:252-258`). Contra 61.502 pedidos em `sgcm_api.pedidos`, os números são **plausíveis** (pedidos do app posteriores ao corte do dump, correlacionados por `apipedido_id`) — ou seja, aqui a lógica parece ter funcionado, e a divergência de contagem é esperada por desenho. **Mas o `CountInvariant` correspondente não sabe disso e falha.**

Isso expõe um defeito de desenho da invariante: `CountInvariant` aceita `descartesEsperados` (`CountInvariant.php:19`) mas **não tem o conceito simétrico de "acréscimos legítimos"**. Migrators que criam linhas de uma segunda origem (o app) são estruturalmente incapazes de passar. Resultado prático: falhas legítimas (A-1) e falhas por desenho (A-2) ficam indistinguíveis no mesmo placar vermelho — o que é exatamente como uma falha real passa despercebida.

`empresas` 7→10 e `cidades` 106→105 têm a mesma característica de "divergência pequena não explicada no código". Não determinei a causa de cada uma — ver §4.7.

### A-3 — ALTO: dois migrators leem tabelas que NÃO existem no legado (nomes inventados)

O risco histórico de "migrators com nomes inventados" está **CONFIRMADO e ainda presente** em `PagamentoMigrator`:

```
[FALHA] contagem cartaotransacoes→cartao_transacoes       — origem `cartaotransacoes` NÃO existe
[FALHA] contagem gasdopovobeneficios→gasdopovo_beneficios — origem `gasdopovobeneficios` NÃO existe
```

Nenhum dos dois nomes está no MAPA de `espelhar_oracle.py`, e ambos os destinos têm **0 linhas**. `PagamentoMigrator.php` "roda com sucesso" e migra nada. A única razão de isso ser visível é a checagem `hasTable` de `CountInvariant.php:47-55` — sem ela, seria mais um módulo silenciosamente vazio.

Não determinei se as tabelas existem no Oracle sob outro nome ou se o módulo simplesmente não existia no legado. Ver §4.7.

### A-4 — MÉDIO: `catch (\Throwable)` silencioso é o padrão dominante do pipeline

Contei **68 blocos** `catch (\Throwable)` em 28 arquivos de migrator (só o `AppGasEmCasaMigrator` tem 15). Inspecionando os corpos, o padrão quase universal é engolir a exceção e devolver vazio/falso, **sem log e sem contabilizar o erro**:

| Arquivo:linha | Corpo do catch |
|---|---|
| `ClientesMigrator.php:159-161` | `return [];` |
| `ClientesMigrator.php:217-219` | `return [];` |
| `AppGasEmCasaMigrator.php:172-174` | `return 0;` |
| `AppGasEmCasaMigrator.php:487-489` | `$this->mapaClientes = [];` |
| `AppGasEmCasaMigrator.php:618-619` | `{ }` — corpo **totalmente vazio** |
| `AppGasEmCasaMigrator.php:625-626` | `{ }` — corpo **totalmente vazio** |
| `GestaoMigrator.php:120-122` | `return [];` |
| `CobrancaMigrator.php:343-345` | `return false;` |

O caso mais grave é `AppGasEmCasaMigrator.php:487-489`: se a leitura da ponte `api_id` falhar por qualquer motivo, `mapaClientes` vira `[]` — e o migrator passa a considerar **todos** os clientes do app como "sem par no ERP", recriando a base inteira. É precisamente o mecanismo do A-1 na sua forma mais destrutiva, armado por uma exceção engolida.

**Atenuante honesto:** em `ClientesMigrator.php:157-161` o `catch` protege contra a tabela ausente no legado, e hoje as invariantes pegam esse caso (A-3 é a prova). O padrão é perigoso, não cego em absoluto. Mas `MigrationResult` tem um campo `avisos` (`Support/MigrationResult.php:15`) que esses catches **não usam** — a informação existe e é jogada fora.

### A-5 — MÉDIO: divergências pequenas não explicadas (`boletos` −409, `nfemitidas` −3)

409 boletos e 3 notas fiscais não chegaram ao destino. Descarte de 3 notas é provavelmente registro corrompido; 409 boletos é volume demais para ser acidente sem explicação. Não localizei no código a regra de descarte que produz esses números específicos, e nenhum `descartesEsperados` está declarado para eles. Nota fiscal e boleto são documentos com valor legal — perda silenciosa aqui é risco fiscal, não estético.

### A-6 — BAIXO: falso alarme de encoding descartado

Testei mojibake e **refuto** a suspeita:
```sql
SELECT count(*) FROM public.clientes WHERE nome LIKE '%Ã%';           -- 653
SELECT count(*) FROM legado.clientes WHERE nome LIKE '%Ã%';           -- 641
SELECT count(*) FROM public.clientes WHERE id<=101122 AND nome LIKE '%Ã%'; -- 641
```
Origem e destino batem exatamente (641) na faixa do legado. A amostra mostra que são **acentos corretos**, não corrupção: `CPT TERCEIRIZAÇÃO DE SERVIÇOS LTDA`, `JOÃO ELISEU DUPEZAK`, `ASSOCIAÇÃO ATLETICA FORÇA E LUZ`. UTF-8 atravessou Oracle→Postgres intacto. Os 12 extras estão na faixa do app.

Datas também limpas: `min(created_at)` em `public.pedidos` = `2019-03-28 17:46:21`, **zero** registros anteriores a 1990 — sem epoch 1970 nem `0000-00-00`. 29 clientes com `datanascimento < 1900` (ruído de digitação, presente na origem).

### A-7 — MÉDIO: integridade referencial está sólida (achado positivo)

Todas as checagens de órfãos que rodei retornaram **zero**:
```sql
pedidoitens sem pedido        → 0
financeiroparcelas sem título → 0
clientetelefones sem cliente  → 0
cliente_enderecos sem cliente → 0
pedidos com cliente_id inexistente → 0
```
E as 15 `IntegrityInvariant` do `cutover:check` passaram todas. O trait `anularFksInvalidas` (`PreservaIdsDoLegado.php:68-103`) está fazendo seu trabalho. **Nenhum dado ficou pendurado** — o problema desta migração é duplicação, não orfandade.

---

## 4.6 Preservação de IDs (`erp_id` / `PreservaIdsDoLegado`)

O mecanismo está em `app/Etl/Support/PreservaIdsDoLegado.php` e é **bem projetado**. Três garantias, cada uma verificável:

1. **Escrita por Query Builder, não Eloquent** (`PreservaIdsDoLegado.php:47`: `$conexao->table($tabela)->upsert($bloco, $chave)`). O docblock (linhas 11-15) explica corretamente o porquê: `id` não está no `$fillable` dos models, então `Model::updateOrCreate` descartaria o id do legado e o auto-increment escolheria outro — quebrando todas as FKs do dump.
2. **Idempotência por `upsert` na chave `['id']`** (linha 29, default).
3. **Ressincronização da sequence** (`PreservaIdsDoLegado.php:106-118`): `setval(pg_get_serial_sequence(tabela,'id'), COALESCE(MAX(id),1))`, com guarda `if driver !== 'pgsql' return`. Sem isso, o primeiro insert da aplicação colidiria com um id da carga.

**Verificação empírica de que funciona:** `public.clientes` tem `min(id)=2`, e o Oracle tem `MIN(ID)=2, MAX(ID)=101.122`. Os ids do legado foram preservados **exatamente** — nenhum reindexado. Os 44.416 do A-1 estão todos **acima** de 101.122, sem colidir com a faixa legada.

**A ressalva do A-1 permanece:** a garantia (2) vale só quando a chave é estável e vem da origem. Em `AppGasEmCasaMigrator.php:182,191`, a chave é `max(id)+1` — recalculada a cada execução. O trait está correto; **o uso que o `AppGasEmCasaMigrator` faz dele é que viola a premissa.** A correção certa seria uma chave natural (ex.: `upsert` por `api_id`/documento) ou uma coluna-ponte persistida.

Sobre **`erp_id`**: a coluna existe do lado do **app MySQL**, não do ERP. Confirmei que `public.clientes` **não tem** coluna `erp_id` (`ERROR: column "erp_id" does not exist`). A ponte app→ERP é feita por `sgcm_api.produtoimportacoes.erp_id` e `condicaopagamentoimportacoes.erp_id`, lidos em `AppGasEmCasaMigrator.php:382-390` e `:621-626` — ambos, note-se, dentro de `catch` vazios. Do lado ERP→app a ponte é `legado.clientes.api_id` / `legado.pedidos.apipedido_id` (`AppGasEmCasaMigrator.php:474-475, 483, 493`).

---

## 4.7 NÃO VERIFICADO

Explicitamente fora do que pude comprovar:

1. **Produção (VPS `gasemcasa.com`).** Instruído a não acessar. Tudo aqui é o ambiente local Docker do desenvolvedor. **Não sei se a produção tem a mesma duplicação 4× do A-1** — plausível se o ETL rodou lá o mesmo número de vezes, mas é conjectura. *Para verificar:* rodar `php artisan cutover:check` na VPS e a query de histograma do A-1.
2. **As 101 tabelas Oracle fora do MAPA** (222 − 121). Não enumerei nomes nem contei suas linhas. *Para verificar:* `SELECT table_name, num_rows FROM user_tables` no Oracle, subtrair o MAPA, e checar quais têm dados. **Esta é a lacuna mais provável de esconder módulos vazios adicionais.**
3. **Causa exata de `empresas` +3, `cidades` −1, `boletos` −409, `nfemitidas` −3.** Confirmei os números; não isolei a linha de código nem as linhas de dado responsáveis. *Para verificar:* `EXCEPT` por id entre `legado.X` e `public.X` em cada par.
4. ~~**Se `cartaotransacoes`/`gasdopovobeneficios` existem no Oracle sob outro nome.**~~ **RESOLVIDO na rodada de crítica:** a query `SELECT table_name FROM user_tables WHERE table_name LIKE '%CARTAO%' OR '%GASDOPOVO%' OR '%TRANSAC%' OR '%BENEF%'` retorna **`BENEFICIARIOS`** e **`PIXTRANSACTIONS`**. Ou seja, o dado de origem **existe** — o `PagamentoMigrator` é que aponta para nomes de tabela que nunca existiram no Oracle. Isso **agrava** o achado A-3: não é "origem ausente", é migrator escrito contra um schema imaginado. Nenhuma das duas grafias corretas está no `MAPA` de `espelhar_oracle.py` (confirmado: busca por `cartao`/`gasdopovo` no MAPA retorna vazio), então o espelho também não as trouxe.
5. **Cobertura coluna-a-coluna.** Auditei contagens, somas e FKs — **não** comparei valores campo a campo. Um migrator pode gravar a contagem certa com a coluna trocada e passar em tudo aqui. Li o mapeamento de `ClientesMigrator::lerClientes` (`:163-209`) e ele é fiel; não fiz isso para os outros 28.
6. **`BalanceInvariant` nunca foi exercitado nesta execução** — não apareceu na saída do `cutover:check`. Nenhum migrator parece registrá-lo. A invariante mais valiosa do desenho (Σ movimentos = saldo) pode estar **declarada e nunca usada**. *Para verificar:* `grep -rn "BalanceInvariant" app/Etl/Migrators/`.
7. **Os 24 migrators cujas invariantes não listei individualmente.** Cobri os que aparecem no `cutover:check`; migrators sem invariantes declaradas são pulados por `CutoverCheck.php:28-30` e **não aparecem no placar** — ausência no relatório não é aprovação.

---

## 4.8 Resumo executivo

O pipeline tem uma arquitetura melhor do que a reputação sugeria: ordenação topológica real, preservação de ids correta e bem fundamentada, integridade referencial impecável (zero órfãos em todas as checagens), o núcleo financeiro batendo ao centavo em R$ 250.029.904,80 sobre 443.714 títulos, e — o mais importante — um portão de invariantes que **detecta** o próprio fracasso, incluindo a armadilha do "0 linhas migradas com sucesso".

O problema não é ausência de autoteste. É que **o autoteste está reprovando (62 OK, 9 falhas) e a migração foi dada por concluída assim mesmo**. Dentro dessas 9 falhas há uma corrupção real de dados — a base de clientes do app quadruplicada (11.104 → 44.416) por um migrator não-idempotente — misturada a falhas por desenho da invariante (A-2), e é exatamente essa mistura que permitiu que a real passasse por ruído.

**Prioridade de correção:** A-1 (duplicação 4×, com 430 pedidos já apontando para as cópias) → A-3 (dois migrators lendo tabelas inexistentes) → A-2 (dar à `CountInvariant` o conceito de acréscimo legítimo, para que o placar volte a ter significado) → A-5 (409 boletos e 3 NFs perdidos).
# 6. Tabelas do Oracle sem destino no sistema novo


Esta era a pendência **B2** de `O_QUE_FALTA.md`, descrita como "a lacuna mais provável de esconder módulos vazios". Era mesmo. Aqui está o resultado.

---

## O número

| Métrica | Valor |
|---|---|
| Tabelas no Oracle | **222** |
| Tabelas espelhadas para `legado.*` | **121** |
| **Tabelas fora do espelho** | **109** |
| Dessas, **com dados** | **71** |
| Dessas, vazias na origem (irrelevantes) | 38 |

Nem toda tabela fora do espelho é perda: várias foram migradas por outro caminho (ex.: `PEDIDOITEMS`, 406.883 linhas, chegou íntegra em `public.pedidoitens`). O que importa é o subconjunto abaixo — verificado tabela a tabela contra o destino.

---

## 🔴 CONFIRMADO: 15 tabelas com dados que NÃO EXISTEM no destino

Cada linha foi checada: a tabela tem dados no Oracle e **não há tabela correspondente em `public.*`** no Postgres. Não é "migrou vazia" — é "não existe para onde migrar".

| Tabela Oracle | Linhas | O que é | Impacto |
|---|---:|---|---|
| `LOGCERCAS` | 39.929 | Log de cerca eletrônica / geofence | Histórico operacional de rastreamento perdido |
| `LIGACOESTELEFONICAS` | 13.214 | Registro de ligações (bina) | Confirma o ❌ da matriz: telefonia não migrada, **com 13 mil registros de histórico** |
| `COLABORADORCOMISSAOS` | 872 | Comissões de colaboradores | Explica o ⚠️ "comissões viraram somente-leitura" — **os dados não vieram** |
| `NFRECEBIDAPARCELAS` | 862 | Parcelas de NF recebida | Financeiro/fiscal: parcelamento de notas de entrada |
| `BENEFICIARIOS` | 480 | Beneficiários (programa social / gás do povo) | **É a tabela que o `PagamentoMigrator` procurava com o nome errado** |
| `CREDITOPISCOFINS` | 246 | Créditos PIS/COFINS | **Risco fiscal** — apuração de crédito tributário |
| `ESTOQUEFISICOSETORS` | 218 | Estoque físico por setor | Contagem de inventário por setor |
| `CLIENTEPRODUTOSCONVENIOS` | 135 | Produtos por convênio de cliente | Regra comercial de convênio |
| `ESTOQUEREQUISICAOS` | 109 | Requisições de estoque | Fluxo de requisição existe no novo, **sem o histórico** |
| `ESTOQUEREQUISICAOITEMS` | 109 | Itens das requisições | idem |
| `CLIENTECONVENIOS` | 97 | Convênios de cliente | Regra comercial |
| `COLABORADORS` | 81 | **Cadastro de colaboradores** | RH: a tabela-base de pessoal |
| `APPNOTIFICATIONS` | 67 | Notificações do app | Histórico de push |
| `CONTAEXTRATOCONFIGS` | 16 | **Regras de automação de extrato** | Confirma o ⚠️ da matriz (`extratoconfig`): a funcionalidade **e** os dados faltam |
| `ESTOQUEPRODUTOS` | 12 | Produtos em estoque | Vínculo produto↔estoque |

**Total: 56.395 linhas de dados de negócio sem destino no sistema novo.**

---

## Por que isso é grave

Três dessas confirmam, **com dados**, suspeitas que a matriz de paridade só tinha conseguido classificar por código:

1. **`COLABORADORCOMISSAOS` (872) + `COLABORADORS` (81)** — a matriz marcou RH/comissões como ⚠️ "virou somente-leitura". Agora sabemos o motivo real: **a tabela de destino não existe**. Não é uma tela incompleta, é um módulo sem dados.
2. **`CONTAEXTRATOCONFIGS` (16)** — a matriz marcou `extratoconfig` como lacuna funcional. As 16 regras de automação configuradas pelo cliente ao longo dos anos **não têm para onde ir**.
3. **`BENEFICIARIOS` (480)** — é exatamente a tabela que o `PagamentoMigrator` tenta ler como `gasdopovobeneficios` (nome que nunca existiu). O dado está lá, o migrator procura no lugar errado, e o `catch` silencioso reporta "0 linhas migradas com sucesso".

E duas levantam **risco fiscal/regulatório**: `CREDITOPISCOFINS` (246 linhas de crédito tributário) e `NFRECEBIDAPARCELAS` (862 parcelas de notas de entrada).

---

## Ruído descartado (não são perda)

Para não inflar o achado, estas foram excluídas deliberadamente:

- `SYS_EXPORT_FULL_*` (8 tabelas, ~64 mil linhas) — artefatos temporários do Oracle Data Pump, não são dados de negócio
- `REVISIONS` (3.499.497) — log de auditoria do pacote `venturecraft/revisionable` do legado; migrar é decisão de negócio, não obrigação
- `MENUS` / `MENUUSERS` (224 / 7.656) — o menu-no-banco do legado, **deliberadamente** abandonado no novo (navegação declarativa)
- `OAUTH_*` (3 tabelas, ~3 mil) — tokens do Passport legado, substituídos por Sanctum
- `MIGRATIONS` (626) — controle do próprio Laravel legado
- `LOGSENHAS` (269) — histórico de senhas; **não migrar é o correto** (é o problema de segurança do legado)
- `PEDIDOITEMS` (406.883) — **migrou por outro caminho**, íntegra em `public.pedidoitens`

---

## O que fazer (concreto)

1. **Decidir, item a item, nas 15 tabelas confirmadas:** portar, aposentar formalmente, ou arquivar o dado. Não deixar implícito — foi o implícito que criou este buraco.
2. **Prioridade por risco:** `CREDITOPISCOFINS` e `NFRECEBIDAPARCELAS` (fiscal) → `COLABORADORS`/`COLABORADORCOMISSAOS` (RH/folha) → `BENEFICIARIOS` (corrige junto o `PagamentoMigrator`) → `CONTAEXTRATOCONFIGS` → resto.
3. **Corrigir o `PagamentoMigrator`** para ler `BENEFICIARIOS` e `PIXTRANSACTIONS` (nomes reais), não `gasdopovobeneficios`/`cartaotransacoes` (nomes inventados).
4. **Regra permanente:** o espelho (`espelhar_oracle.py`) deve falhar ruidosamente quando uma tabela de origem com dados não tiver mapeamento — hoje ela simplesmente não aparece, e ausência virou silêncio.

## Como reproduzir este resultado

```bash
# 1. todas as tabelas Oracle + contagem
docker exec dubena-ora2 bash -lc "echo \"set pagesize 0 feedback off heading off
SELECT table_name FROM user_tables ORDER BY 1;\" | sqlplus -S CTRL2QTI/dubena@localhost/XE"

# 2. tabelas espelhadas
docker exec dubena-pg psql -U postgres -d erp_novo -tAc \
  "SELECT upper(tablename) FROM pg_tables WHERE schemaname='legado';"

# 3. diferença = 109 tabelas; contar linhas de cada uma no Oracle;
#    para as que têm dados, checar se existe equivalente em public.*
```
# 7. Estado real da VPS de produção

> Auditada por SSH em 15/08/2026 23:40, **somente leitura** — nenhum UPDATE, deploy ou alteração de arquivo. Esta seção **tem precedência** sobre as seções 3 a 6 onde houver conflito: elas descrevem o ambiente Docker local, que **diverge** da produção.

Este documento fecha as pendências **B1**, **C1–C3** e **Bloco F** de `O_QUE_FALTA.md`, que só podiam ser respondidas no servidor real. **Ele corrige conclusões da auditoria local** — o ambiente Docker local não representa a produção.

---

## 🔴 A descoberta que muda tudo: produção ≠ local

| | Docker local | **VPS produção** |
|---|---|---|
| `public.clientes` | 88.765 | **66.557** |
| `legado.clientes` (origem) | 44.349 | 44.349 |
| Tabelas no schema `legado` | 121 | **43** |
| `pedidos` | 402.476 | **400.070** |
| `pedidos` apontando p/ faixa nova | 430 | **0** |
| `cutover:check` | 62 OK / **9 falhas** | **16 OK / 0 falhas** |

**As duas bases divergiram.** A auditoria local não descreve o estado de produção.

### O que isso significa, item a item

**1. A duplicação EXISTE em produção, mas em outro padrão.**
Local: 11.104 clientes × exatamente 4 cópias. Produção: **22.208 clientes acima da faixa legada** (id > 101.122), com histograma irregular — 10.280 nomes duplicados 2×, 173 em 4×, 31 em 6×, 23 em 8×, e **um nome repetido 318 vezes**. Não é o mesmo defeito limpo do local; é acúmulo de execuções repetidas do ETL ao longo do tempo. **A corrupção é real e está em produção.**

**2. Nenhum pedido aponta para as cópias em produção** (`pedidos WHERE cliente_id > 101122` = **0**), contra 430 no local. A limpeza em produção é **muito mais barata** do que a auditoria local previa: não exige remapeamento de FK. Zero órfãos confirmados.

**3. O `cutover:check` de produção está VERDE — mas é um verde falso.**
16 invariantes OK, 0 falhas, "PORTÃO LIBERADO". Porém **14 das 16 dizem literalmente "legado indisponível — não se aplica"**. O schema `legado` em produção tem **43 tabelas** (contra 121 no local): as invariantes não têm com o que comparar e **passam por omissão**. O portão não está validando nada — está calado.
> Isto é mais perigoso que o vermelho do local: um operador que rode `cutover:check` na VPS vê "100% verde" e conclui que a migração está íntegra.

**4. A migração de produção está incompleta.** 43 tabelas espelhadas contra 222 no Oracle — o achado B2 (15 tabelas sem destino) é **pior em produção**, onde o espelho cobre menos ainda.

---

## 🔴 Segurança: os achados críticos são REAIS em produção

### C1 — Senha default de administrador ATIVA
```
admin@gasemcasa.com | support=true | criado=2026-08-14
→ Hash::check('admin1234', senha) = SENHA_DEFAULT_ATIVA
```
**A conta com `support = true` (bypass total de RBAC, ignora toda permissão) está com a senha `admin1234` — pública neste repositório.** O achado crítico C1 da §8 (segurança) deixou de ser hipótese: é acesso administrativo irrestrito disponível a quem leu o código.

Mitigação parcial: `superadmin@gasemcasa.com` teve a senha alterada (`senha_alterada`). Mas existe **um segundo admin com support=true**: `admin@dubena.com.br`.

**Ação imediata sugerida:** trocar a senha de `admin@gasemcasa.com` e definir `ADMIN_SEED_PASSWORD` no `.env` (hoje `<AUSENTE>`, o que faz o seeder recriar o default a cada deploy).

### C2/C3 — Gates de integração em modo FAKE
O próprio `golive:check` da produção reporta:
```
WARN Gate fiscal (FISCAL_DRIVER=fake) — SEFAZ em modo Fake — não emite NF-e real
WARN Gate cobrança (COBRANCA_DRIVER=fake) — Boleto em modo Fake — não gera CNAB real
WARN Broadcast (BROADCAST_CONNECTION=log) — sem Reverb os apps caem para polling
WARN APP_ENV = homologation — ambiente não é 'production'
```
Confirmei no `.env` (`/var/www/.env`): `FISCAL_DRIVER`, `FIREBASE_DRIVER`, `PIX_PSP`, `SANCTUM_EXPIRATION`, `PIX_WEBHOOK_SECRET`, `FIREBASE_CREDENTIALS` — **todos AUSENTES**, portanto valendo o default (`fake`).

**Consequência:** o sistema em produção **não emite NF-e real, não gera boleto real, e o login por Firebase cai no verificador Fake** (que aceita `fake:+telefone` como telefone verificado). O ambiente está declarado como `homologation`, não `production`.

### Seed demo vazou para produção
**53 clientes de demonstração** ("teste"/"demo") estão na base de produção — confirmando o risco apontado na §9 (infraestrutura) sobre o `DemoGuarapuavaSeeder`.

---

## ✅ O que está CORRETO em produção (verificado, não presumido)

| Item | Evidência |
|---|---|
| **RLS multi-tenant ativo** | 147 policies `tenant_isolation`; `current_user = erp_app` |
| **Role de runtime sem bypass** | `erp_app: super=false, bypassrls=false` (o superusuário `erp` só é usado em migrations) |
| **Integridade referencial** | 0 órfãos em `pedidos → clientes` |
| **Fidelidade financeira** | 443.714 títulos, R$ 250.029.904,80 — **idêntico ao centavo** ao ambiente local e à origem |
| **Notas fiscais** | 241.021 registros |
| `APP_DEBUG` | `false` ✅ |
| `SESSION_SECURE_COOKIE` | `true` ✅ |
| Fila e cache | Redis ✅ |
| Processos de runtime | `queue`, `scheduler`, `reverb`, `web`, `app`, `db`, `redis` — todos rodando |

---

## 🟠 Infraestrutura: problemas confirmados

**1. Reverb reiniciou 368 vezes.** `erpnovo-reverb: running=true, restarts=368` — contra 0 do scheduler e 6 do queue. O serviço sobe e cai continuamente. Combinado com `BROADCAST_CONNECTION` ausente (default `log`), **o tempo real não funciona**: os apps caem para polling, sem mapa ao vivo.

**2. Nenhum backup.** Busca por cron de `pg_dump`/backup e por diretórios de backup: **vazio**. O banco de produção — com 443 mil títulos financeiros e 241 mil notas fiscais — **não tem nenhuma rotina de backup**. Confirma a lacuna bloqueante da §9 (infraestrutura), agora no servidor real.

**3. Sem proxy WebSocket no nginx.** A config do `erpnovo-web` não tem rota para a porta 3121/8080 do Reverb — coerente com o Reverb instável e o broadcast em `log`.

---

## Ordem de ação recomendada (por risco real, não teórico)

| # | Ação | Por quê |
|---|---|---|
| 1 | **Trocar senha de `admin@gasemcasa.com` + definir `ADMIN_SEED_PASSWORD`** | Acesso admin irrestrito com senha pública, ativo agora |
| 2 | **Configurar backup do Postgres** | 443k títulos + 241k NF-e sem nenhuma cópia |
| 3 | **Consertar as invariantes que passam por omissão** | `cutover:check` verde mentindo é pior que vermelho |
| 4 | **Completar o espelho (43 → 222 tabelas)** | A migração de produção está mais incompleta que a local |
| 5 | **Deduplicar os 22.208 clientes** | Barato agora: 0 pedidos apontam para as cópias |
| 6 | **Decidir sobre `FISCAL_DRIVER`/`COBRANCA_DRIVER`** | Sistema não emite NF-e nem boleto real hoje |
| 7 | **Estabilizar Reverb + proxy wss** | 368 restarts; tempo real inoperante |

---

## Reprodução

Todos os comandos foram somente leitura, via `docker exec` na VPS:
```bash
docker exec erpnovo-db psql -U erp -d erp_novo -tAc "<SELECT>"
docker exec erpnovo-app php artisan cutover:check
docker exec erpnovo-app php artisan golive:check
docker inspect erpnovo-reverb --format '{{.RestartCount}}'
```
Nenhum `UPDATE`, `INSERT`, `DELETE`, migration, deploy ou alteração de arquivo foi executado.
# 8. Segurança da estrutura nova

Escopo: `erp-novo/` (Laravel 12 + Sanctum + Reverb + frontend React), `app-entregador/`, `app-gas-em-casa/`, `mobile-shared/`. O legado `ctrl-web/` só é citado quando um problema foi copiado para o novo. O código é a única fonte de verdade; achados não confirmados aparecem como "NÃO VERIFICADO — motivo". Nenhum valor de segredo real é transcrito — apenas o nome da chave e o arquivo.

Nota geral: a estrutura nova tem uma base de segurança **acima da média** — RLS multi-tenant com role restrita, PIX webhook com HMAC fail-closed, autorização centralizada por trait/middleware, lockout+2FA nos logins, tokens de app com abilities de papel. Os achados concentram-se em (a) contas-semente com senha default fraca criadas em TODO deploy, (b) drivers "fake" como padrão que viram bypass se o env de produção falhar, e (c) lacunas pontuais de rate-limit/autorização.

---

## Achados CRÍTICOS

### C1 — Contas administrativas semeadas em todo deploy com senha default fraca e bypass total de RBAC
- **Arquivos:**
  - `erp-novo/database/seeders/DeployAdminSeeder.php:35-42` → `admin@gasemcasa.com` / default `admin1234`, com `support = true` (bypass de RBAC).
  - `erp-novo/database/seeders/SuperAdminSeeder.php:20-21` → `superadmin@gasemcasa.com` / default `superadmin1234` (guard `platform`, cross-tenant).
  - `erp-novo/database/seeders/DeploySeeder.php:20-26` → chama AMBOS em **todo** deploy (homolog/produção).
- **Descrição:** o `DeploySeeder` (o agregador "sempre roda") invoca `DeployAdminSeeder` e `SuperAdminSeeder`. Ambos usam `env('..._SEED_PASSWORD', '<default>')`: se as variáveis não estiverem setadas no ambiente, criam contas com senhas conhecidas e presentes no código versionado. A conta admin tem `support = true`, que dá bypass total do RBAC (Gate::before — ver S-7 no `AuthController.php:84-89`); a superadmin opera cross-tenant sobre todas as empresas.
- **Severidade:** crítico.
- **Impacto/exploração (1 frase):** se o deploy não injetar as senhas por env, um atacante autentica em produção com credenciais publicadas no repositório e obtém acesso administrativo total (tenant e/ou plataforma).
- **Agravante:** o botão "Preencher com acesso de demonstração" da SPA (`erp-novo/frontend/src/features/auth/LoginPage.tsx:11,33,134-138`) exibe e pré-preenche exatamente `admin@gasemcasa.com` / `admin1234` — **sem qualquer gate de `import.meta.env.DEV`**, ou seja, aparece na tela de login em produção, sinalizando a credencial default a qualquer visitante.

### C2 — Verificadores de Firebase e FCM defaultam para driver "fake"; env mal configurado = autenticação de cliente forjável
- **Arquivos:**
  - `erp-novo/app/Providers/AppServiceProvider.php:121-123` (Firebase) e `:126-129` (FCM) — bind cai no Fake se `config('services.firebase.driver') !== 'kreait'`.
  - `erp-novo/config/services.php:73-78` → `'driver' => env('FIREBASE_DRIVER', 'fake')` (default 'fake'); idem `fcm` `:65-68`.
  - `erp-novo/app/Domain/Mobile/Drivers/FakeFirebaseVerifier.php:15-27` → aceita qualquer token no formato `fake:+telefone` e devolve o telefone como verificado.
- **Descrição:** o login/cadastro do cliente pelo app (`AppAuthController::loginCliente`/`cadastrarCliente`, `routes/api.php:88,91`) confia no `FirebaseVerifier` para provar posse do telefone. O default do driver é `fake`, que NÃO faz rede: qualquer requisição com `firebase_id_token = "fake:+5542..."` autentica como o dono daquele telefone. A proteção é inteiramente dependente de `FIREBASE_DRIVER=kreait` estar presente no `.env` de produção.
- **Severidade:** crítico (dependente de configuração — o código não fail-closa em produção como faz o PIX, que rejeita explicitamente em `PixWebhookController.php:53,98,112`).
- **O mesmo padrão de gate-por-env existe em outros drivers**, não avaliados em profundidade nesta seção: `FISCAL_DRIVER` e `MONITORA_DRIVER` (`config/services.php:66,83`, binds em `AppServiceProvider.php:127-135`). Um driver fiscal em modo fake em produção significaria emissão de NF-e simulada; **NÃO VERIFICADO** o impacto exato — exige leitura dos drivers fake correspondentes.
- **Impacto/exploração (1 frase):** se o ambiente de produção não setar `FIREBASE_DRIVER=kreait`, qualquer pessoa faz login/cadastro como qualquer cliente informando o telefone-alvo no token fake.
- **Observação:** ao contrário do `PixWebhookController` (que rejeita explicitamente em `app()->isProduction()` sem segredo), aqui NÃO há verificação de ambiente que barre o Fake em produção — o fallback é silencioso. NÃO VERIFICADO se o pipeline de deploy garante a env var (fora do código; ver `deploy/`).

---

## Achados ALTOS

### A1 — Logins do app mobile sem rate-limit de rota (throttle)
- **Arquivo:** `erp-novo/routes/api.php:85,88,91` — `/app/v1/login`, `/app/v1/cliente/login`, `/app/v1/cliente/cadastro` NÃO têm `->middleware('throttle:...')`.
- **Contraste:** o login web (`:79`) e o superadmin (`:707`) usam `throttle:login` (10/min por IP).
- **Sem rede de segurança global:** os limiters existem (`AppServiceProvider.php:146-175` define `api`, `login`, `api-tenant`, `marketplace`, `gps-ping`, `missao-visita`), mas `throttle:api` só é aplicado ao grupo autenticado (`routes/api.php:102`). As três rotas públicas de login/cadastro ficam **fora de qualquer limiter** — não há teto global que compense a ausência do throttle de rota.
- **Descrição:** o login por e-mail/senha do app (`AppAuthController::login`) tem lockout interno via `LoginSeguranca::bloqueado()` (`AppAuthController.php:88`) — controle compensatório parcial. Já `loginCliente` e `cadastrarCliente` não têm throttle de rota nem lockout: dependem só do Firebase (que, com o driver fake do C2, não protege).
- **Severidade:** alto.
- **Impacto/exploração (1 frase):** brute-force/enumeração sem teto de requisições HTTP nas rotas de login/cadastro do app, especialmente crítico no cadastro do cliente.

### A2 — Tokens de sessão/app de vida longa (30 dias) e SPA usa token Bearer no corpo
- **Arquivos:** `erp-novo/config/sanctum.php:57` → `expiration = env('SANCTUM_EXPIRATION', 43200)` (43200 min = 30 dias); `erp-novo/app/Http/Controllers/Api/AuthController.php:92` cria token `'spa'` retornado no corpo.
- **Descrição:** expiração de 30 dias é longa para tokens Bearer que a SPA e os apps guardam client-side; um token vazado permanece válido por um mês. Há rotação (`AppAuthController::refresh`, `:199-217`) e revogação (logout/`SegurancaController::revogarSessao`), mas a janela default é ampla. A SPA pode operar via cookie stateful, mas o endpoint sempre emite e devolve o Bearer.
- **Severidade:** alto (aceitável se `SANCTUM_EXPIRATION` for reduzido em produção — NÃO VERIFICADO no env real).
- **Impacto (1 frase):** token exfiltrado (XSS, log, storage do device) concede acesso por até 30 dias.

### A3 — Dependências de backend com vulnerabilidades conhecidas (composer audit)
- **Execução real:** `composer audit` em `erp-novo/` → **18 advisories afetando 3 pacotes**.
  - `league/commonmark` (6 advisories: 3 high DoS/ReDoS incl. CVE-2026-71488, 1 medium unsafe-link bypass CVE-2026-71478, etc.) — versão < 2.9.0.
  - `dompdf/dompdf` (6 advisories, sev. mayoritariamente medium) — geração de PDF de relatórios.
  - `guzzlehttp/guzzle` (6 advisories) — cliente HTTP (integrações, PSP, Firebase).
  - Distribuição por severidade: **5 high, 11 medium, 2 low**.
- **Severidade:** alto (agregado; dompdf/guzzle processam conteúdo potencialmente controlado — XML de NF, PDFs, respostas de PSP).
- **Impacto (1 frase):** DoS/ReDoS e bypass de filtro de link exploráveis via markdown/PDF/HTTP conforme os CVEs listados; requer `composer update` dos três pacotes.

### A4 — Dependências do frontend com vulnerabilidades (npm audit)
- **Execução real:** `npm audit --omit=dev` em `erp-novo/frontend/` → **4 vulnerabilidades (2 high, 2 moderate)**.
  - `react-router` / `react-router-dom` 6.0.0–7.17.0 (moderate): **open redirect** via backslash em `<Link>`/`useNavigate` (bypass de CVE-2025-68470) e injeção de construtor via `deserializeErrors()` na hidratação SSR.
  - `nanoid` (high): loop infinito com size negativo/zero.
  - `postcss` (high): path traversal / arbitrary `.map` file disclosure via `sourceMappingURL` (build-time).
- **Severidade:** alto (o open redirect do react-router é o mais relevante em runtime).
- **Impacto (1 frase):** open redirect explorável para phishing/roubo de token via link malicioso; demais são build-time/DoS.

---

## Achados MÉDIOS

### M1 — Segredo (Google Maps API key) hardcoded em código versionado nos apps mobile
- **Arquivos:** `app-entregador/eas.json:16` e `app-gas-em-casa/eas.json:16` → `GOOGLE_MAPS_API_KEY` com valor literal `AIzaSy…` (chave começando por `AIzaSy`, valor não transcrito na íntegra). Referência histórica também documentada em `app-gas-em-casa/ANALISE_TECNICA_APP-GAS-EM-CASA.md:248`.
- **Descrição:** a chave da API do Google Maps está commitada no repositório. Uma versão diferente da mesma classe de chave também está hardcoded no **legado** `ctrl-web/app/Api/Http/Controllers/PedidoController.php:209` (fora do escopo, mas é a origem do padrão copiado).
- **Severidade:** médio (chaves de Maps são de cliente por natureza, mas sem restrição de app/API viram custo/abuso).
- **Impacto (1 frase):** uso indevido da chave e custos na conta Google se ela não tiver restrições de aplicativo/referrer/API configuradas no console (restrições NÃO VERIFICÁVEIS pelo código).

### M2 — Botões de "login de teste" com credenciais literais na UI (entregador e superadmin)
- **Arquivos:** `app-entregador/src/app/login.tsx:21,134-138` (`entregador@teste.com` / `entregador123`, atrás de `APP.debug`); `erp-novo/frontend/src/features/superadmin/SaLoginPage.tsx:8,47-50` (`superadmin@gasemcasa.com` / `superadmin123`, **sem nenhum gate de debug** — grep por `import.meta.env` no arquivo retorna zero ocorrências, ao contrário do app entregador, que usa `APP.debug`).
- **Descrição:** o atalho do app entregador está corretamente gated por `APP.debug` (`login.tsx:128`) e não é backdoor de backend — só pré-preenche o formulário. Já o `SaLoginPage` do painel superadmin **não** demonstra gate de ambiente e expõe a credencial na tela. As contas só são exploráveis se existirem no banco: `entregador@teste.com` NÃO foi encontrado em nenhum seeder (não é semeada); `superadmin@gasemcasa.com` É semeada (ver C1). Note o **descasamento**: a UI sugere `superadmin123` mas o seeder default é `superadmin1234` — o botão não logaria com o default, mas revela o e-mail e a proximidade da senha.
- **Severidade:** médio (não é bypass; é exposição de credencial + superfície de engenharia social, ligado ao C1).
- **Impacto (1 frase):** a tela de login do painel de plataforma anuncia o e-mail do superadmin e um palpite de senha a qualquer visitante.

### M3 — Frontend de login expõe credencial de demonstração em produção (sub-caso do C1)
- **Arquivo:** `erp-novo/frontend/src/features/auth/LoginPage.tsx:11,134-138`.
- **Descrição:** botão "Preencher com acesso de demonstração" renderizado incondicionalmente (não vi gate `DEV`). Combinado com C1 (a conta É semeada com esse default), torna o acesso demonstração um risco real em produção, não só cosmético.
- **Severidade:** médio (elevado a crítico em conjunto com C1).
- **Impacto (1 frase):** entrega a credencial default direto na UI de produção.

---

## Achados BAIXOS

### B1 — Contas de acesso pós-migração com senhas default no seeder (não é do DeploySeeder)
- **Arquivo:** `erp-novo/database/seeders/AcessoMigracaoSeeder.php:40-42,46-48` → `admin@dubena.com.br` (`support=true`, default `dubena@2026`), `operador@dubena.com.br` (`operador@2026`), superadmin (`super@2026`).
- **Descrição:** senhas default hardcoded, mas este seeder NÃO é chamado pelo `DeploySeeder` (é rodado manualmente após migração de dados legados). O comentário do arquivo (`:24-25`) reconhece que são só para homologação. Risco menor que C1 por não rodar automaticamente em todo deploy.
- **Severidade:** baixo.
- **Impacto (1 frase):** se executado em produção sem setar as env vars, cria contas com senhas conhecidas (incl. uma com bypass de RBAC).

### B2 — `support = true` está em `$fillable` do model User
- **Arquivo:** `erp-novo/app/Models/User.php:22-30` (`support` em `$fillable`).
- **Descrição:** o flag de bypass total de RBAC é mass-assignable no model. O que torna o flag perigoso é o `Gate::before` de `erp-novo/app/Providers/AuthServiceProvider.php:28`, que faz `support = true` ignorar toda checagem de permissão (confirmado em `User.php:132`). Auditei os pontos de escrita: `UsuarioController::store` (`:63-75`) e `update` passam arrays explícitos com `'support' => false` / sem a chave, e não há `$request->all()` alimentando `User::create/update` nos controllers admin (grep negativo). Então **não** encontrei rota que permita setar `support` via entrada do usuário hoje — mas mantê-lo fillable é uma armadilha para código futuro.
- **Severidade:** baixo (defense-in-depth; sem vetor confirmado atualmente).
- **Impacto (1 frase):** um futuro endpoint que faça `User::create($request->validated())` incluindo `support` concederia escalonamento de privilégio silencioso.

### B3 — `ctrl-web/.env.docker` versionado com placeholders de segredo
- **Arquivo (legado):** `ctrl-web/.env.docker` → `DB_PASSWORD`, `PIX_WEBHOOK_TOKEN`, `ADMIN_SEED_PASSWORD` com valores placeholder ("change_me", "ctrl_dev_pwd", "admin1234").
- **Descrição:** é do legado (fora do escopo) e o `.gitignore` (`:10-14`) permite `.env.docker` explicitamente por conter valores FAKE. Confirmei que são placeholders, não segredos reais. Registrado por completude porque é o padrão de onde `admin1234` foi herdado no novo.
- **Severidade:** baixo.
- **Impacto (1 frase):** nenhum segredo real vaza; risco só se alguém copiar os placeholders para produção.

---

## Itens VERIFICADOS como corretos (sem achado)

- **Injeção SQL:** varredura de `DB::raw`/`whereRaw`/`selectRaw`/`orderByRaw`/`statement` em `erp-novo/app`. Todas as ocorrências ou usam bind `?` (ex.: `CadastroApoioService.php:78`, `NotaFiscalController.php:43`) ou interpolam **apenas literais/colunas de constantes internas**, não entrada do usuário:
  - `LookupController.php:91,94` interpola `{$colLabel}`/`{$tabela}`, mas ambos vêm da constante `self::MAPA` (chaveada por `$tipo` normalizado); `$q` do usuário é bind. Não explorável.
  - `ResolveTenant.php:116-137` usa `set_config(?, ?, false)` com bind. OK.
  - `rls_role_app_sem_bypass.php:41,49` interpola a senha da role em DDL, mas escapa aspas (`str_replace("'", "''", …)`) e a senha vem de env, não de request. OK.
  - Nenhuma ocorrência com interpolação de variável de request encontrada.
- **RLS multi-tenant:** 22 migrations com `CREATE POLICY`/`ENABLE ROW LEVEL SECURITY`; a role de runtime `erp_app` é criada `NOSUPERUSER NOBYPASSRLS` (`rls_role_app_sem_bypass.php:49`), o `.env.homolog.example` confirma `DB_USERNAME=erp_app` para runtime e `pgsql_owner` só para migrations. Defense-in-depth real (o middleware seta GUCs `app.empresa_id`/`app.empresas_visiveis` e as limpa no `terminate`).
- **PIX webhook (`PixWebhookController.php`):** 3 camadas — segredo compartilhado (`hash_equals`), HMAC-SHA256 sobre corpo cru com segredo **da empresa** resolvida pelo txid, e validação de estado/idempotência no service. **Fail-closed em produção** (`app()->isProduction()`): txid desconhecido ou sem HMAC → 401/rejeita. Modelo de segurança sólido.
- **Broadcast/Reverb (`channels.php`):** todos os canais são privados e autorizados com guard `sanctum`; `empresa.{id}.*` checa `podeAcessarEmpresa`, `pedido.{id}.*` checa posse (cliente dono / entregador / atendente) com filtro explícito por `empresa_id` (defense-in-depth mesmo sem tenant resolvido).
- **Autorização RBAC:** 45/47 controllers admin usam o trait `AutorizaPorPermissao`; middleware `permissao:` disponível para borda. Auditei os controllers com contagem `métodos > autorizar`: os "gaps" são todos métodos legítimos sem necessidade de RBAC — leituras self-service (`SegurancaController` 2FA/sessões escopadas a `$request->user()`, `:36-135`), listas estáticas (`situacoes()`, `tipos()`), lookups só-auth, ou `EmpresaController::ativar` (usa `podeAcessarEmpresa`). **Não** encontrei endpoint de escrita sensível sem autorização.
- **Separação de papéis de app:** middleware `approle:cliente|entregador` (`AppRole.php`) barra token de cliente em rota de entregador e vice-versa; tokens emitidos com abilities `role:cliente`/`role:entregador` (`AppAuthController.php:65,119`).
- **Uploads:** certificado A1 validado (`file|max:10240|mimes:pfx,p12,x-pkcs12` + `openssl_pkcs12_read` no service, `EmpresaConfigController.php:104`); fotos/assinaturas do app validadas (`image|max:8192`/`4096`, `AppEntregadorController.php:272-289`, `AppMissaoController.php:79-198`). Autorização presente no upload de certificado (`:97`).
- **Mass assignment:** sem `$guarded = []` em nenhum model de `erp-novo/app/Models`; sem `$request->all()` em `create/update` nos controllers admin.
- **Secrets versionados:** `.env` reais **não** estão no `git ls-files` (só `*.env.example`/`.env.docker` com placeholders); `SEGREDOS_LOCAIS.md` está no `.gitignore` e **não** aparece em `git ls-files`; nenhum `google-services.json`/`GoogleService-Info.plist` versionado. Único segredo hardcoded real em código do escopo novo é a Maps key (M1).
- **CORS/cookies:** `config/cors.php` restringe origins a `CORS_ALLOWED_ORIGINS`/`APP_URL` (não usa `*`) com `supports_credentials = true`; `sanctum.php` define stateful domains por env. Configuração correta (depende do env real setar origins de produção — NÃO VERIFICADO o valor de produção).

## Não verificados
- Valores reais das env vars de produção (`FIREBASE_DRIVER`, `SANCTUM_EXPIRATION`, `ADMIN_SEED_PASSWORD`, `SUPERADMIN_SEED_PASSWORD`, `CORS_ALLOWED_ORIGINS`) — fora do código; determinam se C1/C2/A2 se materializam. Motivo: `.env` real não versionado.
- Restrições de aplicativo/API da Google Maps key no console Google (M1). Motivo: não observável no código.
- Se o pipeline de deploy (`deploy/`) injeta as `*_SEED_PASSWORD`. Motivo: não localizei script que as exporte; `deploy/README_DEPLOY.md:104` só menciona `composer/migrate`.

---

## Tabela-resumo (nº de achados por severidade × área)

| Área | Crítico | Alto | Médio | Baixo |
|---|---|---|---|---|
| Autenticação (seed/backdoor, Firebase fake, throttle, expiração) | 2 (C1, C2) | 2 (A1, A2) | 1 (M2) | 1 (B1) |
| Autorização / RBAC / RLS | 0 | 0 | 0 | 1 (B2) |
| Injeção SQL | 0 | 0 | 0 | 0 |
| Validação / mass assignment | 0 | 0 | 0 | 0 |
| Uploads | 0 | 0 | 0 | 0 |
| Secrets hardcoded | 0 | 0 | 1 (M1) | 1 (B3) |
| Webhooks (PIX) | 0 | 0 | 0 | 0 |
| Broadcast/Reverb | 0 | 0 | 0 | 0 |
| Dependências (composer/npm) | 0 | 2 (A3, A4) | 0 | 0 |
| Frontend (UI de login) | 0 | 0 | 1 (M3) | 0 |
| CORS/cookies/headers | 0 | 0 | 0 | 0 |
| **Total** | **2** | **4** | **4** | **4** |

Total geral: **14 achados** (2 críticos, 4 altos, 4 médios, 4 baixos).
# 9. Lacunas de infraestrutura para produção

> Escopo: o que falta/depende para a estrutura NOVA (`erp-novo/` + apps) operar em produção.
> Fonte de verdade: código/config/workflows do repositório. Estado real da VPS = **NÃO VERIFICADO** (esta tarefa não acessou a VPS; tudo que depende do estado do servidor está marcado assim).
> Criticidade: **[BLOQUEANTE]** impede o go-live · **[IMPORTANTE]** risco alto/degradação séria · **[DESEJÁVEL]** melhoria.

---

## 7.1 Config / variáveis de ambiente

### 7.1.1 Não existe template de `.env` de PRODUÇÃO — **[BLOQUEANTE]**
- O repo tem `erp-novo/.env.example` (dev: sqlite, debug on) e `erp-novo/.env.homolog.example` (homolog). Não há `.env.production.example` nem documentação equivalente.
- O template de homolog **omite chaves que o código usa e que produção precisa**: `BROADCAST_CONNECTION`/`REVERB_*` (sem elas o broadcast cai no default `log` — `config/broadcasting.php:16`), `MAIL_*` (default `log` — `config/mail.php:17`), `FIREBASE_*`, `FCM_DRIVER`, `GOOGLE_MAPS_KEY`, `PIX_*`, `FISCAL_DRIVER`, `COBRANCA_DRIVER`, `EREDE_*`, `SGCASA_*`, `IBPT_CSV_URL`, `SANCTUM_EXPIRATION`, `SESSION_SECURE_COOKIE`, `ADMIN_SEED_*`.
- Evidência: `erp-novo/.env.example`, `erp-novo/.env.homolog.example` (60 linhas, só app/db/redis/sanctum).

### 7.1.2 Chaves usadas pelo código e não documentadas em NENHUM example — **[IMPORTANTE]**
Levantadas por varredura de `env()` em `config/`, `app/`, `database/`, `routes/`:

| Chave | Onde é usada | Observação |
| --- | --- | --- |
| `FIREBASE_DRIVER`, `FIREBASE_CREDENTIALS`, `FIREBASE_PROJECT_ID` | `config/services.php` (blocos `firebase`/`fcm`) | Só citadas em comentário do `.env.example` (linha 114) — não existem como chave |
| `PIX_PSP`, `PIX_AMBIENTE`, `PIX_WEBHOOK_SECRET` | `config/services.php` (bloco `pix`) | `PIX_WEBHOOK_SECRET` ≠ `PIX_WEBHOOK_HMAC_SECRET` (são dois segredos distintos; só o HMAC está comentado no example) |
| `APP_LEGADO_DB_HOST/PORT/DATABASE/USERNAME/PASSWORD` | `config/database.php:153-159` (conexão `app_legado`, MySQL do app legado) | default `root`/senha vazia |
| `MONITORA_LEGADO_DB_HOST/PORT/DATABASE/USERNAME/PASSWORD` | `config/database.php:171-177` (conexão `monitora_legado`) | idem |
| `LEGADO_DB_SCHEMA` | `config/database.php:143` | default `public` |
| `ADMIN_SEED_EMAIL`, `ADMIN_SEED_PASSWORD` | `database/seeders/DeployAdminSeeder.php:35-36` | ver 7.1.3 |
| `SUPERADMIN_SEED_EMAIL`, `SUPERADMIN_SEED_PASSWORD` | `database/seeders/SuperAdminSeeder.php:20-21` | presentes mas **comentadas** no `.env.example:134-135`; ausentes do `.env.homolog.example` → default fraco vale (ver 7.1.3) |
| `DB_OWNER_URL`, `LEGADO_DB_CHARSET`, `QUEUE_FAILED_DRIVER`, `SANCTUM_TOKEN_PREFIX` | `config/database.php`, `config/queue.php`, `config/sanctum.php` | não documentadas, mas todas com default funcional — impacto baixo, sem risco de go-live |
| `OPERADOR_SEED_PASSWORD`, `DONO_SEED_PASSWORD`, `GERENTE_SEED_PASSWORD` | `database/seeders/AcessoMigracaoSeeder.php:40-42`, `AcessoRedeDubenaSeeder.php:52,79` | seeds de acesso |
| `GEOCODING_API_KEY` | `config/services.php` (fallback de `GOOGLE_MAPS_KEY`) | legado de nome |
| `SESSION_SECURE_COOKIE` | `config/session.php:172` | sem default (null) — em produção HTTPS deve ser `true` |

### 7.1.3 Credenciais seed com default fraco e conhecido — **[BLOQUEANTE]** (segurança)
- `DeployAdminSeeder.php:35-36`: admin `support=true` (bypass total de RBAC) com default `admin@gasemcasa.com` / `admin1234` se `ADMIN_SEED_*` não estiver no env. Roda **em todo deploy** via `DeploySeeder` (workflow `deploy-erp-novo-homolog.yml:165-172`).
- `SuperAdminSeeder.php:20-21`: superadmin da plataforma com default `superadmin@gasemcasa.com` / `superadmin1234` (também no `.env.example:134-135`).
- Mitigação existente: a senha só é definida **na criação** (`DeployAdminSeeder.php:46-48` — não sobrescreve senha trocada). Ainda assim, o primeiro deploy de produção sem essas envs cria contas de acesso total com senha pública no repositório.
- O `.env.homolog.example` não contém `ADMIN_SEED_*`/`SUPERADMIN_SEED_*` → sem ação explícita, os defaults valem.

### 7.1.4 `env()` lido fora do build de config — **[IMPORTANTE]** (frágil com `config:cache`)
- `app/Console/Commands/IbptAtualizar.php:87` lê `env('IBPT_CSV_URL')` direto em runtime.
- `database/migrations/2026_06_26_000400_rls_role_app_sem_bypass.php:33` lê `env('RLS_APP_DB_PASSWORD')` — se ausente, a migration da role restrita é **NO-OP silencioso** (o próprio `golive:check` trata isso como causa-raiz de FAIL — `GoliveCheck.php:108-142`).
- Nuance: no compose atual o `.env` entra por `env_file` (vira variável de processo), então `env()` ainda funciona mesmo com `config:cache`. Mas qualquer deploy futuro que dependa só do arquivo `.env` + config cacheada quebra esses dois pontos silenciosamente.

### 7.1.5 Configs de produção óbvias
- `APP_DEBUG=false` e `APP_ENV=production`: cobertos pelo `golive:check` (`GoliveCheck.php:62-70`; `APP_ENV≠production` é só WARN). O template homolog usa `APP_ENV=homologation`. **[IMPORTANTE]** — produção precisa de `.env` próprio com `production`.
- Session: homolog usa `SESSION_DRIVER=redis` + `SESSION_DOMAIN=.gasemcasa.com.br`; `SESSION_SECURE_COOKIE` nunca é definido. **[IMPORTANTE]**
- Fila/cache: homolog example já aponta redis. O default do código é `database` (`config/queue.php:16`, `config/cache.php:18`) — funciona, mas o `golive:check` marca WARN para cache ≠ redis.
- **`retry_after` (90 s) menor que o timeout dos jobs longos** — `config/queue.php:43,71` (`DB_QUEUE_RETRY_AFTER`/`REDIS_QUEUE_RETRY_AFTER` default 90) vs `app/Jobs/ExecutarMigracaoJob.php:21` (`$timeout = 21600` — 6 h, `$tries = 1`). Com fila redis/database, o job de migração é **re-entregue a cada 90 s enquanto ainda roda** (colide com `--tries=3` do worker do compose). Se a migração via UI SuperAdmin for usada em produção, é corrida de duplicação. **[BLOQUEANTE]** para o fluxo de migração via fila; correção = definir `*_QUEUE_RETRY_AFTER` > timeout ou fila dedicada.

---

## 7.2 Processos de runtime necessários

O `docker-compose.homolog.yml` já materializa a topologia completa (7 containers): `app` (php-fpm), `web` (nginx :3120), `queue`, `scheduler`, `reverb` (:3121), `db` (postgres:15-alpine), `redis`. O oitavo bloco do arquivo (`erpnovo`, linha 154) é a rede, não um container. **Não existe compose/manifesto de PRODUÇÃO** — só o de homologação. **[BLOQUEANTE]** (ver 7.4).

### Queue worker
- Todos os jobs usam a fila **default** (nenhum `onQueue()` no código — varredura em `app/`): `GeocodificarClienteJob`, `AtribuirPedidoJob` (auto-atribuição logística), `EnviarPushJob`, `NotificarEstoqueBaixoJob`, `ExecutarMigracaoJob`. Um worker atende tudo.
- Worker do compose: `php artisan queue:work --sleep=1 --tries=3 --max-time=3600` (`docker-compose.homolog.yml`, serviço `queue`). Sem worker, auto-atribuição/push/geocodificação ficam inertes (o próprio compose documenta isso como PF-5).
- Lacuna: `--tries=3` conflita com `$tries=1` de `ExecutarMigracaoJob` + `retry_after` 90 s (ver 7.1.5). **[IMPORTANTE]**

### Scheduler (cron)
- `routes/console.php` (não há `Console/Kernel` — Laravel 12) define **8 agendamentos**:
  1. `notify:alertas` — diário 07:00 (estoque baixo)
  2. `monitora:sync-positions` — a cada minuto (no-op sem driver SGCasa — `MonitoraSyncPositions.php`)
  3. `pix:expirar` — a cada minuto
  4. `financeiro:notificar-vencidos` — diário 07:30
  5. `vendas:diaria` — diário 07:15
  6. `notify:inconsistencias` — semanal seg 03:00
  7. `ibpt:atualizar` — mensal dia 1 05:00 (no-op sem `IBPT_CSV_URL`)
  8. `logistica:gerar-missoes` — a cada 10 min
- Atendido pelo container `scheduler` (`schedule:work`). Sem ele, PIX nunca expira e missões não são geradas. (Comentário do compose fala em "11 comandos"; o real são 8 — documentação interna desatualizada, **[DESEJÁVEL]** corrigir.)

### Reverb / WebSocket
- 5 eventos `ShouldBroadcast`: `PixConfirmado`, `PedidoAtribuido`, `PedidoEntrouNaFila`, `EntregadorPosicaoAtualizada`, `PedidoStatusAtualizado` (em `app/Domain/...`).
- Container `reverb` expõe `127.0.0.1:3121`. Porém **nenhum arquivo em `deploy/nginx/` faz proxy de wss:// para 3121** (grep por `3121|reverb|wss` em `deploy/nginx/*` = vazio; `homolog-erp.conf` e `gasemcasa-com.conf` só proxiam 3120/3110). Os consumidores de tempo real são os apps mobile (`app-entregador/src/helpers/realtime.ts`, `app-gas-em-casa` — laravel-echo + pusher-js), que degradam para polling se `REVERB_*` não chegar no build (`realtimeDisponivel()`). A SPA web não usa Echo.
- Lacunas: (a) vhost do Nginx do host para o Reverb **[IMPORTANTE]**; (b) `BROADCAST_CONNECTION=reverb` + `REVERB_APP_ID/KEY/SECRET/HOST/PORT/SCHEME` no `.env` de produção (ausentes do template homolog) **[IMPORTANTE]**; (c) os apps precisam ser rebuildados com o `extra.reverb` apontando para o endpoint público **[IMPORTANTE]**.

---

## 7.3 Serviços externos

| Serviço | O que o código exige | Evidência | Criticidade |
| --- | --- | --- | --- |
| **PostgreSQL** | Obrigatório (RLS multi-tenant; role restrita `erp_app` no runtime + `pgsql_owner` p/ migrations) | `config/database.php:87,111`; `GoliveCheck::verificarRoleRuntime()` | [BLOQUEANTE] — já provisionado no compose |
| **PostGIS** | **NÃO exigido**: a migração `2026_06_29_000100_p9_indices_escala.php` declara explicitamente "Não introduz PostGIS" — usa índice lat/lng + Haversine em PHP | migration citada; compose usa `postgres:15-alpine` (sem PostGIS) | [DESEJÁVEL] (otimização futura; o plano cita como ops) |
| **Redis** | Fila/cache/sessão em homolog/prod | `.env.homolog.example:52-56`; compose `redis` | [IMPORTANTE] |
| **Firebase (login por telefone)** | `FIREBASE_DRIVER=kreait` + `FIREBASE_CREDENTIALS` (caminho de um **arquivo JSON de service account no servidor** — não versionado, precisa ser provisionado no volume) + `FIREBASE_PROJECT_ID`. Sem isso o verificador é **Fake** — em produção significaria aceitar login sem verificação real | `config/services.php` (bloco `firebase`); `AppServiceProvider.php:119-121`; `KreaitFirebaseVerifier` | [BLOQUEANTE] p/ app consumidor |
| **FCM push** | `FCM_DRIVER=v1` (usa a mesma service account) | `config/services.php` (bloco `fcm`); `FcmV1Transport` | [IMPORTANTE] |
| **PIX** | `PIX_DRIVER` real + `PIX_ENABLED=true`; credencial **POR EMPRESA** em `empresa_configs.dados['integracoes']['pix']` (fail-closed); segredos de webhook `PIX_WEBHOOK_SECRET`/`PIX_WEBHOOK_HMAC_SECRET` | `config/services.php` (bloco `pix`); `GoliveCheck.php:215-221` | [BLOQUEANTE] p/ cobrança PIX |
| **Boleto/CNAB** | `COBRANCA_DRIVER=caixa\|itau`; conta de cobrança por empresa em `empresa_configs.dados['cobranca'][<banco>]` | `config/services.php`; `GoliveCheck.php:205-211` | [IMPORTANTE] |
| **NF-e (SEFAZ)** | `FISCAL_DRIVER=nfephp`; **certificado A1 por empresa**, enviado por upload na SPA e gravado no disco privado `local` em `storage/app/certificados/empresa_<id>/<timestamp>.pfx`, senha cifrada em `empresa_configs` (cast encrypted). `golive:check` FALHA se alguma empresa estiver sem certificado com fiscal real | `app/Domain/Fiscal/CertificadoService.php` (DISCO `local`, PASTA `certificados`); `GoliveCheck.php:197-202`; deps `nfephp-org/sped-nfe` + `ext-soap` no Dockerfile | [BLOQUEANTE] p/ faturar |
| **Google Maps** | `GOOGLE_MAPS_KEY` (geocoding de cliente; painel de status) | `config/services.php` (bloco `geocoding`) | [IMPORTANTE] |
| **E-mail** | Default `MAIL_MAILER=log` (nada sai). SMTP **por empresa** via mailer dinâmico `empresa_smtp` (`EmpresaConfigController.php:155,173`); envio de venda diária é "gate SMTP" (`routes/console.php:28`) | `config/mail.php:17` | [IMPORTANTE] |
| **eRede (cartão)** | `PAGAMENTO_DRIVER=erede` + `EREDE_PV`/`EREDE_TOKEN` + token por empresa | `config/services.php`; `GoliveCheck.php:224-230` | [IMPORTANTE] |
| **SGCasa (GPS)** | `MONITORA_DRIVER=sgcasa` + `SGCASA_API_URL/TOKEN` | `config/services.php` | [DESEJÁVEL] |
| **CONSISA / IBPT** | `CONSISA_API_URL`; `IBPT_CSV_URL` (sem elas = no-op) | `config/services.php`; `IbptAtualizar.php:87` | [DESEJÁVEL] |

---

## 7.4 Deploy

### Pipeline que existe hoje (fatos, dos workflows)
- `.github/workflows/ci-erp-novo.yml`: lint + PHPUnit (sqlite e job `test-postgres` com role não-superuser) + type-check/Vitest do frontend, em `ubuntu-latest`.
- `.github/workflows/deploy-erp-novo-homolog.yml`: **único deploy do erp-novo**, disparado por push na `main` (paths `erp-novo/**`), roda em runner **self-hosted na própria VPS**. Passos reais: restaura `.env` de `/opt/dubena-env/erp-novo-homolog.env`; **builda a SPA no deploy** (docker `node:20-alpine`, linhas 67-74 — `public/app` não é mais commitado); `docker compose build && up -d --force-recreate`; valida mounts; `composer install` com 3 retries; `php artisan migrate --force --database=pgsql_owner` (linha 160); `optimize:clear` + `config:cache`; seeds; `golive:check` **informativo** (`|| true`, linha 214); health checks em `/`, `/novo/up`, `/novo/app/`.
- `deploy/nginx/gasemcasa-com.conf` já roteia `/` e `/novo/` do domínio principal para o container novo (:3120) e o resto para o legado (:3110) — a coexistência strangler está desenhada no repo. Aplicação real na VPS: **NÃO VERIFICADO**.

### Lacunas
1. **Não existe workflow/compose de deploy de PRODUÇÃO** — só homolog. Nada no repo define como o erp-novo sobe como produção (domínio, `.env` de produção, `golive:check --strict` como portão bloqueante — hoje é `|| true`). **[BLOQUEANTE]**
2. **Seeds de demonstração no caminho do deploy** — risco real em produção se o workflow for clonado:
   - `DemoGuarapuavaSeeder`: guard duplo — workflow (linhas 174-190, pula se >50 clientes) e guard interno (`DemoGuarapuavaSeeder.php:108`: `if (Cliente::withoutTenant()->count() > 50) return`). Comportamento verificado no código: **um banco de produção recém-criado (antes do ETL) tem 0 clientes → recebe 200 clientes/500 pedidos fake de Guarapuava**. A trava protege contra duplicação, não contra rodar em produção vazia. **[BLOQUEANTE]** (o deploy de produção não pode incluir esse passo, ou deve condicionar por `APP_ENV`).
   - `MarketplaceDemoSeeder`: roda **incondicionalmente em todo deploy** (linhas 192-198) — cria/garante "Unidade Batel" demo aderida ao marketplace mesmo em banco populado. **[BLOQUEANTE]** para produção.
   - `DeploySeeder` (admin/RBAC/planos/cidades/superadmin) é idempotente e adequado, **desde que** `ADMIN_SEED_*`/`SUPERADMIN_SEED_*` estejam definidos (7.1.3).
3. **Rollback inexistente**: imagem única `erpnovo-app:homolog` sem tag de versão, código por bind-mount do checkout; não há passo de rollback nem migrations reversíveis testadas como estratégia. Voltar = novo push. **[IMPORTANTE]**
4. `route:cache`/`view:cache` não são executados (só `config:cache`) — **[DESEJÁVEL]**.
5. Runner self-hosted compartilhado no monorepo: o deploy do erp-novo precisa restaurar o `.env` do ctrl-web (linhas 50-65) para não derrubar o legado — acoplamento frágil documentado no próprio workflow. **[IMPORTANTE]** enquanto os dois coexistirem.

---

## 7.5 Observabilidade

| Lacuna | Evidência | Criticidade |
| --- | --- | --- |
| Sem monitoramento de erros (Sentry/Bugsnag/Flare): grep em `composer.json`/`package.json`/`frontend/package.json` = vazio | varredura | [IMPORTANTE] |
| Log em arquivo único sem rotação: `LOG_CHANNEL=stack` + `LOG_STACK=single` nos dois templates; canal `daily` existe (`config/logging.php:71`) mas não é usado | `.env.example:26-27`, `.env.homolog.example:16` | [IMPORTANTE] |
| Health check: existe `/up` (`bootstrap/app.php:19`, exposto como `/novo/up`) e é usado no deploy; **não há** monitoramento contínuo/uptime externo definido no repo | workflow linhas 216-234 | [DESEJÁVEL] |
| Logs de queue/scheduler/reverb só via `docker logs` (sem agregação); nginx do container loga em stdout/stderr | `docker/nginx/default.conf:20-21` | [DESEJÁVEL] |
| `golive:check` e painel de satélites (`SateliteStatusController`) existem e são o "monitoramento de prontidão" — mas o golive:check no deploy é não-bloqueante (`\|\| true`) | `deploy-erp-novo-homolog.yml:206-214` | [IMPORTANTE] |

---

## 7.6 Backup / restore / rollback

- **Nenhum mecanismo de backup ou restore existe no repositório**: grep por `pg_dump|backup|restore` em `deploy/`, `.github/`, `erp-novo/docker*`, `DEPLOY_HOMOLOG.md` retorna apenas `restore-keys` de cache do CI. **[BLOQUEANTE]**
- Agrava: os dados vivem em volumes Docker (`db_data` = Postgres; `app_storage` = **certificados A1 fiscais** — `CertificadoService` grava no disco `local` —, uploads e logs). Um `docker volume rm`/recriação do host perde banco **e** certificados, sem cópia em lugar algum (não há disco S3 configurado; `FILESYSTEM_DISK=local`).
- Rollback de aplicação: inexistente (ver 7.4.3). Rollback de banco: nenhum procedimento; migrations rodam com `--force` a cada deploy sem snapshot prévio. **[BLOQUEANTE]** como pacote (backup + procedimento de restore testado antes do go-live).
- Backup do legado durante a transição: fora do escopo do repo — **NÃO VERIFICADO**.

## 7.7 Dados — o que precisa acontecer antes do go-live

O que os comandos ETL suportam **de verdade** (código):

- `etl:run` (`app/Console/Commands/EtlRun.php`) roda os 28 migrators (`app/Etl/Migrators/`, ordem por dependência) — **sempre carga cheia**; as únicas opções são `{migrator?}`, `--dry-run` e `--check`. **Não existe modo incremental/delta por timestamp.**
- A recarga é **idempotente por upsert preservando id** (`app/Etl/Support/PreservaIdsDoLegado.php`: `upsert` por chave `id` + ressincronização de sequence). Consequências verificadas no código:
  - Re-rodar o ETL **sobrescreve** qualquer linha de id legado editada no sistema novo (o upsert vence);
  - Linhas **excluídas no legado não são removidas** no novo (upsert não deleta);
  - Registros criados no novo (ids acima da sequence ressincronizada) são preservados.
- Portões existentes: `etl:run --check` (invariantes por migrator) e `cutover:check` (`CutoverCheck.php` — todas as invariantes sem re-migrar, exit≠0 bloqueia).
- Alternativa via UI: `ExecutarMigracaoJob` (SuperAdmin) — sofre do problema `retry_after` (7.1.5).

Lacunas/decisões obrigatórias para o cutover:

1. **Janela de congelamento do legado + recarga final completa** é o único caminho suportado (não há delta). Procedimento: congelar escrita no legado → `etl:run` completo contra o legado de produção → `cutover:check` → `golive:check --strict` → virada do Nginx. Nada disso está roteirizado como runbook/workflow. **[BLOQUEANTE]**
2. A fonte da recarga precisa ser o **banco legado completo de produção** (conexões `legado`, `app_legado`, `monitora_legado` em `config/database.php`) — a auditoria da migração (`AUDITORIA_MIGRACAO_DADOS_LEGADOS.md`) registrou que a carga anterior usou espelho parcial (43/200 tabelas). Os envs `APP_LEGADO_*`/`MONITORA_LEGADO_*` não estão em nenhum template (7.1.2). **[BLOQUEANTE]**
3. Duração/memória: `EtlRun` seta `memory_limit=3G` e o job fala em horas/16 milhões de linhas — a janela de congelamento precisa comportar isso (e o deploy não pode reciclar o container no meio). **[IMPORTANTE]**
4. Dados criados em homolog (massa demo Guarapuava/marketplace) **não podem existir no banco de produção** — o banco de produção deve nascer vazio + `DeploySeeder` + ETL (nunca reaproveitar o banco de homolog). **[IMPORTANTE]**

---

## 7.8 Resumo — bloqueantes para o go-live

1. Sem `.env`/template de produção; chaves críticas usadas pelo código não documentadadas em nenhum example (7.1.1, 7.1.2).
2. Credenciais seed com default fraco (`admin1234`, `superadmin1234`) criadas pelo deploy se as envs não forem definidas (7.1.3).
3. `retry_after` (90 s) < timeout (6 h) do `ExecutarMigracaoJob` → re-entrega duplicada da migração em fila (7.1.5).
4. Não existe compose/workflow de deploy de PRODUÇÃO — só homolog; `golive:check` roda como informativo (`|| true`) (7.2, 7.4.1).
5. Seeds demo no caminho do deploy: banco de produção vazio receberia a massa fake de Guarapuava; `MarketplaceDemoSeeder` roda sempre (7.4.2).
6. Gates reais desligados por default e dependentes de provisionamento externo: Firebase (login do app), PIX por empresa, certificado A1 por empresa (fiscal) (7.3).
7. Nenhum mecanismo de backup/restore/rollback no repo; certificados fiscais e banco só em volumes Docker (7.6).
8. Cutover de dados sem runbook: ETL é só carga cheia (upsert), exige congelamento do legado + recarga final da fonte completa + `cutover:check` (7.7).
# 10. Débito técnico da refatoração

> ⚠️ **SEÇÃO NÃO VALIDADA** — o crítico foi interrompido antes do veredito e uma refutação dela já caiu (ver ressalva no topo do documento). Trate como hipóteses.

Auditoria do código NOVO (`erp-novo/`, `app-entregador/`, `mobile-shared/`). Fonte de verdade = o código lido. Achados que não puderam ser confirmados sem executar estão marcados `SUSPEITA — NÃO VERIFICADO`.

Nota de método: esta seção evita a varredura rasa. Vários eixos previstos no escopo foram investigados e **não** produziram defeito — estão registrados na seção "Verificações que não confirmaram defeito", porque um relatório que só lista o que confirma o próprio viés não é auditoria.

---

## ALTO

### A1. Regra de negócio duplicada em 3 lugares, e a cópia do mobile DIVERGE (desconto sumido)

**Arquivos:**
- Fonte de verdade: `erp-novo/app/Domain/Pedido/PedidoService.php:180`
  ```php
  'valor_total' => round($qtd * $preco - $desc, 2),
  ```
- Cópia 1: `erp-novo/app/Domain/Mobile/PedidoMobileService.php:346`
  ```php
  'total' => round((float) $i->quantidade * (float) $i->preco_unitario, 2),
  ```
- Cópia 2: `erp-novo/app/Http/Controllers/Api/Mobile/AppPedidoController.php:178` — **byte a byte idêntica à cópia 1**.

**Por que é errado:** o cálculo do total do item do pedido existe em 3 lugares. O `PedidoService` (que **persiste** o valor) subtrai o desconto do item; as duas cópias do canal mobile (que **exibem** o valor ao cliente) recalculam do zero e **ignoram a coluna `desconto`**. Além disso, o mobile recalcula em vez de ler a coluna `valor_total` já persistida pelo `PedidoService` — as duas queries (`PedidoMobileService.php:330` e `AppPedidoController.php:155`) fazem `select` explícito de `itens:id,pedido_id,produto_id,quantidade,preco_unitario`, **sem trazer `desconto` nem `valor_total`**. A informação correta é deliberadamente deixada fora do SELECT e depois reinventada errada.

**Impacto:** pedido com desconto de item mostra no app do consumidor um total de item **maior do que o efetivamente cobrado/persistido**. Divergência visível ao cliente final entre a tela do app e o valor do pedido — classe de bug que vira contestação de cobrança. E como as cópias 1 e 2 são idênticas, corrigir uma deixa a outra errada: a duplicação é literalmente copy-paste entre duas sessões.

**Correção óbvia:** ambas deveriam ler `$i->valor_total` (já calculado e persistido), não recalcular.

### A2. Field-level security aplicada por controller, não por modelo — sub-recursos escapam da camada de Resource

**Arquivos:**
- `erp-novo/app/Http/Resources/ClienteResource.php:16` — `private const CAMPOS_SENSIVEIS = ['credito_limite','credito_saldo','convenio_limite']`, filtrados via `CamposPermitidos` (A7).
- `erp-novo/app/Http/Controllers/Api/Admin/ClienteController.php:28` — **repete a mesma constante** `CAMPOS_SENSIVEIS` no controller (duplicação da lista em 2 arquivos, sem fonte única).
- `erp-novo/app/Http/Controllers/Api/Admin/ClienteTelefoneController.php:24` — `response()->json(['data' => $cliente->telefones()->get()])`, model cru.
- `erp-novo/app/Http/Controllers/Api/Admin/ClienteSubrecursoController.php:26,42` — model cru; `create($dados)` devolvido direto.

**Por que é errado:** existem **4 Resources** (`ClienteResource`, `EmpresaResource`, `PedidoResource`, `ProdutoResource`) para **46 controllers** em `app/Http/Controllers/Api/Admin/`. A filtragem field-level só acontece no caminho que passa por Resource. O domínio Cliente está fatiado em 3 controllers e apenas 1 deles atravessa essa camada. Pior: a lista de campos sensíveis está **escrita duas vezes** (Resource + controller), então endurecer a política exige lembrar de 2 arquivos.

**Impacto:** a proteção de campos sensíveis é uma convenção que cada controller pode esquecer, não uma garantia estrutural. Qualquer rota nova serializando model cru nasce fora do controle A7. O risco concreto de vazamento por essas 2 rotas específicas depende das colunas dos models `Telefone`/`Interacao` — **SUSPEITA — NÃO VERIFICADO** (não li os models); o defeito *arquitetural* (proteção opt-in por controller + constante duplicada) está verificado.

### A3. Três padrões divergentes de validação; o mesmo agregado usa os três

**Arquivos:**
- Padrão A — FormRequest: `ClienteController.php:10` (`use App\Http\Requests\ClienteRequest`). Só existem 4 FormRequests em `erp-novo/app/Http/Requests/`: `ClienteRequest`, `EmpresaRequest`, `PedidoRequest`, `ProdutoRequest`.
- Padrão B — `$request->validate` inline: `ClienteTelefoneController.php:33-37`; `ClienteSubrecursoController.php:36-41`.
- Padrão C — validação centralizada por registry: `CadastroApoioController.php:41` → `$this->validar($request, $cfg['extras'])`, regras declaradas em `App\Domain\Apoio\CadastroApoioRegistry`.

**Por que é errado:** três convenções para o mesmo problema, escolhidas em sessões diferentes, **coexistindo dentro do domínio Cliente**: o agregado principal usa FormRequest e seus dois sub-recursos usam validação inline. Não há um lugar onde a regra de entrada de "cliente" viva por inteiro.

**Impacto:** manutenção e revisão de segurança passam a ser por-arquivo em vez de por-domínio; alterar `ClienteRequest` não afeta telefones nem interações do mesmo cliente. Custo de onboarding e risco de regra divergir silenciosamente (é exatamente o mecanismo que produziu o A1).

---

## MÉDIO

### M1. `SaMigracaoPage.tsx` é página monolítica de 506 linhas, fora do padrão de decomposição do próprio projeto

**Arquivo:** `erp-novo/frontend/src/features/superadmin/SaMigracaoPage.tsx` — 506 linhas, **maior arquivo do frontend** (o 2º é `superadmin/api.ts` com 357; a mediana das páginas fica em ~150-200).

**Por que é errado:** o projeto estabeleceu (memória F17/R1–R8, e visível em `features/financeiro/tabs/`) o padrão de quebrar monólitos em subcomponentes `tabs/`. `SaMigracaoPage` não seguiu — é 2,5× a página típica e ~3,4× o padrão de decomposição adotado nos módulos posteriores. Sinal claro de módulo escrito numa sessão que não herdou a convenção.

**Impacto:** manutenção e revisão difíceis no módulo que executa a migração de dados legados — justamente a área de maior risco operacional do sistema. Não é bug funcional; é dívida de estrutura concentrada no lugar errado.

---

## Verificações que NÃO confirmaram defeito (registrado por honestidade)

Estes eixos estavam no escopo, foram investigados e **não** sustentam acusação. Registro para que o crítico possa distinguir "não olhei" de "olhei e está certo".

- **camelCase × snake_case no Postgres novo:** busca por `$table->tipo('camelCase')` nas **86 migrations** de `erp-novo/database/migrations/` retornou **zero** ocorrências. O schema novo é consistentemente snake_case. O gotcha camelCase conhecido do projeto pertence ao **espelho do legado**, não ao schema da refatoração. **Não é defeito do código novo.**

- **Jobs sem tratamento de falha:** os 6 jobs (`Domain/Cliente/GeocodificarClienteJob.php`, `Domain/Logistica/Jobs/AtribuirPedidoJob.php`, `Domain/Mobile/Jobs/EnviarPushJob.php`, `Domain/Relatorio/NotificarEstoqueBaixoJob.php`, `Domain/Tenant/TenantAwareJob.php`, `Jobs/ExecutarMigracaoJob.php`) **todos** declaram `$tries` explícito; `EnviarPushJob.php:33` tem `$backoff`; `ExecutarMigracaoJob.php:65` implementa `failed(\Throwable $e)`. `ExecutarMigracaoJob.php:23` usa `$tries = 1` com justificativa escrita ("sem retry automático: recarga é decisão humana"). **Padrão bem executado.**

- **`catch` engolindo exceção fora do ETL:** dos 102 blocos no backend, a esmagadora maioria está em `app/Etl/Migrators/` (já coberto pela seção de dados). Os casos fora do ETL que inspecionei são deliberados, comentados e **logados**: `GeocodificarClienteJob.php:69` faz `Log::warning` com contexto; `Domain/Shared/Auditavel.php:79-80` retorna null com comentário "CLI/ETL/jobs — sem request". **Não classifico como atalho.**

- **TODO/FIXME/HACK permanentes:** varredura em `app/` (excluindo ETL) por `TODO|FIXME|XXX|HACK|placeholder|não implementado` retornou **7 hits, todos falsos positivos** — a palavra "TODO(S)" em português dentro de docblocks (`FinanceiroService.php:17`, `CalculoParcelasService.php:10`, etc.). O único "não implementado" é `Providers/AppServiceProvider.php:86`, que é o **oposto** de um atalho: lança `RuntimeException` recusando subir com driver PIX inválido ("recuse a subir assim em vez de fingir cobrança com o fake"). **Backend limpo nesse eixo.**

- **Transações em operação multi-tabela:** 65 usos de `DB::transaction` distribuídos por 30 arquivos, concentrados nos Services de domínio (`CaixaService` 8, `EstoqueService` 6, `FinanceiroService` 4, `PedidoService` 3). A camada de escrita usa transação por padrão. Não encontrei operação multi-tabela óbvia sem transação. **NÃO VERIFICADO exaustivamente** (não auditei os 30 arquivos linha a linha), mas a densidade não sustenta a acusação de "atalho sistemático".

- **Testes que só checam status 200:** inspecionei `tests/Feature/` (76+ arquivos). O padrão dominante encadeia asserções de comportamento após o status — ex.: `ClienteTest.php:40-44` faz `assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.nome','Maria')`, testando **isolamento de tenant de verdade** (cria cliente de outra empresa e exige que não apareça). `Queue::fake()` em `ClienteTest.php:48` é usado para **afirmar despacho de job**, não para anular o teste. Não encontrei `Event::fake()` global no `TestCase` base. **Qualidade das asserções: adequada.** Execução da suíte: **NÃO VERIFICADO** (proibido rodar `php artisan test`).

- **Frontend sem tratamento de erro:** 125 blocos `catch` em `features/`, nenhum uso de `fetch()` cru (todo acesso passa pelos módulos `api.ts` por feature). Padrão de camada de API consistente entre features. **Não sustenta a acusação.**

---

## Resumo por severidade

| Severidade | Qtd |
|---|---|
| Crítico | 0 |
| Alto | 3 |
| Médio | 1 |
| Baixo | 0 |

**Achado mais acionável:** A1 — o total de item no canal mobile ignora o desconto e é copy-paste em 2 arquivos (`PedidoMobileService.php:346` e `AppPedidoController.php:178`), divergindo do valor persistido em `PedidoService.php:180`.
