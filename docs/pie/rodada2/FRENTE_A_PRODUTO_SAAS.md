# Frente A — Produto e SaaS (Rodada 2, Auditoria PIE V2)

**Data:** 2026-10-30 · **Commit:** `133f588d` (main) · **Protocolo:** seções 5 (Motor B) e 6.2 (proteção de receita)
**Revisor:** Frente A · **Escopo:** o sistema visto como **SaaS de gestão para N revendas de GLP**, não como o ERP da Dubena.

Estados: **C** = CONFIRMADO · **FI** = FORTE INDÍCIO · **H** = HIPÓTESE · **NR** = NÃO REPRODUZIDO.
Nada aqui repete a Rodada 1: PIE-nn citados pelo ID quando tocam o tema.

---

## 0. Resumo em uma página

O `erp-novo` é **largo e profundo como ERP de revenda única**. Há coisa que
concorrente de disk-gás raramente tem: comodato com vigilância de vasilhame
(`VinculoVasilhame`), Gás do Povo, malote do entregador, roteirização
nearest-neighbor, ranking de entregador, bina, "repetir último pedido" no
balcão e no app, taxa de entrega por faixa e alçada de desconto configuráveis.

Como **produto SaaS**, porém, ele ainda não fica de pé sozinho. Os quatro
bloqueios para vender à revenda nº 2 são estruturais, não de polimento:

1. **Não existe um caminho de produto para criar uma revenda** (P2-A-01): nem o
   SuperAdmin nem a API criam `TenantAccount`. O único caminho é o
   `saas:tenant:importar`, por console, com um JSON de evidências que exige
   empresas e usuários já existentes. Além disso, a criação de empresa está
   congelada por padrão.
2. **O painel do operador da plataforma é cego sob RLS** (P2-A-02): no Postgres,
   como `erp_app` e sem contexto, `empresas` devolve 0 de 141 linhas. O SuperAdmin
   não lista revendas, mostra zero no dashboard e não consegue atribuir plano.
3. **Não há faturamento da plataforma** (P2-A-05): o status da assinatura é
   trocado à mão, nada expira sozinho, não existe fatura, carência nem tela de
   "meu plano" para a revenda.
4. **O app e a marca ainda são da Dubena** (P2-A-08, P2-A-09): o login do
   painel diz "Dubena", o pacote é `br.com.dubena.gasemcasa` e os termos vêm de
   `gasemcasa.com.br`. O controlador LGPD é um só por implantação, embora o app
   seja um marketplace de N revendas.

A licença, ligada desde o PIE-21, **vaza e corta errado ao mesmo tempo**
(P2-A-03, P2-A-04). O DRE e a mala-direta respondem 200 sem contrato. Em
compensação, o plano Essencial perde o relatório de vendas e o de fechamento de
caixa, que são o básico de qualquer revenda.

**Parecer: REPROVADO** como SaaS pronto para onboarding de uma segunda revenda.
Isso é coerente com o próprio plano: `PLANO_TRANSFORMACAO_SAAS.md:165` manda
"não cadastrar uma segunda revenda independente" até os gates, e o F10 depende
de um segundo cliente. Ver §9.

---

## 1. Mapa de jornadas por persona

Passos contados por leitura de código (rota SPA, componente, endpoint), **sem
clique real**. A Frente C faz a navegação em navegador. "Passo" é uma ação
deliberada do usuário: abrir, escolher, digitar um campo que não vem
pré-preenchido, confirmar.

| Persona | Tarefa diária crítica | Onde (tela → endpoint) | Passos | Observação |
|---|---|---|---|---|
| **Atendente / telefonista** | Atender ligação de cliente conhecido e lançar o pedido | `/pedidos` → `PainelChamadas` (bina, polling 5 s, `PainelChamadas.tsx:22`) → `NovoPedidoDialog` → `GET /pedidos/sugestao` (`PedidoSugestaoService::paraCliente`) → `POST /pedidos` | **3–4** (atender ou buscar cliente → conferir sugestão → salvar) | A sugestão preenche itens, preço, condição, operação e setor a partir do último pedido (`PedidoSugestaoService.php:1-60`). A bina depende do webhook do PABX por revenda. |
| Atendente | Lançar pedido de **cliente novo** | `NovoPedidoDialog` só tem `AsyncSelect` de cliente (`NovoPedidoDialog.tsx:164`) → sair → `/clientes/novo` → voltar → reabrir | **7–9** e troca de tela | Não há cadastro rápido no diálogo (P2-A-13). Some-se o PIE-33 (o diálogo descarta o que foi montado). |
| **Caixa** | Abrir/fechar caixa, baixar título, conferir malote do entregador | `/financeiro` (11 abas, `FinanceiroPage.tsx:21-34`): Caixa, A pagar e receber, Malote (`GET /malotes/conferencia`, `POST /malotes/fechar`) | Fechar malote: **3–4** (aba → período/entregador → conferir → fechar) | Funcional. No plano Essencial o **relatório** de fechamentos de caixa responde 402 (P2-A-04). |
| **Gerente de logística** | Distribuir pedidos entre entregadores | `/central` (`CentralPage.tsx`: fila × entregadores, ranking `DistribuidorService`) → `POST` atribuir | **2–3** por pedido (Atribuir → Escolher) | O modo `auto` existe (`DistribuidorService.php:1-30`). Os pesos vêm de `logistica_config` (`:47-48`), ou seja, é configuração e não constante. |
| Gerente de logística | Ver frota/rota ao vivo | `/monitora` (exige o recurso `monitora`) | 1–2 | Fora do Essencial. Mapa sem posição centra em Guarapuava (P2-A-10). |
| **Entregador** (app) | Iniciar jornada, receber rota, concluir entrega com pagamento | `iniciar-jornada` → `(tabs)/rota` (ordem dada pelo `RoteirizadorService`) → `pedido/[id]` → `pedido/[id]/concluir` | **4** por entrega | Ganhos (`ganhos.tsx`), vale-gás e venda em campo existem. **Não emite NFC-e** na entrega: a rota `fiscal/emitir` existe só para `approle:industrial` (`api.php:937-943`) e o app não a chama (grep em `app-entregador/src`: só `cupom` em texto). |
| **Consumidor** (app) | Primeiro pedido | `index` → `(tutorial)/page1` → `selecionar-revenda` → `login` → `policies` → `sms` → `newuser` → `address` → `home` → `carrinho` → `pix`/`track` (`index.tsx:19-26`, `login.tsx:75-76`, `sms.tsx:59-65`, `carrinho.tsx:97-100`) | **~10 telas** | É longo, mas é feito uma vez só. A recompra tem "Pedir de novo / Repetir" na home e em pedidos (`home/index.tsx:137-145`, `pedidos/index.tsx:153`): **2–3 passos**. |
| **Dono da revenda** | Ver como foi o dia; ver resultado do mês | `/` → `ParaHoje` + `PainelDoDiaService` (pedidos em aberto, hoje, valor; receber/pagar hoje e vencido, `PainelDoDiaService.php:44-67`); `/financeiro` aba DRE (`GET /financeiro/dre`) | 1 / 2 | O DRE "premium" do plano Completo sai pela aba do Financeiro **sem licença** (P2-A-03). Não há tela de avaliação dos clientes (P2-A-11) nem de plano contratado (P2-A-05). |
| Dono da revenda | Configurar a revenda nova | `/configuracoes` (cerca de 35 cadastros de apoio, `CadastroSlugs.php:28-63`), `/empresas/:id` (fiscal, certificado, integrações), `/produtos`, `/acessos` | **Sem roteiro**: dezenas de telas, ordem não indicada | Sem assistente nem checklist (P2-A-07). |
| **Operador da plataforma** (SuperAdmin) | Criar revenda, atribuir plano, ver saúde | `/superadmin/empresas` → `PUT /superadmin/empresas/{id}/assinatura` | **Criar: impossível** na UI | Sem botão nem endpoint de criação (`SaEmpresasPage.tsx:54-59`, `api.php:1029-1040`). Sob RLS a lista vem vazia (P2-A-02). Saúde da revenda: 6 contadores (P2-A-12). |

