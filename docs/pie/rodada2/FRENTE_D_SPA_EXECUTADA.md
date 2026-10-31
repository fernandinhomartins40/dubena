# Frente D — SPA do erp-novo executada de verdade (usuário adversarial com navegador)

**Rodada 2 da auditoria PIE** · commit `133f588d` · executado em 30–31/10/2026 · revisor: Frente D

A Rodada 1 (R2) avaliou a SPA **sem nenhum clique**. Nesta frente o sistema foi
levantado localmente com PostgreSQL e **RLS ativa** (runtime `erp_app`, envelope SaaS
ligado como na homologação). As jornadas foram percorridas no Chromium com Playwright
em 1366×768 e em 390×844. Toda afirmação abaixo aponta para uma saída real de script,
uma captura, um comando ou um arquivo:linha. O que não foi executado aparece como
**FORTE INDÍCIO** ou **HIPÓTESE**.

Capturas e logs ficam fora do repositório, em
`C:\Users\fusea\AppData\Local\Temp\claude\c--FuseDev-Dubena\80f4869b-be49-4f17-bc09-aab1411b63c8\scratchpad\d\`
(chamado abaixo de `SCR\`): 127 PNG em `SCR\shots\`, logs `SCR\j*.log.txt` / `SCR\j*.out`
e os scripts `SCR\j01..j13*.js`.

---

## 1. Como o ambiente foi levantado (comandos reais)

| Passo | Comando / ação | Resultado |
|---|---|---|
| Postgres | já estava no ar na 55432 (PID 7484, **não iniciado por mim**, mantido) | `erp_app` NOSUPERUSER/NOBYPASSRLS existente |
| Banco próprio | `psql -U postgres -c "create database pie_r2_demo owner postgres"` | ok (o `erp_teste` não foi tocado) |
| Variáveis | `SCR\env.ps1`: `DB_CONNECTION=pgsql DB_PORT=55432 DB_DATABASE=pie_r2_demo DB_USERNAME=erp_app DB_OWNER_USERNAME=postgres SAAS_ENFORCE_TENANT_ENVELOPE=true SAAS_ENFORCE_LICENCA=true CACHE_STORE=file QUEUE_CONNECTION=sync` (processo, `.env` intocado) | — |
| Migração | `php artisan migrate --database=pgsql_owner --force` | todas DONE (`SCR\migrate.log`) |
| Semeadura | `DB_USERNAME=postgres ADMIN_SEED_PASSWORD=… php artisan db:seed` (DemoGuarapuavaSeeder) | **abortou no passo CRM** (P2-D-06). Clientes, pedidos, financeiro, satélites entraram. Depois `--class=MarketplaceDemoSeeder` e `--class=SuperAdminSeeder` |
| Titularidade SaaS | `php artisan saas:tenant:importar SCR\tenant.json --apply` (1 tenant, 2 empresas, escopo de grupo 1, membership do admin) → `saas:tenant:preencher-chave-operacional --apply` (5.515 linhas, 35 tabelas) → `saas:tenant:proteger-configuracao-grupo --apply` (38 tabelas) → `saas:legacy-full --force` (2 assinaturas) | ok |
| API | `php artisan serve --port=8000` como `erp_app` | ok. No Windows o servidor embutido atende **uma requisição por vez**: os tempos abaixo saem inflados |
| SPA | `npx vite --port 5173` e também `npx vite build --outDir SCR\dist`. O build de produção foi servido pelo próprio Playwright (rota `pie.local`), e as medições usam esse build | ok |
| Proxy | a SPA chama `/novo/api/*` e o proxy do Vite só cobre `/api` (P2-D-18). O Playwright reescreve `/novo/api` → `127.0.0.1:8000/api`, o mesmo strip que o Nginx faz em produção | ok |
| Navegador | `npm i playwright-core@1.56.1 axe-core@4.10.3` em `SCR\`, com o executável `%LOCALAPPDATA%\ms-playwright\chromium-1248\chrome-win64\chrome.exe` | ok |

**Ajustes manuais no banco de demonstração, e por quê:**
1. O admin semeado tem `support=true` e **não consegue logar sob RLS** (P2-D-01). Para seguir,
   fiz `update users set support=false` e atribuí o papel Administrador com `empresa_id`
   **nulo** (papel global, como faz o `AcessoRedeDubenaSeeder`). Foi esse papel global que
   revelou o P2-D-03.
2. O banco foi semeado como superusuário, porque o console sob RLS não grava (armadilha do
   CLAUDE.md).

No primeiro ciclo o envelope estava desligado (default do `.env.example`) e apareceram
500 em `logistica_configs`. Com o envelope ligado, como na homologação, eles **sumiram**.
Por isso não constam como achado.

---

## 2. Jornadas executadas

| # | Jornada (persona) | Passos e entrada adversarial | Resultado observado | Evidência |
|---|---|---|---|---|
| J01 | Login (atendente), desktop e celular | vazio; senha errada; e-mail inexistente; certo; token adulterado | vazio → validação nativa "Preencha este campo."; erradas → "E-mail/usuário ou senha inválidos…" (sem enumeração); certo → painel. **Admin `support` → HTTP 500** e "O serviço de acesso está indisponível" | `SCR\shots\j01-desk-*.png`, `j01-cel-*.png`; P2-D-01 |
| J01b | Sessão inválida ao abrir a página | token trocado no storage, navegar para /pedidos | volta para /login **sem aviso** de sessão expirada | `j01-desk-token-vencido.png` |
| J02 | Todas as 41 rotas + 404 (gerente), desktop; 8 rotas no celular | carregar cada rota; sem overflow horizontal | 40 rotas abrem sem erro de console; **/monitora 4× HTTP 500** (`config-global`); **rota inexistente derruba o app** | `SCR\j02-prod.out`, `j02-desk.json`; P2-D-07, P2-D-11 |
| J03 | **Novo pedido por telefone** (atendente) | busca por nome sem acento, por telefone em 4 formatos; quantidade 0 / -2 / abc / 1,5 / 1e3 / 999999; preço "110,50"; salvar incompleto; Esc; duplo clique; recarregar; token vencido ao salvar | telefone acha (PIE-15 ok); **nome sem acento não acha**; quantidade 0 de um item some sem aviso; `1e3` vira 1.000; Esc descarta tudo; duplo clique → **1 POST** (ok); token vencido → toast "Unauthenticated." e o diálogo fica aberto | `SCR\j03-desk.log.txt`, `j03-desk-0*.png`; P2-D-05, P2-D-12 |
| J04 | Configurar condições de pagamento (gerente) | parcelas "abc", dias "-5", intervalo "1,5"; depois Dinheiro / PIX / Boleto 30 | inválido → toast **"validation.integer (and 2 more errors)"**; válidos gravados | `j04-cond-*.png`; PIE-34 reconfirmado |
| J05 | Mudar situação até entregue (atendente) | #501 Em Separação → Em Rota → Concluído; #503 (1.000.000 un.) → Concluído; #502 → Cancelado | confirmação antes de concluir e de cancelar (ok); #503 barrado com "Saldo insuficiente (atual 648, movimento -1000000)." (ok) | `j05-confirma-*.png` |
| J06 | Caixa e financeiro (caixa) | abrir; receber o Pedido #1 com desconto "5,50", "5%", "R$ 5", "1.000,00", "-10", "999"; fechar | desconto 999 → servidor recusa (ok, R2-08 corrigido), mas o botão mostra "Receber -R$ 879,00"; "-10" vira **Receber R$ 130,00**; "1.000,00" vira 1.00000 (R$ 1); recebido R$ 114,50. **Conta "Aberto" não fecha** | `j06-0*.png`, `SCR\j06.log.txt`; P2-D-09 |
| J06b | Hora do caixa (PIE-32) | fechar e reabrir o Caixa Geral às 16:41 BRT | gravado `fechamento 16:41:13` e `abertura 16:42:02` (hora local) | `j06b-Abrir.png` + `psql` |
| J07 | "Hoje" às 22:30 BRT (PIE-32) | relógio do navegador em 30/10 22:30 −03 | Malote e Novo lançamento sugerem **2026-10-30** (ok) | `j07-*.png` |
| J08 | Alçada de desconto (PIE-17) | "5%"+"abc"; "5%"+"R$ 1.000"; "7,5 %"+"1.000,50" | "abc" recusado (ok); **"R$ 1.000" gravado como R$ 1,00**; "1.000,50" ok; duas regras gerais idênticas aceitas | `j08-alcada-*.png`, `alcada_descontos` ids 1 e 2 |
| J09 | Cadastro de cliente | vazio; CPF "111.111.111-11", "123", "123.456.789-0123456"; nome com 300 caracteres; e-mail inválido | **os 3 CPFs inválidos foram salvos** (clientes 201–203); erros em chave crua (`validation.required`, `validation.max.string`, `validation.email`); **não há campo de telefone** no cadastro novo, mas o servidor exige "telefone ou endereço" | `j09-desk-*.png`; PIE-35 reconfirmado; P2-D-10 |
| J10 | Usuário com papel restrito (dono → operador) | criar "Operador PIE" (senha "123", depois forte); logar como ele no desktop e no celular; abrir 8 rotas administrativas; chamar 8 endpoints | senha fraca → "validation.min.string"; criado; **operador não consegue usar nada**: toda API dá 403 "Empresa sem vinculo de titularidade SaaS aprovado."; o menu e o cabeçalho mostram **"Administrador, Operador"** e todos os módulos | `j10-*.png`, `SCR\j10.log.txt`; P2-D-02, P2-D-03 |
| J11 | Superadmin | senha errada; dashboard, empresas, planos, cidades, migrações, auditoria; procurar "nova revenda" | **Empresas: 0** e dashboard zerado com 2 empresas no banco; **não existe criar revenda**; o assistente de migração abre | `j11_*.png`, `SCR\j11.out`; P2-D-04, P2-D-14 |
| J12 | Novo pedido só por teclado | Tab/Enter/setas/Esc; Alt+N | Enter abre a busca; **setas e Enter não escolhem resultado**; o Tab fica preso entre o campo e as opções; Esc fecha só o popover (ok); anel de foco visível (outline 2px); não há atalho | `SCR\j12.out`, `j12-desk-teclado.png`; P2-D-15 |
| J13 | Novo pedido no celular | 390×844 | layout íntegro, sem rolagem horizontal; o total fica abaixo dos botões | `j13-cel-novo-pedido.png` |

