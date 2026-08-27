# F0-04O1 — custo no produto relacionado ao comodato

**Estado:** CONCLUÍDO  
**Data:** 2026-08-25 22:21 (America/Sao_Paulo)

`Produto` agora oculta `custo_medio` e `custo_frete` por padrão. A API própria
continua expondo esses atributos explicitamente por `ProdutoResource` apenas
quando o field-level autoriza. Isso fecha o `load(['produto'])` do acréscimo de
comodato sem tocar nas alterações preexistentes do usuário nesse domínio.

Validação: 23 testes/64 assertions aprovados; Pint e diff-check aprovados. O
teste novo usa valores-sentinela e também prova `Produto::toArray()` fail-closed.

Próximo: gate de custo no arquivo SPED.
