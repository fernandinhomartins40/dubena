# Plano Arquitetural — Controle de Acesso, Hierarquia e Governança (ERP-NOVO)

> **Status:** Especificação oficial (v1). Documento de arquitetura — **não** contém implementação.
> **Escopo:** Autenticação, autorização (RBAC/ABAC), hierarquia organizacional, multi-tenancy,
> menus dinâmicos, segurança, banco de dados, backend, SPA, central de administração e escalabilidade.
> **Base:** Auditoria do código atual do ERP-NOVO (Laravel 12 + React/Vite SPA + PostgreSQL).

---

## 0. Sumário executivo

O ERP-NOVO **já possui uma base sólida** de autenticação e autorização, mas ela cobre apenas
o "miolo" de um modelo enterprise. Este plano parte do que existe, identifica as lacunas e
projeta a evolução para um sistema **multi-tenant, RBAC+ABAC híbrido, administrável por
interface, auditável e escalável** para redes de franquias com múltiplas filiais e centenas
de usuários.

**Decisão central:** adotar **RBAC como espinha dorsal** (papéis → permissões granulares
`modulo.acao`), **estendido com ABAC** para dois eixos: (a) **escopo hierárquico** (a quais
filiais/departamentos o papel se aplica) e (b) **condições de atributo** (limites de valor,
horário, status do recurso). Isolamento entre empresas garantido pelas **duas barreiras já
existentes** (global scope + Row-Level Security no Postgres).

---

## 1. Estado atual (auditoria do código)

### 1.1 O que já existe e funciona

| Camada | Implementação atual | Arquivo |
|---|---|---|
| **Autenticação** | Sanctum, duplo modo: cookie (SPA stateful) + token Bearer (apps). Login com `Auth::attempt`, regenera sessão, bloqueia inativo. | `app/Http/Controllers/Api/AuthController.php` |
| **Modelo de permissão** | RBAC: `roles` ↔ `permissions` (M:N) + `role_user` (papel por usuário **por empresa**). Permissões são strings `modulo.acao`. | `0001_01_01_000100_create_tenant_e_rbac_tables.php` |
| **Catálogo de permissões** | Fonte única `PermissaoCatalogo` (28 módulos, ações view/create/edit/delete + algumas especiais). RbacSeeder popula a tabela. | `app/Domain/Shared/PermissaoCatalogo.php` |
| **Papéis-base** | Administrador, Gerente, Operador, Entregador — criados por grupo. | `database/seeders/RbacSeeder.php` |
| **Resolução de permissão** | `User::temPermissao()`, `permissoesEfetivas()`, `papeisEfetivos()`. Suporte = bypass total. Papéis por empresa **ou** globais (pivot `empresa_id` nulo). | `app/Models/User.php` |
| **Tenant** | `TenantContext` (scoped), `ResolveTenant` (middleware) resolve empresa+grupo do usuário, troca via header `X-Empresa-Id`. | `app/Http/Middleware/ResolveTenant.php` |
| **Isolamento (2 barreiras)** | 1ª: global scope `BelongsToTenant`/`BelongsToGrupo`. 2ª: RLS Postgres auto-descoberta (107 tabelas) + role `erp_app` sem bypass. | `2026_06_26_000300_rls_tenant_completa.php`, `..._000400_rls_role_app_sem_bypass.php` |
| **Auditoria de dados** | Trait `Auditavel` → `audit_logs` (quem/quando/empresa/antes/depois/IP), oculta campos `encrypted`. | `app/Domain/Shared/Auditavel.php` |
| **Autorização na SPA** | `useAuth().can(perm)` (support = true). Menu declarativo filtra por permissão. Troca de empresa via `EmpresaSwitcher`. | `frontend/src/lib/auth.tsx`, `layouts/AppShell.tsx` |
| **Payload de auth** | `/me` e `/login` devolvem `payloadAuth()` com `roles` + `permissions` efetivas na empresa ativa. | `routes/api.php`, `User::payloadAuth()` |

### 1.2 Lacunas e dívidas identificadas (a corrigir/evoluir)

