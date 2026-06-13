# Análise Técnica Completa — Aplicação "ctrl-web"

> **Documento gerado por auditoria de código-fonte.**
> Base da análise: **exclusivamente o código-fonte real** encontrado no workspace `ctrl-web`.
> Documentação auxiliar (`AGENTS.md`, `readme.md`, `instruções de implantação.md`) foi **ignorada como fonte de verdade** e usada apenas como pista a ser confirmada no código.
> Data da análise: 2026-06-12.
> Cada conclusão referencia o arquivo/linha que a fundamenta. Itens sem evidência conclusiva estão marcados como **[Necessita Validação]**.

---

## 1. Visão Geral da Aplicação

### Objetivo principal
O `ctrl-web` é um **ERP (sistema de gestão empresarial) web de grande porte voltado ao setor de distribuição de GLP/gás e combustíveis**, com forte componente **fiscal brasileiro (NF-e, NFC-e, SAT-CFe, SPED Fiscal e SPED Contribuições)**, **financeiro/bancário (boletos, remessas, PIX, conciliação)** e **logístico (pedidos, entregas, rastreamento de veículos)**.

A natureza do negócio é evidenciada pelos domínios presentes no código: `Valegas`, `Comodato` (vasilhames/botijões em comodato), `Tipocombustivel`, `Veiculoabastecimento`, `Convenio`, rastreamento de frota (`LogcercaController`, `CheckVehiclePosition`).

### Principais funcionalidades identificadas (por evidência de código)
| Funcionalidade | Evidência |
| --- | --- |
| Gestão de clientes, convênios e comodato | `app/Http/Controllers/ClienteController.php`, `ComodatoController.php`, `ComodatogestaoController.php` |
| Pedidos e entregas | `PedidoController.php` (1.661 linhas), `PedidoRepository.php` |
| Emissão fiscal NF-e / NFC-e | `NfemitidaController.php` (2.213 linhas), `app/Processors/Nfe/` |
| SAT-CFe / Cupom Fiscal | `CupomFiscalController.php`, `SatcfeController` |
| SPED Fiscal e Contribuições | `app/Processors/Sped/` (Blocos 0,1,9,A,C,D,E,F,G,H,K,M) |
| Boletos e remessas bancárias (Itaú, Caixa) | `BoletoController.php`, `Services/BoletoItauService.php`, `RemessaCaixaService.php` |
| Pagamentos PIX (API Itaú) | `PixController.php`, `Services/PixService.php` |
| Conciliação bancária / OFX | `ConciliacaoController.php`, `ImportextratoController.php`, dependência `beccha/ofxparser` |
| Financeiro (contas, cheques, fechamentos) | `FinanceiroController.php`, `ChequeemitidoController.php`, `FechamentomensalgestaoController.php` |
| Estoque (físico, setor, transferências, inventário) | `EstoquefisicoController.php`, `EstoqueTransferenciasController.php`, `InventarioController.php` |
| RH (colaboradores, cargos, comissões, exames) | `ColaboradorController.php`, `ColaboradorcomissoesController.php` |
| Aplicativo mobile + notificações push | `app/Http/Controllers/App/AppController.php`, `MobileAppProcessor.php`, `Android*` |
| Rastreamento de frota (banco externo) | `MobileRepository.php` (conexão `monitora`), `CheckVehiclePosition.php` |
| Relatórios e exportações (PDF/Excel) | ~30 `Report*Controller.php`, `mpdf`, `dompdf`, `phpspreadsheet`, `maatwebsite/excel` |
| Dashboard gerencial / BI | `DashboardgerencialController.php`, integração `Pentaho/` |

### Fluxo geral de funcionamento
1. Login (`AuthController@handleLogin`) → carrega na **sessão** empresa padrão, configurações, menu, permissões e empresas permitidas.
2. Usuário opera dentro do contexto de uma **empresa** (multiempresa por grupo — `grupo_id`/`empresa_id` permeiam quase todos os models).
3. Pedidos geram **movimentação de estoque e financeiro** condicionalmente (`PedidoController::setMovimentaEstoqueFinanceiro`), podem **gerar/emitir NF-e/NFC-e**, **boletos** e **cobrança PIX**.
4. Documentos fiscais são processados pela camada `app/Processors/` e transmitidos ao SEFAZ.
5. App mobile e rastreamento consomem a **API Passport** (`routes/api.php`) e bancos MySQL externos.

### Público usuário
- **Interno**: operadores administrativos, financeiro, fiscal, estoque, RH e gestores (back-office ERP via Blade).
- **Campo/Mobile**: entregadores/motoristas (app Android — endpoints `getPedidosPendentes`, `setPedidoSituacao`, rastreamento).
- **Cliente final**: via aplicativo "Gás em Casa" (endpoints `App\AppController`, PIX, pedidos).

