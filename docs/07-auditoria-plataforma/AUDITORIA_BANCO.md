# AUDITORIA — BANCO DE DADOS

> Base: 74 migrations em [database/migrations/](../../erp-novo/database/migrations/), 10 seeders, models Eloquent. Alvo de produção: **PostgreSQL** (`pgsql` runtime restrito `erp_app`, `pgsql_owner` para DDL). Testes: sqlite in-memory.

## 1. Modelagem — visão geral

Schema organizado por blocos numerados (0000→0012 base; 2026_06_* incrementos por fase). Convenções fortes e consistentes:
- **Escopo de tenant** denormalizado: `empresa_id` (operacional) e `grupo_id` (cadastros de apoio compartilhados) presentes nas tabelas e nas **filhas** (backfill explícito na [f02_empresa_id_em_tabelas_filhas](../../erp-novo/database/migrations/2026_06_24_000100_f02_empresa_id_em_tabelas_filhas.php)).
- **Valores monetários** em `decimal(12–14, 2–4)`, nunca string-BR (número cru; formatação no front).
- **Máquinas de estado** materializadas como colunas/enum (`pedidosituacoes.efeito`, `situacao` de cheque/nota/pix).
- **Saldo derivável do histórico** (invariante de auditoria): estoque e caixa gravam `saldo_resultante` por movimento.

## 2. Relacionamentos e constraints

FKs declaradas com política de exclusão explícita e coerente (verificado em [0005 pedidos](../../erp-novo/database/migrations/0005_01_01_000000_create_pedidos_tables.php), [0003 clientes](../../erp-novo/database/migrations/0003_01_01_000100_create_clientes_tables.php)):
- `cascadeOnDelete` para dependências reais (itens→pedido, telefones→cliente, tenant→grupo).
- `restrictOnDelete` para referências que não podem sumir (produto em item, situação em pedido).
- `nullOnDelete` para vínculos opcionais (atendente/entregador/setor).

Uniqueness de negócio presente: `roles(grupo_id,nome)`, `permissions.chave`, `empresa_user(user_id,empresa_id)`, `pedidosituacoes.descricao` única por grupo (validada no controller com `Rule::unique`).

## 3. Índices

Índices compostos alinhados aos padrões de consulta reais:
- `pedidos(empresa_id, pedidosituacao_id)`, `pedidos(empresa_id, datahora)` ([0005](../../erp-novo/database/migrations/0005_01_01_000000_create_pedidos_tables.php)).
- Migrations dedicadas de performance: [f13_indices_performance](../../erp-novo/database/migrations/2026_06_25_000500_f13_indices_performance.php) (contamovimentos, estoquehistorico, financeiroparcelas), [p9_indices_escala](../../erp-novo/database/migrations/2026_06_29_000100_p9_indices_escala.php) (lat/lng de empresas, cidade), [l11_indices_logistica](../../erp-novo/database/migrations/2026_07_02_000100_l11_indices_logistica.php) (`pedidos(empresa_id, entregador_user_id, pedidosituacao_id)`).
- Índices criados de forma **idempotente** (checa existência antes) — bom para re-deploy.

**Gaps de índice** (ver AUDITORIA_PERFORMANCE): buscas por telefone e geoloc de cliente varrem em PHP (não indexáveis assim); `pix_cobrancas.txid` e `pedido_id` devem ter índice/unique dedicado.

## 4. RLS (Row-Level Security)

Migration [rls_tenant_completa](../../erp-novo/database/migrations/2026_06_26_000300_rls_tenant_completa.php) **descobre em runtime** todas as tabelas com `empresa_id`/`grupo_id` e aplica policy `tenant_isolation` (`ENABLE`+`FORCE ROW LEVEL SECURITY`), com allowlist para tabelas de auth/RBAC. Isso é robusto: tabela futura com a coluna é isolada automaticamente. Detalhe em AUDITORIA_MULTI_TENANT.

## 5. Seeders

