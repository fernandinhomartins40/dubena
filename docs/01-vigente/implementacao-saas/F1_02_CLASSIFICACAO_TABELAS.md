# F1-02 - classificacao de tabelas

**Estado:** concluido localmente e validado em PostgreSQL descartavel.
**Data:** 2026-08-26 (America/Sao_Paulo)

## Mecanismo implementado

`saas:classificacao-tabelas:check --connection=pgsql_owner` le o catalogo
PostgreSQL efetivo e valida `config/saas_table_classification.php`. Cada tabela
deve aparecer uma unica vez com `class`, `owner` e `justification`; as classes
aceitas sao somente `PLATFORM`, `TENANT`, `COMPANY`, `DERIVED` e `STAGING`.

O validador falha para tabela ausente, declaracao obsoleta, classe invalida,
owner vazio ou justificativa vazia. Portanto o arquivo com zero entradas nao e
um estado aprovado: ele preserva explicitamente a pendencia em vez de inventar
fronteiras a partir de nome, prefixo, `grupo_id` ou volume de dados.

## Validacao executada

- `php -l` do validador e comando: aprovado.
- `TableClassificationManifestTest`: 2 testes, 3 assertions, zero falhas.
- O comando esta registrado no Artisan e exige PostgreSQL, pois outro driver
  nao prova o catalogo, owner ou policies efetivos.

## Evidencia de catalogo efetivo

Em PostgreSQL 16 descartavel, `php artisan migrate --database=pgsql_owner
--force --no-interaction` concluiu a cadeia integral de migrations. Em seguida,
`php artisan saas:classificacao-tabelas:check --connection=pgsql_owner`
confirmou que o catalogo efetivo e o manifesto sao identicos.

Durante essa prova foi corrigido um defeito de migrations anterior: `GRANT ...
TO erp_app` para role ausente aborta a transacao PostgreSQL, mesmo se a excecao
PHP for capturada. As cinco migrations afetadas agora testam a existencia da
role antes de emitir qualquer `GRANT`; isso preserva o fail-closed em producao
e permite banco local sem role de runtime.

Validacao focal final: 6 testes, 13 assertions, zero falhas (`TenantBoundary`
e `TableClassificationManifest`).

## Proxima acao exata

Iniciar F1-03 pelos agregados COMPANY e TENANT do manifesto: desenhar a
expansao de `tenant_id`/`empresa_id`, com FKs e indices, sem backfill por
empresa majoritaria e sem trocar ainda o contexto de runtime.