### Módulos existentes (104 `Route::resource` + rotas avulsas em `routes/web.php`)
Cadastros, Clientes/Convênios, Colaboradores/RH, Pedidos, Estoque, Financeiro, Fiscal (NF/SPED/SAT), Boletos/Remessas/PIX, Comodato, Veículos/Frota, Relatórios, Dashboard, App Mobile, Configurações/Permissões.

---

## 2. Stack Tecnológica

> Versões **efetivamente instaladas** lidas de `composer.lock`; constraints de `composer.json`.

| Tecnologia | Versão | Utilização | Status |
| --- | --- | --- | --- |
| PHP | `>=7.4` (constraint); runtime documentado em `instruções de implantação.md`: 7.1.14 | Linguagem backend | ⚠️ **Obsoleto** — PHP 7.4 em EOL desde nov/2022; sem suporte de segurança |
| Laravel Framework | **5.4.36** (`composer.lock`) | Framework MVC principal | 🔴 **Crítico** — Laravel 5.4 EOL desde 2017; sem patches de segurança há ~8 anos |
| Oracle Database | 11.2.0 XE (ref. `oraclevar.php`) | Banco principal | ⚠️ Oracle 11g em EOL; via `yajra/laravel-oci8 5.4.21` |
| MySQL | — | Bancos secundários: `monitora` (frota), `sgcm_api` (app) | Ativo (`config/database.php`) |
| yajra/laravel-oci8 | 5.4.21 | Driver Oracle p/ Eloquent | Acoplado ao Laravel 5.4 |
| doctrine/dbal | ~2.4.2 | Alterações de schema/migrations | Antigo |
| Laravel Passport | ^3.0 | OAuth2 / API tokens (mobile) | ⚠️ Antigo (Passport 3.x) |
| lcobucci/jwt | 3.3.3 | JWT (dependência Passport) | Antigo |
| guzzlehttp/guzzle | 6.5.8 | HTTP client (APIs Itaú/PIX/SEFAZ) | ⚠️ Guzzle 6 (atual é 7.x) |
| laravelcollective/html | ^5.2 | Geração de formulários Blade | ⚠️ Abandonado upstream |
| Blade | (Laravel 5.4) | Templates de view (518 arquivos `.blade.php`) | Legado |
| Laravel Elixir + Gulp | gulp ^3.9.1, laravel-elixir ^5.0 | Build de assets (SASS) | 🔴 **Obsoleto** — Elixir descontinuado (~2017); Gulp 3 sem suporte |
| jQuery + plugins | (mask, maskMoney) | Frontend JS (115 arquivos em `public/js`) | Legado, sem SPA |
| nfephp-org/sped-nfe | 5.2.1 | Emissão/processamento NF-e | Específico fiscal BR |
| nfephp-org/sped-da | ^0.1 | DANFE | Versão pré-1.0 |
| eduardokum/laravel-boleto | ^0.9.4 | Geração de boletos | Pré-1.0 |
| developersrede/erede-php | 4.2 | Pagamento cartão (e.Rede) | Integração externa |
| mpdf/mpdf | ^8.0 | Geração PDF | OK |
| barryvdh/laravel-dompdf | ^0.8.0 | Geração PDF (2º motor) | Redundância de libs PDF |
| maatwebsite/excel | ~2.1.0 | Import/export Excel | ⚠️ Versão 2.1 antiga |
| phpoffice/phpspreadsheet | ^1.11 | Planilhas (3º motor de Excel) | OK |
| phpoffice/phppresentation | 1.2.0 | Apresentações | Uso pontual |
| intervention/image | ^2.3 | Processamento de imagens | OK |
| milon/barcode + tecnickcom/tc-lib-barcode | 5.2 / 1.15 | Códigos de barras (2 libs) | Redundância |
| cboden/ratchet + ratchet/pawl | ^0.4.1 / ^0.3.4 | WebSockets | Uso a confirmar |
| venturecraft/revisionable | 1.* | Auditoria/revisão de registros | Customizado via trait própria |
| Pentaho | — | BI/relatórios (`Pentaho/public.zip`) | Dependência externa **[Necessita Validação]** |

### Tecnologias obsoletas / risco (resumo)
- **Laravel 5.4 + PHP 7.4**: combinação sem suporte de segurança. Caminho de atualização exige saltar várias versões majors.
- **Laravel Elixir/Gulp 3**: pipeline de build morto.
- **laravelcollective/html**, **maatwebsite/excel 2.1**, **Passport 3** e bibliotecas fiscais pré-1.0: travadas a versões antigas.

### Observações de configuração
- `composer-setup.php` (99 KB) está **versionado no repositório** — instalador temporário do Composer não deveria estar no controle de versão.
- `vendor_fork/` contém **bibliotecas forkadas/zipadas** (`PHPExcel-1.8.zip`, `phpoffice`, `Util.php`) — código de terceiros modificado e mantido manualmente.

---

## 3. Arquitetura da Aplicação

