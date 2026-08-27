# F0-04H — Licenciamento, ABAC e custo de produto fail-closed

**Data:** 2026-08-25  
**Estado:** CONCLUÍDO COMO CONTENÇÃO  
**Achados contidos:** A-10.3, A-10.4 e A-12.18.  
**Achado parcialmente contido:** A-12.1 — o serviço de licença agora nega por padrão; a cobertura de todas as rotas e o backfill de entitlements pertencem ao rollout transacional de F2.

## Releitura executada

Foram relidos os achados A-10.3 e A-10.4 do Volume 10 e A-12.1–A-12.19 do Volume 12. Também foram lidos integralmente os fontes atuais de licenciamento, avaliação ABAC, catálogo e resolução de permissões por campo, modelos e pivôs da hierarquia organizacional, controllers e resources de produto/estoque, requests, migrations relacionadas e testes de licenciamento, RBAC, ABAC e field-level access. As rotas administrativas foram confrontadas com o alcance descrito pela auditoria.

## Diagnóstico confirmado

- uma empresa sem assinatura recebia todo o catálogo de recursos;
- a ausência de tenant, inclusive em CLI/ETL, também liberava todo o catálogo;
- o avaliador ABAC tratava tipo de condição desconhecido ou parâmetro ausente como autorização;
- ownership sem dono no recurso era aceito;
- `herda_filhos` existia no pivô de escopo, mas não era aplicado para departamento/unidade;
- custos do produto eram devolvidos e aceitos na escrita sem permissão granular;
- aplicar middleware de recurso indiscriminadamente nas 594 rotas agora, antes de criar e popular a matriz de entitlement, bloquearia tenants válidos sem uma migração reversível. Essa parte foi mantida para F2, com o fail-open do serviço já eliminado.

## Alterações

### Licenciamento

- `LicencaService::recursosEfetivos()` retorna conjunto vazio quando não há tenant;
- empresa sem assinatura vigente recebe conjunto vazio, em vez do catálogo completo;
- recursos do plano continuam sendo a base quando há assinatura vigente;
- override positivo explícito pode conceder somente o recurso indicado, mesmo sem assinatura;
- override negativo continua removendo um recurso do plano;
- cache permanece segregado por `empresa_id`.

### ABAC e hierarquia

- condições são carregadas explicitamente por `empresa_id`, usando consulta sem escopo apenas com predicado obrigatório do tenant;
- ausência de tenant, tipo desconhecido e payload incompleto ou inválido agora negam;
- limite exige valor máximo e valor do recurso numéricos;
- ownership exige usuário autenticado e dono presente e igual;
- horário exige dois valores `HH:MM` válidos;
- `herda_filhos` passou a cobrir setor descendente de departamento e departamento/setor descendentes de unidade;
- a verificação de descendência exige IDs pertencentes ao tenant corrente, impedindo que a hierarquia de outra empresa satisfaça o escopo.

### Proteção de custos

- catálogo ganhou `produto.campo.custo.view` e `produto.campo.custo.edit`;
- `ProdutoResource` remove todas as variantes atuais dos campos de custo quando o usuário não possui permissão de leitura;
- criação e atualização de produto ignoram campos de custo sem permissão de edição;
- listagem de saldos de estoque só inclui `custo_medio` com permissão de leitura de custo;
- o contrato de suporte já existente continua sendo a exceção explícita administrada por `CamposPermitidos`.

## Evidência

Validação dirigida inicial:

```text
Tests: 39 passed (106 assertions)
Duration: 3.71s
```

Validação ampliada de autenticação, app, verbos sensíveis, ABAC, estoque, lookup, licenciamento, perfis, RBAC, produto e field-level:

```text
Tests: 83 passed (237 assertions)
Duration: 8.04s
```

Provas negativas adicionadas:

- empresa sem assinatura não recebe recurso algum;
- ausência de tenant não libera catálogo;
- condição desconhecida ou incompleta nega;
- ownership sem campo de dono nega;
- departamento só alcança setor filho quando `herda_filhos=true`;
- custo do produto não aparece sem `view` e não é alterado sem `edit`.

`pint` foi aplicado somente aos nove arquivos do microlote e `git diff --check` passou sem erro.

## Limites deliberados desta contenção

- A-12.1 não está encerrado: F2 precisa criar a matriz rota/capability, backfill de assinatura/recursos, modo de observação, canário, métricas e rollback antes de anexar `recurso:*` a toda a superfície HTTP;
- a permissão de custo protege os pontos atuais de produto e saldo; F0-04 ainda precisa concluir o cross-scan de exports, relatórios e consumidores indiretos antes de declarar cobertura global;
- políticas antigas persistidas com payload incompleto passam a negar; a auditoria/migração desses registros deve ocorrer antes do go-live;
- nenhuma decisão de catálogo/plano comercial foi presumida nesta contenção.
