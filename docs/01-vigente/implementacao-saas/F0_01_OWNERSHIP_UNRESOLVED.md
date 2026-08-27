# F0-01 — marcação explícita de titularidade não resolvida

**Estado:** CONCLUÍDO para a marcação técnica; classificação jurídica pendente.
**Data:** 2026-08-26 (America/Sao_Paulo)

## Implementação

A migration `2026_08_28_000200_empresa_ownership_status.php` adiciona
`empresas.ownership_status`, indexado e com default obrigatório
`OWNERSHIP_UNRESOLVED`. Assim, empresas atuais recebem uma marca explícita ao
aplicar a migration e empresas novas não podem aparentar ter titularidade
classificada por inferência de `grupo_id`, CNPJ ou certificado.

O valor ainda não é editável pelo CRUD: somente a decisão documental e a
modelagem `TenantAccount` de F1 poderão substituí-lo por uma classificação
aprovada. Isso evita criar uma tela que simule decisão jurídica sem evidência.

## Validação

`EmpresaOwnershipStatusTest` aprovou: empresa nova persiste e recarrega como
`OWNERSHIP_UNRESOLVED`. Foram aprovados também `php -l` da model/migration e
`git diff --check`.

## Limite

A marca não identifica o controlador. A lista das 12 empresas e os documentos
que permitem classificá-las continuam dependência externa; a condição do gate
F0 agora é observável no banco, sem atribuir titularidade fictícia.
