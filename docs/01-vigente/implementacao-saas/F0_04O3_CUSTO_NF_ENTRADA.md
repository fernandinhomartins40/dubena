# F0-04O3 — custo e XML na NF de entrada

**Estado:** CONCLUÍDO  
**Data:** 2026-08-25 22:26 (America/Sao_Paulo)

## Evidência reconfirmada

- `NfRecebida` serializava o XML fiscal bruto em qualquer resposta genérica.
- `NfRecebidaItem` serializava `valor_unitario`, que representa custo de aquisição.
- `NfEntradaService::processar()` usa esse valor para alterar o custo médio do
  estoque, mas a rota exigia apenas `fiscal.emitir`.

Foram reutilizados A-12 e o inventário F0-04. A releitura mutável ficou restrita
ao controller, service, dois models e aos testes contratuais de NF de entrada.

## Implementação

- XML foi ocultado estruturalmente no model e não é reexposto por endpoints
  genéricos. Uma eventual consulta futura precisará de rota e permissão próprias.
- `valor_unitario` foi ocultado no model e só é adicionado ao payload pelo
  controller quando o usuário possui `produto.campo.custo.view`.
- Processar a NF agora exige `produto.campo.custo.edit` antes de validação,
  estoque, financeiro ou qualquer outra escrita.
- Usuário com `edit` sem `view` consegue processar, mas recebe resposta redigida.

## Validação e rollback

`CustoNfEntradaAutorizacaoTest`, `NfEntradaApiTest` e `NfEntradaTest`: **11 testes,
50 assertions, zero falha**. Pint e `git diff --check` aprovados.

Rollback local: reverter o apresentador e os `$hidden`; isso reabre a exposição
e não deve ser promovido isoladamente.

Próximo: concluir as contenções conhecidas restantes de F0-04, começando pelo
uso da empresa padrão em vez da empresa ativa nos caminhos diretamente expostos.
