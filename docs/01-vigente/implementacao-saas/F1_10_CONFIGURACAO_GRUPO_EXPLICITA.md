# F1-10 — configuração legada por grupo com ponte documental

**Estado:** implementado e validado localmente; não aplicado nem promovido em homologação.

## Recorte

`pedidosituacoes`, `pedidooperacoes` e `pedido_motivos_atraso` são cadastros
legados compartilhados por `grupo_id`, usados por pedidos de várias empresas.
Eles foram classificados como `COMPANY`, mas não têm `empresa_id`; portanto não
podem receber tenant por inferência de grupo, usuário, volume de linhas ou pela
cópia Dubena.

O mesmo contrato foi estendido a `motivos_nao_venda`, `clientecontatotipos` e
`clientecontatosituacoes`: eles configuram ações de pedido/atendimento e são
referenciados por pedidos e interações de clientes, sem `empresa_id` próprio.

Também são cobertos `contamovimentotipos`, `cargos` e `veiculo_tipos`, usados
respectivamente por movimentos financeiros, colaboradores e veículos. `cargos`
possui dois modelos no código, ambos agora recebem a chave apenas do envelope.

## Desenho implementado

- `tenant_legacy_group_scopes` exige `tenant_account_id`, `grupo_id` único,
  status aprovado, data e `evidence_ref`; possui RLS próprio, permite ao runtime
  ler apenas a própria conta e nega escrita.
- O importador aceita `legacy_group_scopes` somente quando o JSON contém a
  evidência e o tenant já declara uma empresa aprovada naquele grupo. O grupo
  ainda não determina o tenant: a declaração documental é obrigatória.
- O comando `saas:tenant:proteger-configuracao-grupo --apply` preenche as três
  tabelas somente via essa ponte aprovada, recusa qualquer linha sem ponte ou
  divergência prévia e então instala RLS `ENABLE + FORCE`. A migration de
  deploy cria somente a estrutura/funções: ela nunca oculta dados por antecipação.
- As policies exigem igualdade do tenant canônico e grant de leitura/operação
  da membership na empresa do grupo configurado. O grupo é um escopo de
  compatibilidade do cadastro, não uma identidade SaaS.
- Novas escritas dos três modelos copiam `tenant_account_id` exclusivamente do
  `TenantEnvelopeRuntime` ativo. Sem envelope, a policy não libera escrita
  quando o enforcement estiver ligado.

## Não feito deliberadamente

- Não foi criado nenhum `TenantLegacyGroupScope` a partir da cópia Dubena.
- Não houve backfill remoto, alteração de `grupo_id`, nem conversão de outras
  tabelas sem `empresa_id`.
- Não há prova PostgreSQL/runtime role deste recorte: os containers locais
  disponíveis pertencem a outro projeto (`m2-*`) e não foram usados.

## Validação local

`php artisan test --filter='TenantMappingImporterTest|LegacyGroupConfigurationMigrationTest|TableClassificationManifestTest|TenantBoundarySchemaTest|TenantEnvelopeRuntimeTest|ResolveTenantEnvelopeMiddlewareTest'`

Resultado: **18 testes, 193 assertions, aprovados**. Os dois avisos de metadata
PHPUnit preexistentes foram emitidos, sem falha.

Para a extensão dos três cadastros de atendimento, `MotivosPedidoTest`,
`TenantMappingImporterTest` e os testes de migration aprovaram **12 testes, 39
assertions**.

Para o recorte financeiro/RH/frota, os testes focais aprovaram **17 testes, 86
assertions**.

Em PostgreSQL 15 descartável, o gate `RlsCoberturaTest` também passou com a
role `erp_app` (`rolsuper=false`, `rolbypassrls=false`): **6 testes, 352
assertions, zero skips**. Durante essa prova foi corrigida uma migration legada
que recriava `monitora_veiculo_tipos`, já criada no schema inicial; a guarda
`Schema::hasTable()` impede que ela bloqueie a cadeia de migrations.

## Próximo passo exato

Após deploy em homologação, executar preview de um JSON documental que inclua
`legacy_group_scopes`, revisar a evidência, aplicar o JSON e só então executar
`saas:tenant:proteger-configuracao-grupo --apply`. Em seguida, recertificar
PostgreSQL e a role `erp_app`, incluindo leitura, escrita cruzada, ausência de
contexto e dois jobs sequenciais.
