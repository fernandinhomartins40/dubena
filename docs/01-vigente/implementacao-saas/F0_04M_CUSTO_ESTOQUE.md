# F0-04M — custo nas rotas de estoque

**Estado:** CONCLUÍDO  
**Data:** 2026-08-25 22:15 (America/Sao_Paulo)  
**Branch/SHA de referência:** `main` / `4d8a3f3`

## Evidência reutilizada

Reutilizado o inventário certificado de consumidores de custo. A leitura foi
restrita ao controller/model/service de estoque, field-level, paginação e testes
diretos; uma busca final confirmou os consumidores restantes.

## Implementação

- histórico, listagem e resposta de transferências usam projeção única;
- `custo_unitario` só aparece com `produto.campo.custo.view`;
- entrada manual que informa custo sem `produto.campo.custo.edit` recebe 403
  antes de qualquer escrita;
- quem pode editar mas não visualizar grava o custo e recebe resposta redigida;
- `EstoqueHistorico.custo_unitario` e `EstoqueSaldo.custo_medio` ficaram ocultos
  por padrão, prevenindo nova serialização crua;
- a projeção autorizada continua acessando explicitamente os atributos ocultos.

## Validação

29 testes aprovados, 93 assertions, zero falha.  
Pint e `git diff --check` aprovados.  
Os testes provam ausência recursiva do nome e do valor-sentinela, negação sem
efeito, edição sem leitura e leitura autorizada.

## Rollback

Reverter projeção, gate de escrita, `$hidden` e teste novo. Isso reabre custo em
payloads operacionais e deve ser acompanhado pelo bloqueio das rotas de estoque.

## Próximo recorte

A-12.18 ainda possui consumidores fora do estoque: trilhas de auditoria,
relatório de auditoria, comodato, SPED e NF de entrada. Devem ser tratados em
microlotes separados para não misturar contratos fiscal e operacional.