### Padrão arquitetural
- **Monólito Laravel MVC** clássico (Models em `app/`, Controllers em `app/Http/Controllers/`, Views Blade em `resources/views/`).
- Há **tentativa parcial** de camadas adicionais: `app/Repository/` (7 repositórios), `app/Services/` (9 serviços), `app/Processors/` (camada de processamento fiscal/financeiro, 144 arquivos / ~24.000 linhas).
- **Padronização inconsistente**: 203 models, mas apenas 7 repositórios; a maior parte da lógica de negócio vive **dentro dos controllers** (vários com 1.000–2.200 linhas — ex.: `NfemitidaController.php` 2.213, `SearchController.php` 2.097, `PedidoController.php` 1.661).

### Monólito ou microsserviços
**Monólito.** Não há separação em serviços independentes. Existe acoplamento a **3 bancos** distintos no mesmo app:
- `oracle` (principal — ERP)
- `monitora` (MySQL — rastreamento de frota; `MobileRepository.php:48,684`)
- `sgcm_api` (MySQL — app mobile; `app/Cupom.php:14` via `protected $connection = 'sgcm_api'`)

### Camadas existentes
```
Rotas (web.php 1.107 linhas / api.php)
   │
   ▼
Middleware (auth, pode=AuthorizeCustom, web, throttle)
   │
   ▼
Controllers (161) ── frequentemente "gordos", com regra de negócio
   │
   ├─► Processors (Nfe, Sped, caixa, financeiro, estoque, MobileApp)
   ├─► Services (Boleto, Remessa, Pix, Revisions, Carbon)
   ├─► Repository (Cliente, Pedido, Mobile, Fechamento, Select)
   │
   ▼
Models Eloquent (203) ──► Oracle / MySQL(monitora) / MySQL(sgcm_api)
   │
   ▼
Views Blade (518) + assets jQuery/Gulp
```

### Acoplamentos excessivos
- **Controllers ↔ Sessão**: regras de autorização e contexto de empresa dependem fortemente de `Session::get('permissoes')`, `Session::get('empresa_padrao')` (ex.: `AuthorizeCustom::getPermissoes`, `AuthController@handleLogin`). Isso dificulta testes, uso stateless e escala horizontal.
- **`customHelper.php`** (1.974 linhas, ~60 funções globais) é um **God-helper**: formatação de moeda/data Oracle, cripto, e-mail, GPS, revisões, zip — acoplado por todo o sistema via autoload `files`.
- **Dois mecanismos de autorização paralelos**: Policies (`app/Policies/`, 98 arquivos) **e** middleware `AuthorizeCustom` (`app/Http/Middleware/AuthorizeCustom.php`) reimplementam a mesma lógica de permissão (`visualizar/criar/editar/deletar`) — **regra de autorização duplicada**.

### Dependências circulares
Não foi detectada dependência circular formal de namespaces, mas há **forte interdependência implícita** via helpers globais e sessão compartilhada **[Necessita Validação por ferramenta estática]**.

### Fluxo de requisições
`Request → middleware web (cookies/sessão/CSRF) → middleware auth → middleware pode (AuthorizeCustom) → Controller → Processor/Service/Repository → Eloquent → DB`. A API usa `auth:api` (Passport) + `throttle`.

### Fluxo de dados
Dados de formulário chegam predominantemente via **`$_GET`/`$_POST` diretos (199 ocorrências)** em vez de `Request` validado — ver Seção 7. Conversões de moeda/data para o formato Oracle são feitas manualmente em `customHelper.php` antes de persistir.

---

## 4. Modelagem de Dados

> Mapeada a partir de **621 migrations** (`database/migrations/`, 2013→2026) e **203 models** (`app/*.php`).

### Volume
- **211** migrations de criação de tabela (`create_*`) → ordem de **~200 tabelas**.
- **402** migrations de alteração (`alter_*`) → forte evolução incremental ao longo de 13 anos de migrations versionadas (e código anterior em SVN — ver `.gitignore` `.svn/`).
- Migrations recentes (dez/2025) adicionam campos **IBS/CBS** (`alter_nfimpostos_add_ibscbs_fields`) — sistema **ativo e em manutenção fiscal** (Reforma Tributária).

### Relacionamentos (Eloquent)
Confirmados via models. Exemplo `Cliente` (`app/Cliente.php`):
- `belongsTo`: Empresa, EmpresasGrupo, Bairro, Cidade, Rua, Estado (rguf/uf), Tipopessoa, Setor, Segmento.
- `hasMany`: Clientetelefone, Clientecontato, Clienteconveniodependente, Clienteproduto, Clientepromocao, Pedido.
- `hasOne`: Clienteconvenio. `belongsToMany`: Condicaopagamento.
- Entidades centrais: **Empresa/EmpresasGrupo** (multiempresa), **Cliente**, **Pedido/Pedidoitem**, **Nfemitida/Nfemitidaitem**, **Financeiro/Financeiroparcela**, **Conta**, **Estoque\***, **Comodato\***, **Valegas\***.

