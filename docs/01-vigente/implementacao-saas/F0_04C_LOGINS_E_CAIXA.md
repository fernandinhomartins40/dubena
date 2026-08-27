# F0-04C — Logs pré-tenant e transferência de caixa

**Data:** 2026-08-25  
**Estado:** CONCLUÍDO  
**Achados contidos:** A-12.17 e caminho de transferência de A-12.2/T-01.2.

## Releitura executada

Foram lidos integralmente Volume 3, `AuditoriaController`, `CaixaController`, `CaixaService`, models e testes associados. O Volume 12 já havia sido relido integralmente no microlote anterior.

## Contenções

- a auditoria do tenant lista somente `login_logs.empresa_id = empresa ativa`;
- eventos `empresa_id = NULL` não são mais atribuídos por conveniência a todos os tenants e ficam reservados à futura auditoria de plataforma;
- `CaixaService::transferir()` exige a empresa esperada e valida as duas contas por owner antes do primeiro movimento;
- o controller passa a empresa do `TenantContext` resolvido, não apenas a empresa padrão do usuário;
- testes de domínio e HTTP provam que conta de outro tenant não altera nenhum saldo.

## Substituição canônica

- F1/F2: separar trilha pré-tenant/plataforma da trilha tenant e remover `support`;
- F5: aplicar owner explícito a todas as portas financeiras, RLS real e contratos de autorização por recurso.

## Evidência

```text
Tests: 20 passed (56 assertions)
Duration: 3.22s
```

Sintaxe PHP aprovada em todos os arquivos alterados.

## Limite do escopo

A-12.2 abrange outros caminhos de IDs globais além da transferência. Em especial, baixa em conta, estoque e atribuição logística ainda exigem releitura/validação individual; este diário não os declara corrigidos.
