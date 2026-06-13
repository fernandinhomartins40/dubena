# Plano de Modernização do Ecossistema "Gás em Casa" / ctrl+

> **Documento mestre de execução.** Consolida os 4 relatórios técnicos de auditoria e as decisões estratégicas tomadas.
> Objetivo: **modernizar, sair de stacks caras/inviáveis (Oracle), garantir segurança e habilitar multi-tenant**, testando continuamente numa VPS Ubuntu 22 com Docker, **sem tocar na produção atual** até a virada deliberada.
> Data: 2026-06-12 · Status: planejamento aprovado para início.

---

## 0. Sumário Executivo da Estratégia

| Pergunta | Decisão tomada |
| --- | --- |
| Banco do ERP | **Migrar Oracle → PostgreSQL** (eliminar licença cara e inviável em Docker/VPS) |
| Abordagem de modernização | **Híbrida**: app mobile (não toca, só *hardening*) · api/monitoramento (moderniza enxuto) · ERP (upgrade incremental, nunca reescrita) |
| Multi-tenant | **1 banco + `tenant_id` + Row-Level Security (RLS) do PostgreSQL** |
| Web + API | **Unificar** ERP e API numa só plataforma Laravel — como **destino da Fase 5/6**, não ponto de partida |
| Hospedagem | **Docker na VPS Ubuntu 22** (somente backend; apps mobile ficam fora) |
| Ambiente | **Staging blindado na VPS** desde o início; **produção atual intocada** |
| Regra de ouro | **Segurança → Testes → Banco → Framework → Unificação → Multi-tenant** (nunca fora de ordem) |

### Arquitetura-alvo (destino)
```
  App iOS (nativo)    ┐
  App Android (nativo)┤──HTTPS──► [ PLATAFORMA ÚNICA ]
                      ┘            Laravel (PHP 8) + PostgreSQL + Redis
                                   ├─ /            painel web ERP (operadores)
                                   ├─ /api/v1,/v2  apps (versionado, retrocompatível)
                                   ├─ multi-tenant: tenant_id + RLS
                                   └─ Docker · VPS Ubuntu 22 · HTTPS
```

---

## 1. Ponto de Partida (o que a auditoria encontrou)

| Sistema | Stack atual | Estado | Papel no destino |
| --- | --- | --- | --- |
| **ctrl-web** (ERP) | Laravel 5.4 / PHP 7.4 / **Oracle 11g** · ~122k linhas | 🔴 Crítico (EOL, SQLi, webhook PIX, cripto base64, 0% testes) | **Núcleo** — vira a plataforma única |
| **api-app-gc** (API) | Laravel 5.6 / PHP 7.1 / MySQL · ~10k linhas | 🟠 EOL, segredos, token frágil | **Funde no ERP** (módulo `/api`) |
| **monitoramento-veiculos** | Laravel 5.4 / PHP 5.6 / MySQL+Oracle · ~8k linhas + módulo `mysql_*` PHP 4/5 | 🔴 Crítico (endpoint sem auth, segredos, código morto) | **Moderniza** (módulo ou serviço) |
| **app-gas-em-casa** (mobile) | React Native 0.79 / Expo 53 / React 19 · ~10k linhas | 🟢 Moderno — só *hardening* | **Mantém** (consome a API) |

### Riscos críticos transversais (de todos os relatórios)
1. **Stack EOL** sem patch de segurança em 3 dos 4 sistemas.
2. **Segredos encadeados**: `app_key` hardcoded no app → API confia → token de usuário fixo → acesso a tudo.
3. **Endpoints sem autenticação** movendo dinheiro/operação: webhook PIX, `/savePosition`.
4. **Bancos heterogêneos sem fronteira**: cada sistema lê o banco do outro direto (ex.: monitoramento faz SQL bruto no Oracle do ERP com `EMPRESA_ID=2` chumbado).
5. **~0% de testes** no legado + segredos mal protegidos (senha do certificado NF-e em Base64; credenciais de produção no Git).

---

## 2. Princípios Inegociáveis