1. **Hierarquia rasa:** só existe `grupo → empresa`. **Não há** filial, departamento, setor, equipe, cargo (no sentido RBAC). A entidade `Empresa` tem flag `matriz` mas sem árvore de filiais.
2. **Sem Gates/Policies do Laravel:** autorização é 100% via `temPermissao()` manual nos controllers. Funciona, mas é fácil esquecer uma checagem (risco de IDOR de função). Não há ponto único de enforcement.
3. **Sem ABAC:** permissões são binárias (`pode/não pode`). Não há "aprovar até R$ X", "estornar só caixa próprio", "ver só sua equipe".
4. **Super-admin hardcoded:** `support=true` é bypass global gravado no `users`. Não há conceito formal de "Super Admin da Plataforma" separado de tenant, nem trilha de quando o suporte agiu.
5. **Sem UI de administração:** **não existem** telas nem rotas de API para criar/editar usuários, papéis, atribuir permissões. Hoje só por seeder/código — viola o requisito "sem alterar código".
6. **Drift de catálogo:** o menu da SPA referencia permissões fantasma (`cidade.view`, `estoquesetor.view`, `nfemitida.view`, `vendavalegas.view`) que **não existem** no catálogo; e o controller `GeoController` exige `cidade.view` também ausente. Mascarado pelo bypass do `support`.
7. **Granularidade só de módulo×ação:** não há permissão por **campo**, **widget de dashboard**, **relatório específico**, **exportação/importação** como itens independentes (embora a convenção `modulo.acao` comporte isso).
8. **Segurança de sessão incompleta:** sem 2FA, política de senha, expiração configurável, listagem/revogação de sessões ativas, controle de dispositivos, bloqueio por tentativas (existe só rate-limit de login por IP).
9. **Auditoria parcial:** cobre CRUD de models com a trait, mas **não** cobre eventos de segurança (login, logout, troca de empresa, mudança de permissão/papel, falha de autorização).
10. **Sem histórico de permissões:** alterações de papel/permissão não são versionadas.

---

## 2. Arquitetura geral de autorização

### 2.1 Modelo escolhido: **RBAC + ABAC (híbrido)**

| Camada | Responsabilidade | Mecanismo |
|---|---|---|
| **RBAC** (base) | "O quê" o usuário pode fazer. | Papéis → permissões `modulo.acao`. |
| **ABAC — escopo** | "Onde" pode fazer (quais filiais/deptos). | Atribuição papel↔usuário carrega um **escopo hierárquico**. |
| **ABAC — condição** | "Sob quais condições". | Regras de atributo na permissão (limite de valor, horário, ownership). |

**Por que híbrido (e não só RBAC ou só ABAC):**

- **RBAC puro não basta** para um ERP: "Gerente" precisa significar coisas diferentes na Filial A e na Filial B; "aprovar compra" depende do **valor**; "estornar caixa" deveria ser limitado ao **caixa que o operador abriu**. RBAC binário não expressa isso sem explodir o número de papéis ("Gerente-FilialA-até5mil"…), o que é ingovernável.
- **ABAC puro é poderoso, porém opaco e caro de administrar** — políticas em linguagem de regras são difíceis de auditar por um administrador de empresa. ERPs corporativos esperam "perfis" nomeados.
- **Híbrido = o melhor dos dois:** o administrador pensa em **perfis (papéis)** — familiar e auditável; o sistema aplica **escopo + condições** por baixo, cobrindo os casos que RBAC não alcança. É o padrão de mercado (SAP, Salesforce, Azure RBAC + conditions).

**Compatibilidade com o existente:** o RBAC já está pronto e em uso. ABAC entra como **extensão aditiva** (novas colunas/tabelas e um avaliador de política), sem reescrever o que funciona.

### 2.2 Princípios de design

