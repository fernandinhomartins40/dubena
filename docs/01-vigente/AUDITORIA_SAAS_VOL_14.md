# Auditoria SaaS — Volume 14 — ETL, conversão e dados iniciais

**Estado:** fechado quanto à leitura estática do recorte (100%: 107 artefatos, 19.484 linhas; 106 arquivos não vazios e o `.gitkeep` de 0 linhas).  
**Data:** 2026-08-25.  
**Fonte de evidência:** somente código, schema, scripts e testes. Documentação e comentários foram usados apenas para confrontar promessas com execução, nunca como prova de funcionamento.

## Resultado executivo

O mecanismo não está pronto para onboarding SaaS seguro. Foram encontrados **23 achados: 19 altos e 4 médios**. Os bloqueadores centrais são: o mapa humano de empresas é salvo mas ignorado; o painel executa todos os migradores contra qualquer tipo de origem; falhas parciais terminam como `concluida`; a proteção pós-cutover existe só no CLI; scripts contêm credenciais e podem destruir o último espelho válido; e posições GPS desconhecidas são atribuídas ao tenant majoritário. Os testes exercitam componentes, mas não cobrem esses contratos de produção.

## Critérios canônicos

Aplicam-se exclusivamente os critérios do método mestre: **C1 — conceito ausente**; **C2 — classificação por texto**; **C3 — flag como proxy**; **C4 — convenção não declarada**; **C5 — conceitos misturados**; **C6 — escopo de tenant errado**. Riscos técnicos de segurança, atomicidade ou operação que não respondem honestamente a uma dessas perguntas são marcados como **transversais fora de C1–C6**; não se cria uma taxonomia paralela.

## Inventário integral

Contagem por grupo: `app/Etl` 42/10.664; orquestração 6/1.275; `database/etl` 3/811; `scripts` 1/329; seeders 15/2.278; factories 18/493; testes Migration 10/1.401; testes Feature 8/802; infraestrutura relacionada 4/1.431. Total: **107/19.484**. O número real de migradores é **28**.

### `app/Etl` — 42 artefatos, 10.664 linhas

```text
app/Etl/MigratorRegistry.php 155
app/Etl/Contracts/Invariant.php 16
app/Etl/Contracts/Migrator.php 33
app/Etl/Invariants/.gitkeep 0
app/Etl/Invariants/BalanceInvariant.php 119
app/Etl/Invariants/CountInvariant.php 105
app/Etl/Invariants/IntegrityInvariant.php 45
app/Etl/Invariants/SumInvariant.php 66
app/Etl/Migrators/AppGasEmCasaMigrator.php 850
app/Etl/Migrators/CadastrosApoioMigrator.php 156
app/Etl/Migrators/CadastrosContabeisMigrator.php 350
app/Etl/Migrators/CaixaMigrator.php 268
app/Etl/Migrators/ClientesMigrator.php 282
app/Etl/Migrators/CobrancaMigrator.php 371
app/Etl/Migrators/ComplementosMigrator.php 899
app/Etl/Migrators/CrmMigrator.php 323
app/Etl/Migrators/EmpresaConfigMigrator.php 413
app/Etl/Migrators/EmpresasMigrator.php 324
app/Etl/Migrators/EstadosMigrator.php 125
app/Etl/Migrators/EstoqueMigrator.php 313
app/Etl/Migrators/FinanceiroMigrator.php 320
app/Etl/Migrators/FiscalConfigMigrator.php 151
app/Etl/Migrators/FiscalMigrator.php 702
app/Etl/Migrators/FrotaMigrator.php 213
app/Etl/Migrators/GeograficoMigrator.php 350
app/Etl/Migrators/GestaoMigrator.php 182
app/Etl/Migrators/IbptMigrator.php 181
app/Etl/Migrators/MatrizTributariaMigrator.php 522
app/Etl/Migrators/MobileMigrator.php 130
app/Etl/Migrators/MonitoraLegadoMigrator.php 514
app/Etl/Migrators/PagamentoMigrator.php 90
app/Etl/Migrators/PedidosMigrator.php 467
app/Etl/Migrators/ProdutosMigrator.php 116
app/Etl/Migrators/RhMigrator.php 429
app/Etl/Migrators/SatelitesMigrator.php 376
app/Etl/Migrators/SementeCountInvariant.php 33
app/Etl/Migrators/UsersMigrator.php 304
app/Etl/Support/InvariantResult.php 37
app/Etl/Support/MigrationContext.php 31
app/Etl/Support/MigrationResult.php 26
app/Etl/Support/PreservaIdsDoLegado.php 119
app/Etl/Support/RegistraFalhaDeLeitura.php 158
```