1. **Produção atual NÃO se toca** até virada deliberada e validada.
2. **Ordem das fases é sagrada**: não se migra banco sem testes; não se faz multi-tenant sem banco unificado e seguro.
3. **Uma variável por vez**: nunca migrar banco + subir framework + fundir sistemas simultaneamente.
4. **Staging é descartável**: pode quebrar/recriar; nunca vira produção por acidente.
5. **Dados de teste anonimizados**: nunca dados reais de cliente em staging (LGPD).
6. **API retrocompatível**: endpoints usados por apps publicados **nunca** são removidos/alterados — só se adicionam versões novas.
7. **Cada fase tem um Portão de Saída**: critério objetivo de "pronto" antes de avançar.
8. **Tudo versionado em Git**, com branches de dev/staging separadas de produção.

---

## 3. Setup da VPS de Staging (base de toda a execução)

> A VPS Ubuntu 22 hospeda **staging blindado**, separado da produção. Acompanha o roadmap inteiro.

### 3.1 Topologia na VPS
```
  VPS Ubuntu 22 (Docker)
  ┌────────────────────────────────────────────────┐
  │ Nginx (reverse proxy + TLS/Certbot)            │
  │   ├─ dev-erp.dominio       → container ctrl-web │
  │   ├─ dev-api.dominio       → container api      │
  │   └─ dev-monitora.dominio  → container monitora │
  │ PostgreSQL (staging, dados fake/anonimizados)  │
  │ MySQL (transição, p/ api/monitora)             │
  │ Redis (cache/sessão/fila)                       │
  └────────────────────────────────────────────────┘
  Produção atual = fora desta VPS, intocada
```

### 3.2 Checklist de blindagem do staging (OBRIGATÓRIO antes de expor)
- [ ] **Acesso restrito**: firewall `ufw` liberando só seu IP/VPN, **ou** HTTP Basic Auth no Nginx.
- [ ] **HTTPS desde o dia 1**: Let's Encrypt/Certbot em todos os subdomínios.
- [ ] **`APP_DEBUG=false`** em todos os `.env` de staging.
- [ ] **Chaves novas** geradas para staging (NÃO reusar as de produção — que estão comprometidas no Git).
- [ ] **Dados fake/anonimizados** (CPF, telefone, e-mail, cartão) — nunca dump cru de produção.
- [ ] **Backup automatizado** do volume de banco de staging.
- [ ] **SSH com chave** (sem senha), `root` desabilitado, `fail2ban`.
- [ ] Containers **não rodam como root**; segredos via variáveis de ambiente do Docker (não no código).

---

## FASE 0 — Fundação de Ambiente (1–2 semanas)
**Objetivo: os sistemas backend rodando em Docker na VPS de staging, "as-is", sem segredo no código.**

> Vem ANTES de tocar em lógica de negócio. Valida o pipeline Docker → VPS.

### Ações
- [ ] Criar `docker-compose` por sistema (PHP-FPM + Nginx + banco + Redis).
- [ ] Subir cada sistema em container e fazer **apenas rodar** (ainda com Oracle/MySQL atuais).
- [ ] **Extrair TODOS os segredos do código** → `.env`/variáveis Docker:
  - `ctrl-web`: limpar `composer-setup.php` versionado; segredos de config.
  - `monitoramento`: remover credenciais hardcoded de `config/database.php` (defaults `reset1`, `dubena@4321`) e do módulo `integration/` (`mysql_connect(...)`).
  - `api-app-gc`: tirar `instruções api-siav.txt` (com APP_KEY/FCM/Maps) do versionamento.
- [ ] Configurar Git: branches `dev`/`staging` separadas; `.gitignore` cobrindo `.env`.
- [ ] Deploy do conjunto na VPS de staging com a blindagem da seção 3.2.

### 🚪 Portão de Saída
- Os sistemas backend sobem com `docker compose up` na VPS de staging, acessíveis via HTTPS e **acesso restrito**, com **zero segredo hardcoded** e `APP_DEBUG=false`.

### Piloto recomendado
> Começar por **`monitoramento-veiculos`** (menor, já MySQL, sem amarra Oracle): valida todo o pipeline Docker/VPS/staging com baixo risco antes de aplicar no ERP.

---

## FASE 1 — Blindagem de Segurança (2–4 semanas)
**Objetivo: fechar os buracos críticos identificados, ainda sobre a base atual, em staging.**