1. **Default-deny:** nada é permitido sem permissão explícita. Suporte é a única exceção (e auditada).
2. **Enforcement em camadas (defense-in-depth):** middleware → policy/gate → service → RLS no banco. Uma falha numa camada é contida pela próxima.
3. **Fonte única da verdade:** o catálogo de permissões permanece centralizado e versionado; teste de contrato garante 1:1 entre catálogo, controllers e menu.
4. **Tudo administrável por interface:** criar/editar perfis, atribuir permissões e escopos sem deploy.
5. **Tudo auditável:** toda decisão sensível e toda mudança de acesso gera trilha.

---

## 3. Hierarquia organizacional

### 3.1 Árvore proposta

```
PLATAFORMA  (Super Admin — fora de tenant)
│
└── GRUPO  (rede / franqueadora)            [já existe: grupos]
    │
    └── EMPRESA (tenant operacional / CNPJ)  [já existe: empresas]
        │
        └── FILIAL / UNIDADE                 [NOVO: unidades]
            │
            └── DEPARTAMENTO                 [NOVO: departamentos]
                │
                └── SETOR / EQUIPE           [NOVO: setores/equipes]
                    │
                    └── USUÁRIO (com CARGO)  [já existe: users + NOVO: cargos RBAC]
```

### 3.2 Responsabilidade de cada nível

| Nível | É tenant? | Responsabilidade | Quem administra |
|---|---|---|---|
| **Plataforma** | Não | Operação da plataforma, criação de grupos/empresas, suporte. **Nunca opera dados de negócio** sem registro. | Super Admin (Anthropic/operador da plataforma) |
| **Grupo** | Escopo de apoio | Padrões da rede: papéis-modelo, cadastros de apoio compartilhados, políticas. | Admin do Grupo (franqueador) |
| **Empresa** | **Sim** (isolamento total) | Unidade de isolamento de dados/usuários/config. CNPJ. | Administrador da Empresa |
| **Filial/Unidade** | Sub-escopo | Local físico/operacional dentro da empresa. Escopo de dados e de pessoas. | Gerente de Filial |
| **Departamento** | Sub-escopo | Agrupamento funcional (Financeiro, Vendas, Fiscal…). | Gerente/Coordenador |
| **Setor/Equipe** | Sub-escopo | Time de execução; base para "ver só minha equipe". | Supervisor |
| **Usuário** | — | Identidade que recebe papéis com escopo. | — |

> **Compatibilidade:** Filial/Departamento/Setor entram como **novas tabelas escopadas por
> empresa** (`BelongsToTenant`), portanto já herdam o isolamento RLS automaticamente. A flag
> `empresas.matriz` existente vira a raiz natural da árvore de unidades.

### 3.3 Escopo hierárquico nas atribuições

A tabela de atribuição papel↔usuário ganha um **escopo**: a partir de qual nó da árvore o
papel vale (e se desce para os filhos). Exemplos:

- "Gerente" **na Filial Centro** (e em todos os deptos abaixo dela).
- "Operador de Caixa" **no Setor Caixa 1** apenas.
- "Diretor" **na Empresa inteira** (raiz).

---

## 4. Níveis administrativos (perfis de administração)

| Nível administrativo | O que administra | Limite |
|---|---|---|
| **Super Administrador da Plataforma** | Grupos, empresas, planos, suporte global, feature flags. | Toda a plataforma; ações auditadas como "suporte". |
| **Administrador do Grupo** | Papéis-modelo da rede, cadastros de apoio do grupo, criação de empresas do grupo. | Só seu grupo. |
| **Administrador da Empresa** | Usuários, papéis, permissões, unidades, deptos, setores, config da empresa. | Só sua empresa (tenant). |
| **Administrador Financeiro** | Tudo de Financeiro/Caixa/Cobrança + papéis **restritos a esses módulos**. | Módulos financeiros da sua empresa. |
| **Administrador Fiscal** | Config fiscal, NF-e/NFC-e, papéis fiscais. | Módulos fiscais da sua empresa. |
| **Gerente** | Operação ampla da sua unidade; aprova dentro de limites; não mexe em config/usuários. | Sua filial/depto. |
| **Supervisor** | Operação da sua equipe; aprova nível 1. | Seu setor/equipe. |
| **Operador** | Execução do dia a dia (vender, lançar, baixar). | Seus recursos. |
| **Consulta** | Somente leitura. | Conforme escopo. |

