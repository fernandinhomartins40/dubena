# F0-04D — Owner obrigatório na porta de caixa

**Data:** 2026-08-25  
**Estado:** CONCLUÍDO  
**Achado contido:** A-7.1 integralmente na porta central.

## Releitura executada

Foram lidos integralmente o Volume 7 e os fontes atuais de `CaixaService`, `PagamentoService`, `ChequeService`, `MaloteService`, respectivos controllers, models e testes. Foi feita busca global por todos os chamadores das mutações de caixa.

## Alterações

- `CaixaService::movimentar()` exige `empresaId` e inclui o owner na mesma consulta que bloqueia a conta;
- conta ausente ou de outra empresa falha antes de alterar saldo;
- owner explícito foi propagado por baixa simples/lote, transferência, lançamento retroativo, estorno, saldo inicial, cartão, cheque, benefício e malote;
- abrir/fechar/estornar também filtram a conta/movimento pelo owner esperado;
- a baixa valida o owner do título com `withoutTenant + empresa_id`, sem depender do contexto ambiente;
- o controller usa `TenantContext` resolvido e nega ausência de tenant;
- registro de cartão persiste `empresa_id` explícito.

## Evidência

```text
Tests: 42 passed (91 assertions)
Duration: 3.61s
```

Foram cobertos caixa (domínio e API), regras de caixa fechado, cheque, conciliação e malote. Sintaxe PHP aprovada em todos os arquivos alterados. Um teste direto de serviço prova que conta de outra empresa é recusada sem depender da camada HTTP.

## Próxima substituição

F5 consolidará título/liquidação/conta numa porta única com idempotência, origem e RLS PostgreSQL. Esta contenção não declara resolvidos A-7.2–A-7.5.