### Chaves primárias
Padrão Eloquent `id` (sequences Oracle). `config/database.php` usa `'fetch' => PDO::FETCH_CLASS`.

### Chaves estrangeiras, índices
- FKs/índices são definidos nas migrations (`->foreign(...)`, `->index(...)`) **[Necessita Validação de cobertura completa]** — dado o histórico de `alter_` e SQL manual, há risco de FKs/índices ausentes em tabelas antigas.

### Procedures / Triggers / Views / Sequences
- **Sequences Oracle**: gerenciadas pelo driver `oci8` e por seeder `AlterSequencesSeeder.php`.
- **Triggers/Procedures/Views no banco**: praticamente **não versionados** nas migrations (busca por `TRIGGER`/`sequence` retornou ~1 ocorrência). Forte indício de que **objetos de banco (triggers, procedures, views, synonyms) vivem direto no Oracle, fora do controle de versão** — risco de divergência ambiente↔código. **[Necessita Validação no banco de produção]**

### Problemas de modelagem identificados
| Problema | Evidência / Risco |
| --- | --- |
| **Schema fora do VCS** | Triggers/procedures/views Oracle não estão em migrations |
| **Múltiplos bancos heterogêneos** | Oracle + 2× MySQL, sem camada de integração formal — joins cruzados feitos em SQL bruto (`CheckVehiclePosition.php`) |
| **Normalização inconsistente** | Campos duplicados de endereço no `Cliente` (`endereco`/`numero` **e** `endereco_app`/`latitude_app`/`longitude_app`/`nome_app`) sugerem desnormalização para o app |
| **402 alters** | Modelagem evoluiu por acréscimo; tabelas antigas podem conter colunas obsoletas/sem uso **[Necessita Validação]** |

### Mapa conceitual (núcleo)
```
                         ┌──────────────┐
                         │ EmpresasGrupo│
                         └──────┬───────┘
                                │ 1:N
                          ┌─────▼─────┐  N:M  ┌───────┐
                          │  Empresa  ├───────┤ User  │
                          └─────┬─────┘       └───┬───┘
            ┌───────────────────┼─────────────┐   │ permissões
            ▼                   ▼             ▼   ▼
        ┌───────┐          ┌────────┐    ┌──────────┐
        │Cliente│──1:N────►│ Pedido │    │ Menuuser │
        └───┬───┘          └───┬────┘    └──────────┘
            │                  │ 1:N
   ┌────────┼──────┐      ┌────▼─────┐    ┌──────────┐
   ▼        ▼      ▼      │Pedidoitem│    │ Nfemitida│◄── Processors/Nfe
Convenio Comodato Telefone└──────────┘    └────┬─────┘
                               │               │
                          ┌────▼─────┐    ┌─────▼──────┐
                          │Financeiro│    │ Sped/SAT   │
                          └────┬─────┘    └────────────┘
                               ▼
                      Boleto / Remessa / Pix
```

---

## 5. Fluxos de Negócio

### Principais processos
1. **Ciclo do Pedido** (crítico): criação → movimenta estoque/financeiro (condicional à config da empresa, `PedidoController::setMovimentaEstoqueFinanceiro`) → geração de NF-e/NFC-e (`geraEmite`) → cobrança (boleto/PIX) → entrega (app mobile, `setPedidoSituacao`) → conclusão.
2. **Emissão Fiscal** (crítico e de alto risco): `NfemitidaController` + `app/Processors/Nfe/` (TagMaker 1.761 linhas, NfeImpostoProcessor 1.001, IcmsBase 943) → cálculo de tributos → assinatura com certificado → transmissão SEFAZ → DANFE.
3. **SPED Fiscal/Contribuições**: geração de blocos (`app/Processors/Sped/`) — obrigação acessória.
4. **Financeiro/Bancário**: geração de boletos (Itaú/Caixa), remessas/retornos, conciliação OFX, **PIX (cobrança e baixa via webhook)**.
5. **Comodato/Valegas**: controle de vasilhames em comodato e vale-gás (núcleo do negócio de GLP).
6. **Rastreamento de frota**: `CheckVehiclePosition` (command agendado) cruza Oracle ↔ MySQL `monitora` por cercas geográficas.

### Fluxos críticos
- **Confirmação de pagamento PIX** (`PixService::processReturn`) — ver alerta de segurança na Seção 7.
- **Cálculo de impostos NF-e** — alta complexidade tributária, baixíssima cobertura de testes.
- **Movimentação de estoque/financeiro do pedido** — efeitos colaterais financeiros.