### Ações por achado crítico
- [ ] **Webhook PIX** (`ctrl-web` `PixService::processReturn`): validar assinatura/origem **e** conferir `valor pago == valor cobrado` antes de marcar como pago. Parametrizar a query `whereRaw` com binding.
- [ ] **`/savePosition`** (`monitoramento` `ApiController`): exigir autenticação/token; validar que `deviceid` existe e pertence a um veículo.
- [ ] **`getUsuarios`** (`monitoramento`): remover o campo `password` (hash) do JSON de resposta.
- [ ] **Auth fictícia** (`testarToken == '123456'`) e **segredo literal `'secret'`** (`encodeSecret`): substituir por mecanismo real (env/APP_KEY).
- [ ] **Esquema de token** (`api-app-gc` `getToken`/`app_key`): parar de derivar acesso de `sha1(APP_KEY)` + usuário fixo; mover para OAuth/Passport por cliente com escopos.
- [ ] **Cripto base64** (`ctrl-web` senha do certificado PFX e e-mail): migrar para `Crypt::encrypt` (APP_KEY).
- [ ] **SQLi** confirmados (`MetavendaController`, `ClienteController`, módulo `integration/`): parametrizar.
- [ ] **IDOR** (`monitoramento` `veiculos/dropdown`; `api-app-gc` middleware `access` que só loga): aplicar autorização por dono/tenant.
- [ ] **Política de senha** `min:4` → mín. 8 + complexidade (todos).
- [ ] **Rotacionar TODAS as credenciais** já expostas no Git (estão comprometidas — o histórico as guarda): bancos, Google Maps, FCM, APP_KEY, Traccar.
- [ ] **App mobile** (`app-gas-em-casa`): forçar TLS (remover `NSAllowsArbitraryLoads`/`allowHttp`); mover `app_key`/Maps key para EAS Secrets; criptografar MMKV (`encryptionKey`); remover `console.*` e `dd()` de produção.

### 🚪 Portão de Saída
- Os 5 riscos críticos transversais (seção 1) fechados e verificados em staging. Nenhum endpoint sensível sem auth. Nenhum segredo válido no Git.

---

## FASE 2 — Rede de Testes de Caracterização (3–6 semanas)
**Objetivo: capturar o comportamento ATUAL antes de mudar banco/framework. É a rede de proteção da migração.**

> Fase mais pulada e mais arrependida. Sem ela, migrar Oracle e subir Laravel é dirigir vendado num sistema fiscal.

### Ações
- [ ] Testes "golden master" (entrada X → saída Y) dos **fluxos que movem dinheiro/fiscal**:
  - Pedido → movimentação de estoque/financeiro (`ctrl-web` `PedidoController`).
  - Emissão **NF-e/NFC-e** e cálculo de impostos (`Processors/Nfe`).
  - **SPED** Fiscal/Contribuições.
  - **PIX**, **boleto**, conciliação.
  - Fluxo do app: token → cliente → pedido → pagamento (`api-app-gc`).
  - Ingestão de posição e cercas (`monitoramento`).
- [ ] Rodar a suíte na base atual e congelar como referência (baseline).
- [ ] Integrar a suíte ao container (rodar em CI local/staging).

### 🚪 Portão de Saída
- Fluxos fiscais/financeiros críticos cobertos por testes que **passam na base atual**. Baseline congelado.

---

## FASE 3 — Migração Oracle → PostgreSQL (6–12 semanas)
**Objetivo: eliminar o Oracle (caro/inviável), protegido pela rede de testes da Fase 2.**

> Maior trabalho técnico. Só começa com a Fase 2 fechada.

### Ações
- [ ] Migrar **schema**: tabelas, sequences Oracle → `SERIAL`/`IDENTITY`, índices, constraints.
- [ ] **Versionar o schema oculto**: trazer triggers/procedures/views/synonyms do Oracle (hoje fora do VCS) para migrations/DDL.
- [ ] Traduzir **SQL Oracle-específico** → PostgreSQL: `TO_DATE`/`TO_CHAR`, paginação, `whereRaw`, `||` de concatenação, tratamento de datas.
- [ ] Trocar driver `yajra/laravel-oci8` → driver PostgreSQL nativo do Laravel.
- [ ] Migrar dados (ETL) com **anonimização** para staging.
- [ ] Rodar a suíte da Fase 2 a **cada passo** — ela detecta regressão.
- [ ] `api-app-gc`/`monitoramento` (já MySQL): consolidar no PostgreSQL também (passo menor).

