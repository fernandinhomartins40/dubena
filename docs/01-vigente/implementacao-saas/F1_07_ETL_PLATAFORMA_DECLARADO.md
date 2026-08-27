# F1-07 - ETL de plataforma declarado

**Estado:** implementado localmente; aguarda deploy de homologação.
**Data:** 2026-08-27 (America/Sao_Paulo)

`ExecutarMigracaoJob` não é um job de negócio tenant-aware: ele lê uma origem
externa e pode criar empresas antes de existir fronteira operacional. Por isso
foi declarado explicitamente como `platformJob` e passou a carregar o
`platformAdminId` do request autorizado.

Antes de executar, o worker verifica que a migração foi criada pelo mesmo
administrador de plataforma e que ele continua ativo. Divergência, ausência ou
desativação falham fechadas; não existe opt-out implícito por ser console/fila.

Validação: `MigracaoFerramentaTest` aprovou 12 testes/33 assertions e
`JobsTratamentoFalhaTest` aprovou 7 testes/32 assertions.
