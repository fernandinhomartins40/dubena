# F1-14 — Snapshot e rollback de grants (item 5 do gate)

Data: 2026-08-28 (America/Sao_Paulo)

## Por que faltava

O importador documental escreve cinco tabelas de fronteira numa transação —
`tenant_accounts`, `tenant_companies`, `tenant_legacy_group_scopes`,
`tenant_memberships`, `tenant_company_grants` — e, como **efeito colateral**,
promove `empresas.ownership_status` para `OWNERSHIP_APPROVED`.

Nada disso é reversível pelos mecanismos existentes:

- `migrate:rollback` não desfaz: são **dados**, não schema;
- o `down()` das migrations de proteção se recusa de propósito a restaurar
  policy fail-open;
- `tenant_staging_artifacts` não serve: exige um `tenant_account_id` (o snapshot
  precisa cobrir todos, inclusive o estado "nenhum tenant") e tem TTL/purge, que
  apagaria justamente a evidência de rollback.

Ou seja: uma decisão de titularidade errada não tinha volta registrada. E
titularidade é a decisão que o próprio checkpoint classifica como jurídica.

## O comando

`saas:tenant:snapshot-grants <arquivo> [--restore]`, somente leitura por padrão.
O snapshot vai para um arquivo **fora do banco que ele restaura**.

Cobre as cinco tabelas **mais** `empresas.ownership_status`. Esse último ponto é
o que faz diferença: sem ele o rollback deixaria empresas marcadas como
aprovadas sem vínculo nenhum — um estado que nenhum gate detecta, porque o
pre-cutover só reclama do caminho inverso (vínculo aprovado sem ownership).

Na restauração o `DELETE` segue a ordem inversa do `INSERT`, por causa das FKs, e
tudo acontece numa transação.

## Prova executada

PostgreSQL 16, cadeia completa (138 migrations) do zero:

| Passo | Resultado |
|---|---|
| Snapshot com 1 tenant / 1 vínculo | gravado, 5 tabelas contabilizadas |
| Decisão errada aplicada (empresa 802 vinculada + ownership promovido) | 2 vínculos, 2 empresas aprovadas |
| `--restore` | 1 vínculo, 1 empresa aprovada |
| `empresas.ownership_status` da 802 | de volta a `OWNERSHIP_UNRESOLVED` |
| `--restore` com snapshot de outro banco | **recusado**, exit 1 |

A última linha é a trava que importa na prática: restaurar num banco diferente do
capturado apagaria a fronteira de outro ambiente. O nome do banco é a checagem
mínima possível, e ela falha fechado.

## Evidência

- `SaasSnapshotGrantsTest`: 3 testes / 13 assertions.
- Suíte integral: **1.336 passes, 4.220 assertions, 8 skips, zero falhas**.
- Pint aprovado.

## Procedimento de uso

Antes de qualquer `saas:tenant:importar --apply` em homologação ou produção:

1. `php artisan saas:tenant:snapshot-grants snapshots/<data>-pre-apply.json`
2. revisar as contagens impressas;
3. só então executar o `--apply`;
4. se o resultado divergir do esperado:
   `php artisan saas:tenant:snapshot-grants snapshots/<data>-pre-apply.json --restore`.

O arquivo deve ser guardado fora do banco e junto da evidência documental que
motivou o mapeamento.

## Estado do gate F1

| Item | Estado |
|---|---|
| 1. Gate PostgreSQL/RLS em homologação com role runtime | **aberto** — depende de deploy |
| 2. Recertificar todas as tabelas classificadas | fechado (F1-11) |
| 3. Jobs, eventos e WebSockets sem envelope | fechado (F1-12) |
| 4. Demais grafos pai-filho/FKs | fechado (F1-13) |
| 5. Rollback/snapshot de grants | fechado (este) |
| 6. Atualizar checkpoint e declarar F1 | pendente do item 1 |

`erp-novo/perda.sql` segue pré-existente e intocado.
