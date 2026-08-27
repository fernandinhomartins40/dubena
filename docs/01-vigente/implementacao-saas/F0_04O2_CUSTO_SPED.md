# F0-04O2 — custo no SPED Fiscal

**Estado:** CONCLUÍDO  
**Data:** 2026-08-25 22:22 (America/Sao_Paulo)

O SPED Fiscal contém legitimamente custo unitário e valor de inventário nos
registros H005/H010. Esses campos não foram removidos: o arquivo inteiro agora
exige `produto.campo.custo.view`, além de `fiscal.view`. EFD-Contribuições não
foi alterada porque seu contrato não contém o inventário de custo.

Validação: 12 testes/192 assertions aprovados; Pint e diff-check aprovados.

Próximo: NF de entrada, separando resumo, XML/preço de aquisição e autorização
para processar a atualização de custo médio.