---

## 2. Onboarding de uma revenda nova (time-to-first-order)

### 2.1 O que o código exige, passo a passo

| # | Passo | Quem/onde | Trava |
|---|---|---|---|
| 1 | Criar o titular (`TenantAccount`) | **Só** `php artisan saas:tenant:importar <json> --apply` (`SaasImportarTenantMapping.php:12`), que roda `TenantMappingImporter::apply` (`TenantMappingImporter.php:91-130`) | O JSON exige `empresa_id` e `user_id` **já existentes** e `*_evidence_ref` em cada vínculo (`:70-80`). Não há endpoint nem tela. **P2-A-01** |
| 2 | Criar a empresa (CNPJ) | `POST /api/admin/empresas` (`EmpresaController.php:59-91`) — de dentro de um tenant que já existe | `TransformationFreeze::assertCompanyCreationAllowed` com padrão **`true`** (`config/saas_transformation.php:12-13`, `.env.example:173`). E a empresa nasce no tenant **do ator**: serve para filial, não para revenda nova. |
| 2b | Alternativa: migrar de sistema antigo | `/superadmin/migracoes` (`MigracaoController`) | Só origens da família do legado: `erp_pg`, `app_mysql`, `monitora_mysql` (`MigracaoService.php:33-37`). Também congelado (`assertMigrationWritesAllowed`, `MigracaoController.php:162`). Revenda vinda de outro software não tem porta. |
| 3 | Primeiro usuário administrador | `DeployAdminSeeder` cria "Grupo Padrão / Empresa Matriz / UF SP" com `support = true` (`DeployAdminSeeder.php:21-45`) — é seed de implantação, não de revenda | Não há convite nem "criar dono da revenda". |
| 4 | Papéis | `RbacSeeder` gera Administrador / Gerente / Operador / Entregador **para os grupos que existem quando ele roda** (`RbacSeeder.php:19-23, 39-63`) | Grupo criado depois fica sem papéis até rodar o seeder de novo (manual: nenhum script de deploy chama `db:seed`; grep em `deploy/`, `.github/` e Dockerfiles). |
| 5 | Plano/assinatura | `saas:assinatura:criar` (CLI) ou `PUT /superadmin/empresas/{id}/assinatura` | Pela UI falha sob RLS (P2-A-02). A assinatura é **por empresa** (P2-A-06). |
| 6 | Fiscal | `/empresas/:id` → certificado A1, `nfce-token`, `GET /empresas/{id}/fiscal/prontidao` (`ProntidaoFiscalService`) | É a **única** checagem de prontidão por revenda. O driver real tem o PIE-02 pendente. |
| 7 | Cadastros de apoio | `/configuracoes`: condições de pagamento, operações, **situações de pedido com papel `EM_ROTA`** (sem ela o app do entregador falha ao iniciar a rota, `DemoGuarapuavaSeeder.php:479-483`), setores/locais de estoque, classes, unidades, contas | Tudo nasce vazio. Só o plano de contas é copiado (`EmpresaController.php:83`, `PlanoContaModeloService`). |
| 8 | Catálogo e preço | `/produtos`, `/produtos/precos`; capacidade "P13" à mão (`ProdutoFormPage.tsx:160`) | Não há catálogo padrão de GLP/água. O catálogo bom (P13, P13 recarga, P20, P45, água 20 L/10 L, galão) existe **só** dentro do `DemoGuarapuavaSeeder` (`:266-274`), misturado a clientes e pedidos falsos. |
| 9 | Estoque inicial | entrada/ajuste em `/estoque` | O saldo **não pode ficar negativo** (`EstoqueService.php:107-110`): sem entrada, o primeiro pedido não concretiza. |
| 10 | Geografia/área de entrega | `/geografico` (importação IBGE/logradouros), cercas, taxas por faixa | Necessário para a taxa de entrega e para o marketplace (`MarketplaceService.php:44-66`). |
| 11 | Entregadores | Colaborador + usuário com papel Entregador + jornada | Funciona via `/colaboradores` + `/acessos`. |
| 12 | App do consumidor | Aderir ao marketplace (`empresas.app_marketplace_ativo`) **ou** build white-label com `EMPRESA_ID` | A flag **não tem tela nem campo no request** (está só no `$fillable`, `Empresa.php:41`): depende de banco ou seed (P2-A-09). |
| 13 | PIX/cartão | `/empresas/:id` → integrações | Fail-closed por empresa: correto. |
| 14 | 1º pedido → entrega → recebimento | Fluxo da §1 | — |

### 2.2 Prova executada

```
php artisan test tests/Feature/SuperAdminTest.php tests/Feature/LicenciamentoSaasTest.php \
  tests/Feature/LicencaEnforcementTest.php tests/Feature/AusenciaDeAssinaturaTest.php \
  tests/Feature/TenantMappingImporterTest.php
Tests: 50 passed (138 assertions) — 11.01s
```

Os testes provam que o importador **recusa** vínculo sem evidência e que a
licença nega 402 sem assinatura. Eles **não** provam onboarding: não existe teste
"criar revenda → configurar → primeiro pedido", porque não existe esse fluxo. O
PIE-36 já registra a falta de um teste encadeado pedido → entrega → caixa.