**Não percorridos no navegador:** estoque (a tela abriu, mas não houve movimento),
relatórios (só abertura) e configurações > Sistema (só a 500 vista via /monitora).
Detalhes na seção 6.

### Tempo até a tela ficar pronta (build de produção, 1366×768)

Critério: h1 presente, nenhum skeleton e nenhuma chamada de API pendente por 450 ms.
**Ressalva:** o PHP embutido do Windows serializa as requisições, e cada tela dispara de 3
a 8 chamadas. Os números mostram a ordem de grandeza e servem para comparar telas entre
si. Não representam a produção.

| Tela | ms | Tela | ms |
|---|---|---|---|
| Início | 5.792 | Financeiro | 4.290 |
| Pedidos | 4.357 | Estoque | 3.557 |
| Central de entregas | 5.975 | Clientes | 3.844 |
| Rastreamento | 13.079 (4× 500) | Demais 34 rotas | 2.0–4.2 s |

Chamada isolada: login 1,28 s; `/up` 0,63 s; lookup de cliente 1,2–1,5 s (só uma
chegou a 3,6 s, com o servidor ocupado).

### Acessibilidade (axe-core 4.10, WCAG 2 A/AA)

| Tela | Violações |
|---|---|
| Início | color-contrast (serious, 10 nós) |
| Pedidos | color-contrast (serious, 10) |
| Clientes | **aria-valid-attr-value (critical, 1: aba `ativos` com aria-controls inválido)** + color-contrast (serious, 10) |
| Estoque | color-contrast (serious, 12) |
| Financeiro | color-contrast (serious, 13) |

