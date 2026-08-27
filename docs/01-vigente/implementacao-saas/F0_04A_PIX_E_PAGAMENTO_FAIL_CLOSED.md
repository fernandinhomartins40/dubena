# F0-04A — PIX e pagamento fail-closed

**Data:** 2026-08-25  
**Estado:** CONCLUÍDO  
**Escopo:** primeira contenção do F0-04; o item F0-04 completo permanece em andamento.

## Releitura executada

Foram relidos integralmente o provider de bindings, o controller público do webhook PIX, o serviço/contrato/drivers de pagamento, a configuração de serviços e os testes diretamente associados. Também foi feita busca global pelas chamadas de teste ao endpoint `/api/pix/webhook`.

## Riscos confirmados

1. `PagamentoDriver` selecionava `FakePagamentoDriver` silenciosamente quando `PAGAMENTO_DRIVER` faltava ou era inválido, inclusive em produção.
2. O webhook PIX rejeitava ausência total de autenticação apenas em produção. Em desenvolvimento, CI e homologação, conhecer `txid` e valor bastava para confirmar uma cobrança pela rota pública.
3. `txid` desconhecido era rejeitado antecipadamente apenas em produção.

## Alterações

- o binding de `PagamentoDriver` usa o gate de driver real e recusa produção sem `PAGAMENTO_DRIVER=erede`;
- o webhook exige, em qualquer ambiente, pelo menos uma autenticação verificável: token compartilhado de camada 1 ou HMAC;
- empresa com PIX próprio sem HMAC não herda segredo da plataforma em nenhum ambiente;
- `txid` inexistente é rejeitado antes do processamento em qualquer ambiente;
- simulações de teste usam segredo explícito ou HMAC, sem abrir a rota pública;
- o data provider do gate de drivers passou a cobrir pagamento online.

## Evidência executável

Comando de regressão:

```text
php artisan test tests/Feature/FaseF1SegurancaTest.php tests/Feature/F12FrotaCrmGatesTest.php tests/Feature/PixWebhookFailClosedTest.php tests/Feature/CobrancaTest.php tests/Feature/IntegracaoTenantTest.php tests/Feature/MobileTest.php
```

Resultado inicial: 85 testes aprovados e 1 falha exclusivamente no nome do enum usado pelo novo assert (`PixSituacao` em vez de `SituacaoPix`). O nome foi corrigido.

Reexecução do arquivo afetado:

```text
Tests: 7 passed (12 assertions)
```

Os outros cinco arquivos já haviam passado integralmente na execução conjunta. Sintaxe PHP dos dois arquivos de aplicação aprovada. `git diff --check` sem erro; apenas avisos preexistentes de normalização CRLF/LF.

## Delimitação

F0-04 não está concluído. Ainda precisam ser reconfirmadas e contidas as demais ocorrências de serviço/rota cross-tenant, logs globais, sobrescrita genérica de segredos e fakes críticos descritas nos volumes. Essa releitura foi delegada em dois recortes somente-leitura para evitar edições concorrentes.
