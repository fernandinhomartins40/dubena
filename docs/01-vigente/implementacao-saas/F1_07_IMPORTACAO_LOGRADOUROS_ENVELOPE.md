# F1-07 - Importação geográfica com TenantEnvelope

**Estado:** implementado localmente; aguarda deploy e prova no worker de homologação.
**Data:** 2026-08-27 (America/Sao_Paulo)

`ImportarLogradourosJob` agora captura o envelope no dispatch e executa dentro
de `TenantEnvelopeRuntime`. O envelope exige operação na empresa ativa; payload
de negócio sem envelope é negado quando o enforcement está ativo.

O job continua compatível enquanto a flag está desligada. Também passou a
registrar a desistência final em `failed()`, além de preservar o registro de
staging como `falhou` na última tentativa.

Validação: `JobsTratamentoFalhaTest` aprovou 6 testes/29 assertions e os dois
cenários focais de `ImportacaoLogradourosTest` aprovaram 2 testes/6 assertions.