---

## 3. Reconferência no navegador das correções da Rodada 1

| Item | Situação no ACHADOS | Observado no navegador | Veredito |
|---|---|---|---|
| **PIE-15** busca por telefone no lookup | CORRIGIDO | "94725", "(42) 94725-0763", "42947250763" e "0763" acham Patrícia Lustosa (`j03-desk-02-busca-telefone.png`). A **listagem** de clientes continua sem buscar por telefone (`/clientes?q=94725` → total 0) | **OK no lookup.** Mas a busca por **nome sem acento** falha (P2-D-05, novo) |
| **PIE-17** "5%" e formatos BR no desconto | CORRIGIDO | "5%", "7,5 %", "1.000,50" e "abc" tratados corretamente; **"R$ 1.000" virou teto de R$ 1,00** (`alcada_descontos.id=1`, valor_max 1.00) | **PARCIAL**: P2-D-08 |
| **PIE-32** data e hora locais | CORRIGIDO | caixa grava 16:41:13 / 16:42:02 BRT; às 22:30 os campos sugerem 30/10 | **OK** |
| **R2-10** Esc descarta o pedido | PENDENTE (dentro de PIE-33) | com cliente e 2 itens, Esc fecha o diálogo sem perguntar; ao reabrir, cliente e itens sumiram (`j03-desk-06-apos-esc.png`, `07-reaberto.png`) | **NÃO CORRIGIDO** |
| R2-09 item com quantidade 0 some | PENDENTE | P13×2 + P45×0 → pedido #501 criado só com P13 (`pedidoitens`), toast "Pedido #501 criado." sem menção | **NÃO CORRIGIDO** |
| R2-12 / PIE-33 sessão vencida | PENDENTE | toast "Unauthenticated." em inglês; não leva ao login. O diálogo continua aberto, o que é bom | **NÃO CORRIGIDO** |
| R2-08 desconto maior que a parcela | rebaixado | servidor recusa com "Desconto maior que o valor da parcela com juros e multa." O botão ainda mostra "-R$ 879,00" | **OK no servidor** |
| PIE-34 mensagens em inglês | PENDENTE | `validation.integer`, `validation.required`, `validation.min.string`, `validation.email` em toasts e campos | **NÃO CORRIGIDO** |
| PIE-35 CPF sem dígito verificador | PENDENTE | 3 CPFs inválidos salvos | **NÃO CORRIGIDO** |

