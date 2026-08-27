# F0-04J — ETL e cutover fail-closed

**Data:** 2026-08-25  
**Estado:** CONCLUÍDO COMO CONTENÇÃO  
**Achados contidos:** A-14.3, A-14.5, A-14.9, A-14.16, A-14.17 e T-02.06.  
**Achados contidos contra vazamento/perda silenciosa, com substituição pendente:** A-14.18 e A-14.19.  
**Gate preparado, ainda não provado no ambiente requerido:** T-02.05.

## Releitura executada

Foram relidos os achados A-14.3, A-14.5, A-14.9 e A-14.16–A-14.19 do Volume 14, T-02.05 e T-02.06 da auditoria de testes, e integralmente os fontes atuais do painel/job/serviço de migração, runner ETL, checks de cutover/go-live/banco, invariantes de contagem e soma, scripts Python de espelhamento Oracle e posições GPS, modelos/migrations da ferramenta e seus testes dirigidos.

## Diagnóstico confirmado

- exceções por etapa eram convertidas em item de resultado e o job marcava a execução inteira como `concluida`;
- nome de retomada inválido selecionava zero migradores e chegava a 100%;
- invariantes de soma/contagem tratavam origem inacessível como sucesso;
- `cutover:check` liberava o portão quando nenhuma invariante era executada;
- `etl:run` permitia escrita de produção sem `--check`; a inspeção pós-cutover falhava aberta e o override não produzia evidência operacional;
- `golive:check` só era estrito quando uma pessoa lembrava da opção;
- `banco:producao-check` devolvia sucesso no SQLite, embora não tivesse comprovado PostgreSQL/RLS;
- os scripts Python não compartilhavam o freeze da aplicação;
- o espelho derrubava a cópia boa antes de extrair, truncava texto/LOB, aceitava divergência e terminava com exit zero após falhas;
- posições de device desconhecido eram atribuídas à empresa com maior frota.

## Alterações

### Painel e job

- retomada valida cada nome contra o registry antes de enfileirar e exige conjunto não vazio;
- qualquer etapa com exceção persiste resultado parcial, mantém progresso abaixo de 100 e relança uma falha agregada;
- o job transforma essa falha em `STATUS_FALHOU`; apenas execução integralmente bem-sucedida chega a `concluida/100`;
- o freeze padrão continua aplicado no controller e novamente no serviço, protegendo HTTP e job.

### Checks e gates

- origem inacessível torna `CountInvariant` e `SumInvariant` falhas inconclusivas;
- `cutover:check` falha quando nenhuma invariante foi executada;
- escrita via `etl:run` bloqueia quando não consegue provar o estado de cutover;
- produção exige `--check`; override pós-cutover emite log crítico;
- `golive:check` torna avisos bloqueantes automaticamente em `production`;
- `banco:producao-check` fora de PostgreSQL retorna falha inconclusiva;
- `RlsCoberturaTest` ganhou negativas reais de SELECT, UPDATE e INSERT cross-tenant, além da prova da role `NOSUPERUSER/NOBYPASSRLS` já existente.

### Scripts de conversão

- ambos exigem `SAAS_FREEZE_MIGRATION_WRITES=false` explicitamente antes de ler credenciais ou escrever;
- credenciais continuam obrigatórias via ambiente, sem defaults secretos;
- o espelho carrega cada tabela em staging e só troca a cópia anterior após contagem exata e zero linha descartada;
- tabela desconhecida, falha de view, contagem impossível, formato descartado ou divergência agora falham e resultam em exit não zero;
- truncamentos `SUBSTR(..., 200/500)` foram removidos; tipo/coluna que o transporte atual não preserva integralmente é recusado em vez de produzir uma cópia falsa;
- criação automática de veículo órfão no tenant majoritário foi removida; device sem mapa explícito não recebe owner inventado.

## Evidência

Primeira validação dirigida:

```text
Tests: 40 passed (109 assertions)
Duration: 29.91s
```

Validação ampliada após as negativas e gates adicionais:

```text
Tests: 42 passed, 5 skipped (116 assertions)
Duration: 28.81s
```

Prova determinística isolada do estado final do job:

```text
Tests: 1 passed (4 assertions)
```

Os cinco skips são deliberadamente PostgreSQL-only: role, cobertura das policies e negativas reais de SELECT/UPDATE/INSERT. Em SQLite eles não podem provar RLS; por isso `banco:producao-check` agora retorna inconclusivo/falha fora de PostgreSQL. O gate T-02.05 só poderá ser declarado aprovado quando esse mesmo recorte rodar em PostgreSQL real com role runtime restrita.

AST dos dois scripts Python, sintaxe PHP, `pint` dirigido e `git diff --check` passaram. Permanecem apenas avisos de conversão CRLF já existentes no workspace.

## Limites e substituição canônica

- A-14.18 está contido por recusa: o espelho atual não trunca, mas também não transporta LOB/coluna ignorada. F7 deve substituí-lo por cópia bruta integral, checksums e manifesto por coluna;
- A-14.19 está contido contra vazamento: device desconhecido é descartado/contado. F7 deve persistir quarentena sem tenant e permitir replay após decisão humana;
- o staging é por tabela; F7 exige snapshot/run imutável do conjunto inteiro, lineage e promoção coordenada;
- os batches do staging podem ficar materializados durante a execução; eles não substituem a cópia boa, e são removidos em falha conhecida, mas limpeza de processo morto requer runbook/TTL;
- o mapa de empresas ainda não governa todas as FKs (A-14.1) e o registry ainda precisa ser particionado por tipo de origem (A-14.2); ambos pertencem à modelagem canônica F7;
- invariantes atuais ainda são globais, não por run/tenant (A-14.8); F7 deve receber mapa, recorte e `conversion_run_id` obrigatórios;
- T-02.05 permanece pendente por falta de execução PostgreSQL real nesta sessão; o teste e o comportamento fail-closed foram preparados, não usados como substituto da prova.
