# Análise Técnica Completa — Aplicação "monitoramento-veiculos"

> **Documento gerado por auditoria de código-fonte.**
> Base da análise: **exclusivamente o código-fonte real** do workspace `monitoramento-veiculos`.
> Documentação auxiliar (`readme.md`, comentários) foi **ignorada como fonte de verdade** — exceto onde o próprio código/config versionado expõe segredos, registrado como evidência de segurança.
> Data da análise: 2026-06-12.
> Cada conclusão referencia o arquivo/linha que a fundamenta. Itens sem evidência conclusiva estão marcados como **[Necessita Validação]**.

---

## 1. Visão Geral da Aplicação

### Objetivo principal
O `monitoramento-veiculos` é um **sistema web de rastreamento e monitoramento de frota de veículos** (entregadores de gás/GLP), integrado ao ERP `ctrl+` e à plataforma **GPS Traccar**. Recebe posições GPS dos dispositivos, mantém a última posição de cada veículo, exibe-as em mapa, controla **cercas eletrônicas (geofences)** e gera relatórios de rotas/eventos.

Evidência: models `Position`, `Ultimaposicao`, `Device`, `Cerca`, `Cercapoligono`, `Veiculo`; controllers `RastreamentoController`, `CercaController`; integração Traccar (`customHelper.php:603 buscarDadosTraccar`); endpoint `/savePosition` no padrão Traccar (`ApiController:52`).

### Principais funcionalidades identificadas (por evidência de código)
| Funcionalidade | Evidência |
| --- | --- |
| Recepção de posições GPS (Traccar) | `ApiController@savePosition` (`routes/api.php:26`) |
| Última posição por veículo | `Ultimaposicao`, `SearchController@getPosicaoAtual` |
| Visualização em mapa (rastreamento) | `RastreamentoController@index`, `config.keygooglemaps` |
| Cercas eletrônicas (geofence/polígonos) | `CercaController`, `Cerca`, `Cercapoligono`, `getCercaPoligono` |
| Setores e rotas | `Setor`, `RotaController`, `getCoordenadasSetor`, `getRotas` |
| Cadastro de veículos e tipos | `VeiculoController`, `Veiculo`, `Veiculotipo` |
| Multiempresa / grupos de empresa | `EmpresaController`, `EmpresasGrupoController` |
| Usuários e permissões (menu) | `UsersController`, `Menu`, `menu_user` |
| Relatórios (rotas, eventos) | `ReportController`, `RotaController`, `EventoController` |
| Integração com pedidos do ERP | `SearchController@getPedidosPendentes` (conexão `oracle3` ao ERP) |
| API mobile (usuários/empresas/token) | `ApiController@getUsuarios/getEmpresas/testarToken` |
| Sincronização batch de posições (legado) | `integration/atualizaposicoes.php` (script standalone) |

### Fluxo geral de funcionamento
1. Dispositivos GPS enviam posições → **Traccar** → `POST /savePosition` (`ApiController`) → grava em `positions` e atualiza `ultimaposicaos`.
2. (Legado paralelo) Script CLI `integration/atualizaposicoes.php` lê posições pendentes de um banco `sgcasa_monitoramento` e insere em `monitora` via `mysql_*`.
3. Usuário autentica (`AuthController`), seleciona empresa e visualiza veículos/cercas no mapa (`RastreamentoController`).
4. O sistema consulta **pedidos pendentes diretamente no Oracle do ERP** (`SearchController`, conexão `oracle3`) para sobrepor entregas ao rastreamento.
5. Posições podem ser retransmitidas a outro sistema via **WebSocket** (`sendWsMessage`, atualmente comentado).

### Público usuário
- **Operadores/gestores de logística** da revenda (web, mapa de frota).
- **Aplicativo mobile** (consome `getUsuarios`/`getEmpresas`).
- **Integrações server-to-server** (Traccar → `savePosition`; ERP via `oracle3`).

### Módulos existentes
Autenticação, Empresas/Grupos, Usuários, Veículos, Rastreamento, Cercas, Setores/Rotas, Relatórios (rotas/eventos), Configuração (Traccar/Google Maps), API mobile, Integração batch (legado).

---

## 2. Stack Tecnológica

> Versões lidas de `composer.lock`.