**Time-to-first-order hoje:** não é mensurável. Exige engenheiro com acesso de
console e banco: passos 1, 3, 4, 5 e 12, mais o descongelamento dos freezes.
**HIPÓTESE:** com o roteiro acima feito por quem conhece o sistema, de 1 a 2 dias
de configuração, dominados pelos passos 7 a 10.

---

## 3. Monetização e empacotamento

| Item | Situação | Evidência |
|---|---|---|
| Planos | **Existem**: Essencial R$ 349,90 (app consumidor, app entregador, cobrança, NFC-e), Completo R$ 749,90 (catálogo inteiro), Legacy Full R$ 0 (transição, não vendável) | `PlanosSeeder.php:36-41, 46-84` |
| Recursos | 10 chaves | `RecursoCatalogo.php:26-37` |
| Limites | 3 chaves (empresas, usuários, veículos monitorados), **sem teto semeado** = ilimitado | `RecursoCatalogo.php:51-55`, `PlanosSeeder.php:17-22` |
| Enforcement | `RecursoPorRota` por prefixo de `api/admin/`, ligado pelo PIE-21 | `RecursoPorRota.php:38-56` |
| Recursos **sem nenhuma porta** | `marketplace`, `app_consumidor`, `app_entregador`, `tempo_real`: grep em `app/` acha essas chaves só no catálogo. O teste fixa que `api/app/v1/*` fica fora do mapa (`LicencaEnforcementTest.php:414`) | **P2-A-03** |
| Trial | Status `trial` com `trial_ate` (`Assinatura.php:21, 58-70`) | Nada expira o trial: nenhum agendamento em `routes/console.php` toca assinatura |
| Inadimplência | Status manual; quando aplicado, corta **na hora** todo módulo opcional, inclusive NFC-e e cobrança, sem carência | `Assinatura.php:62-64`, `SuperAdminService.php:152` |
| Faturamento da plataforma | **Não existe**: sem fatura, sem cobrança da revenda, sem gateway (grep `fatura`, `asaas`, `iugu`, `stripe`, `vindi` em `app/`, `config/`, migrations) | **P2-A-05** |
| Upgrade/downgrade/self-service | Não existe: a revenda não vê o próprio plano (nenhuma tela em `frontend/src` fora do superadmin consome `assinatura`), e o 402 cai no tratamento genérico | **P2-A-05** |
| Unidade de cobrança | Assinatura por **empresa**, embora D2 diga "a assinatura pertence ao tenant" | `Assinatura.php:31`, `LicencaService.php:178-193`, `PLANO_TRANSFORMACAO_SAAS.md:128-132` — **P2-A-06** |

**O que impede vender hoje à revenda nº 2,** em ordem de bloqueio: P2-A-01
(não dá para criá-la), P2-A-02 (não dá para administrá-la pelo painel), P2-A-05
(não dá para cobrá-la), P2-A-09 (app e LGPD de quem?), PIE-02 (NF-e real),
PIE-22 (login de telefone real) e P2-A-10, se ela estiver fora do fuso de Brasília.

---

## 4. Convenção da Dubena que vazou para o produto

Classificação: **B** = bloqueia multi-revenda · **I** = incomoda · **OK** = aceitável (comentário, teste, ETL ou demo).

| # | Onde | O quê | Classe |
|---|---|---|---|
| 1 | `frontend/src/features/auth/LoginPage.tsx:78-80` | Login do painel de **toda** revenda: logotipo "D", "Dubena", "Gás em Casa" | **B** (comercial) — P2-A-08 |
| 2 | `superadmin/SaLayout.tsx:80`, `SaLoginPage.tsx:69` | Marca "Dubena" no painel da **plataforma** | I (a plataforma não é a Dubena) — P2-A-08 |
| 3 | `app-gas-em-casa/app.json:3-13, 76`; `app-entregador/app.json:3-13, 59` | Nome "Gás em Casa", `scheme: dubenagasemcasa`, pacote `br.com.dubena.gasemcasa[.entregador]` | **B** se o modelo for white-label; I se for marketplace — P2-A-09 |
| 4 | `app-gas-em-casa/src/services/user.service.ts:60-62` | Termos e políticas lidos de `https://www.gasemcasa.com.br/termos.php` | **B** (LGPD: termos de um terceiro para cliente de outra revenda) — P2-A-09 |
| 5 | `erp-novo/config/paginas_publicas.php:15-23` | Controlador LGPD único por implantação (env), com `app_nome` padrão "Gás em Casa" | **B** no modelo marketplace — P2-A-09 |
| 6 | `app-entregador/src/app/login.tsx:125`; `app-gas-em-casa/src/app/(auth)/address.tsx:243` | "Gás em Casa" em texto fixo | I |
| 7 | `config/app.php:79` | `timezone` da plataforma `America/Sao_Paulo`; não existe fuso por tenant | **B** para revenda em AM/MT/MS/RO/RR/AC — P2-A-10 |
| 8 | `satelites/MapaAoVivoTab.tsx:13`, `RotaTab.tsx:15` | Centro do mapa fixo em Guarapuava, sem cair para as coordenadas da empresa (a `CercasTab.tsx:175-185` já cai) | I — P2-A-10 |
| 9 | `geografico/ImportacaoTab.tsx:151` | Placeholder "Ex.: Guarapuava" | OK |
| 10 | `DeployAdminSeeder.php:26-29` | Empresa base com `uf = 'SP'` | OK (seed de implantação) |
| 11 | `Satelite/VinculoVasilhame.php:35, 114, 121` | Tabela `tipo_glp` 3/4/5 → P13/P20/P45 e termos VASILHA/CASCO/BOTIJÃO/CILINDRO | OK: é **sugestão** revisada por pessoa (`VinculosTab.tsx:14-17`) e o tipo explícito prevalece (`:100`) |
| 12 | `Relatorio/RelatorioService.php:688-699` | Corte de 60 dias em "clientes sem compra" justificado pelo giro do P13 | I: é parâmetro com padrão, mas o padrão não é configuração do tenant |
| 13 | `Logistica/Drivers/HaversineDriver.php:16` | 25 km/h fixos no ETA sem Google | I (moto urbana × caminhão rural) |
| 14 | `Monitora/Drivers/OverpassMalha.php:80` | Já corrigido: o User-Agent vem da plataforma | OK |
| 15 | `config/services.php:74-77` | Um único projeto Firebase para a plataforma | I/H: app white-label por revenda com Firebase próprio não validaria o token; um projeto com vários apps resolve |
| 16 | `DistribuidorService.php:28-30`, `CalculadoraTaxaEntrega`, alçadas | Pesos, taxas e tetos lidos da configuração da empresa | **OK**: é exemplo do que está certo |