---

## 4. Achados

### P2-D-01 — Usuário `support` não consegue logar sob RLS (HTTP 500)
- **Categoria:** identidade / RLS · **Severidade:** ALTA. É o primeiro acesso de toda instalação nova: o `DeployAdminSeeder` cria o admin com `support=true`. · **Estado:** CONFIRMADO
- **Onde:** `app/Http/Controllers/Api/AuthController.php:88-90` → `app/Domain/Seguranca/AuditoriaSeguranca.php:24-35` → tabela `security_events`, policy `tenant_isolation WITH CHECK app_tenant_can_operate(tenant_account_id, empresa_id)`.
- **Reprodução:** `update users set support=true`, depois `POST /api/login` → `HTTP 500`. O log mostra `new row violates row-level security policy for table "security_events"` no insert `login.suporte` com `tenant_account_id` nulo. A tela diz "O serviço de acesso está indisponível. Tente novamente mais tarde." (`j01-desk-painel.png`, 1ª execução).
- **Esperado × observado:** login com trilha de auditoria × 500. O login acontece antes do envelope, então a linha sai sem tenant e a policy canônica a recusa. É a armadilha "empresa_id nulo legítimo" do CLAUDE.md.
- **Correção:** `WITH CHECK (tenant_account_id IS NULL OR app_tenant_can_operate(...))` em `security_events`, como foi feito em `integracao_consumos`; ou registrar o evento depois de resolver o tenant da empresa do usuário. Envolver o registro em try/catch **não** é aceitável (o evento sumiria).
- **Aceite:** teste no portão `test:pgsql-rls`: login de usuário `support` como `erp_app` → 200 e 1 linha `login.suporte`. **Esforço:** P

### P2-D-02 — Usuário criado em "Usuários e permissões" não consegue usar o sistema
- **Categoria:** onboarding / SaaS · **Severidade:** ALTA. Com o envelope ligado (como na homologação), nenhum funcionário novo cadastrado pelo painel trabalha. · **Estado:** CONFIRMADO
- **Onde:** `app/Http/Controllers/Api/Admin/UsuarioController.php:71-76` (cria `User` e papéis, mas nenhum `TenantMembership`/grant).
- **Reprodução:** J10. Cria-se "Operador PIE" (toast "Usuário salvo."), e o login dele dá 200. Todas as chamadas seguintes devolvem `403 {"message":"Empresa sem vinculo de titularidade SaaS aprovado."}` e o painel mostra "Não foi possível carregar" (`j10-desk-03-operador-inicio.png`). Só existe 1 linha em `tenant_memberships` (a do admin, importada pelo CLI).
- **Impacto:** a revenda não consegue dar acesso ao próprio atendente sem rodar `saas:tenant:importar-memberships` com JSON. A mensagem culpa a empresa, quando falta é o vínculo do usuário.
- **Correção:** criar membership e grant (`can_read`/`can_operate` na empresa ativa) na mesma transação do `store`; corrigir a mensagem para "Seu usuário não tem acesso a esta empresa".
- **Aceite:** teste Postgres: o admin cria um usuário pela API, e esse usuário consegue `GET /api/admin/pedidos` → 200. **Esforço:** M

