# F0-07 — baseline particionado

**Estado:** CONCLUÍDO
**Data:** 2026-08-26 (America/Sao_Paulo)

## Resultado do baseline

A primeira execução integral produziu **1.283 passes, 8 skips e 4 falhas**.
As quatro falhas estavam todas no domínio de comodato e não foram aceitas como
baseline: a migration já aprovada tornou `comodatos.sentido` obrigatório, mas
três cenários de criação ainda não declaravam o sentido e o cenário da
distribuidora modelava um comodato recebido como concedido.

O contrato foi confirmado na migration, no model e no service: `CONCEDIDO` é
patrimônio da revenda entregue ao cliente; `RECEBIDO` é patrimônio de terceiro
em posse da revenda. Os testes foram alinhados a esse contrato sem alterar o
comportamento de produção.

## Recertificação

- conjunto focal de comodato: **23 passes, 53 assertions**;
- suíte integral pós-correção: **1.287 passes, 3.883 assertions, 8 skips,
  zero falhas**;
- duração da suíte integral: 379,80 s.

Os oito skips são cenários PostgreSQL/RLS executados separadamente pelo gate
real `composer test:pgsql-rls`, que já registrou 6 testes, 346 assertions e
zero skip em PostgreSQL com role runtime. Os dois avisos remanescentes são
metadados de doc-comment de PHPUnit, não falhas nem skips de domínio.

## Gate F0

Os gates locais de contenção, infraestrutura, RLS, catálogo e baseline possuem
evidência. O gate F0 **não está aprovado ainda** por duas pendências externas
que não podem ser presumidas:

1. F0-03: rotação/revogação real das credenciais anteriormente expostas;
2. F0-01: titular/controlador jurídico de cada empresa atual e marcação formal
   de `OWNERSHIP_UNRESOLVED` quando não houver decisão.

Também permanece pendente o ensaio remoto autorizado do pipeline de promoção e
rollback, registrado em F0-05A. Nenhuma dessas pendências foi convertida em
sucesso ou contornada por mudança de código.

## Próximo passo exato

Obter as decisões/execuções externas acima e registrá-las. Com o gate F0
aprovado, iniciar F1-01 pelo schema raiz de `tenant_accounts`, memberships,
tenant-company e grants — sem inferir tenant a partir de `grupo_id`.