Comentários e docblocks com "Guarapuava" ou "Dubena" (`GrafoViario.php:41`,
`NormalizadorTexto.php:340`, etc.) registram a origem da medida: OK.

---

## 5. Matriz de lacunas de mercado (disk-gás)

| Expectativa do dono de revenda | Situação | Onde / por quê | Fundamento |
|---|---|---|---|
| Controle de vasilhame/casco por cliente | **EXISTE** | Comodato + `VinculoVasilhame` + vigilância (`ComodatoVigiar`, `GerarAlertasComodato`) | Código |
| Comodato com contrato | **EXISTE** | `ComodatoPdfService`, `/comodatos` | Código |
| Gás do Povo | **EXISTE** | `GasDoPovoService`, `/gas-do-povo`, `gasdopovo.*` | Código |
| Vale-gás / convênio | **EXISTE** | `/vale-gas`, `/convenios` | Código |
| Pedido por WhatsApp | **NÃO EXISTE** | "whats" só aparece como flag de telefone (`ClienteTelefoneController`) | Expectativa: **HIPÓTESE A VALIDAR** |
| Bina → pedido | **EXISTE** (depende do PABX) | `TelefoniaService`, `PainelChamadas` | Código |
| Repetir último pedido (balcão e app) | **EXISTE** | `PedidoSugestaoService`; app `home/index.tsx:137-145` | Código |
| Recompra prevista por consumo médio | **NÃO EXISTE** | Só há "não compra há N dias" (`MalaDiretaController.php:39-53`, `RelatorioService::clientesSemCompra`); nenhum intervalo médio por cliente | Dado para isso existe (`data_ultima_compra`, histórico de pedidos) |
| Aviso "seu gás deve estar acabando" | **NÃO EXISTE** | O push existe (`PushService`, `FcmV1Transport`), mas nenhum agendamento envia recompra (`routes/console.php:17-63`) | Infra pronta; expectativa **H** |
| Fidelidade/pontos/cashback | **NÃO EXISTE** | Promoções e sorteios existem (CRM); pontos não | **H** |
| Roteirização | **EXISTE** (heurística) | `RoteirizadorService` nearest-neighbor, Google opcional | Código |
| Sugestão de entregador | **EXISTE** | `DistribuidorService` (sugerir/auto) | Código |
| Comissão de entregador | **EXISTE** | `relatorios/comissoes`, `colaboradores/{id}/comissoes`, `ExtratoRemuneracaoService`, app `ganhos.tsx` | Código. No Essencial o relatório dá 402 (P2-A-04) |
| Fechamento de caixa por entregador (malote) | **EXISTE** | `MaloteService`, aba Malote | Código |
| NFC-e na entrega | **PARCIAL** | Emissão por pedido na SPA (`POST pedidos/{id}/emitir-nfce`) e rota de campo só para `industrial`; o app do entregador não emite | Código |
| Conciliação de maquininha | **PARCIAL** | Registro manual de transação (NSU, taxa) em `/cartoes` (`PagamentoController.php:37-52`); não importa arquivo da adquirente | Código; a necessidade é **H** |
| Indicadores do dia | **EXISTE** | `PainelDoDiaService`, `ParaHoje` | Código |
| Avaliação da entrega pelo consumidor | **PARCIAL — grava e ninguém lê** | `PedidoMobileService::avaliar` (`:322-340`); `pedido_avaliacoes` sem leitor em `app/` ou `frontend/` | Código — P2-A-11 |
| Agendamento de entrega ("entregar amanhã às 10h") | **NÃO EXISTE** | Nenhum campo de data prevista em `Pedido` | **H** |
| Pedido recorrente para comércio | **NÃO EXISTE** | — | **H** |
| Preço por cliente/condição | **EXISTE** | `ClientePreco`, origem do preço na sugestão | Código |
| Alçada de desconto | **EXISTE** | `/alcadas` | Código |

---

## 6. Métricas e instrumentação de produto

| Pergunta | Resposta | Evidência |
|---|---|---|
| Eventos de uso (telas, funis) | **Não** | Nenhum SDK de analytics no backend, na SPA ou nos apps; `ErrorBoundary.tsx:31` registra "coletor externo quando houver" |
| Ativação da revenda (1º pedido, 1º app, 1ª NF) | **Não** | — |
| Retenção/churn por revenda | **Não** | — |
| NPS | **Parcial**: pós-venda manual (`PosVenda.nota`) e avaliação do app sem leitor | P2-A-11 |
| Custo por revenda | **Grava e ninguém lê**: `ConsumoDeIntegracao` registra chamadas Google, sem tela, endpoint ou comando que leia | `ConsumoDeIntegracao.php:1-20`; grep `integracao_consumos` |
| Uso das pontes legadas | `ponte:uso` (F9-07) | `PonteUso.php` |
| Saúde da revenda no SuperAdmin | **6 contadores** (empresas, ativas, assinaturas ativas/inadimplentes, planos, por plano) | `SuperAdminService.php:346-358`. Sob RLS, todos dão zero (P2-A-02). O trial vencido é contado como ativo (P2-A-05). |

---

## 7. Achados

### P2-A-01 — Não existe caminho de produto para criar uma revenda (tenant)
- **Categoria:** Produto / onboarding · **Severidade:** ALTA (o objetivo declarado do programa, N revendas, não se realiza sem engenheiro; não é risco de segurança) · **Estado:** C
- **Arquivo:linha:** `routes/api.php:1017-1066` (SuperAdmin sem `POST empresas`/tenant); `TenantMappingImporter.php:91-130` (único `TenantAccount::create` fora de teste e factory); `SaasImportarTenantMapping.php:12`; `config/saas_transformation.php:12-13`; `.env.example:173`; `EmpresaController.php:59-91`; `MigracaoService.php:33-37`
- **Rota/tela:** `/superadmin/empresas` (sem ação de criar, `SaEmpresasPage.tsx:54-59`)
- **Reprodução:** grep `TenantAccount::create` em `app/` → só no importador; listar rotas `superadmin/*`.
- **Esperado × observado:** o operador cria a conta, a empresa e o dono, e envia convite × JSON de evidências por console, com empresa e usuário preexistentes e o freeze ligado por padrão.
- **Impacto:** a revenda nº 2 depende de operação manual em produção. Erro de JSON vira vazamento ou órfão (o importador protege, mas não guia).
- **Correção proposta:** `TenantProvisioningService` (tenant + empresa + grupo + papéis padrão + plano de contas + catálogo inicial + assinatura trial + convite do dono, numa transação, como `pgsql_owner`), exposto em `POST /superadmin/revendas` e num assistente na SPA do SuperAdmin. O freeze passa a valer só para a criação **livre** pelo tenant.
- **Teste de aceite:** no Postgres, `POST /superadmin/revendas` → login do dono convidado → `GET /api/admin/produtos` devolve o catálogo inicial → pedido concretiza. Um segundo tenant não enxerga o primeiro.
- **Esforço:** G

