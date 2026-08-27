# Memoria de retomada — F1 SaaS

Atualizado em 2026-08-26 (America/Sao_Paulo). Este arquivo e o registro operacional das pendencias reais; ele nao converte ausencia de decisao em autorizacao para inferir dados da Dubena.

## O que esta pronto localmente

- Schema de fronteira (`tenant_accounts`, memberships, empresas, grants e links), chaves aditivas em todas as tabelas COMPANY e classificacao completa do catalogo.
- `TenantEnvelope`, resolver fail-closed, runtime com limpeza em `finally`, funcoes RLS canonicas, trigger de consistencia, staging com TTL e importador de mapeamento documental.
- Alias HTTP `tenant.saas` e middleware preparados, mas deliberadamente fora das rotas produtivas enquanto nao houver mapping aprovado.
- `saas:tenant:importar` faz dry-run por padrao; `--apply` persiste apenas depois de uma revisao humana do mesmo JSON.

## Pendencias que nao podem ser resolvidas por codigo

1. A decisao operacional para a rede Dubena foi fornecida pelo operador em 2026-08-26 e corroborada por consulta somente-leitura da VPS: o Grupo Dubena deve ser um unico tenant e o Grupo Padrao e teste. A proposta ainda deve passar pelo dry-run no banco alvo depois do deploy das migrations; ver `ANALISE_VPS_DUBENA_2026-08-26.md` e `mapeamentos/DUBENA_VPS_2026-08-26_PROPOSTA.json`.
2. A rotacao/revogacao externa dos segredos de F0-03 e o ensaio remoto de promocao/rollback continuam pendentes. Nao houve evidencia de execucao remota neste repositorio.
3. A habilitacao de rota e o cutover exigem ambiente PostgreSQL com a role de runtime real, destino confirmado e rollback aprovado; nao executar isso em um banco desconhecido.

## Sequencia exata apos receber a decisao documental

1. Elaborar JSON com `tenants[]`, `legal_name`, `classification_evidence_ref`, empresas (`empresa_id`, `ownership_evidence_ref`), memberships e grants com suas evidencias.
2. Rodar `php artisan saas:tenant:importar <arquivo.json>` no banco alvo confirmado. Revisar as contagens e toda mensagem de erro.
3. Somente com aprovacao humana, rodar o mesmo comando com `--apply`.
4. Rodar `php artisan saas:f1:pre-cutover-check --connection=pgsql_owner`. Ele e somente leitura e falha fechado se uma empresa estiver sem vinculo aprovado, sem ownership aprovado, sem chave COMPANY ou sem funcao RLS.
5. Converter os jobs de negocio ainda legados para transportar o `TenantEnvelope`; os candidatos conhecidos sao geocodificacao de cliente, atribuicao de pedido, push e notificacao de estoque. Jobs de plataforma precisam declarar explicitamente esse opt-out e nao podem executar atos de negocio sem enfileirar um job tenant-aware.
6. Anexar `tenant.saas` apenas apos os itens anteriores e executar o gate F1 real: role de runtime, policies canonicas, ausencia de contexto negada, leitura/escrita cruzadas negadas e dois tenants no mesmo worker sem residuo.

## Limites conhecidos do checkpoint

- As policies legadas ainda nao foram substituidas pelas policies canonicas em todas as tabelas COMPANY: fazer isso antes do mapping bloquearia os dados existentes, e fazer depois sem teste de role runtime seria apenas aparencia de seguranca.
- O gate F1 ainda nao esta aprovado. O comando de pre-cutover e um portao de preparacao, nao uma certificacao do gate completo.
- Nenhuma copia Dubena foi adaptada automaticamente. F8 continua dependente de F1-F7 em shadow e de MappingSets por dominio.

## Retomada segura se a sessao for interrompida

Comecar por este arquivo e `ESTADO_ATUAL.md`, conferir `git status`, e rodar os testes focais do ultimo microlote. Nunca repetir a auditoria inteira apenas para retomar; reler integralmente apenas o codigo que sera modificado e seus consumidores diretos, conforme o procedimento continuo.

## Evidencia do ultimo microlote

- `php artisan test tests/Feature/SaasF1PreCutoverCheckTest.php tests/Feature/TenantMappingImporterTest.php tests/Feature/TenantBoundarySchemaTest.php --no-coverage`: 8 testes, 168 assertions, aprovados.
- `vendor/bin/pint --test app/Console/Commands/SaasF1PreCutoverCheck.php tests/Feature/SaasF1PreCutoverCheckTest.php`: aprovado.
- `git diff --check` nos arquivos do microlote: aprovado.