| Tecnologia | Versão | Utilização | Status |
| --- | --- | --- | --- |
| PHP | `>=5.6.4` (constraint) | Linguagem backend | 🔴 **Obsoleto** — PHP 5.6 EOL desde 2018; o módulo `integration/` usa `mysql_*` (removido no PHP 7) |
| Laravel Framework | **5.4.36** (`composer.lock`) | Framework MVC | 🔴 **Crítico** — Laravel 5.4 EOL desde 2017 |
| Oracle DB | (via `yajra/laravel-oci8 5.4`) | Conexões `oracle`/`oracle3` (ERP) | ⚠️ Acoplado |
| MySQL | — | Conexões `mysql`(monitora)/`mysql2`(sgc_dubena) | Banco principal do monitoramento |
| Laravel Passport | ^2.0 | OAuth2 / API tokens | 🔴 **Muito antigo** (Passport 2; atual 11+) |
| laravelcollective/html | ^5.2 | Formulários Blade | ⚠️ Abandonado upstream |
| cboden/ratchet + ratchet/pawl | ^0.4 / ^0.3.4 | WebSocket (retransmissão de posição) | Uso pontual (comentado) |
| guzzlehttp/guzzle | (via deps) | HTTP client (API Traccar) | OK |
| intervention/image | ^2.3 | Imagens (logo empresa) | OK |
| milon/barcode | ^5.2 | Código de barras | ⚠️ Possível código morto **[Necessita Validação]** |
| maatwebsite/excel | ~2.1.0 | Export Excel | ⚠️ Versão antiga |
| barryvdh/laravel-dompdf | ^0.8.0 | Geração PDF | OK |
| venturecraft/revisionable | 1.* | Auditoria de registros | Legado |
| doctrine/dbal | ~2.4.2 | Schema/migrations | Antigo |
| jenssegers/date | ^3.2 | Datas localizadas | OK |
| Laravel Elixir + Gulp | (gulpfile.js) | Build de assets | 🔴 **Obsoleto** — Elixir/Gulp descontinuados |
| Blade + jQuery/Bootstrap | — | Frontend (47 views) | Legado |
| PHPUnit | ~5.7 | Testes | Antigo |
| **Traccar** | — | Plataforma GPS externa (consumida) | Serviço externo |
| **Google Maps** | — | Mapa (`config.keygooglemaps`) | Serviço externo |

### Serviços externos / APIs
- **Consumidas:** Traccar (REST, Basic Auth — `buscarDadosTraccar`), Google Maps, ERP `ctrl+` (Oracle direto via `oracle3`).
- **Expostas:** `routes/api.php` (`/savePosition` **sem auth**, `/getUsuarios`, `/getEmpresas`, `/testarToken` com `auth:api`) + rotas web AJAX (`SearchController`).

### Ferramentas de build / deploy
- Build: **Laravel Elixir/Gulp** (`gulpfile.js`) — obsoleto.
- Deploy: sem CI/CD versionado. `composer-setup.php` (99 KB) **versionado** no repo (instalador temporário). `.gitignore` referencia `.svn` (origem SVN).

### Obsolescência (resumo)
Stack **igualmente obsoleta** ao ERP (Laravel 5.4 / PHP 5.6), com o agravante de um **módulo `integration/` em PHP 4/5** (`mysql_*`, construtores no estilo antigo) que **não roda em PHP 7+**.

---

## 3. Arquitetura da Aplicação

### Padrão arquitetural
- **Monólito Laravel MVC** (controllers + models Eloquent em `app/` + views Blade).
- **Sem camadas auxiliares efetivas**: há pastas `Processors/`, `Repository/`, `Jobs/`, mas (ver §6) os Processors são **código morto copiado do ERP** e o Repository tem 1 classe.
- **Módulo paralelo não-Laravel**: `integration/` — scripts PHP standalone procedurais (legado pré-Laravel), com sua própria conexão a banco.

### Monólito ou microsserviços
**Monólito**, fortemente **acoplado a um ecossistema distribuído**: 5 conexões de banco configuradas (`config/database.php`):
- `oracle` (local), `mysql` (`monitora` — principal), `mysql2` (`sgc_dubena`), `oracle3` (ERP remoto `134.122.8.179`), `pgsql`.