### P2-A-02 — Painel SuperAdmin cego sob RLS (lista vazia, dashboard zerado, assinatura 404)
- **Categoria:** Funcionamento real / operação da plataforma · **Severidade:** ALTA (o operador não administra nem cobra nenhuma revenda pela UI; mesma classe dos 5 comandos de `CLAUDE.md`, mas na superfície HTTP) · **Estado:** FI (banco medido; o caminho HTTP foi lido, não executado no Postgres)
- **Arquivo:linha:** `SuperAdminService.php:41-66` (`Empresa::query()`), `:98-150` (`definirAssinatura`), `:346-358` (dashboard); `routes/api.php:1020` (`auth:platform`, sem `tenant`); nenhum uso de `ConexaoDeOwner`/`EnxergaAtravesDaRls` nesses arquivos
- **Reprodução (executada, read-only, Postgres local `erp_teste`):**
  ```
  begin read only; set local role erp_app;
  select count(*) from empresas;      -- 0   (como postgres: 141)
  select count(*) from assinaturas;   -- 0
  -- policy: grupo_id = current_setting('app.grupo_id')  (sem GUC => nada)
  ```
- **Esperado × observado:** a lista de revendas e os contadores × vazio e zero. `Empresa::findOrFail` em suspender, reativar ou definir assinatura → 404.
- **Evidência adicional:** `SuperAdminTest` passa em sqlite (sem RLS), por isso a suíte não acusa.
- **Impacto:** o PIE-04 (suspender empresa) só funciona por console. Toda a gestão comercial da §3 fica inacessível.
- **Correção proposta:** o `SuperAdminService` lê e escreve por conexão de plataforma (`pgsql_owner` ou uma role `erp_platform` com policy própria e auditada), nunca `erp_app` sem contexto. Adicionar um teste no portão `test:pgsql-rls`.
- **Teste de aceite:** no portão Postgres, `GET /superadmin/empresas` com 2 tenants semeados devolve 2; `PUT .../assinatura` devolve 200.
- **Esforço:** M · **Observação:** sobrepõe-se à Frente C (superadmin adversarial); registrado aqui porque bloqueia onboarding e monetização.

### P2-A-03 — Licença vaza: DRE, mala-direta, NFC-e por pedido e 4 recursos sem porta
- **Categoria:** Proteção de receita (6.2) · **Severidade:** MÉDIA (sem segundo cliente pagante hoje, a perda é potencial; vira ALTA no dia em que houver Essencial pagante) · **Estado:** C (execução)
- **Arquivo:linha:** `RecursoPorRota.php:38-56` (mapa); `routes/api.php:753` (`financeiro/dre` → `RelatorioController::dre`, o mesmo de `relatorios/dre`); `:711` (`crm/mala-direta`: o prefixo `mala-direta` do mapa não casa); `:411` (`pedidos/{id}/emitir-nfce`); `:937-943` (`app/v1/.../fiscal/emitir`); `frontend/src/features/financeiro/api.ts:147` (a aba DRE usa justamente `/financeiro/dre`)
- **Reprodução (executada):** uma sonda no scratchpad (`...\scratchpad\a\SondaLicencaFrenteATest.php`), empresa **sem assinatura**, `SAAS_ENFORCE_LICENCA=true`:
  ```
  GET  /api/admin/relatorios/dre                 => 402
  GET  /api/admin/financeiro/dre                 => 200   <- vaza
  GET  /api/admin/crm/mala-direta                => 200   <- vaza
  POST /api/admin/pedidos/999999/emitir-nfce     => 404   <- passou da licença, chegou ao controller
  GET  /api/admin/notas                          => 402
  ```
  Recursos `marketplace`, `app_consumidor`, `app_entregador`, `tempo_real`: grep em `app/` → nenhuma checagem. O teste `LicencaEnforcementTest.php:414` **fixa** que `api/app/v1/pedidos` fica fora do mapa.
- **Impacto:** o diferencial do Completo ("relatórios gerenciais", "marketplace", "tempo real") sai de graça. Quatro das dez linhas da grade comercial não decidem nada.
- **Correção proposta:** licença por **rota nomeada/controller** (atributo ou manifesto), não por prefixo. Um guardião no `api:manifest` reprova rota sem decisão explícita (núcleo ou recurso). Gate `app_consumidor`/`app_entregador` no login dos apps e `marketplace` em `MarketplaceService::empresasNoPonto`.
- **Teste de aceite:** um guardião percorre todas as rotas do manifesto e exige classificação. Uma regressão plantada (rota nova de DRE) reprova.
- **Esforço:** M

### P2-A-04 — Empacotamento corta o básico do Essencial e o menu promete o que a licença nega
- **Categoria:** Produto / empacotamento · **Severidade:** MÉDIA · **Estado:** C (execução + leitura)
- **Arquivo:linha:** `RecursoPorRota.php:55` (`'relatorios/' => 'relatorios_avancados'`: cobre vendas, fechamentos de caixa, comissões, movimentação de caixa, estoque baixo, aniversariantes, vale-gás, comodatos, `api.php:737-751`); `PlanosSeeder.php:36-41` (Essencial sem `relatorios_avancados` e sem `crm`); `AppShell.tsx:84, 87, 91` (Pós-venda, Metas e Checklists **sem** `feature: 'crm'`, enquanto o backend os barra como `crm`, `RecursoPorRota.php:40-45`)
- **Reprodução (executada):** a sonda acima → `relatorios/vendas` 402, `relatorios/fechamentos-caixa` 402, `pos-vendas`/`metas`/`checklists` 402.
- **Esperado × observado:** uma revenda no Essencial vê vendas do período, fechamento de caixa e comissão do entregador × 402. O menu mostra Pós-venda, Metas e Checklists e o clique devolve erro, exatamente o que `rbac.ts:22` e `AppShell.tsx:34` dizem querer evitar.
- **Correção proposta:** separar relatórios operacionais (núcleo) de gerenciais (DRE, SPED, análises); alinhar o `feature` do menu com o mapa do backend por um teste que compare os dois.
- **Teste de aceite:** um teste de contrato menu × `RecursoPorRota` para cada item com `permission`.
- **Esforço:** P