### Orquestração — 6 arquivos, 1.275 linhas

```text
app/Console/Commands/BancoProducaoCheck.php 359
app/Console/Commands/CutoverCheck.php 58
app/Console/Commands/EtlRun.php 196
app/Http/Controllers/Api/SuperAdmin/MigracaoController.php 236
app/Jobs/ExecutarMigracaoJob.php 88
app/Services/Migracao/MigracaoService.php 338
```

### Conversores Python — 4 arquivos, 1.140 linhas

```text
database/etl/diagnostico_producao.py 180
database/etl/espelhar_oracle.py 443
database/etl/migrar_posicoes.py 188
scripts/cnefe_importar.py 329
```

### Seeders — 15 arquivos, 2.278 linhas

```text
database/seeders/AcessoMigracaoSeeder.php 125
database/seeders/AcessoRedeDubenaSeeder.php 160
database/seeders/CidadesPlataformaSeeder.php 31
database/seeders/DatabaseSeeder.php 36
database/seeders/DemoGuarapuavaSeeder.php 768
database/seeders/DeployAdminSeeder.php 56
database/seeders/DeploySeeder.php 45
database/seeders/HomologSeeder.php 294
database/seeders/MarketplaceDemoSeeder.php 156
database/seeders/PerfisCampoTesteSeeder.php 228
database/seeders/PlanosSeeder.php 56
database/seeders/RbacSeeder.php 95
database/seeders/SuperAdminSeeder.php 35
database/seeders/Concerns/ResolveSenhaSeed.php 74
database/seeders/data/guarapuava.php 119
```

### Factories — 18 arquivos, 493 linhas

```text
database/factories/EmpresaFactory.php 26
database/factories/GrupoFactory.php 20
database/factories/RegiaoFactory.php 22
database/factories/UserFactory.php 51
database/factories/Apoio/SegmentoFactory.php 22
database/factories/Caixa/ContaFactory.php 29
database/factories/Cliente/ClienteFactory.php 28
database/factories/Cliente/ClienteTelefoneFactory.php 22
database/factories/Estoque/SetorFactory.php 25
database/factories/Financeiro/FinanceiroFactory.php 29
database/factories/Frota/VeiculoFactory.php 29
database/factories/Geografico/BairroFactory.php 25
database/factories/Geografico/CidadeFactory.php 28
database/factories/Pedido/PedidoSituacaoFactory.php 31
database/factories/Produto/ProdutoCondicaoPrecoFactory.php 24
database/factories/Produto/ProdutoFactory.php 26
database/factories/Rh/ColaboradorFactory.php 30
database/factories/Saas/PlatformAdminFactory.php 26
```

### Testes — 18 arquivos, 2.203 linhas

```text
tests/Migration/BalanceInvariantTest.php 57
tests/Migration/CercaPoligonoTest.php 154
tests/Migration/ConfigOperacionalEComodatoTest.php 145
tests/Migration/CountInvariantAjustesTest.php 130
tests/Migration/DedupClientesFksTest.php 76
tests/Migration/EstadosMigratorTest.php 52
tests/Migration/F15MigratorsTest.php 227
tests/Migration/FalhaDeLeituraTest.php 122
tests/Migration/FksNaoMapeadasTest.php 241
tests/Migration/NumeracaoFiscalTest.php 197
tests/Feature/BancoProducaoCheckTest.php 62
tests/Feature/EtlTravaPosCutoverTest.php 65
tests/Feature/MigracaoFerramentaTest.php 195
tests/Feature/MigradorEnumValidoTest.php 83
tests/Feature/MigradoresModulosTest.php 99
tests/Feature/F14RastreabilidadeTest.php 93
tests/Feature/JobsTratamentoFalhaTest.php 104
tests/Feature/ContratoIntegracoesMigradasTest.php 101
```