### P2-D-03 — Papel global de QUALQUER usuário vaza para todos os usuários (`papeisEfetivos`)
- **Categoria:** autorização · **Severidade:** ALTA · **Estado:** CONFIRMADO (SQL + payload + tela)
- **Onde:** `app/Models/User.php:191-193`:
  ```php
  return $this->roles()
      ->wherePivot('empresa_id', $empresaId)
      ->orWherePivotNull('empresa_id')
  ```
  SQL gerado (tinker): `... where "role_user"."user_id" = ? and "role_user"."empresa_id" = ? or "role_user"."empresa_id" is null`. O `or` escapa do filtro de `user_id`.
- **Reprodução:** com o admin tendo papel Administrador global (`empresa_id` nulo, o mesmo que `AcessoRedeDubenaSeeder.php:79` faz para o dono da rede), o operador (só papel Operador, empresa 1) recebe no login `roles: ["Administrador","Operador"]` e **151 permissões**. A SPA mostra o cabeçalho "Administrador, Operador" e o menu inteiro (Usuários, Empresas, Configurações, Histórico) (`j10-desk-03-operador-inicio.png`).
- **O que segura e o que não segura:** o Gate de RBAC usa `temPermissao` (`User.php:160-179`, duas consultas separadas e corretas): `Gate::forUser(operador)->allows('usuario.view')` = false. Mas `papeisEfetivos`/`permissoesEfetivas` alimentam:
  (a) o payload da SPA (menus e botões indevidos);
  (b) o **anti-escalonamento** de `PapelController.php:270` (quem edita papéis pode conceder o que não tem);
  (c) o escopo hierárquico e as condições ABAC do `PolicyEvaluator.php:79`. Um papel global de outra pessoa entra no cálculo de escopo e condição, e pode afrouxar restrições. Os itens (b) e (c) são **FORTE INDÍCIO** por leitura de código e não foram explorados no navegador.
- **Correção:** `->where(fn ($q) => $q->where('role_user.empresa_id', $empresaId)->orWhereNull('role_user.empresa_id'))`. O comentário em `User.php:188-190` registra a tentativa sem agrupamento.
- **Aceite:** teste com dois usuários, A com papel global e B com papel na empresa: `B->papeisEfetivos()` não contém o papel de A. Plantar a regressão (tirar o agrupamento) tem de reprovar o teste. **Esforço:** P

### P2-D-04 — Superadmin não enxerga nenhuma empresa sob RLS
- **Categoria:** SaaS / plataforma · **Severidade:** ALTA. Sem isso não há suspender, plano ou recursos por tela. · **Estado:** CONFIRMADO
- **Onde:** `app/Http/Controllers/Api/SuperAdmin/EmpresaController.php:23` (`index`) e o dashboard do `SuperAdminPainelController`. Nenhum dos dois usa `EnxergaAtravesDaRls` nem conexão de owner.
- **Reprodução:** login superadmin; `GET /api/superadmin/empresas` → `{"data":[]}`; dashboard `empresas_total: 0`, com 2 empresas e 2 assinaturas no banco (`j11_superadmin_empresas.png`).
- **Correção:** ler pela via que atravessa a RLS (trait `EnxergaAtravesDaRls`), com auditoria. **Aceite:** teste Postgres como `erp_app`: o superadmin lista as N empresas. **Esforço:** M

### P2-D-05 — Busca de cliente diferencia acento: "joao" não acha "João"
- **Categoria:** UX operacional · **Severidade:** ALTA. O atendente digita rápido e sem acento; com a resposta "Nenhum resultado.", tende a cadastrar o cliente de novo. · **Estado:** CONFIRMADO
- **Onde:** `app/Http/Controllers/Api/Admin/LookupController.php:207` (`LOWER(col) LIKE ?`); a listagem `/clientes?q=` tem o mesmo problema.
- **Reprodução:** no Novo pedido, "joao haick", "patricia" e "acougue" → "Nenhum resultado."; com acento acha (`j03-desk-02-busca-sem-acento.png`; API: `q=joao` → `[]`, `q=João` → 5).
- **Correção:** comparar com `unaccent(lower(...))` (extensão a instalar; `pg_trgm` já existe) ou coluna normalizada indexada. Atenção ao custo sob RLS. **Aceite:** "joao" acha "João Haick" no lookup e na listagem. **Esforço:** P