### P2-A-05 — Não há faturamento da plataforma nem ciclo de vida de assinatura
- **Categoria:** Monetização · **Severidade:** ALTA (sem isso não há receita recorrente operável; cada cobrança é manual e a suspensão é decisão humana sem trilha financeira) · **Estado:** C
- **Arquivo:linha:** `SuperAdminService.php:152` (troca manual de status); `Assinatura.php:58-70` (`vigente()` considera `trial_ate`/`fim`); `SuperAdminService.php:349-350` (dashboard conta `trial`/`ativa` **por status**, então trial vencido aparece como ativo); `routes/console.php:17-63` (nenhum agendamento de assinatura); grep `fatura|asaas|iugu|stripe|vindi` sem ocorrência de faturamento da plataforma; nenhuma tela de plano na SPA do tenant
- **Impacto:** sem trial que vence, sem aviso, sem carência, sem fatura. Inadimplente perde NFC-e e cobrança **no mesmo instante** (`Assinatura.php:62-64`): uma pendência comercial vira parada fiscal da revenda.
- **Correção proposta:** (1) máquina de estados trial → ativa → em atraso (carência configurável, só aviso) → suspensa (somente leitura do núcleo, nunca bloqueio fiscal de nota já iniciada) → cancelada; (2) job diário de transição; (3) fatura mensal da plataforma reaproveitando o domínio `Cobranca` com a conta da **plataforma**; (4) página "Meu plano" com 402 explicativo e pedido de upgrade.
- **Teste de aceite:** trial com `trial_ate` ontem → o job o move e o dashboard não o conta como ativo; inadimplente dentro da carência ainda emite NFC-e.
- **Esforço:** G

### P2-A-06 — Assinatura por empresa (não por tenant): filial nova nasce sem módulos
- **Categoria:** Modelo SaaS · **Severidade:** MÉDIA · **Estado:** C (estático)
- **Arquivo:linha:** `Assinatura.php:31` (`empresa_id`); `LicencaService.php:178-193`; `EmpresaController.php:59-91` (cria a empresa, não cria assinatura); `PLANO_TRANSFORMACAO_SAAS.md:128-132` (D2: "a assinatura pertence ao tenant")
- **Reprodução:** com a licença ligada, uma revenda cria a 2ª unidade → `recursoHabilitado('nfce', novaEmpresa)` é falso → 402 em `/notas` e `/boletos` nessa unidade.
- **Impacto:** a rede paga por unidade sem que isso seja decisão comercial explícita. O limite `empresas` é contado por tenant (`LimiteContratado.php:58-59`) e o plano por empresa: duas réguas.
- **Correção proposta:** assinatura no `tenant_account_id`, com add-ons por empresa quando o custo for local (como D2 prevê).
- **Teste de aceite:** criar filial em tenant com plano Essencial → a filial herda `nfce`.
- **Esforço:** M

### P2-A-07 — Revenda nova nasce vazia: sem catálogo inicial, sem assistente, sem checklist
- **Categoria:** Onboarding · **Severidade:** MÉDIA · **Estado:** C
- **Arquivo:linha:** `CadastroSlugs.php:28-63` (cerca de 35 cadastros de apoio); `DemoGuarapuavaSeeder.php:259-274, 474-500` (o catálogo GLP/água e as situações com `PapelSituacao::EM_ROTA` só existem no demo); `EstoqueService.php:107-110`; grep `onboarding|primeiros passos|wizard` na SPA sem resultado; a única prontidão é a fiscal (`ProntidaoFiscalService`)
- **Impacto:** o dono descobre a ordem errando. Exemplos: sem situação `EM_ROTA` o app do entregador falha ao iniciar a rota; sem entrada de estoque o pedido não concretiza.
- **Correção proposta:** extrair do demo um `CatalogoInicialRevenda` (situações com papel, operações Venda/Disk, condições, setores Depósito/Loja/Veículo, classes GLP/Água/Acessórios, produtos P13/P20/P45/água com preço **zerado** a preencher, tipos de pessoa e de telefone) aplicado pelo provisionamento (P2-A-01), mais um checklist "pronto para vender" no `/` com fiscal, preço, estoque, entregador, área de entrega e app.
- **Teste de aceite:** revenda provisionada → checklist mostra 6 pendências → preenchidas → 1º pedido concretiza sem mexer em `/configuracoes`.
- **Esforço:** M

### P2-A-08 — Marca "Dubena" no login do painel de todas as revendas (correção F9-06 incompleta)
- **Categoria:** White-label · **Severidade:** MÉDIA (bloqueia a venda: o funcionário da revenda B entra numa tela com a marca da concorrente) · **Estado:** C
- **Arquivo:linha:** `frontend/src/features/auth/LoginPage.tsx:78-80`; `superadmin/SaLayout.tsx:80`; `superadmin/SaLoginPage.tsx:69`. O F9-06 está registrado como "fechada" (`F9_PROGRESSO.md:15, 41-43`), mas só o `index.html`/título foi tratado.
- **Correção proposta:** marca da **plataforma** (`VITE_APP_NAME`/logo) no login; marca da **revenda** após o login (já há precedente em `AppShell.tsx:168`).
- **Teste de aceite:** um teste de componente renderiza o `LoginPage` com `VITE_APP_NAME=X` e não encontra "Dubena"; um guardião grep proíbe "Dubena" em `frontend/src` fora de `*.test.*` e comentários.
- **Esforço:** P

### P2-A-09 — Modelo de distribuição dos apps indefinido: marketplace com identidade, termos e controlador LGPD da Dubena
- **Categoria:** Produto / LGPD / white-label · **Severidade:** ALTA (decisão de produto que define lojas, contas Firebase/Apple/Google e quem é o controlador dos dados do consumidor) · **Estado:** C (fatos); a decisão está pendente
- **Arquivo:linha:** `selecionar-revenda.tsx:22-30` (marketplace) **e** `app.config.ts:23-25` (white-label por `EMPRESA_ID`); `app.json:3-13, 76` (pacote `br.com.dubena.*`); `user.service.ts:60-62` (termos de `gasemcasa.com.br`); `config/paginas_publicas.php:7-23` (um controlador por implantação, embora o comentário diga "cada revenda que publicar o seu app tem o seu controlador"); `MarketplaceService.php:53-54` + `Empresa.php:41` (`app_marketplace_ativo` sem tela nem request: a adesão ao marketplace não é operável); licença `marketplace` sem porta (P2-A-03)
- **Impacto:** no modo marketplace, o consumidor aceita termos de **uma** empresa e compra de **outra**. Não há termo por revenda nem consentimento ao trocar de revenda. No modo white-label, cada revenda exige rebuild com nome, pacote, ícone e Firebase alterados à mão.
- **Correção proposta:** decidir (A) marketplace da plataforma, com marca da plataforma, a plataforma como operadora/controladora conjunta e termos da revenda exibidos na escolha, ou (B) white-label por revenda, com pipeline EAS parametrizado (nome, pacote, ícone, `EMPRESA_ID`, Firebase) e páginas públicas por empresa. Em ambos: termos servidos pelo ERP por empresa, adesão ao marketplace como campo do painel e com licença.
- **Teste de aceite:** (A) `GET /publico/termos?empresa=X` devolve o controlador X e o app mostra os termos da revenda escolhida; (B) build com `APP_SLUG=revendaB` gera pacote e nome sem "dubena".
- **Esforço:** M (decisão e termos por empresa) / G (pipeline white-label)

