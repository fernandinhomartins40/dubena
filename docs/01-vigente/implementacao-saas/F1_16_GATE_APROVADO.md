# F1-16 — Gate F1 aprovado em homologação (itens 1 e 6)

Data: 2026-08-29 (America/Sao_Paulo)

Verificação por SSH autorizado. Somente leitura — nenhuma escrita de negócio.

## O portão aprovou

```
saas:f1:pre-cutover-check --connection=pgsql_owner
EXIT=0
1 empresa(s) legada(s) sem TenantCompany APPROVED permanecem fora da fronteira SaaS
```

A empresa fora da fronteira é o "Grupo Padrão" de teste. Ela **deve** ficar
fora, e o resolver a nega — é o comportamento correto, não uma pendência.

O que torna esse exit 0 diferente do anterior: agora o portão verifica **policy
canônica ativa com RLS forçada**, não apenas a existência da coluna. Ele
reprovou três vezes durante esta sequência (4 tabelas → 2 → 0) e só aprovou
quando a cobertura ficou real.

## Cobertura em homologação

| | |
|---|---|
| Tabelas com `tenant_account_id` | 150 |
| Com policy canônica + `FORCE` | **141** |

As 9 restantes são exceções declaradas: `audit_logs`/`login_logs` (auditoria
anterior ao envelope), `config_globais` (vai para `IntegrationAccount` na F6),
`grupos`/`empresas` (espinha do tenancy) e as tabelas de fronteira/staging, que
têm policy própria.

## A prova que o item 1 exigia, sobre dados reais

Role de runtime confirmada: `erp_app`, `rolsuper=false`, `rolbypassrls=false`.

| Cenário | Resultado |
|---|---|
| Sem contexto → `clientes` | **0** (existem 55.453) |
| Tenant alheio → `clientes` | **0** |
| Tenant alheio → `pedidos` | **0** |
| Tenant alheio → `sequencias` | **0** |
| Tenant alheio → `financeiros` | **0** |
| `UPDATE` cruzado | **0 linhas**, confirmado pelo owner |

Com o envelope legítimo (membership 1, o dono da rede), a visibilidade bate
exatamente com os grants: 11 empresas concedidas, e os 55.453 clientes
pertencem a essas 11.

### Correção de um diagnóstico intermediário

Numa primeira medição eu comparei os clientes visíveis com os de **uma única**
empresa e o número não bateu — o que parecia vazamento. Não era: o membership
tem grant explícito nas 11 empresas da rede. A verificação estava mal formulada,
não o isolamento. O teste correto é o tenant sem grants, na tabela acima.

## Efeito das migrations desta sequência

| Migration | Efeito verificado em homologação |
|---|---|
| `001600` | `sequencias` protegida; **14/14** sequências fiscais com empresa e tenant, zero órfã |
| `001700` | trigger ativo; **20** números de sorteio reais, zero cruzando tenant |
| `001800` | os 2 pivots passaram a `rls=true force=true` canônica |
| `001900` | `transportadoras` e `malha_fiscal` saíram da policy legada |

## Estado do gate F1

| Item | Estado |
|---|---|
| 1. Gate PostgreSQL/RLS com role runtime | **fechado** — evidência acima |
| 2. Recertificar tabelas classificadas | fechado (F1-11) |
| 3. Jobs, eventos e WebSockets | fechado (F1-12) |
| 4. Grafos pai-filho/FKs | fechado (F1-13) |
| 5. Rollback/snapshot de grants | fechado (F1-14) |
| 6. Atualizar checkpoint | **este documento** |

**F1 está concluída.**

## Ressalvas que seguem abertas (não bloqueiam F1)

1. **19 tabelas PLATFORM ainda com policy por `grupo_id`** — `cidades`,
   `bancos`, `profissoes`, `unidadesmedida` e outros catálogos compartilhados.
   Catálogo de plataforma não deveria filtrar por grupo; é inconsistência de
   classificação, não de isolamento. Pertence ao recorte de classes.
2. **`operacoes_fiscais` é PLATFORM mas tem `grupo_id`** — já registrado em
   F1-11.
3. **`tenant.saas` continua fora das rotas.** F1 entrega a fronteira provada;
   ligar o middleware é decisão de cutover, com o enforcement ainda em
   `SAAS_ENFORCE_TENANT_ENVELOPE=false`.
4. **O `--apply` do comando documental não foi executado por mim** — as tabelas
   group-scoped com dados já haviam sido convertidas em sessão anterior. O
   preview segue `ready:true` e o snapshot está em
   `/opt/dubena-snapshots/f1-pre-apply-20260828-234328.json`.

`erp-novo/perda.sql` segue pré-existente e intocado.