### P2-D-06 — `DB::table(...)->whereKey()` não existe: vira `where "key" = ?`
- **Categoria:** defeito de código · **Severidade:** ALTA · **Estado:** CONFIRMADO no seeder; FORTE INDÍCIO nos caminhos HTTP (não clicados)
- **Onde:** `app/Models/Crm/SorteioNumero.php:23`, `app/Models/Crm/ChecklistPergunta.php:24`, `app/Http/Controllers/Api/Admin/EmpresaConfigController.php:484`. `Query\Builder` (Laravel 12.69.3) não tem `whereKey` e cai no `dynamicWhere`.
- **Reprodução:** `db:seed` aborta em "→ CRM" com `SQLSTATE[42703]: column "key" does not exist ... select "tenant_account_id" from "sorteios" where "key" = 1` (`SCR\seed.log`). Consequências:
  - o DemoGuarapuavaSeeder nunca completa (gestão e o fim do CRM não entram);
  - todo número de sorteio e toda pergunta de checklist criados sem `tenant_account_id` explícito falham;
  - salvar configuração financeira da empresa (plano de contas/centro de custo) com envelope ativo dá 500.
- **Correção:** `->where('id', $x)`. **Aceite:** teste Postgres criando `SorteioNumero` via relação, e salvando a config da empresa com `planoconta_id`. **Esforço:** P

### P2-D-07 — `GET /config-global` dá 500 para revenda sem a linha de configuração
- **Categoria:** RLS / onboarding · **Severidade:** ALTA (revenda nova) · **Estado:** CONFIRMADO
- **Onde:** `app/Models/ConfigGlobal.php` (`$fillable` sem `tenant_account_id`) + `app/Domain/Tenant/BelongsToGrupo.php` (só preenche a chave se ela estiver no `$fillable`. O `BelongsToTenant` já foi corrigido disso, o `BelongsToGrupo` não). Mesmo padrão em `Geografico/ImportacaoLogradouro.php`. Disparo: `ConfigGlobalController.php:26,59` (`firstOrCreate`).
- **Reprodução:** abrir /monitora → 4× `500 ... new row violates row-level security policy for table "config_globais"` (`SCR\j02-prod.out`). Afeta a chave do Google Maps (`satelites/extraApi.ts:206`) e Configurações > Sistema.
- **Correção:** adotar em `BelongsToGrupo` a mesma detecção por catálogo de `BelongsToTenant::temChaveSaas`. **Aceite:** teste Postgres `GET /config-global` em grupo sem linha → 200. **Esforço:** P

### P2-D-08 — PIE-17 incompleto: "R$ 1.000" vira R$ 1,00
- **Severidade:** MÉDIA. O teto da alçada cai 1000×: o vendedor fica sem conceder, ou o gerente acha que limitou e não limitou. · **Estado:** CONFIRMADO
- **Onde:** `frontend/src/lib/format.ts:58-62`. Sem vírgula, "1.000" passa na regex `^-?\d+(\.\d+)?$` e vira `1`.
- **Reprodução:** J08: teto "R$ 1.000" → lista "máx. R$ 1,00", `alcada_descontos.valor_max = 1.00`.
- **Correção:** tratar ponto seguido de exatamente 3 dígitos (sem vírgula) como milhar, ou recusar a ambiguidade. **Aceite:** `decimalBR('1.000') === 1000`, `decimalBR('1.000.000') === 1000000`, `decimalBR('1.5') === 1.5`. **Esforço:** P

### P2-D-09 — Conta nova fica "Aberto" e não fecha; não há como abrir
- **Categoria:** caixa · **Severidade:** MÉDIA · **Estado:** CONFIRMADO
- **Onde:** `CaixaService::criarConta` (`app/Domain/Caixa/CaixaService.php:338-352`) não define `fechado` (default `false`) nem cria fechamento; `CaixaTab.tsx:29` mostra "Fechar" quando `fechado=0`; `fechar()` exige `ContaFechamento` aberto (l. 116-131).
- **Reprodução:** J06. "Banco - Conta Movimento" aparece "Aberto"; Fechar → "Não há caixa aberto para fechar." e o botão Abrir não aparece. A conta recebeu R$ 114,50 mesmo assim. Na mesma linha há outro botão "Abrir" (link "Abrir registro"), que confunde (`j06-01-caixas.png`).
- **Correção:** a conta nasce `fechado=true`, ou a tela oferece Abrir quando não há fechamento aberto. Renomear o link da linha para "Ver movimentos". **Esforço:** P