### P2-A-10 — Fuso único da plataforma e mapas presos em Guarapuava
- **Categoria:** Convenção vazada · **Severidade:** MÉDIA (para revenda fora do horário de Brasília, "hoje", caixa, venda diária e comissão ficam deslocados de 1 a 2 h: a mesma classe do PIE-32) · **Estado:** C (estático)
- **Arquivo:linha:** `config/app.php:79`; nenhum campo de fuso em `empresas`/`empresa_configs` (grep `fuso|timezone`); `MapaAoVivoTab.tsx:13`, `RotaTab.tsx:15` (centro fixo, sem cair para a empresa como a `CercasTab.tsx:175-185` já faz)
- **Correção proposta:** `empresas.fuso` (IANA), aplicado ao envelope do tenant (`date_default_timezone_set` + `SET TIME ZONE` na sessão) e exposto no `/me` para a SPA; mapas caem para as coordenadas da empresa.
- **Teste de aceite:** empresa com `America/Manaus`, pedido às 23h30 local → `PainelDoDiaService` o conta no dia local.
- **Esforço:** M

### P2-A-11 — Avaliação do consumidor e custo de integração gravados e nunca lidos
- **Categoria:** Produto / dado sem uso · **Severidade:** MÉDIA (a voz do cliente final, o sinal mais barato de churn, morre no banco; dado pessoal sem finalidade também contraria a minimização da LGPD) · **Estado:** C
- **Arquivo:linha:** `PedidoMobileService.php:322-340` (`PedidoAvaliacao::create`); grep `PedidoAvaliacao|pedido_avaliacoes` em `app/` e `frontend/src` → só o model e o gravador; `ConsumoDeIntegracao.php:1-20` (grava; nenhum leitor)
- **Correção proposta:** avaliações ruins (≤ 3) viram pendência no `/alertas` e entram no `PosVenda`; nota média por entregador na Central e no painel do dia; consumo de integração por empresa no SuperAdmin.
- **Teste de aceite:** avaliação 2★ pelo app → aparece em `/alertas` do tenant; não aparece para outro tenant.
- **Esforço:** P

### P2-A-12 — Nenhuma instrumentação de produto; o SuperAdmin não vê a saúde das revendas
- **Categoria:** Métricas · **Severidade:** MÉDIA · **Estado:** C
- **Arquivo:linha:** `SuperAdminService.php:346-358`; nenhum SDK de analytics (grep em `package.json` dos três frontends e no `composer.json`); `ErrorBoundary.tsx:31`
- **Correção proposta:** tabela `metricas_diarias_tenant` (job noturno como owner: pedidos, pedidos pelo app, NF emitidas, usuários ativos, último login, avaliações, erros 5xx) e uma tela "Saúde" no SuperAdmin com semáforo (sem pedido há 3 dias, sem login há 7, trial vencendo). Sem SDK de terceiro com PII.
- **Teste de aceite:** um tenant semeado sem pedido há 4 dias aparece em vermelho.
- **Esforço:** M

### P2-A-13 — Novo pedido sem cadastro rápido de cliente
- **Categoria:** UX / jornada principal · **Severidade:** MÉDIA (é a ligação de cliente novo, justamente a que traz receita nova) · **Estado:** C (estático)
- **Arquivo:linha:** `NovoPedidoDialog.tsx:164` (só `AsyncSelect`); grep "novo cliente" no diálogo sem resultado
- **Correção proposta:** "Cadastrar '<texto digitado>'" no fim do lookup, com nome, telefone (vindo da bina) e endereço, criando por `POST /clientes` sem sair do diálogo.
- **Teste de aceite:** vitest: digitar um telefone inexistente → criar → o diálogo segue com o cliente selecionado e os itens preservados.
- **Esforço:** P

### P2-A-14 — Papéis padrão não espelham as personas da revenda
- **Categoria:** Produto / RBAC · **Severidade:** BAIXA · **Estado:** C
- **Arquivo:linha:** `RbacSeeder.php:39-63` (Administrador, Gerente, Operador, Entregador; o Operador acumula atendimento, caixa, estoque e financeiro)
- **Correção proposta:** modelos Atendente, Caixa e Logística, criados pelo provisionamento (P2-A-01) e editáveis em `/acessos`.
- **Esforço:** P

---

## 8. Oportunidades (ELIMINAR → JUNTAR → AUTOMATIZAR → ANTECIPAR → CRIAR)