### Infraestrutura relacionada — 4 arquivos, 1.431 linhas

```text
config/queue.php 155
config/services.php 165
database/migrations/2026_08_13_000100_create_migracoes_tables.php 74
routes/api.php 1037
```

## Achados

### A-14.1 — ALTA — C6 (escopo de tenant errado) — o mapa de empresas é decorativo

O endpoint aceita `mapear`, `criar` e `ignorar` e persiste `mapa_empresas` (`MigracaoController.php:123-150`), mas `MigracaoService::executar` cria apenas um `MigrationContext` e roda os migradores sem ler esse campo (`MigracaoService.php:115-169`). IDs legados continuam sendo preservados. **Impacto SaaS:** uma decisão explícita de ignorar pode ser violada; uma empresa pode sobrescrever/cair no tenant de mesmo ID. **Direção:** transformar o mapa em dependência obrigatória do contexto e remapear todas as FKs tenant; teste E2E para as três ações.

### A-14.2 — ALTA — C5 (conceitos misturados) — tipo de origem não limita o pipeline

Os três tipos selecionam apenas uma conexão (`MigracaoService.php:30-34,117-127`); em seguida o registry completo é executado (`:119-165`). Ao mesmo tempo, AppGas e Monitora usam conexões fixas próprias (`AppGasEmCasaMigrator.php:837`; `MonitoraLegadoMigrator.php:501`), e AppGas tenta correlacionar `clientes`/`pedidos` via `$ctx->legado()` (`AppGasEmCasaMigrator.php:530-564`). **Impacto:** uma origem MySQL do app/Monitora é tratada também como ERP, enquanto fontes externas configuradas podem apontar a outra base; carga zero, parcial ou contaminada. **Direção:** registry por tipo e bundle explícito de conexões correlacionadas, recusando pré-requisitos ausentes.

### A-14.3 — ALTA — C5 (conceitos misturados) — falha parcial termina como concluída

Cada exceção é capturada e convertida em item `erro`, sem relançar (`MigracaoService.php:137-164`); o job então marca incondicionalmente `STATUS_CONCLUIDA` (`ExecutarMigracaoJob.php:61-69`). **Impacto:** operador e automação recebem sucesso para migração incompleta. **Direção:** estado `concluida_com_falhas` ou falha global, transações/checkpoints e gate obrigatório antes de concluir.

### A-14.4 — ALTA — C1 (conceito ausente) — descartes não preservam linhas nem permitem reprocessar

O schema promete chave e JSON original (`2026_08_13_000100_create_migracoes_tables.php:52-63`), mas o serviço cria no máximo um registro genérico por aviso, sem `chave_origem` nem `dados` (`MigracaoService.php:146-155`). **Impacto:** milhares de pulos agregados viram uma mensagem, sem identificação, prova ou replay. **Direção:** descarte por linha, chave natural/ID, payload sanitizado, contagem reconciliável e exportação testada.

### A-14.5 — ALTA — C4 (convenção não declarada) — painel ignora a trava pós-cutover

A trava está somente no comando `etl:run` (`EtlRun.php:29-35,139-176`). O serviço do painel chama migradores diretamente (`MigracaoService.php:115-169`) e as rotas permanecem disponíveis ao SuperAdmin (`routes/api.php:1017-1029`). **Impacto:** reexecução após entrada em produção pode sobrescrever registros vivos. **Direção:** serviço único de lock/cutover usado por CLI, job e HTTP, com override auditado e duplo controle.

### A-14.6 — ALTA — C4 (convenção não declarada) — concorrência não é excluída atomicamente