### Regras de negócio duplicadas / em múltiplos locais (evidências)
- **Autorização**: duplicada entre `app/Policies/*` e `app/Http/Middleware/AuthorizeCustom.php`.
- **Conclusão/situação de pedido**: lógica de `pedidosituacao` aparece em `Cliente.php` (`dataultimopedidoconcluido`), `PedidoController`, `PedidoRepository` e endpoints da API (`setPedidoSituacao`).
- **Formatação Oracle (moeda/data/percentual)**: centralizada em `customHelper.php`, porém chamada de forma dispersa e há conversões ad-hoc em `whereRaw` (ex.: `TO_DATE(...)` espalhados em controllers).
- **`clienteConvenioDependente` vs `clienteConvenioDependete`** (`Cliente.php:105,110`): duas relações quase idênticas com nomes divergentes (uma com erro de digitação) — indício de duplicação por evolução não padronizada.

---

## 6. Estrutura do Código

### Organização
- Diretórios seguem o esqueleto Laravel 5.4. `app/` tem **203 models na raiz** (sem subpasta `Models/`) — padrão antigo do Laravel 5, hoje considerado poluído.
- Camadas auxiliares (`Processors`, `Services`, `Repository`, `Enums`, `Http/Resources`) existem, mas **convivem com controllers gordos** que ignoram essas camadas.

### Métricas
- **~121.700 linhas de PHP em `app/`** (1.921 arquivos PHP no total do projeto fora de vendor).
- Controllers gigantes: `NfemitidaController` 2.213, `SearchController` 2.097, `customHelper` 1.974, `NfwebController` 1.763, `PedidoController` 1.661, `CupomFiscalController` 1.359, `ClienteController` 1.310.
- **518 views Blade**, **115 arquivos JS**, **235 CSS**.

### Complexidade das funções
**Alta.** Controllers concentram parsing de input, regra de negócio, montagem de SQL e formatação de saída. Métodos com `$_GET` aninhados e múltiplos `if`/`whereRaw` (ex.: `MetavendaController::index`, `BairroController::index`).

### Duplicação de código
Presente — autorização duplicada, relações duplicadas, conversões de formato repetidas, **3 bibliotecas de PDF/Excel e 2 de barcode** fazendo trabalho sobreposto.

### Código morto / comentado / debug
- **~1.241 linhas comentadas/debug** nos controllers (estimativa por contagem).
- **133 ocorrências** de `dd()/dump()/var_dump()/die()/exit()` no código de `app/` — incluindo **debug esquecido em produção** (ex.: `PedidoController.php:80-81` `// dd(url('/')); // dd(sha1(env("APP_API_KEY")));`).
- Blocos inteiros comentados em `PixService.php` (lógica de ambiente sandbox/homolog desativada por comentário, linhas 76–106, 225–242, 319–329) — **chaveamento de ambiente feito comentando código** em vez de configuração.

### Classificação de qualidade por área
| Área | Classificação | Justificativa |
| --- | --- | --- |
| Estrutura de pastas | **Regular** | Segue Laravel, mas models na raiz e camadas inconsistentes |
| Controllers | **Ruim** | Gordos, com regra de negócio, SQL bruto e `$_GET` direto |
| Camada fiscal (Processors) | **Regular** | Organizada por blocos, porém densa e sem testes |
| Helpers globais | **Ruim** | God-helper de 1.974 linhas |
| Autorização | **Ruim** | Lógica duplicada e bypass por AJAX (ver §7) |
| Tratamento de input | **Crítico** | `$_GET`/`$_POST` direto, SQLi confirmado |
| Testes | **Crítico** | ~0% de cobertura efetiva |
| **Geral** | **Ruim → Crítico** (em segurança e input handling) | — |

---

## 7. Segurança

> Auditoria baseada em padrões reais encontrados no código.

### 🔴 CRÍTICA

| # | Vulnerabilidade | Evidência | Impacto |
| --- | --- | --- | --- |
| S1 | **SQL Injection** via input não sanitizado em `whereRaw`/`DB::statement` | `MetavendaController.php:40` (`whereRaw("... = '".$_GET['data']."'")`); `PixService.php:163` (`whereRaw("(pedido_id = $pedido_id ...")` com input do webhook); `ClienteController.php:692` (`DB::statement("DELETE ... where cliente_id = ".$cliente->id." AND ... IN (".$strRemove.")")`) | Vazamento/alteração/exclusão de dados, potencial RCE via Oracle |
| S2 | **"Criptografia" caseira = apenas Base64** para segredos sensíveis | `customHelper.php:1127-1147` (`customCrypt`/`customDecrypt` só fazem `base64_encode/decode`). Usado para **senha do certificado digital A1 da NF-e** (`Tools.php:64` `customDecrypt($empresa->nfesenhapfx, 6)`; `EmpresaController.php:527`) e **senha de e-mail** (`customHelper.php:1457`) | Quem ler o banco recupera a senha do **certificado fiscal** e do e-mail trivialmente. Permite emissão fraudulenta de NF-e em nome da empresa |
| S3 | **Webhook PIX sem autenticação nem validação de origem/valor** | `routes/api.php:29-30` (`webhook/pix` e `webhook` fora do grupo `auth:api`); `PixService::processReturn` marca pedido como **Concluído/pago** confiando no payload, **sem validar assinatura nem conferir `valor pago == valor cobrado`** (`completeReturn` apenas grava `status = Concluida`) | **Fraude financeira**: atacante pode confirmar pedidos como pagos enviando POST forjado ao webhook |