| # | Tipo | Oportunidade | Impacto | Esforço | Risco | Dependência | Base |
|---|---|---|---|---|---|---|---|
| O1 | ELIMINAR | Eliminar o JSON de evidências e o console no onboarding: provisionamento de revenda em uma tela (P2-A-01) | **Muito alto** (destrava a revenda nº 2) | G | Médio (toca o kernel de tenant: fazer como owner, com teste Postgres) | P2-A-02 | Fundamentada |
| O2 | ELIMINAR | Eliminar a ida a `/clientes/novo` no meio do pedido (P2-A-13) | Alto (jornada nº 1) | P | Baixo | — | Fundamentada |
| O3 | ELIMINAR | Eliminar a marca Dubena do painel e das páginas públicas (P2-A-08) | Alto comercial | P | Baixo | — | Fundamentada |
| O4 | JUNTAR | Juntar licença e menu numa única fonte (o manifesto classifica rota → núcleo/recurso; o menu deriva dele) — fecha P2-A-03 e P2-A-04 juntos | Alto (receita + UX) | M | Baixo | — | Fundamentada |
| O5 | JUNTAR | Avaliação do app + pós-venda + alertas numa fila única de "cliente insatisfeito" (P2-A-11) | Médio | P | Baixo | — | Fundamentada |
| O6 | JUNTAR | Assinatura no tenant com add-on por empresa (P2-A-06) | Médio | M | Médio (migração de dado de assinatura) | — | Fundamentada |
| O7 | AUTOMATIZAR | Ciclo de vida da assinatura (trial → carência → suspensão só-leitura) com job diário (P2-A-05) | Alto | M | Médio (não pode parar NF de quem paga atrasado) | Decisão do dono sobre carência | Fundamentada |
| O8 | AUTOMATIZAR | Catálogo inicial de revenda GLP e checklist "pronto para vender" (P2-A-07) | Alto (time-to-first-order) | M | Baixo | O1 | Fundamentada |
| O9 | AUTOMATIZAR | Fatura da plataforma reaproveitando `Cobranca` (boleto/PIX) com conta da plataforma | Alto | G | Médio (dinheiro: fail-closed, idempotência) | O7 | Fundamentada (ausência) |
| O10 | ANTECIPAR | Previsão de recompra por cliente (intervalo médio entre pedidos) + push "seu gás deve estar acabando" pelo `PushService` existente + lista "ligar hoje" para o atendente | **Alto, se confirmado** | M | Baixo (opt-in, LGPD: base legal de marketing) | Firebase real (PIE-22) | **Hipótese a validar com revendas** (o dado existe: `data_ultima_compra`, histórico) |
| O11 | ANTECIPAR | Semáforo de saúde por revenda no SuperAdmin (P2-A-12) | Alto (churn da plataforma) | M | Baixo | P2-A-02 | Fundamentada |
| O12 | ANTECIPAR | Fuso por revenda antes do primeiro cliente fora de Brasília (P2-A-10) | Médio | M | Médio (datas) | — | Fundamentada |
| O13 | CRIAR | Decisão e implementação do modelo de app (marketplace × white-label) com termos por revenda (P2-A-09) | Alto | M/G | Alto (lojas, LGPD) | Decisão do dono | Fundamentada |
| O14 | CRIAR | Pedido por WhatsApp (link com carrinho pré-montado ou bot) | Alto, se confirmado | G | Médio (custo da API Meta) | — | **Hipótese a validar** |
| O15 | CRIAR | NFC-e emitida pelo entregador na conclusão (o backend já tem `fiscal/emitir`) | Médio | M | Alto (fiscal; PIE-02) | PIE-02, certificado | Hipótese (depende da UF e da prática da revenda) |
| O16 | CRIAR | Importação de extrato da adquirente para conciliar cartão | Médio | M | Baixo | — | Hipótese a validar |
| O17 | CRIAR | Fidelidade (a cada N botijões, 1 desconto) sobre Promoções | Médio | M | Baixo | — | Hipótese a validar |

---

## 9. Fechamento

**Inspecionado (leitura direcionada):** `routes/api.php` (SuperAdmin, licença, relatórios, fiscal, app); `app/Domain/Saas/*`; `Models/Saas/Assinatura`, `Plano`, `TenantAccount`; `Domain/Tenant/TenantMappingImporter`; `RecursoPorRota`, `Recurso`; `SuperAdminService`; `Api/SuperAdmin/*`; `Admin/EmpresaController::store`; `MigracaoController`, `MigracaoService`; `config/saas_transformation.php`, `app.php`, `services.php`, `paginas_publicas.php`; seeders `Planos`, `Rbac`, `DeployAdmin`, `Deploy`, `DemoGuarapuava`, `Homolog`; `PedidoSugestaoService`, `RoteirizadorService`, `DistribuidorService`, `MaloteService`, `TelefoniaService`, `PainelDoDiaService`, `VinculoVasilhame`, `RelatorioService::clientesSemCompra`, `MalaDiretaController`, `PagamentoController` (cartões), `PedidoMobileService::avaliar`, `ConsumoDeIntegracao`, `EstoqueService`; SPA `routes.tsx`, `AppShell.tsx`, `LoginPage`, `NovoPedidoDialog`, `PainelChamadas`, `CentralPage`, `FinanceiroPage`, `superadmin/*`, `satelites/*Tab`; apps `app.json`, `app.config.ts`, `constants/app.ts`, `selecionar-revenda.tsx`, fluxo de navegação, `user.service.ts`, `EvaluateModal`; docs `PLANO_TRANSFORMACAO_SAAS.md` (§1, 3, 4, F10, 9, 10), `F9_PROGRESSO.md`, `ESTADO_DAS_106_TAREFAS.md`, `APPS_DE_CAMPO_E_CENTRAL_DE_VENDAS.md` §1.

**Executado:**
- `php artisan test` em 5 arquivos (SuperAdmin, Licenciamento, LicencaEnforcement, AusenciaDeAssinatura, TenantMappingImporter): **50 passed, 138 assertions**.
- Sonda de licença (arquivo **fora** do repositório, no scratchpad `a\`) via `vendor/bin/phpunit`: resultados em P2-A-03 e P2-A-04.
- Postgres local `erp_teste`, transação **read only** com `SET LOCAL ROLE erp_app`: `empresas` 0 de 141 sem contexto. Policy lida em `pg_policies`.

**Não testado:**
- Nenhum clique real em navegador nem aparelho (as contagens de passos da §1 vêm do código).
- O SuperAdmin HTTP contra o Postgres (a P2-A-02 fica em FI).
- A homologação na VPS (não acessada).
- Os apps não foram executados (emulador e Gradle proibidos).
- Não houve conversa com revendas: tudo marcado H na §5 e na §8 é expectativa de mercado sem evidência.

**Limitações:** o banco `erp_teste` não tem `assinaturas` nem `planos` semeados, então o zero dessas tabelas sob `erp_app` não distingue "RLS" de "vazio". A prova de cegueira vale para `empresas` (141 → 0), que é a lista principal do SuperAdmin.

**Nenhum arquivo do projeto foi alterado.** Só este relatório foi escrito. A sonda ficou no scratchpad.

### Parecer da Frente A: **REPROVADO**

Como **produto para N revendas**, o sistema não está pronto para vender à
revenda nº 2: não há como criá-la pelo produto (P2-A-01), o operador da
plataforma não a enxerga (P2-A-02), não há como cobrá-la (P2-A-05) e app e marca
ainda são da Dubena (P2-A-08, P2-A-09). Isso **não** contradiz a qualidade do
ERP para a operação de uma revenda, que é ampla e, em comodato e logística,
acima do esperado para o segmento. O próprio plano já trata esse gate como
aberto (F10, `PLANO_TRANSFORMACAO_SAAS.md:165, 545`). O caminho mais curto até
um "APROVADO" é O1 + P2-A-02 + O4 + O7 + O3, e a decisão do modelo de app (O13).