O controller faz apenas leitura de status e depois update/dispatch (`MigracaoController.php:153-175`); não há lock global, compare-and-set nem unicidade de migração ativa. A fila longa evita reentrega prematura (`ExecutarMigracaoJob.php:21-44`; `config/queue.php:76-100`), não duas migrações distintas. **Impacto:** corridas em `max(id)+1` e upserts cross-run. **Direção:** advisory/distributed lock global por destino, aquisição atômica e idempotency key.

### A-14.7 — ALTA — C2 (classificação por texto) — filtro `apenas` desconhecido produz falso sucesso

O endpoint valida só tamanho/tipo (`MigracaoController.php:162-165`). Nomes inexistentes resultam em lista vazia (`MigracaoService.php:119-125`), progresso 100 e retorno sem erro (`:127-169`), depois `concluida`. **Impacto:** retomada digitada incorretamente não executa nada e aparenta êxito. **Direção:** validar contra nomes do registry e exigir ao menos um migrador.

### A-14.8 — ALTA — C6 (escopo de tenant errado) — invariantes são globais, não por execução/tenant

Count e Sum contam tabelas inteiras no legado e destino (`CountInvariant.php:73-86`; `SumInvariant.php:51-64`). **Impacto:** onboarding subsequente em SaaS populado acusa falso desvio ou mascara contaminação por dados de outros tenants. **Direção:** toda invariante deve receber recorte de origem, `migracao_id`, empresa/grupo e mapa aplicado.

### A-14.9 — ALTA — C4 (convenção não declarada) — cutover falha aberto sem origem e sem checks

Count/Sum retornam OK quando o legado está indisponível (`CountInvariant.php:51-64`; `SumInvariant.php:39-43`). Migradores frequentemente retornam `[]`; `cutover:check` pula listas vazias e libera se nenhuma falha existir (`CutoverCheck.php:20-56`). **Impacto:** `PORTÃO LIBERADO` pode significar que nada foi verificado. **Direção:** resultado `SKIP/INCONCLUSIVO`, manifesto mínimo obrigatório e falha se fonte/tabela/check requerido não estiver disponível.

### A-14.10 — ALTA — C1 (conceito ausente) — invariante de saldo ignora saldo ausente

Sem movimentos retorna OK (`BalanceInvariant.php:77-80`); se há movimento mas não há registro de saldo correspondente, a chave é ignorada (`:88-105`). **Impacto:** saldo materializado perdido pode passar o cutover. **Direção:** ausência de saldo para chave no recorte é divergência; diferenciar banco realmente vazio de fonte indisponível.

### A-14.11 — MÉDIA — C4 (convenção não declarada) — registry aceita dependência inexistente e nomes duplicados

Nomes duplicados sobrescrevem silenciosamente no mapa (`MigratorRegistry.php:122-128`); dependência desconhecida é simplesmente ignorada (`:140-144`). **Impacto:** erro de cadastro altera ordem/cobertura sem falhar CI. **Direção:** validar unicidade e existência de toda dependência antes do topological sort.

### A-14.12 — MÉDIA — C6 (escopo de tenant errado, por analogia de escopo do destino) — conexão de destino declarada é ignorada

`MigrationContext` recebe `conexaoNova`, mas `novo()` devolve a conexão default (`MigrationContext.php:13-29`). **Impacto:** teste/operação que acredita apontar para destino isolado pode escrever no banco padrão. **Direção:** `DB::connection($this->conexaoNova)` e teste de isolamento.

### A-14.13 — ALTA — C2 (classificação por texto) — falhas de leitura ainda são silenciosas

Vários migradores usam `catch (\Throwable) { return []; }`, por exemplo CRM (`CrmMigrator.php:258-264`) e Pedidos (`PedidosMigrator.php:222-250`). O CLI só falha para aviso com a frase exata `leitura falhou` (`EtlRun.php:99-110`). **Impacto:** tabela quebrada é indistinguível de tabela vazia e o processo pode retornar sucesso. **Direção:** erro tipado central, zero `catch` silencioso e política de severidade independente de texto.