### 🚪 Portão de Saída
- ERP roda em **PostgreSQL** em staging, com **todos os testes da Fase 2 passando**. Oracle eliminado do caminho.

---

## FASE 4 — Upgrade Incremental de Framework/Runtime (paralelo/após Fase 3)
**Objetivo: sair do Laravel 5.x / PHP antigo, em saltos testados.**

### Ações
- [ ] **ERP**: Laravel 5.4 → 5.5 → 5.8 → 6 (LTS) → 7 → 8 (e além), com **PHP 8.x**, validando a suíte a cada salto.
- [ ] Atualizar dependências acopladas (Passport, dbal, etc.).
- [ ] **`api-app-gc`/`monitoramento`**: modernizar/reescrever enxuto já no padrão do destino (Laravel atual, PHP 8).
- [ ] **Reescrever/eliminar o módulo `integration/`** (`mysql_*` PHP 4/5) — substituir por código Laravel; unificar a ingestão de posição num único caminho.
- [ ] **Eliminar código morto**: 3.414 linhas de Processors órfãos no `monitoramento`; código comentado/`dd()` no ERP.
- [ ] Substituir build obsoleto (Gulp/Elixir) por Laravel Mix/Vite.

### 🚪 Portão de Saída
- Backend em **Laravel atual + PHP 8** em staging, suíte de testes verde, sem código morto crítico nem módulos em runtime obsoleto.

---

## FASE 5 — Unificação Web + API (após Fases 3 e 4)
**Objetivo: fundir API e ERP numa plataforma única, eliminando a duplicação fiscal (`*_importacao`).**

> Acontece **naturalmente** quando ERP e API já compartilham o PostgreSQL e estão cobertos por testes — não é passo forçado no começo.

### Ações
- [ ] Trazer as rotas da API para dentro do ERP modernizado como **`/api/v1`, `/api/v2`** (mesma base de código, mesmo banco).
- [ ] Substituir as tabelas espelho `*_importacao` por **leitura direta** das tabelas do ERP (fim da sincronização redundante).
- [ ] **Versionamento retrocompatível**: manter os contratos exatos que os apps publicados consomem (`v2/order/root`, `client/getById`, etc.). **Nada é removido** — só se adiciona.
- [ ] Apontar uma **build de staging do app** para `dev-api.dominio` e validar ponta a ponta.

### 🚪 Portão de Saída
- API servida pelo mesmo Laravel do ERP, sobre o mesmo PostgreSQL, com apps de teste funcionando e **contratos antigos preservados**.

---

## FASE 6 — Multi-Tenant (após unificação)
**Objetivo: transformar `empresa_id`/`grupo_id` em isolamento de tenant real para vários revendedores.**

> A fundação já existe no código (`empresa_id`/`grupo_id` em quase todo model). Aqui ela vira isolamento forçado pelo banco.

### Ações
- [ ] **Modelar o tenant**: definir quem é o "revendedor" (provavelmente `grupo_id`/`empresa_id` → `tenant_id`).
- [ ] Adicionar `tenant_id` onde faltar; backfill dos dados.
- [ ] **Ativar Row-Level Security (RLS)** no PostgreSQL: políticas que filtram por `tenant_id` no nível do banco (mesmo que um dev esqueça o `where`, o banco bloqueia o vazamento).
- [ ] **Middleware de tenant**: injeta o `tenant_id` do usuário autenticado em toda requisição/conexão.
- [ ] **Onboarding**: processo de provisionamento de novo revendedor (criação de tenant, seeds iniciais).
- [ ] Testes de isolamento: dois tenants coexistindo sem ver dados um do outro.

### 🚪 Portão de Saída
- Dois tenants de teste coexistem em staging **sem vazamento** (validado por RLS + testes). Onboarding de novo revendedor documentado.

---

## FASE 7 — Virada para Produção (deliberada, fora do escopo de migração)
**Objetivo: promover a plataforma validada para produção, sem quebrar o que funciona.**

### Ações (alto nível)
- [ ] Plano de cutover com janela e **rollback** definido.
- [ ] Migração final de dados de produção (Oracle → PostgreSQL) com validação.
- [ ] Monitoramento/observabilidade (logs estruturados sem dados sensíveis, métricas, alertas).
- [ ] Backups e DR de produção.
- [ ] Publicação das builds novas dos apps nas lojas (mantendo compat. para apps antigos durante a transição).