> **Administração delegada:** "Administrador da Empresa" pode **criar papéis** mas **nunca
> conceder permissão que ele próprio não possui** (princípio do menor privilégio / no
> privilege escalation). Validado no backend.

---

## 5. Sistema de perfis (papéis personalizados)

### 5.1 Perfis-modelo (sementes, editáveis)

Diretoria, Financeiro, Vendas, Fiscal, Estoque, RH, Compras, Operacional, Auditoria, Suporte —
criados pelo RbacSeeder como **modelos**; cada empresa pode adotar, **duplicar**, **editar** e
**desativar** sem código.

### 5.2 Ciclo de vida do perfil (tudo por UI)

```
Criar  →  Duplicar (clona permissões)  →  Editar (marca/desmarca permissões + escopo)
       →  Atribuir a usuários  →  Desativar (mantém histórico, bloqueia novas atribuições)
```

- **Papéis de grupo (modelo)** vs **papéis de empresa (locais):** o admin da empresa pode
  partir de um modelo do grupo e personalizar localmente.
- **Desativação ≠ exclusão:** papel desativado preserva trilha de auditoria; exclusão só se
  nunca usado.
- **Versionamento:** cada alteração de permissões do papel gera um snapshot (histórico).

---

## 6. Permissões granulares

### 6.1 Convenção e níveis

Mantém-se `modulo.acao`, **estendida** para cobrir todos os alvos pedidos:

| Alvo | Padrão de chave | Exemplo |
|---|---|---|
| Módulo | `modulo.view` | `financeiro.view` |
| Tela/Subtela | `modulo.tela.view` | `financeiro.conciliacao.view` |
| Menu | (derivado de `modulo.view`) | item some se sem `view` |
| Botão/Ação | `modulo.acao` | `caixa.estornar` |
| Campo | `modulo.campo.{nome}.{view\|edit}` | `cliente.campo.limite_credito.edit` |
| API | (a rota exige a mesma chave da ação) | `pedido.create` |
| Relatório | `relatorio.{slug}.view` | `relatorio.dre.view` |
| Exportação | `modulo.export` | `cliente.export` |
| Importação | `modulo.import` | `produto.import` |
| Dashboard | `dashboard.{slug}.view` | `dashboard.vendas.view` |
| Widget | `widget.{slug}.view` | `widget.faturamento_dia.view` |
| Integração | `integracao.{slug}.{view\|edit}` | `integracao.sefaz.edit` |
| Workflow | `workflow.{slug}.{executar\|aprovar}` | `workflow.compra.aprovar` |

Cada item é **habilitável individualmente**. O catálogo continua sendo a fonte da verdade;
a granularidade fina entra **incrementalmente** (não é preciso explodir tudo de uma vez).

### 6.2 Permissões de campo (field-level)

Para campos sensíveis (limite de crédito, custo, comissão, dados pessoais), a permissão
`...campo.{nome}.{view|edit}` controla **render** (SPA) e **mass-assignment/serialização**
(backend). Sem `view` o campo não viaja no JSON; sem `edit` ele volta read-only e é ignorado
na escrita.

---

## 7. Permissões por ação (catálogo de verbos)

Verbos independentes, cada um uma permissão própria:

`view, create, edit, delete, cancelar, aprovar, reprovar, estornar, emitir, fechar_caixa,
reabrir_caixa, export, import, imprimir, enviar, assinar, baixar (financeiro), conciliar`

- Já existem: `view/create/edit/delete`, `fiscal.emitir`, `produto.config/preco`.
- A adicionar ao catálogo conforme o módulo: `caixa.fechar/reabrir/estornar`, `financeiro.baixar`,
  `pedido.cancelar/aprovar`, `nfe.cancelar`, `*.export/import/imprimir/assinar`.

Verbos sensíveis (`estornar`, `reabrir_caixa`, `aprovar`, `cancelar`) são candidatos naturais a
**condições ABAC** (limite, ownership, janela de tempo).