### 🟠 ALTA

| # | Vulnerabilidade | Evidência | Impacto |
| --- | --- | --- | --- |
| S4 | **Bypass de autorização para qualquer requisição AJAX** | `AuthorizeCustom.php:36-41`: se a rota contém `ajax.` **ou** `$request->ajax()` for verdadeiro, retorna `true` **sem checar permissão**. O header `X-Requested-With` é trivialmente forjável | Escalada/quebra de autorização — boa parte das ações sensíveis são chamadas via AJAX |
| S5 | **Upload sem whitelist de tipo/extensão** | `DocumentoController.php:198,257`, `VendasmensaisgestaoController.php:483`, `AppnotificationController.php:388` usam `getClientOriginalExtension()` para nomear/salvar sem validar `mimes:` | Upload de arquivo arbitrário (ex.: `.php`) → possível RCE conforme diretório servido |
| S6 | **Política de senha fraca** | `User.php:91` `'password' => 'required|min:4'` | Senhas de 4 caracteres em sistema financeiro/fiscal |
| S7 | **XSS potencial** em saída não escapada | 186 views usam `{!! !!}`; ex.: `{!!$nome!!}` renderizando nome de cliente sem escape | Injeção de script se o dado de origem for controlável pelo usuário |

### 🟡 MÉDIA

| # | Item | Evidência |
| --- | --- | --- |
| S8 | Segredo de API logado/exposto em debug | `PedidoController.php:81` `// dd(sha1(env("APP_API_KEY")))` (comentado, mas indica prática) |
| S9 | CSRF: nenhuma exceção configurada (bom), **mas** muitas ações sensíveis dependem do bypass AJAX (S4) | `VerifyCsrfToken.php` (`$except` vazio) |
| S10 | `composer-setup.php` e `vendor_fork/` (libs forkadas) versionados | superfície de manutenção/segurança não rastreada |
| S11 | Endpoint público `app/storeConfig` sem `auth:api` | `routes/api.php:27` |
| S12 | `clearBrowserCache()` manipula headers manualmente | `AuthController.php:101-109` (frágil, não substitui controle de sessão adequado) |

### 🟢 BAIXA
- Senhas de usuário usam Hash do Laravel (Bcrypt via `Authenticatable`) — **correto** (`User.php`).
- `.env` está no `.gitignore` (segredos não versionados).
- Não foram encontradas **credenciais reais hardcoded** em `config/`/`app/` (apenas placeholders default `your-secret` em `config/filesystems.php`/`queue.php`).

---

## 8. Performance

| Item | Evidência | Risco |
| --- | --- | --- |
| **Risco de N+1 queries** | 88 controllers usam `foreach`; relações Eloquent acessadas em laços sem `with()` aparente em vários pontos; `Cliente::dataultimopedidoconcluido` executa query por instância | Degradação em listagens grandes |
| **SQL bruto extenso** (566 usos de `DB::select/raw/whereRaw`) | Queries longas montadas por concatenação (ex.: `AuthController::getQuery`, `ComodatogestaoController`, `ConciliacaoController`) | Difícil otimização; planos Oracle dependentes de bind vs. literal |
| **Sessão pesada** | Login carrega menu, permissões, empresas e notificações inteiras na sessão (`AuthController@handleLogin`) | Sessões grandes; custo a cada request com `SESSION_DRIVER=file` |
| **Relatórios sem paginação no banco** | `customHelper::paginate` pagina **Collection já carregada em memória** (linha 1023) em vez de paginar no SQL | Consumo de memória em relatórios grandes |
| **Geração de documentos** | 3 libs PDF/Excel; relatórios fiscais volumosos | Pico de memória/tempo (ref. `instruções de implantação.md` pede timeout ≥60s) |
| **Build de assets morto** | Gulp 3/Elixir | Sem minificação/cache-busting moderno |

---

## 9. Débito Técnico

| Débito | Impacto | Evidência |
| --- | --- | --- |
| Framework/linguagem em EOL (Laravel 5.4 / PHP 7.4) | **Crítico** | `composer.lock` |
| SQLi e input handling via `$_GET`/`$_POST` (199x) | **Crítico** | §7 |
| "Cripto" base64 para segredos fiscais | **Crítico** | `customHelper.php:1127` |
| Cobertura de testes ~0% (4 métodos de teste, sendo `ExampleTest`) | **Crítico** | `tests/` |
| Autorização duplicada (Policies + middleware) com bypass AJAX | **Alto** | §3, §7 |
| Controllers gordos (até 2.213 linhas) | **Alto** | §6 |
| God-helper `customHelper.php` | **Alto** | 1.974 linhas |
| Schema Oracle (triggers/procedures/views) fora do VCS | **Alto** | §4 |
| Chaveamento de ambiente por código comentado | **Médio** | `PixService.php` |
| Redundância de libs (3 PDF/Excel, 2 barcode) | **Médio** | `composer.json` |
| `dd/dump/die` (133) e código morto/comentado | **Médio** | §6 |
| `vendor_fork/` + `composer-setup.php` versionados | **Médio** | raiz do projeto |
| Múltiplos bancos sem camada de integração | **Médio** | `config/database.php` |
| Build front-end obsoleto (Gulp 3) | **Baixo/Médio** | `gulpfile.js` |