- [DeploySeeder](../../erp-novo/database/seeders/DeploySeeder.php) (todo deploy, idempotente): admin base, RBAC, planos, cidades, SuperAdmin.
- [DemoGuarapuavaSeeder](../../erp-novo/database/seeders/DemoGuarapuavaSeeder.php) (786 linhas): massa realista completa; só roda com banco vazio no deploy (proteção `<=50 clientes`).
- **Não usa `WithoutModelEvents`** de propósito — o preenchimento de `empresa_id/grupo_id` depende do evento `creating` do trait. Decisão correta e documentada.

## 6. Achados classificados

| ID | Prio | Achado | Evidência | Recomendação |
|---|---|---|---|---|
| DB-1 | **P1** | `role_user` tem PK composta `(user_id, role_id, empresa_id)` com `empresa_id` **nullable**. No PostgreSQL, coluna de PRIMARY KEY não aceita NULL → inserir um papel **global** (empresa_id NULL) falha. O código lê papéis globais (`wherePivotNull('empresa_id')` em [User::temPermissao](../../erp-novo/app/Models/User.php)) mas não consegue persisti-los no PG. Latente hoje (seeders sempre passam empresa_id), mas quebra ao usar papel global. | [0001_..._tenant_e_rbac](../../erp-novo/database/migrations/0001_01_01_000100_create_tenant_e_rbac_tables.php) L54-58 | Trocar PK por `id` autoincrement + `unique(user_id, role_id, empresa_id)` (unique aceita NULL no PG e permite múltiplos NULLs — atenção: aí a unicidade de papel global não é garantida; alternativa: usar `empresa_id = 0` sentinela). Decidir semântica antes do dump. |
| DB-2 | **P2** | Uso de `ilike` (operador **exclusivo do Postgres**) em ~20 arquivos de controller/serviço, mas a suíte roda em sqlite → esses caminhos de busca **não são exercidos pelos testes** e podem divergir em runtime. | [ClienteController::index](../../erp-novo/app/Http/Controllers/Api/Admin/ClienteController.php), Pedido/Usuario/SuperAdmin | Testar contra Postgres em CI (serviço pg) OU abstrair a busca case-insensitive por driver. |
| DB-3 | **P2** | Gotcha conhecido (registrado em memória): seed passa no sqlite e quebra no Postgres por tamanho de coluna `varchar`. | histórico do projeto | Validar `migrate:fresh --seed` contra Postgres antes da homologação (portão de cutover). |
| DB-4 | **P3** | `financeiroparcelas` e outras filhas ganharam `empresa_id` por backfill, mas a coluna nasceu **nullable** e não há evidência de `NOT NULL` posterior. | [f02 backfill](../../erp-novo/database/migrations/2026_06_24_000100_f02_empresa_id_em_tabelas_filhas.php) | Após backfill validado, `ALTER … SET NOT NULL` para a RLS não ter linha "órfã" sem tenant. |
| DB-5 | **P3** | `pix_cobrancas` consultada por `txid` (webhook, com `lockForUpdate`) e por `pedido_id` — verificar índice/unique dedicado. | [PixService](../../erp-novo/app/Domain/Cobranca/PixService.php) | `unique(txid)` + `index(pedido_id)`. |
| DB-6 | **P4** | Não há FK formal de `pedidos.financeiro_id` (nullable, "FK quando N5 chegar"). | [0005 pedidos](../../erp-novo/database/migrations/0005_01_01_000000_create_pedidos_tables.php) L59 | O N5 chegou; formalizar a FK. |

## 7. Preparação para o dump de produção

- ETL com invariantes (Count/Sum/Balance/Integrity) e comando `etl:run --check` é o **portão de cutover** correto ([EtlRun](../../erp-novo/app/Console/Commands/EtlRun.php)).
- **Recomendação forte**: rodar a suíte e o seed **contra Postgres** (não sqlite) antes do dump — DB-1/DB-2/DB-3 só aparecem no PG.

## 8. Conclusão

Modelagem **coesa e disciplinada** (tenant denormalizado, decimais, índices por padrão de acesso, RLS auto-descoberta). O achado **DB-1 (PK com nullable no PG)** é o único com potencial de falha dura e deve ser resolvido antes de habilitar papéis globais. DB-2/DB-3 reforçam a necessidade de CI/validação em Postgres.

→ Plano: [PLANO_BANCO.md](PLANO_BANCO.md)