---

## 8. Multi-tenancy do controle de acesso

Tudo do RBAC/ABAC **respeita o isolamento já garantido pelas 2 barreiras**:

| Recurso | Como é isolado | Estado |
|---|---|---|
| Usuários | `users.empresa_id` + `empresa_user` (multi-empresa) + RLS | ✅ existe |
| Permissões (catálogo) | Global (mesmo conjunto p/ todos) — correto, é vocabulário | ✅ |
| Papéis | `roles.grupo_id`; papéis de empresa via escopo | ✅ (evoluir p/ empresa) |
| Atribuições (`role_user`) | carrega `empresa_id` | ✅ existe |
| Configurações | `empresa_configs`/`config_fiscais` escopados + RLS | ✅ (corrigido) |
| Perfis personalizados | escopados por empresa/grupo | a implementar |
| Logs/auditoria | `audit_logs.empresa_id` + filtro | ✅ (ampliar) |
| Arquivos | storage por `empresa_id/` (path namespacing) | a definir |
| Integrações | credenciais por empresa, escopadas + `encrypted` | ✅ (config fiscal já cifra CSC) |

**Garantia central:** nenhuma empresa enxerga/administra dados de outra — provado em runtime
(RLS bloqueia query crua cross-tenant; ver `RlsCoberturaTest` e o plano de tenancy).

---

## 9. Menus dinâmicos (SPA)

Já é declarativo (`AppShell.NAV` filtrado por `can()`). Evolução:

1. **Endpoint de navegação derivado do servidor** (`/me` passa a incluir a árvore de menu
   permitida) **ou** manter declarativo na SPA com o catálogo sincronizado — recomenda-se
   **manter declarativo** (mais simples, já funciona) e **corrigir o drift** (item 1.2.6).
2. **Filtragem em cascata:** menu → submenu → tela → botão → campo, todos por `can()`.
3. **Dashboards/relatórios/widgets** entram no menu condicionados às permissões `dashboard.*`,
   `relatorio.*`, `widget.*`.
4. **Rota protegida:** um guard de rota (`<RequirePermission>`) nega acesso direto por URL,
   não só esconde o item (defense-in-depth no front; o backend é a autoridade final).

---

## 10. Segurança

| Recurso | Proposta | Estado |
|---|---|---|
| **Auditoria de dados** | `Auditavel` → `audit_logs`. | ✅ existe |
| **Auditoria de segurança** | Novos eventos: login ok/falha, logout, troca de empresa, criação/edição de papel, concessão/revogação de permissão, falha de autorização (403). | a implementar |
| **Histórico de login** | Tabela `login_logs` (user, ip, user-agent, sucesso, motivo). | a implementar |
| **Histórico de permissões** | Snapshot versionado a cada alteração de papel/atribuição. | a implementar |
| **Sessões ativas** | Listar tokens Sanctum + sessões; revogar individual/todas. | base existe (Sanctum) |
| **Controle de dispositivos** | `app_devices` já existe (mobile); estender p/ web (fingerprint + aprovação). | parcial |
| **Bloqueio automático** | Lockout após N falhas (por usuário+IP), além do rate-limit atual. | rate-limit ✅; lockout a fazer |
| **2FA** | TOTP opcional por usuário; obrigatório para papéis administrativos. | a implementar |
| **Política de senha** | Min. tamanho, complexidade, expiração, histórico, reuso. | a implementar |
| **Expiração de sessão** | TTL configurável por empresa; idle timeout. | a implementar |
| **Cifra de segredos** | cast `encrypted` (CSC fiscal já usa). | ✅ padrão estabelecido |

---

## 11. Banco de dados

### 11.1 Tabelas existentes (mantidas)

`grupos, empresas, empresa_user, users, roles, permissions, permission_role, role_user, audit_logs`.

### 11.2 Tabelas novas (propostas)