---

## 10. Riscos da Aplicação

### Operacionais
- **Servidor preso a runtime antigo** (PHP 7.4/7.1, Oracle 11g): qualquer atualização de SO/infra pode quebrar o ambiente.
- **Schema de banco não versionado** (triggers/procedures): risco de divergência entre ambientes e de perda de regras ao migrar.
- **Sessão em arquivo** + estado pesado: dificulta balanceamento/escala horizontal.

### De negócio
- **Fraude de pagamento PIX** (webhook sem validação, S3) — risco financeiro direto.
- **Emissão fiscal fraudulenta** caso o banco vaze (senha do certificado em base64, S2).
- **Indisponibilidade de obrigações fiscais** (NF-e/SPED) se a stack quebrar — impacto regulatório.

### Tecnológicos
- **Sem suporte de segurança** do framework/linguagem há anos.
- **Dependências pré-1.0/abandonadas** (boleto, sped-da, laravelcollective).
- **Forks manuais** de bibliotecas (`vendor_fork`) sem trilha de atualização.

### De segurança
- SQLi, bypass de autorização, upload inseguro, XSS (§7).

### Dependências críticas (single points of failure)
- Oracle 11g + driver `oci8 5.4`.
- Certificado digital A1 e integração SEFAZ.
- API Itaú (PIX/boleto) e e.Rede (cartão).
- Bancos MySQL externos (`monitora`, `sgcm_api`).

---

## 11. Estratégia de Modernização

> **Premissa: manter a stack principal (PHP/Laravel/Oracle/Blade).** As ações abaixo modernizam **sem reescrever** o sistema.

### Curto Prazo (0–3 meses) — Estancar risco
- **Segurança crítica primeiro:**
  - **S3 (PIX):** exigir autenticação/validação de assinatura no webhook e **conferir `valor pago == valor cobrado`** antes de marcar como pago. Remover SQLi da linha `PixService.php:163` (usar bindings).
  - **S1 (SQLi):** substituir todas as concatenações em `whereRaw`/`DB::statement`/`DB::select` por **bindings parametrizados**. Priorizar `MetavendaController`, `ClienteController`, controllers de relatório.
  - **S2 (cripto):** migrar `nfesenhapfx`/`emailsenha` de base64 para `Crypt::encrypt` (APP_KEY) do Laravel; rotacionar segredos.
  - **S4 (bypass AJAX):** remover o atalho `if ($request->ajax()) return true;` e validar permissão também em rotas AJAX.
  - **S5 (upload):** aplicar `mimes:`/`mimetypes:` e armazenar fora do webroot.
  - **S6:** elevar política de senha (mín. 8 + complexidade).
- **Higiene:** remover `dd/dump/die` (133), `composer-setup.php` e segredos de debug; mover `vendor_fork` para pacotes versionados ou forks formais.
- **Observabilidade:** centralizar logs e alertas das integrações fiscais/financeiras.

### Médio Prazo (3–9 meses) — Estabilizar e testar
- **Rede de testes:** criar suíte de **caracterização** (golden master) cobrindo fluxos fiscais (NF-e/SPED), pedido→financeiro e PIX, **antes** de qualquer refactor — hoje a cobertura é ~0%.
- **Versionar o schema de banco:** trazer triggers/procedures/views/sequences Oracle para migrations ou repositório de DDL.
- **Reduzir controllers gordos:** extrair regra de negócio para `Services`/`Processors` já existentes (começar pelos top-5 controllers). Unificar autorização em **uma** estratégia (Policies).
- **Padronizar input:** substituir `$_GET`/`$_POST` por `FormRequest` validados (199 pontos).
- **Build front-end:** migrar Gulp 3/Elixir para Laravel Mix/Vite (sem trocar Blade).

### Longo Prazo (9–24 meses) — Sustentabilidade
- **Upgrade incremental do framework:** Laravel 5.4 → 5.8 → 6 (LTS) → … com PHP 8.x, em saltos testados pela suíte de caracterização. Atualizar `oci8`/`passport` em conjunto.
- **Desacoplar integrações** (PIX/boleto/SEFAZ/rastreamento) em módulos/serviços com contratos claros; avaliar mover sessão para Redis e relatórios pesados para fila.
- **Racionalizar dependências:** consolidar para **uma** lib de PDF e **uma** de Excel; remover `vendor_fork`.
- **Consolidar bancos:** definir camada de integração formal entre Oracle e os MySQL (`monitora`/`sgcm_api`).

