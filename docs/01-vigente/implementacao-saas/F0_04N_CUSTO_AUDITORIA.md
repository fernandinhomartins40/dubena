# F0-04N — custo na leitura da auditoria

**Estado:** CONCLUÍDO  
**Data:** 2026-08-25 22:19 (America/Sao_Paulo)  
**Branch/SHA de referência:** `main` / `4d8a3f3`

## Implementação

- `ConsultaTrilha` passou a apresentar custo somente para observador com
  `produto.campo.custo.view`;
- a remoção cobre nomes canônicos, aliases legados e arrays aninhados;
- `/auditoria/trilha`, `/auditoria/registro` e `/relatorios/auditoria` reutilizam
  a mesma regra;
- `antes/depois` permanecem completos no banco; a sanitização ocorre somente na
  leitura, preservando a evidência para auditor autorizado.

## Validação

26 testes aprovados, 107 assertions, zero falha.  
Pint aplicado; `git diff --check` aprovado.

## Rollback

Reverter o presenter e seus chamadores. Isso expõe custos históricos para papéis
genéricos de auditoria/relatório e não é seguro sem bloquear essas rotas.

## Próximo recorte

Tratar separadamente a serialização do produto no comodato, o inventário do
SPED e a NF de entrada, pois possuem contratos e permissões distintos.