### Camadas existentes
```
Rotas (web.php / api.php)
   │
Middleware (web, auth, auth:api)
   │
Controllers (17) ── lógica de negócio embutida
   │
   ├─► Eloquent Models (13) ──► MySQL(monitora) / MySQL2(sgc_dubena)
   ├─► DB::connection('oracle3') ──► Oracle do ERP ctrl+ (acesso direto ao schema)
   ├─► customHelper.php (Traccar, encodeSecret, ...)
   └─► WebSocket (Ratchet) — retransmissão (comentada)

[Paralelo / fora do Laravel]
   integration/atualizaposicoes.php (CLI) ──► mysql_*(sgcasa_monitoramento → monitora)
```

### Acoplamentos excessivos
- **Acesso direto ao schema do ERP via SQL bruto** (`SearchController:118-148`, conexão `oracle3`), com query gigante e **`GRUPO_ID = 2 AND EMPRESA_ID = 2` hardcoded** (linha 146) — multi-tenancy quebrada e acoplamento ao modelo de dados do ERP.
- **Dois caminhos de ingestão de posição** (Laravel `savePosition` **e** script `integration/`) — lógica duplicada e divergente.
- **Mapa de dispositivos (deviceid↔veiculoid) hardcoded** em `integration/Class/Monitoramento.php:6-24`.

### Dependências circulares
Não evidenciadas no app Laravel. **[Necessita Validação por ferramenta estática]**

### Fluxo de requisições
Web: `Request → web/auth → Controller → Eloquent/oracle3 → Blade/JSON`. API: `savePosition` **sem middleware**; demais com `auth:api`.

### Fluxo de dados
GPS → Traccar → `savePosition` → `positions` + `ultimaposicaos`. Pedidos do ERP lidos sob demanda do Oracle remoto. Posições opcionalmente empurradas por WebSocket (desativado por comentário).

---

## 4. Modelagem de Dados

> Mapeada de **46 migrations** e **13 models**. Banco principal: **MySQL `monitora`**.

### Tabelas (17 criadas — migrations `create_*`)
`users`, `password_resets`, `empresas_grupos`, `empresas`, `empresa_user`, `menus`, `menu_user`, `sessions`, `veiculotipos`, `veiculos`, `setors`, `configs`, `ultimaposicaos`, `cercas`, `cercapoligonos`, `positions`, `devices`.

### Relacionamentos / PKs / FKs
- PKs padrão `id` (Eloquent).
- Núcleo de rastreamento: `Veiculo (deviceid, empresa_id, grupo_id)` ↔ `Position (deviceid)` ↔ `Ultimaposicao (veiculo_id, deviceid)`; `Cerca` ↔ `Cercapoligono`; `Setor`.
- Multiempresa: `Empresa` ↔ `EmpresasGrupo`; `User` ↔ `Empresa` (via `empresa_user`).

### Procedures / Triggers / Views
Não há DDL desse tipo nas migrations (MySQL puro com lógica na aplicação). ✅ rastreável.

### Problemas de modelagem identificados
| Item | Evidência / Risco |
| --- | --- |
| **Acoplamento ao schema do ERP** | `SearchController` lê `pedidos`, `clientes`, `ruas`, `bairros`, etc. do Oracle do ERP por SQL bruto — fora do controle de migrations deste sistema |
| **`positions` cresce indefinidamente** | `savePosition` insere cada ping GPS sem rotação/particionamento aparente → tabela de alto volume |
| **Vínculo device↔veículo duplicado** | No banco (`Veiculo.deviceid`) **e** hardcoded no script `integration/` |
| **Bancos heterogêneos** (`monitora`, `sgc_dubena`, `sgcasa_monitoramento`, ERP Oracle) | Sincronização frágil, risco de divergência |
| Campos/tabelas órfãs | `devices`, `configs` precisam confirmação de uso pleno **[Necessita Validação]** |