### P2-D-10 — Cadastro de cliente novo não tem telefone, e o servidor exige telefone ou endereço
- **Severidade:** MÉDIA. No disk-gás o primeiro dado é o telefone de quem liga. · **Estado:** CONFIRMADO
- **Onde:** `frontend/src/features/clientes/ClienteFormPage.tsx:219` (a aba Contatos só existe com `editId`).
- **Reprodução:** J09. Nome + CPF → toast "Informe ao menos um telefone ou o endereço (cidade e número) do cliente." e a tela não tem onde informar o telefone (`j09-desk-valido.png`, 1ª rodada).
- **Correção:** campo de telefone no cadastro novo, gravado na mesma transação. **Esforço:** M

### P2-D-11 — A página 404 derruba o app ("Algo deu errado nesta tela")
- **Severidade:** MÉDIA · **Estado:** CONFIRMADO (404); FORTE INDÍCIO (`SaMigracaoPage.tsx:422`, botão "Baixar o que não entrou")
- **Onde:** `components/ui/button.tsx` com `asChild`: `{loading && <Loader2/>}{children}` entrega `[false, <Link/>]` ao `Slot`, que exige um único filho. `NotFoundPage.tsx`.
- **Reprodução:** `/novo/app/rota-que-nao-existe` → `Error: Slot failed to slot onto its children`; tela sem menu e com "Recarregar", que repete o erro (`j02-desk_rota-que-nao-existe.png`).
- **Correção:** com `asChild`, renderizar só `children` (ou usar `Slottable`). **Aceite:** teste vitest renderizando `<Button asChild><a/></Button>`. **Esforço:** P

### P2-D-12 — Quantidade aceita 1,5 botijão e 1.000.000 sem teto
- **Severidade:** MÉDIA · **Estado:** CONFIRMADO
- **Reprodução:** na UI, "1e3" vira total R$ 120.000,00 e "1,5" vira R$ 180,00. Na API, pedidos #502 (1,5) e #503 (1.000.000) foram **criados**; só ao concluir o estoque barra o #503.
- **Correção:** inteiro para produto unitário e teto configurável por tenant; na UI, `step=1` e recusar a notação exponencial. **Esforço:** P

### P2-D-13 — Revenda sem condição de pagamento não consegue criar pedido, e a tela não diz por quê
- **Severidade:** MÉDIA (onboarding) · **Estado:** CONFIRMADO
- **Reprodução:** o DemoGuarapuavaSeeder cria 0 `condicaopagamentos` (os 500 pedidos semeados ficaram sem condição). No Novo pedido, a condição mostra "Nenhum resultado." e o salvar pede "a condição de pagamento".
- **Correção:** semear condições padrão na criação do tenant e, no popover vazio, linkar para "Configurações > Financeiro > Condições". **Esforço:** P

### P2-D-14 — Não existe onboarding de revenda nova no produto
- **Categoria:** produto SaaS · **Severidade:** MÉDIA · **Estado:** CONFIRMADO
- O superadmin não tem "nova revenda/empresa" (`SCR\j11.out`: 0 botões; rotas `routes/api.php:1017-1066` sem POST de empresa/tenant).
- Para a revenda local operar foi preciso: JSON de titularidade + `saas:tenant:importar --apply` + `preencher-chave-operacional` + `proteger-configuracao-grupo` + `legacy-full`, e ainda criar as condições à mão. Somados P2-D-01/02/07/13, uma revenda nova não passa do login sem intervenção técnica.
- **Correção:** assistente "Nova revenda" no superadmin que execute essa sequência numa transação. **Esforço:** G

### P2-D-15 — Seleção de cliente/produto ruim por teclado
- **Severidade:** MÉDIA (o atendente digita com o fone no ombro) · **Estado:** CONFIRMADO
- **Onde:** `components/ui/async-select.tsx` (lista de `<button>`, sem `role=listbox`/`option`, sem setas).
- **Reprodução:** J12. Enter abre a busca; ↓ e Enter não escolhem; o Tab alterna só entre o campo e as opções. Escolher exige Tab + Enter, e não há atalho para "Novo pedido" (Alt+N não faz nada).
- **Correção:** padrão combobox (cmdk/Radix), com ↓/↑/Enter e Enter escolhendo o 1º resultado; atalho global. **Esforço:** M

### P2-D-16 — BaixaDialog não permite receber parte do valor
- **Severidade:** MÉDIA (produto) · **Estado:** CONFIRMADO por tela e leitura (`financeiro/tabs/BaixaDialog.tsx:26,35-43`: envia só juros/multa/desconto; o valor é sempre o integral)
- **Impacto:** o cliente que paga metade não tem como ser registrado; desconto negativo ("-10") aumenta o valor a receber na tela (Receber R$ 130,00). A aceitação disso pelo servidor **não foi testada**.
- **Esforço:** M