```
unidades            (id, empresa_id, parent_id?, tipo[filial|matriz], nome, cnpj?, ...)   -- BelongsToTenant
departamentos       (id, empresa_id, unidade_id, nome)                                    -- BelongsToTenant
setores_org         (id, empresa_id, departamento_id, nome)                               -- BelongsToTenant
cargos              (id, empresa_id|grupo_id, nome, role_id?)         -- cargo opcional vincula papel padrão
role_user.scope     -> estender role_user com: unidade_id?, departamento_id?, setor_id?, herda_filhos(bool)
permission_conditions (id, permission_id, tipo[limite|ownership|horario], parametros json) -- ABAC
role_versions       (id, role_id, snapshot json, alterado_por, criado_em)                  -- histórico
login_logs          (id, user_id?, email, empresa_id?, ip, user_agent, sucesso, motivo, criado_em)
security_events     (id, empresa_id?, user_id?, tipo, alvo, detalhes json, ip, criado_em)
user_2fa            (user_id, secret_cifrado, habilitado, confirmado_em, recovery_codes json)
password_policies   (empresa_id, min_len, exige_complexidade, expira_dias, historico_qtd)
sessions/tokens     -> usar Sanctum personal_access_tokens + sessions (já existem)
```

### 11.3 Índices, chaves e constraints

- **Tenant:** todas as novas tabelas com `empresa_id` (ou `grupo_id`) → entram **automaticamente**
  na RLS auto-descoberta. Índice composto `(empresa_id, <fk natural>)`.
- **Atribuições:** `role_user` PK composta já existe; adicionar índice em `(user_id, empresa_id)`.
- **Permissões:** `permissions.chave` unique (existe); `permission_role` PK composta (existe).
- **Hierarquia:** `unidades.parent_id` self-FK; CHECK para evitar ciclo (validação na app).
- **Auditoria:** `audit_logs`/`login_logs`/`security_events` indexados por `(empresa_id, criado_em)`
  para relatório rápido; particionamento por data quando o volume exigir (escala).

### 11.4 Escalabilidade do schema

- RLS + índices por `empresa_id` mantêm consultas O(dados-da-empresa), não O(plataforma).
- Tabelas de log são **append-only** → candidatas a particionamento mensal e retenção.
- Catálogo de permissões é pequeno e cacheável globalmente.

---

## 12. Backend — onde a permissão é validada

**Arquitetura de enforcement em 4 camadas (defense-in-depth):**

| Camada | Papel | Implementação proposta |
|---|---|---|
| **1. Middleware** | Autentica (Sanctum) e resolve tenant (`ResolveTenant`). Pode barrar por permissão grossa na rota. | já existe; adicionar middleware `can:permission` opcional por rota |
| **2. Gate/Policy** | **Ponto único de autorização** por recurso/ação. Substitui as checagens `temPermissao()` espalhadas. | **introduzir Gates/Policies** que internamente chamam `temPermissao()` + avaliador ABAC |
| **3. Service** | Regras de negócio + condições ABAC que dependem do estado (limite, ownership). | avaliador `PolicyEvaluator` injetado nos services sensíveis |
| **4. RLS (banco)** | Isolamento de dados por tenant — última linha. | já existe ✅ |

**Justificativa:** hoje a autorização vive só na camada 1.5 (`temPermissao()` manual no
controller), o que é frágil (fácil esquecer). Centralizar em **Gates/Policies** dá um ponto
único, testável e auditável, sem jogar fora o `temPermissao()` (vira a base do Gate). O
**avaliador ABAC** roda na policy/service porque precisa do recurso carregado (ex.: valor do
pedido, dono do caixa).

**Avaliador ABAC (esboço conceitual):** `permite(user, acao, recurso): bool` =
`RBAC.tem(user, acao)` **E** `escopoCobre(user, recurso.unidade)` **E**
`condições(permission, recurso).todas_satisfeitas`.

---

## 13. Frontend SPA — consumo de permissões