### Mapa conceitual (núcleo)
```
   ┌──────────────┐ 1:N ┌──────────┐ 1:N ┌──────────┐
   │ EmpresasGrupo├────►│ Empresa  ├────►│ Veiculo  │
   └──────────────┘     └────┬─────┘     └────┬─────┘
                             │ N:M (empresa_user)│ deviceid
                             ▼                   ├──────────────┐
                        ┌────────┐               ▼              ▼
                        │  User  │         ┌──────────┐  ┌──────────────┐
                        └────────┘         │ Position │  │ Ultimaposicao│
                                           │ (histórico│ │ (atual)      │
                                           │  GPS)     │ └──────────────┘
                        ┌────────┐         └──────────┘
                        │ Setor  │   ┌────────┐ 1:N ┌──────────────┐
                        └────────┘   │ Cerca  ├────►│ Cercapoligono│
                                     └────────┘     └──────────────┘
   [externo] oracle3 → ERP: pedidos, clientes, ruas, bairros, cidades…
```

---

## 5. Fluxos de Negócio

### Principais processos
1. **Ingestão de posição GPS** (`savePosition`): grava histórico + atualiza última posição, com regra de "não aceitar posição futura" (`if dhposition < Carbon::tomorrow()`, `ApiController:64`).
2. **Visualização de frota** no mapa (rastreamento + cercas + setores).
3. **Sobreposição de entregas**: pedidos pendentes do ERP exibidos junto ao rastreamento (`SearchController`).
4. **Geofencing**: cadastro e checagem de cercas/polígonos.
5. **Sincronização batch legada** (`integration/`): move posições de `sgcasa_monitoramento` para `monitora`.

### Fluxos críticos
- **Recepção de posição** (`savePosition`) — **sem autenticação** (ver §7).
- **Leitura direta do ERP** (`oracle3`) — depende de disponibilidade e schema do ERP.

### Regras duplicadas / em múltiplos locais
- **Ingestão de posição duplicada**: `ApiController@savePosition` (Laravel) **e** `integration/Monitoramento::inserePosicoes` (procedural) — **duas implementações** do mesmo processo, com lógicas distintas de "última posição".
- **Filtro de empresa hardcoded** (`GRUPO_ID=2/EMPRESA_ID=2`) vs. multiempresa via sessão — inconsistência.
- **Vínculo device↔veículo** duplicado (banco vs. array hardcoded).

---

## 6. Estrutura do Código

### Organização
- Esqueleto Laravel padrão, **pequeno** (~8.108 linhas em `app/`, 17 controllers, 13 models, 47 views).
- **Anomalia grave**: `app/Processors/` contém `caixaProcessor.php`, `financeiroProcessor.php`, `nfProcessor.php`, `EstoqueProcessor.php`, `androidProcessor.php` — **3.414 linhas** de código de **domínio do ERP (caixa, financeiro, NF, estoque)** que **não têm relação com rastreamento** e **não são referenciados por nenhum controller/rota** (grep retornou vazio). É **código morto copiado** do `ctrl-web`.

### Qualidade / complexidade
- App Laravel: **regular** — controllers diretos, pouco SQL bruto (7 ocorrências), senhas com bcrypt.
- Módulo `integration/`: **crítico** — procedural, `mysql_*` (deprecado/removido), SQLi, credenciais hardcoded, construtores PHP 4.

### Código morto / comentado / debug
- **3.414 linhas** de Processors órfãos (cópia do ERP).
- **5** `dd/dump/die` no app.
- Blocos comentados relevantes: `sendLastPositionAPI` (WebSocket homolog/prod, `ApiController:107-122`), `SearchController:116,151-152`.
- `customHelper.php` (619 linhas) — helper global herdado do ERP, parcialmente aplicável.

### Classificação de qualidade por área
| Área | Classificação |
| --- | --- |
| App Laravel (controllers/models) | **Regular** |
| Módulo `integration/` | **Crítico** (mysql_*, SQLi, segredos) |
| Código morto (Processors) | **Crítico** (3.414 linhas órfãs) |
| Configuração/segredos | **Ruim** (credenciais nos defaults) |
| Testes | **Crítico** (1 teste) |
| **Geral** | **Ruim → Crítico** |

---

## 7. Segurança

### 🔴 CRÍTICA