### P2-D-17 — Sessão vencida e reload no Novo pedido
- **Severidade:** BAIXA (detalha PIE-33/34, já pendentes) · **Estado:** CONFIRMADO. Token vencido ao salvar → toast "Unauthenticated." e nenhum caminho para relogar sem perder o pedido; o reload descarta o rascunho.

### P2-D-18 — `npm run dev` não fala com a API
- **Severidade:** BAIXA (ambiente de dev) · **Estado:** CONFIRMADO. `vite.config.ts:22-26` faz proxy só de `/api`, mas `base: '/novo/app/'` faz `lib/api.ts:13` chamar `/novo/api`. Resultado: 404 "did you mean /novo/app/novo/api/login". O comentário de `api.ts:9` ("Em DEV cai em /api/admin") está desatualizado. **Esforço:** P

### P2-D-19 — Acessibilidade automática
- **Severidade:** BAIXA–MÉDIA · **Estado:** CONFIRMADO (axe). Contraste insuficiente nas 5 telas principais (10–13 nós cada) e `aria-valid-attr-value` crítico na aba "ativos" de /clientes. **Esforço:** P

### P2-D-20 — Alçada: regra geral duplicada e "Produto (id)"
- **Severidade:** BAIXA · **Estado:** CONFIRMADO. Duas regras gerais ativas para o mesmo escopo (ids 1 e 2), sem dizer qual vale; o campo de produto pede o id interno. **Esforço:** P

---

## 5. O que está bem (verificado no navegador)
- Login sem enumeração de usuário; validação nativa no vazio; superadmin idem.
- Duplo clique em "Criar pedido" → 1 único POST.
- Concluir e cancelar pedido pedem confirmação explicando o efeito; o estoque insuficiente é barrado com mensagem clara.
- Hora local no caixa e "hoje" correto às 22:30 (PIE-32).
- Desconto maior que a parcela é recusado pelo servidor.
- Layout sem rolagem horizontal em 390 px nas 8 telas testadas; o Novo pedido cabe no celular.
- Sugestão de endereço, telefone e último pedido ao escolher o cliente; "Repetir último pedido".

## 6. O que não foi possível executar, e por quê
- **Relatórios, estoque e configurações em profundidade:** as telas abriram sem erro, mas não houve geração de relatório nem movimento de estoque pela UI, por falta de tempo (a sessão caiu uma vez por limite de uso).
- **Operador navegando com papel restrito:** o P2-D-02 impede que o usuário criado faça qualquer coisa. Por isso não foi possível provar no navegador que ele "vê só o que deve"; o que se viu foi o oposto no menu (P2-D-03). Não concedi a membership pelo CLI
  para contornar: preferi registrar o defeito como ele está.
- **Exploração do P2-D-03 (b) e (c):** leitura de código, sem PoC.
- **Tempos reais:** o servidor embutido do PHP no Windows é monothread. Os tempos servem só para comparação relativa.
- **Homologação:** não foi tocada (regra do briefing). Os achados P2-D-01/03/04 dependem de dados que podem existir lá (admin `support`, papel global do dono), e isso **não foi verificado na VPS**.

## 7. Parecer da Frente D

**REPROVADO** para operação SaaS com revenda nova. Com RLS e envelope ligados, uma revenda
recém-criada esbarra em 500 no login do admin de suporte (P2-D-01) e não consegue dar
acesso a funcionários pelo painel (P2-D-02). O superadmin não a enxerga (P2-D-04). Há 500
em config e sorteio (P2-D-06/07), e um vazamento de papel global para todos os usuários
(P2-D-03).

A jornada principal do disk-gás (telefone → pedido → concluir → receber) **funciona** para
o usuário que já tem vínculo. Ainda assim, três correções da Rodada 1 seguem pendentes no
uso real: R2-09, R2-10 e PIE-34. PIE-17 está parcial.

## 8. Estado deixado
- Banco `pie_r2_demo` mantido no Postgres local para reprodução (`drop database pie_r2_demo` remove). O Postgres não foi parado: já estava no ar antes desta frente.
- Servidores `php artisan serve` e Vite iniciados por esta frente foram encerrados ao final.
- Nenhum arquivo versionado alterado; só este relatório foi criado. `storage/logs/laravel.log` e `storage/framework/cache` locais (ignorados pelo git) receberam entradas.