| Aspecto | Proposta | Estado |
|---|---|---|
| **Carregamento inicial** | `/me` traz `roles` + `permissions` efetivas + (novo) `scopes` e flags de 2FA. | ✅ base |
| **Cache** | TanStack Query (`['me']`, staleTime 5min). Invalida em troca de empresa/logout. | ✅ existe |
| **Atualização** | `refresh()` após trocar empresa ou quando o admin alterar o próprio acesso. | ✅ existe |
| **Renderização condicional** | `can(perm)` em botões/campos; helper `<Can permission="...">`. | ✅ `can()`; criar `<Can>` |
| **Proteção de rotas** | `<RequirePermission>` envolvendo rotas; redireciona/403. | a implementar |
| **Componentes protegidos** | Field-level: campos sem `view` não renderizam; sem `edit` ficam read-only. | a implementar |
| **Menus dinâmicos** | já filtra por `can()`; **corrigir chaves fantasma**. | ⚠️ drift |

> **Princípio:** a SPA **esconde/desabilita** por UX, mas **a autoridade é o backend**. Nunca
> confiar só no front.

---

## 14. Central de Administração de Acessos (UI)

Nova área `Administração → Acessos` (visível só com `acesso.admin`):

| Módulo da central | Função | Permissão |
|---|---|---|
| **Usuários** | CRUD, ativar/inativar, reset de senha, atribuir papéis+escopo, forçar 2FA. | `usuario.*` |
| **Perfis (papéis)** | Criar/duplicar/editar/desativar, marcar permissões, definir escopo. | `papel.*` |
| **Permissões** | Visualizar catálogo, montar perfis (não cria chave nova — é código). | `papel.edit` |
| **Grupos/Empresas** | (Super Admin/Admin Grupo) gerir tenants. | `empresa.*`, `grupo.*` |
| **Unidades/Filiais** | Árvore de unidades. | `unidade.*` |
| **Departamentos/Setores/Equipes** | Estrutura organizacional. | `departamento.*`, `setor.*` |
| **Cargos** | Cargo ↔ papel padrão. | `cargo.*` |
| **Sessões ativas** | Listar/revogar. | `seguranca.sessao` |
| **Histórico/Auditoria** | Trilha de dados + eventos de segurança + histórico de permissões. | `auditoria.view` |

**Novas chaves de permissão necessárias** (adicionar ao catálogo): `usuario`, `papel`, `unidade`,
`departamento`, `setor`, `cargo`, `seguranca`, `auditoria` × ações.

Toda administração é **por interface**, sem deploy — requisito atendido pelas novas rotas de API
+ telas.

---

## 15. Escalabilidade

| Cenário | Suporta? | Como |
|---|---|---|
| Pequena empresa (1 unidade, poucos usuários) | ✅ | Hierarquia é opcional; usa só empresa+papéis. |
| Média (deptos, dezenas de usuários) | ✅ | Unidades/deptos + papéis com escopo. |
| Grande (centenas de usuários, múltiplas filiais) | ✅ | Escopo hierárquico + índices por empresa + RLS. |
| Rede de franquias | ✅ | Grupo = rede; papéis-modelo herdados; isolamento por empresa. |
| Múltiplas filiais | ✅ | Árvore `unidades` + escopo nas atribuições. |
| Milhares de empresas | ✅ | RLS O(dados-da-empresa); logs particionáveis; catálogo cacheado. |

**Limites e mitigação:** o gargalo típico é o relatório cross-período em tabelas de log →
particionamento + retenção; e a avaliação ABAC em loops → cache de decisão por request e regras
simples (evitar policy-engine pesado).

---

## RELATÓRIO FINAL

### Arquitetura proposta
RBAC (papéis→permissões `modulo.acao`) **+ ABAC** (escopo hierárquico + condições de atributo),
sobre o multi-tenant de 2 barreiras já existente (global scope + RLS). Enforcement em 4 camadas
(middleware → gate/policy → service → RLS).

### Modelo de autorização recomendado
**Híbrido RBAC+ABAC**, default-deny, administração delegada sem escalonamento de privilégio,
catálogo como fonte única da verdade.