| # | Vulnerabilidade | Evidência | Impacto |
| --- | --- | --- | --- |
| S1 | **Credenciais de banco hardcoded nos defaults** | `config/database.php`: senha `reset1` (linha 71), `dubena@4321` (linha 84), hosts reais (`192.168.10.1`, `134.122.8.179`), `toor` (oracle). Versionado no git | Vazamento de **credenciais de produção** de múltiplos bancos (monitora, sgc_dubena, ERP Oracle) |
| S2 | **Credenciais hardcoded em código procedural** | `integration/Class/SGCasa.php:10` e `Monitoramento.php:28`: `mysql_connect("192.168.10.1","root","reset1")` | Idem, em texto puro no código |
| S3 | **Endpoint `/savePosition` sem autenticação** | `routes/api.php:26` (fora de `auth:api`); `ApiController:52` grava posição sem validar origem/existência do device | **Injeção de posições GPS falsas** de qualquer veículo → rastreamento e cercas manipuláveis; possível DoS por flood na tabela `positions` |
| S4 | **SQL Injection no módulo `integration/`** | `Monitoramento.php:44,66` e `SGCasa.php` concatenam valores direto em INSERT/UPDATE (`mysql_query`) | Execução de SQL arbitrário se os dados de origem forem manipuláveis |
| S5 | **API expõe hash de senha dos usuários** | `ApiController@getUsuarios:25` faz `select('email','id','name','password')` e retorna no JSON | **Exposição de hashes de senha** + e-mails (quebra de confidencialidade; facilita brute-force offline) |

### 🟠 ALTA

| # | Vulnerabilidade | Evidência | Impacto |
| --- | --- | --- | --- |
| S6 | **"Autenticação" trivial hardcoded** | `ApiController@testarToken:46`: retorna "OK" se `telefone == '123456'` | Bypass/validação fictícia |
| S7 | **Segredo de assinatura hardcoded** | `customHelper.php:597` `hash_hmac('sha256', $txt, 'secret', true)` — chave literal `'secret'` em `encodeSecret` | Tokens/secrets forjáveis por quem conhece o código |
| S8 | **Senha do Traccar em texto no banco + Basic Auth** | `Config.passwordtraccar` (`Config.php:9`), `buscarDadosTraccar` monta `base64(user:pass)` (`customHelper:607`) | Vazamento do banco expõe acesso ao Traccar (controle da frota) |
| S9 | **Rota AJAX sem autorização por dono** | `routes/web.php:71-78` `veiculos/dropdown` retorna veículos de **qualquer** `empresa_id` informado via `Input::get('option')` | IDOR — enumeração de veículos de outras empresas |

### 🟡 MÉDIA
- S10 — **Política de senha fraca**: `min:4` (`User.php:33`, `UsersController:86`).
- S11 — **`composer-setup.php` versionado** (99 KB) e `APP_DEBUG=true` no `.env.example`.
- S12 — **Multi-tenancy hardcoded** (`SearchController:146` `EMPRESA_ID=2`) — risco de vazamento entre empresas se reaproveitado.

### 🟢 BAIXA / Pontos positivos
- ✅ Senhas de usuário com **bcrypt** (`UsersController:104,205,259`).
- ✅ App Laravel usa **query builder/Eloquent** na maior parte (pouco SQL bruto; `oracle3` usa query estática sem input → sem SQLi naquele ponto).
- ✅ `.env` no `.gitignore`.

---

## 8. Performance

| Item | Evidência | Risco |
| --- | --- | --- |
| **Tabela `positions` de alto volume** | `savePosition` insere cada ping sem rotação/índice/particionamento evidente | Crescimento ilimitado; consultas e backups lentos |
| **Insert em massa por string concatenada** | `integration/Monitoramento::inserePosicoes` monta um único INSERT gigante | Risco de exceder `max_allowed_packet`; sem transação |
| **Consulta ao ERP por SQL bruto pesado** | `SearchController:118-148` — múltiplos JOINs no Oracle remoto a cada request | Latência dependente do ERP e da rede (`oracle3` em IP externo) |
| **Polling de rastreamento** | `config.temporefresh` (refresh do mapa) | Carga proporcional ao nº de veículos/usuários **[Necessita Validação do intervalo]** |
| **Código morto carregado** | 3.414 linhas de Processors órfãos | Peso de manutenção (não de runtime, pois não são chamados) |
| Pontos positivos | `ultimaposicaos` separa "estado atual" do histórico (bom para leitura no mapa) | ✅ |

---

## 9. Débito Técnico