> Esta fase é **posterior** e só inicia com tudo validado em staging.

---

## 4. Matriz Fase × Sistema (o que acontece com cada um)

| Fase | ctrl-web (ERP) | api-app-gc | monitoramento | app mobile |
| --- | --- | --- | --- | --- |
| 0 Docker | dockeriza as-is | dockeriza as-is | **piloto** | — |
| 1 Segurança | PIX, cripto, SQLi | token/app_key | savePosition, IDOR | TLS, MMKV, segredos |
| 2 Testes | fiscal/financeiro | fluxo app | posição/cerca | — |
| 3 Banco | **Oracle→Postgres** | →Postgres | →Postgres | — |
| 4 Framework | upgrade incremental | moderniza | reescreve `integration/` | — |
| 5 Unificação | recebe `/api` | **funde no ERP** | módulo/serviço | aponta p/ staging |
| 6 Multi-tenant | RLS + tenant_id | herda | herda | herda |

---

## 5. Riscos do Plano e Mitigações

| Risco | Mitigação |
| --- | --- |
| Quebrar regra fiscal na migração de banco | Rede de testes (Fase 2) **antes** da Fase 3; saltos pequenos |
| Quebrar apps publicados | API **retrocompatível** e versionada; nunca remover endpoint em uso |
| Staging virar produção por acidente | Disciplina: staging descartável, dados fake, virada deliberada (Fase 7) |
| Mexer em várias variáveis ao mesmo tempo | Ordem das fases sagrada; uma variável por vez |
| Vazamento de dados em staging exposto | Blindagem 3.2 (acesso restrito, HTTPS, anonimização) |
| Vazamento entre tenants | RLS no PostgreSQL (defesa no nível do banco) |
| Credenciais comprometidas no Git | Rotação total na Fase 1 |

---

## 6. Ordem de Execução Recomendada (resumo operacional)

```
[Setup VPS staging blindado]
        │
   FASE 0 ──► Docker + extrair segredos        (piloto: monitoramento)
        │  🚪 sobe em staging, sem segredo
   FASE 1 ──► Blindagem de segurança
        │  🚪 buracos críticos fechados
   FASE 2 ──► Testes de caracterização
        │  🚪 baseline fiscal/financeiro verde
   FASE 3 ──► Oracle → PostgreSQL
        │  🚪 ERP em Postgres, testes passando
   FASE 4 ──► Upgrade Laravel/PHP + limpeza
        │  🚪 stack atual, sem código morto
   FASE 5 ──► Unificação web + API
        │  🚪 /api no ERP, apps de teste ok
   FASE 6 ──► Multi-tenant (tenant_id + RLS)
        │  🚪 2 tenants isolados
   FASE 7 ──► Virada para produção (posterior, deliberada)
```

> **Regra de ouro:** Segurança → Testes → Banco → Framework → Unificação → Multi-tenant.

---

## 7. Próximos Passos Imediatos (esta semana)

1. **Provisionar a VPS de staging** com a blindagem da seção 3.2 (firewall, HTTPS, SSH por chave).
2. **Iniciar a Fase 0 pelo piloto** (`monitoramento-veiculos`): `docker-compose` (PHP-FPM + Nginx + MySQL + Redis) + extração de segredos.
3. Validar o pipeline Docker → VPS → staging acessível por HTTPS com acesso restrito.
4. Replicar o padrão para `api-app-gc` e `ctrl-web`.

---

### Apêndice — Referências aos relatórios de auditoria
- `ctrl-web/ANALISE_TECNICA_CTRL-WEB.md`
- `api-app-gc/ANALISE_TECNICA_API-APP-GC.md`
- `app-gas-em-casa/ANALISE_TECNICA_APP-GAS-EM-CASA.md`
- `monitoramento-veiculos/ANALISE_TECNICA_MONITORAMENTO-VEICULOS.md`

> Itens que dependem de acesso ao banco/ambiente de produção (cobertura de FKs/índices, objetos de banco ocultos, volume de tabelas, modelagem definitiva do tenant) devem ser **validados durante as Fases 2 e 3**, quando houver acesso controlado em staging.