### A-14.14 — ALTA — C4 (convenção não declarada) — validação é opcional e trava pós-cutover falha aberto

`etl:run` só roda invariantes com `--check` (`EtlRun.php:22-25,68-74`). A inspeção pós-cutover permite execução ao capturar qualquer exceção (`:172-175`) e também quando a origem não responde (`:154-155`). **Impacto:** erro operacional desativa as duas últimas barreiras. **Direção:** check obrigatório em produção e comportamento fail-closed para incapacidade de decidir.

### A-14.15 — ALTA — C4 (convenção não declarada) — banco:check não comprova PostgreSQL real nem conjunto requerido

Para driver diferente de PostgreSQL o comando termina com sucesso (`BancoProducaoCheck.php:51-55`). O espelho é aprovado por limiar numérico de tabelas (`:204-217`), sem comparar o manifesto nominal. O teste só confirma o atalho SQLite (`BancoProducaoCheckTest.php:16-62`). **Impacto:** tabelas essenciais podem faltar enquanto tabelas irrelevantes satisfazem o total. **Direção:** manifesto exato gerado do pipeline e teste de integração PostgreSQL.

### A-14.16 — ALTA — transversal fora de C1–C6 (segurança de segredo) — credenciais de banco versionadas

Oracle e PostgreSQL estão hard-coded (`database/etl/espelhar_oracle.py:26-28`); MySQL e PostgreSQL também em `migrar_posicoes.py:24-26`. **Impacto:** segredo reutilizado exposto e scripts perigosamente acoplados a endpoints locais. **Direção:** rotacionar credenciais, remover do histórico e exigir env/secret manager sem default.

### A-14.17 — ALTA — transversal fora de C1–C6 (atomicidade e sinalização de falha) — espelhamento destrói a cópia boa e termina 0 após falhas

Cada tabela destino é derrubada e recriada antes da extração (`espelhar_oracle.py:312-326`). Falha ao criar a view retorna zero deixando tabela vazia (`:342-347`), blocos são commitados individualmente (`:389-396`), divergência é apenas texto (`:410-417`) e exceções por tabela apenas são impressas antes de continuar (`:427-437`). O `main` termina com exit zero (`:420-443`). **Impacto:** falha no meio elimina o último espelho utilizável e CI/operador recebe sucesso. **Direção:** staging por execução, carga/transação/checksum, swap atômico somente após validar e exit não zero.

### A-14.18 — ALTA — C1 (conceito ausente: cópia bruta fiel) — espelho trunca dados de forma irreversível

CLOB/NCLOB/LONG são limitados a 200 e textos a 500 caracteres (`espelhar_oracle.py:279-295`). **Impacto:** observações, dados fiscais e conteúdo probatório deixam de ser fiéis antes mesmo dos migradores. **Direção:** transportar LOB integralmente ou registrar exceção campo a campo com checksum/arquivo bruto imutável.

### A-14.19 — ALTA — C6 (escopo de tenant errado) — GPS desconhecido é entregue ao tenant majoritário

O script seleciona a empresa/grupo com mais veículos (`migrar_posicoes.py:59-64`) e cria nela placeholders para aparelhos desconhecidos (`:43-86`). **Impacto:** trajetória de um cliente pode aparecer para outro; além de corrupção, há vazamento de localização. **Direção:** quarentena sem tenant, mapa explícito device→empresa e bloqueio até resolução humana.

### A-14.20 — ALTA — C1 (conceito ausente: identidade/checkpoint da posição de origem) — posições não são idempotentes nem retomáveis

O script faz `COPY` sem chave de origem/deduplicação (`migrar_posicoes.py:169-173`) e commit por lote (`:173`). **Impacto:** rerun duplica todo o histórico; falha parcial + retomada duplica lotes já confirmados. **Direção:** source ID/unique, checkpoint durável, run ID e upsert/staging reconciliado.

### A-14.21 — ALTA — C6 (escopo de tenant errado) — seed de deploy colide com IDs preservados