| Débito | Impacto | Evidência |
| --- | --- | --- |
| Stack EOL (Laravel 5.4 / PHP 5.6) | **Crítico** | `composer.lock` |
| Módulo `integration/` em PHP 4/5 (`mysql_*`) | **Crítico** | `integration/Class/*` — não roda em PHP 7+ |
| Credenciais de produção hardcoded | **Crítico** | `config/database.php`, `integration/*` |
| Endpoint de posição sem auth + exposição de senha-hash | **Crítico** | `ApiController` |
| 3.414 linhas de código morto (Processors do ERP) | **Alto** | `app/Processors/` |
| Acoplamento direto ao schema do ERP (oracle3) | **Alto** | `SearchController` |
| Ingestão de posição duplicada (Laravel vs. script) | **Alto** | `ApiController` vs `integration/` |
| Vínculo/multi-tenancy hardcoded | **Médio** | `Monitoramento.php`, `SearchController:146` |
| Cobertura de testes ~0% (1 teste) | **Médio/Alto** | `tests/` |
| Build obsoleto (Elixir/Gulp) | **Médio** | `gulpfile.js` |
| `composer-setup.php` versionado | **Baixo/Médio** | raiz |

---

## 10. Riscos da Aplicação

### Operacionais
- **Módulo `integration/` quebrado em PHP moderno** (`mysql_*`): se ainda em uso, depende de runtime PHP 5 — risco de parada na atualização de servidor.
- **Dependência do ERP Oracle remoto** (`oracle3`, IP externo): indisponibilidade/latência derruba o painel de pedidos.

### De negócio
- **Manipulação de rastreamento** (posições falsas via `savePosition`) — decisões logísticas/cercas comprometidas.
- **Vazamento de credenciais** (S1/S2) — acesso a múltiplos bancos, inclusive ERP.

### Tecnológicos
- Stack sem suporte; Passport 2; build morto.

### De segurança
- Endpoints sem auth, exposição de hash de senha, segredos hardcoded (§7).

### Dependências críticas (SPOF)
- Traccar (origem das posições). Banco `monitora`. ERP Oracle (`oracle3`). Google Maps.

---

## 11. Estratégia de Modernização

> **Premissa: manter a stack principal (PHP/Laravel/MySQL/Oracle).**

### Curto Prazo (0–3 meses) — Estancar risco
- **Remover credenciais dos defaults** (`config/database.php`) e do `integration/`; mover 100% para `.env`; **rotacionar** todas as senhas expostas.
- **Proteger `/savePosition`**: exigir token/assinatura do Traccar e **validar** que o `deviceid` existe e pertence a um veículo; descartar payloads inválidos.
- **Remover o campo `password` de `getUsuarios`** (S5) e a "auth" fictícia `testarToken==123456` (S6).
- **Corrigir IDOR** em `veiculos/dropdown` (filtrar por empresa do usuário autenticado).
- Substituir o segredo literal `'secret'` em `encodeSecret` por `APP_KEY`/env.
- Remover `composer-setup.php` do repo; `APP_DEBUG=false` em produção.

### Médio Prazo (3–9 meses) — Estabilizar
- **Desativar/Reescrever o módulo `integration/`** em Laravel (eliminar `mysql_*`, SQLi e mapa hardcoded); unificar a ingestão de posição em **um** caminho.
- **Parametrizar multi-tenancy** (remover `EMPRESA_ID=2` hardcoded; derivar da sessão).
- **Rotacionar/particionar a tabela `positions`** (TTL/arquivamento, índices em `deviceid`/`dhposition`).
- **Eliminar código morto** (Processors do ERP) — 3.414 linhas.
- Introduzir **testes** dos fluxos de posição/cerca.
- Encapsular o acesso ao ERP (`oracle3`) atrás de um serviço/contrato (idealmente via API do ERP, não SQL direto).

### Longo Prazo (9–18 meses) — Sustentabilidade
- **Upgrade incremental** Laravel 5.4 → 5.8 → 6 LTS com PHP 8.x; atualizar Passport.
- Migrar build Elixir/Gulp → Laravel Mix/Vite.
- Avaliar fila/stream para ingestão de posições em escala; observabilidade.

---

## 12. Estimativa de Complexidade