### Modelo de autenticação
Sanctum (cookie p/ SPA + Bearer p/ apps) — **mantido**; acrescentar 2FA (TOTP), política de
senha, lockout, sessões revogáveis, histórico de login.

### Estrutura de banco de dados
Mantém RBAC atual; adiciona `unidades/departamentos/setores_org/cargos`, escopo em `role_user`,
`permission_conditions` (ABAC), `role_versions`, `login_logs`, `security_events`, `user_2fa`,
`password_policies`. Todas escopadas por tenant → entram na RLS automaticamente.

### Estrutura de permissões
`modulo.acao` estendida a tela/campo/relatório/export/import/dashboard/widget/integração/workflow;
verbos independentes incluindo `aprovar/estornar/fechar_caixa/assinar` etc.

### Hierarquia organizacional
Plataforma → Grupo → Empresa → Filial → Departamento → Setor/Equipe → Usuário(+Cargo).

### Fluxos
- **Autenticação:** login → Sanctum → (2FA se exigido) → `ResolveTenant` → `/me` com permissões+escopo.
- **Autorização:** request → middleware → Gate/Policy (RBAC + ABAC) → Service (condições) → RLS.
- **Administrativo:** Central de Acessos (UI) → API → grava papel/atribuição/escopo → histórico → `/me` reflete.

### Impactos no ERP-NOVO (backend)
Introduzir Gates/Policies como ponto único; criar `PolicyEvaluator` (ABAC); novas tabelas e
models (escopados); rotas/controllers da Central de Acessos; eventos de segurança na auditoria;
**corrigir drift do catálogo** (chaves `cidade.*` etc.).

### Impactos na SPA
`<RequirePermission>` (rotas) e `<Can>` (componentes/campos); central de administração de acessos;
2FA/sessões nas configurações; **corrigir permissões fantasma do menu**.

### Plano de implementação por fases

| Fase | Entrega | Risco | Depende |
|---|---|---|---|
| ~~**A0 — Saneamento**~~ ✅ | Corrigir drift catálogo↔menu↔controllers; teste de contrato reforçado. | Baixo | — |
| ~~**A1 — Enforcement central**~~ ✅ | Gates por chave do catálogo (`AuthServiceProvider`) delegando a `temPermissao()`; trait `AutorizaPorPermissao` (ponto único nos 38 controllers); middleware de rota `permissao:`. Sem mudança funcional. | Baixo | A0 |
| **A2 — Central de Acessos (RBAC)** | API + UI de usuários, papéis, atribuição por empresa. Substitui seeder/código. | Médio | A1 |
| **A3 — Hierarquia** | `unidades/departamentos/setores/cargos` + escopo em `role_user` + UI. | Médio | A2 |
| **A4 — ABAC** | `permission_conditions` + `PolicyEvaluator` (limite/ownership/horário). | Médio-alto | A1, A3 |
| **A5 — Segurança avançada** | 2FA, política de senha, lockout, sessões ativas, `login_logs`. | Médio | A2 |
| **A6 — Auditoria de segurança + histórico** | `security_events`, `role_versions`, relatórios. | Baixo | A2 |
| **A7 — Granularidade fina** | Field-level, relatórios/widgets/integrações como permissões. | Médio | A2 |

> Cada fase é commit+deploy independente (fluxo do projeto: 1 fase = 1 commit na main).

### Recomendações técnicas e melhores práticas
1. **Não jogar fora o que funciona** — RBAC + tenancy atuais são a base; evoluir aditivamente.
2. **Ponto único de autorização** (Gate/Policy) antes de adicionar ABAC.
3. **Default-deny + menor privilégio + sem escalonamento** na administração delegada.
4. **Catálogo como contrato testado** (backend↔SPA 1:1).
5. **Auditar toda decisão sensível** (inclusive 403 e ações de suporte).
6. **ABAC simples e cacheável** — evitar engine de políticas pesado.
7. **Backend é a autoridade**; SPA só melhora UX.
8. **Logs append-only particionáveis**; segredos sempre `encrypted`.
9. **Formalizar o "suporte"** como papel de plataforma auditado, não flag silenciosa.
```