Produção chama `DeployAdminSeeder` antes do restante (`DatabaseSeeder.php:22-29`; `DeploySeeder.php:35-43`), que cria grupo/empresa/admin com IDs automáticos (`DeployAdminSeeder.php:29-54`). Os migradores preservam IDs legados. **Impacto:** empresa legada de mesmo ID pode sobrescrever o bootstrap mantendo admin vinculado, ou usuário legado colidir e ser descartado. **Direção:** namespace/IDs reservados ou executar bootstrap somente após remapeamento; invariante explícita de identidade e ownership.

### A-14.22 — ALTA — C5 (conceitos misturados) — Satélites altera semântica e não é idempotente

Comodato escolhe o produto do item de maior quantidade, mas soma quantidades de todos os produtos (`SatelitesMigrator.php:116-146`); convênios sintéticos começam em `max(id)+1` em cada execução (`:244-279`); todo vale-gás recebe valor zero (`:84-104`). **Impacto:** contrato multi-produto vira produto/quantidade impossível, rerun duplica convênios e valor histórico é perdido. **Direção:** modelo por itens, chave natural persistida e derivação/reconciliação financeira do vale.

### A-14.23 — MÉDIA — C4 (convenção não declarada) — seeders operacionais têm senhas conhecidas e gates inconsistentes

`AcessoRedeDubenaSeeder` possui defaults `dono@2026`/`gerente@2026` (`:52,79`) e `PerfisCampoTesteSeeder` usa `teste123` (`:42,83-87`) sem gate próprio de produção. O comentário do agregador afirma defesa que não é implementada por esses seeders (`DeploySeeder.php:24-31`). **Impacto:** execução manual equivocada cria contas previsíveis. **Direção:** aplicar `ResolveSenhaSeed`, recusar produção, separar namespaces/comandos de demo e testar o gate.

## Cobertura dos testes e verificações

- A suíte focal foi executada sobre os 18 arquivos listados acima: **84 testes passaram, 2 foram pulados, 245 assertions, 178,60 s**. Os dois skips são verificações dependentes do catálogo PostgreSQL em `DedupClientesFksTest`; houve ainda avisos de metadata em doc-comments de `MigradoresModulosTest`. Resultado verde não valida os bancos de produção nem cobre os fluxos críticos abaixo.
- Análise sintática: **98 arquivos PHP** do recorte passaram em `php -l`; os **4 scripts Python** passaram em `ast.parse`; `git diff --check` do volume não reportou erro.
- Lacunas materiais: nenhum teste prova que `mapa_empresas` altera a carga; que uma exceção interna impede `concluida`; que duas migrações distintas são serializadas; que o painel respeita pós-cutover; que `app_mysql`/`monitora_mysql` limitam migradores; que o espelho preserva a cópia anterior; ou que posições são idempotentes/tenant-safe.
- `BancoProducaoCheckTest` não exercita PostgreSQL real; `EtlTravaPosCutoverTest` cobre ausência de fonte/escape, não um banco efetivamente pós-corte; `MigracaoFerramentaTest` comprova persistência do mapa, não seu consumo.

## Ordem recomendada de correção

1. Bloquear o painel e cutover: A-14.1, A-14.2, A-14.3, A-14.5, A-14.9 e A-14.19.
2. Corrigir segurança/destrutividade: A-14.16, A-14.17, A-14.18 e A-14.20.
3. Tornar execução atomicamente exclusiva, retomável e auditável: A-14.4, A-14.6, A-14.7, A-14.13 e A-14.14.
4. Refazer invariantes por execução/tenant e manifestos: A-14.8, A-14.10, A-14.11, A-14.12 e A-14.15.
5. Reconciliar dados iniciais e semântica de satélites: A-14.21, A-14.22 e A-14.23.

## Limites

A leitura estática está fechada em 100% do inventário. Não foi possível comprovar fidelidade contra os bancos Oracle/MySQL/PostgreSQL reais nem executar um cutover destrutivo; portanto valores/volumes reais, planos de execução e comportamento sob concorrência distribuída permanecem **não verificados**. Isso não reduz os achados determinísticos acima, demonstrados pelo fluxo do código.