| Área | Complexidade |
| --- | --- |
| Arquitetura | **Média** — monólito pequeno, mas 5 bancos e módulo legado paralelo |
| Banco de Dados | **Média** — ~17 tabelas próprias + acoplamento ao schema do ERP |
| Backend | **Média** — app enxuto, porém com módulo procedural crítico e código morto |
| Frontend | **Média** — Blade + jQuery/mapa (47 views), build obsoleto |
| Segurança | **Alta** — endpoint sem auth, segredos hardcoded, exposição de senha, SQLi no legado |
| Modernização | **Média/Alta** — EOL + módulo PHP 4/5 elevam o esforço |

---

## 13. Resumo Executivo

### Estado geral
O `monitoramento-veiculos` é um **sistema de rastreamento de frota de porte pequeno-médio** (Laravel 5.4 / MySQL, integrado a Traccar e ao ERP `ctrl+`). O núcleo Laravel é **relativamente simples e funcional**, mas o conjunto está **comprometido por dívida grave de segurança e por um módulo legado `integration/` em PHP 4/5** (`mysql_*`, SQL Injection, credenciais hardcoded) que **não é compatível com PHP moderno**. Há ainda **3.414 linhas de código morto** copiadas do ERP e **credenciais de produção versionadas**.

### Principais problemas encontrados
1. **Segurança crítica**: `/savePosition` **sem autenticação** (injeção de GPS falso); **credenciais de produção hardcoded** (config + `integration/`); **API expõe hash de senha** dos usuários; **SQLi** no módulo legado; "auth" fictícia (`telefone==123456`) e segredo literal `'secret'`.
2. **Stack EOL**: Laravel 5.4 / PHP 5.6 / Passport 2; módulo `integration/` **quebrado em PHP 7+**.
3. **Qualidade**: 3.414 linhas de código morto (Processors do ERP), ingestão de posição duplicada, multi-tenancy e device-map hardcoded, ~0% de testes.
4. **Acoplamento**: leitura direta do schema Oracle do ERP via SQL bruto.

### Principais riscos
- **Manipulação do rastreamento** e **vazamento de credenciais/senhas** (impacto operacional e de confidencialidade imediato).
- **Parada na modernização de servidor** por causa do módulo `mysql_*`.

### Potencial de modernização
**Médio-alto.** O núcleo Laravel é pequeno e tem caminho de upgrade incremental. O maior esforço é **isolar/reescrever o módulo `integration/`**, **eliminar código morto** e **fechar as falhas de segurança** — todas bem localizadas e de correção objetiva.

### Prioridades recomendadas
1. **(Imediato)** Autenticar `/savePosition`; remover credenciais hardcoded e rotacioná-las; tirar `password` de `getUsuarios`; corrigir IDOR do dropdown; remover auth fictícia.
2. **(Curto)** Desativar/reescrever `integration/` (fim do `mysql_*`/SQLi); unificar ingestão de posição.
3. **(Médio)** Eliminar código morto; particionar `positions`; parametrizar multi-tenancy; testes.
4. **(Longo)** Upgrade Laravel/PHP; build moderno; encapsular acesso ao ERP.

---

### Apêndice — Evidências quantitativas coletadas
- LOC PHP em `app/`: **~8.108** · PHP em `app/`: **56** · Controllers: **17** · Models: **13** · Views Blade: **47**
- Migrations: **46** (17 `create_`) · Tabelas próprias: **17**
- Conexões DB: **5** (oracle, mysql `monitora`, mysql2 `sgc_dubena`, oracle3 ERP remoto, pgsql)
- Código morto (Processors do ERP, não referenciados): **3.414 linhas**
- Raw SQL no app: **7** · `dd/dump/die`: **5** · Testes: **1 método**
- Stack: PHP **5.6** · Laravel **5.4.36** · Passport **2** · Traccar/Google Maps externos
- Módulo legado `integration/`: 3 arquivos PHP procedurais com `mysql_*` + credenciais hardcoded (`192.168.10.1/root/reset1`)
- Endpoint sem auth: `POST /savePosition` (`routes/api.php:26`)

> Itens **[Necessita Validação]** (uso atual do módulo `integration/`, intervalo de refresh, tabelas/campos órfãos, dependências circulares, volume real de `positions`) requerem acesso ao ambiente/banco de produção para confirmação definitiva.
