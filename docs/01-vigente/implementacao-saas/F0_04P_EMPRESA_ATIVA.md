# F0-04P — empresa ativa em relatórios e auditoria

**Estado:** CONCLUÍDO  
**Data:** 2026-08-25 22:34 (America/Sao_Paulo)

## Contenção

- `RelatorioController` passou a obter a empresa operacional exclusivamente do
  `TenantContext`, inclusive dashboard, registry e relatório legado de auditoria.
- `AuditoriaController` passou a filtrar eventos, logins, trilhas, opções e busca
  de clientes pela empresa ativa, em vez da empresa padrão do cadastro.
- `CamposPermitidos` agora avalia permissão field-level na empresa ativa. Assim,
  permissão de custo em A não autoriza exibição ao operar B.

A contenção cobre os caminhos diretamente demonstrados por A-12.3. A troca
canônica dos demais usos de empresa padrão permanece em F1, onde contexto, RLS,
jobs e todos os controllers serão tratados como um contrato transversal.

## Validação

- conjunto focal final: **22 testes/112 assertions**, zero falha;
- `RelatorioTest` completo: 9 testes aprovados no mesmo microlote;
- prova adversarial: usuário com custo em A e apenas auditoria em B não recebe
  custo ao usar `X-Empresa-Id: B`;
- Pint e `git diff --check` aprovados.

Rollback local: reverter as injeções de `TenantContext`; isso reabre A-12.3 e
não deve ser promovido isoladamente.
