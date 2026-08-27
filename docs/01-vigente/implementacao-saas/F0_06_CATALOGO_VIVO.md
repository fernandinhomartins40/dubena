# F0-06 — catálogo vivo

**Estado:** CONCLUÍDO
**Data:** 2026-08-26 (America/Sao_Paulo)

## Entregáveis

- `erp-novo/app/Console/Commands/SaasCatalogar.php`: comando
  `php artisan saas:catalogar` reexecutável e com modo `--check`.
- `CATALOGO_VIVO.json`: evidência versionada, gerada do PostgreSQL efetivo e da
  superfície atual de código.
- `SaasCatalogarCommandTest`: impede que SQLite/MySQL sejam aceitos como prova
  de schema, owner ou RLS efetivos.

O catálogo contém, sem valores de segredos: tabelas, colunas, owner, RLS ENABLE
e FORCE, policies, modelos e suas tabelas, jobs `ShouldQueue`, rotas/middlewares,
permissões, recursos, nomes de variáveis de integração e conexões declaradas.

## Evidência executável

Um PostgreSQL 15.14 descartável foi migrado integralmente pela conexão owner.
O comando foi executado e depois validado por `--check`, produzindo:

- 209 tabelas;
- 178 modelos concretos;
- 8 jobs enfileiráveis;
- 600 rotas;
- catálogo de permissões e recursos vigente.

Foram aprovados `php artisan test --filter=SaasCatalogarCommandTest`, `php -l`,
`saas:catalogar --check` em PostgreSQL e `git diff --check`. O container de
PostgreSQL temporário foi removido.

## Decisão e limites

O comando falha fora de PostgreSQL e também não aceita ausência do arquivo no
modo `--check`; ausência de contexto ou de fonte não é registrada como catálogo
vazio. A classificação canônica `PLATFORM/TENANT/COMPANY/DERIVED/STAGING` é a
tarefa F1-02: este catálogo deliberadamente preserva os fatos brutos para que a
classificação não seja inferida da simples presença de `empresa_id` ou `grupo_id`.

## Próximo microlote

F0-07: executar e registrar o baseline particionado, distinguindo falhas conhecidas,
skips PostgreSQL e regressões reais antes de avaliar o gate F0.