---

## 12. Estimativa de Complexidade

| Área | Complexidade |
| --- | --- |
| Arquitetura | **Alta** — monólito de ~122k linhas, 3 bancos, camadas inconsistentes |
| Banco de Dados | **Alta** — ~200 tabelas, 621 migrations, schema parcialmente fora do VCS, Oracle |
| Backend | **Alta** — controllers gigantes, lógica fiscal/financeira densa, SQL bruto |
| Frontend | **Média** — Blade + jQuery (518 views), sem SPA, mas build obsoleto |
| Segurança | **Alta** — vulnerabilidades críticas (SQLi, PIX, cripto), sem testes |
| Modernização | **Alta** — EOL de framework/linguagem + ausência de testes elevam o risco de qualquer mudança |

---

## 13. Resumo Executivo

### Estado geral
O `ctrl-web` é um **ERP fiscal-financeiro maduro, abrangente e em produção ativa** (manutenção fiscal recente — IBS/CBS em dez/2025), construído em **Laravel 5.4 / PHP 7.4 sobre Oracle**, com ~**122 mil linhas** de PHP, **~200 tabelas** e **104 módulos**. É um ativo de negócio relevante, mas tecnicamente **frágil**: assenta sobre framework e linguagem **sem suporte de segurança há anos**, com **cobertura de testes praticamente nula** e **débito técnico acumulado de ~25 anos de evolução sem padronização**.

### Principais problemas encontrados
1. **Segurança crítica**: SQL Injection (input direto em `whereRaw`), **webhook PIX sem validação** (risco de fraude de pagamento), **senha do certificado fiscal protegida apenas com Base64**, bypass de autorização para requisições AJAX.
2. **Stack em EOL**: Laravel 5.4 (2017) + PHP 7.4 + Oracle 11g.
3. **Qualidade**: controllers de até 2.213 linhas, helper global de 1.974 linhas, autorização duplicada, 133 `dd/dump/die`, build front-end morto.
4. **Testabilidade**: ~0% de testes em um sistema fiscal/financeiro de alto risco.
5. **Banco**: regras de banco (triggers/procedures) presumivelmente fora do controle de versão.

### Principais riscos
- **Financeiro/regulatório imediato**: fraude via webhook PIX e exposição do certificado digital.
- **Continuidade**: dependência de runtime obsoleto e de forks manuais de bibliotecas.
- **Manutenção**: qualquer mudança é arriscada sem rede de testes.

### Potencial de modernização
**Alto e viável sem troca de stack.** A arquitetura Laravel, ainda que antiga, oferece caminho incremental de upgrade. A presença de camadas `Services`/`Processors`/`Repository` (mesmo subutilizadas) dá pontos de ancoragem para refatoração. O maior bloqueio não é técnico-conceitual, mas a **ausência de testes**, que precisa ser endereçada primeiro.

### Prioridades recomendadas
1. **(Imediato)** Corrigir as 3 vulnerabilidades **Críticas** (PIX webhook, SQLi, cripto de segredos fiscais) e o bypass de autorização AJAX.
2. **(Curto)** Higiene de produção (debug/segredos), política de senha, validação de upload.
3. **(Médio)** Suíte de **testes de caracterização** dos fluxos fiscais/financeiros + versionamento do schema Oracle.
4. **(Médio/Longo)** Refatorar controllers gordos para as camadas existentes e unificar autorização.
5. **(Longo)** Upgrade incremental Laravel/PHP guiado por testes; racionalizar dependências e bancos.

---

### Apêndice — Evidências quantitativas coletadas
- PHP files (projeto, exceto vendor): **1.921** · LOC PHP em `app/`: **~121.700**
- Controllers: **161** (raiz) · Models: **203** · Views Blade: **518** · JS: **115** · CSS: **235**
- Migrations: **621** (211 `create_`, 402 `alter_`) · Seeds presentes
- `Route::resource`: **104** · `routes/web.php`: 1.107 linhas · `routes/api.php`: 208 linhas
- `app/Processors`: **144** arquivos / **~24.300** linhas (camada fiscal)
- Usos de `DB::select/raw/whereRaw`: **566** · `$_GET/$_POST/$_REQUEST`: **199**
- `dd/dump/die/exit`: **133** · Views com `{!! !!}`: **186**
- Testes reais: **4 métodos** (incl. `ExampleTest`) · Policies: **98**
- Conexões DB: **3** (oracle / mysql `monitora` / mysql `sgcm_api`)

> Itens marcados **[Necessita Validação]** requerem acesso ao banco de produção ou ferramenta de análise estática para confirmação definitiva (cobertura de FKs/índices, objetos de banco, dependências circulares e colunas/tabelas sem uso).
