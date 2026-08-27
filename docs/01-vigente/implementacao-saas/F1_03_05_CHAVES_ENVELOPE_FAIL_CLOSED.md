# F1-03 a F1-05 - expansao, envelope e base fail-closed

**Estado:** implementacao incremental; sem cutover do runtime legado.
**Data:** 2026-08-26 (America/Sao_Paulo)

## Entrega local

- `tenant_company_grants` recebeu, de forma aditiva e inicialmente nullable,
  `tenant_account_id` e `tenant_company_id`. Nenhum valor foi inferido de
  `grupo_id`, empresa majoritaria ou membership antigo.
- Todas as 151 tabelas classificadas como `COMPANY` receberam
  `tenant_account_id` nullable, FK para `tenant_accounts` e indice. A migration
  contem a lista fixa do manifesto desta revisao, e nao le configuracao em
  runtime; nova tabela COMPANY sem a chave faz o teste falhar.
- `TenantEnvelope` e imutavel e serializavel. Exige conta, membership, empresa
  ativa, grants de leitura/operacao e correlation id; operacao sem leitura e
  empresa ativa sem grant operacional sao invalidas.
- `TenantEnvelopeResolver` consulta exclusivamente `tenant_companies` aprovadas,
  memberships ativas e grants aprovados que apontem para a mesma conta e link.
  Ausencia de qualquer elo gera `TenantAccessDeniedException`.

## Decisao de transicao

O middleware `ResolveTenant` legado nao foi trocado por este resolver ainda.
Faze-lo antes de F1-10 negaria todas as empresas existentes, pois a migracao
deliberadamente ainda nao preencheu titulares e grants sem evidencia. O corte
sera feito apos a migracao aprovada, junto com as policies RLS de F1-06 e a
propagacao em jobs de F1-07.

## Validacao

`TenantEnvelopeTest`, `TenantEnvelopeResolverTest` e
`TenantBoundarySchemaTest` passaram sem falhas. O teste de fronteira executado
apos a expansao aprovou 4 testes e 155 assertions.

## Proxima acao exata

Completar F1-03 por agregado COMPANY, iniciando em clientes/pedidos/estoque e
financeiro, com chaves aditivas, indices e plano de backfill que permaneça vazio
ate existir `tenant_companies` aprovado. Depois, F1-06 substitui as policies por
funcoes SQL canonicas antes de qualquer switch HTTP.
