# Estado das 106 tarefas do plano SaaS

Data: 2026-09-01 (America/Sao_Paulo)

Este documento existe porque eu **errei o relato**. Contei "F7: 7 de 9" quando F7
tem **14** tarefas — o `sed` com que li o plano cortou o intervalo antes de F7-10,
e eu não conferi a contagem antes de afirmar.

Duas das quatro que pulei tinham defeito real. Estão corrigidas (commit
`583bafd9`); as outras duas são artefatos de operação que não existem.

O levantamento abaixo é por **verificação**, não por memória.

## Resumo

| Fase | Tarefas | Estado |
|---|---|---|
| F0 | 8 | feitas em sessões anteriores; documento por tarefa |
| F1 | 10 | idem; gate de RLS roda no CI com `--fail-on-skipped` |
| F2 | 9 | idem |
| F3 | 12 | idem (F3-04 coberto pelo F3-04A) |
| F4 | 7 | fechada — `F4_FECHAMENTO.md` |
| F5 | 11 | 10 fechadas; F5-09 é operação |
| F6 | 10 | **fechada** — F6-01 entregue em `95a63fca` |
| F7 | **14** | 13 fechadas; **F7-12 aberta** (operação); F7-03 parcial (falta o que exige staging) |
| F8 | 9 | só o item de código do gate; o resto é operação |
| F9 | 9 | 8 fechadas; F9-07 entregue (medicao); F9-08 parcial |
| F10 | 7 | **intocada** — depende de um segundo cliente real |

## O que está aberto, nomeadamente

### Depende de operação, não de programação

| Tarefa | Por quê |
|---|---|
| F5-09 | homologação fiscal exige especialista contábil e ambiente oficial da SEFAZ |
| F8-01 a F8-09 | ensaio da conversão com dados reais; decisões caso a caso e quatro aprovações |
| F10-01 a F10-07 | exige um **segundo cliente real** com convenções diferentes |
| F7-12 | runbook de cutover com RTO/RPO e responsáveis nomeados |

### ⚠️ Cuidado com esta seção

Duas tarefas que estiveram aqui — **F7-02** e **F7-03** — eram, na verdade,
trabalho de código pendente. Eu as classifiquei como bloqueadas lendo a
exigência mais cara da lista (*"fonte bruta imutável"*, *"CAS/lock"*) e
concluindo que a tarefa inteira dependia dela.

O critério que funciona é ler a tarefa **item a item**: F7-03 tem sete
exigências e só duas precisam de staging; F7-02 tem pré-condições (que já
existiam, no `EtlRun`) e a máquina de estados (que não existia).

"Depende de arquitetura" é uma conclusão que precisa valer para **cada** parte da
tarefa — senão vira desculpa para não entregar as partes que dariam.

### Depende de decisão de arquitetura

| Tarefa | Por quê |
|---|---|
| F7-03 (parte) | LOB integral e "carga nova não derruba a boa" pressupõem área de *staging*. As outras cinco exigências — manifesto, schema, hashes, contagens, watermark — **foram entregues**: eu tinha classificado a tarefa inteira errado |

### Trabalho de código ainda por fazer

| Tarefa | O que falta |
|---|---|
| F9-07 (2ª e 3ª partes) | **substituir** e **remover** as pontes. A medição está entregue (`ponte:uso`); a remoção depende do número que ela vai produzir em produção — hoje a tabela está vazia porque acabou de nascer |
| F9-08 | cenários de navegador (duas abas, request em voo) exigem Playwright, que o projeto não usa |

### O que foi fechado nesta rodada

| Tarefa | Entrega |
|---|---|
| F6-01 | quota, custo, finalidade e health por conta de integração — `integracao_consumos` |
| F7-10 / F7-11 | invariante `INCONCLUSIVA`; seeders sem senha conhecida |
| F7-02 | máquina de estados com enum e CAS — `encerrar()` não sobrescreve desfecho já registrado, e estado inventado lança em vez de virar linha invisível |
| F7-03 (5 de 7) | retrato da fonte legada com hash por tabela — `conversao:snapshot --comparar` reprova se a fonte mudou desde o ensaio |
| F7-13 | bundle imutável de evidência com SHA-256 — `conversao:evidencia` |
| F9-07 (1ª parte) | medição de uso das pontes por (ponte, endpoint, empresa, dia) — `ponte:uso` |
| — | defeito de *grants*: `GRANT SELECT` não restringia, porque `ALTER DEFAULT PRIVILEGES` já dava escrita a toda tabela nova. Descoberto conferindo o banco de homologação, corrigido com `REVOKE` |
| — | **defeito EM PRODUÇÃO, achado conferindo a VPS**: a policy de `integracao_consumos` (F6-01, deployada hoje) tinha `WITH CHECK (empresa_id IS NOT NULL AND ...)`. Com `FORCE ROW LEVEL SECURITY`, o Postgres **rejeitava toda escrita da plataforma** — e a tabela foi criada justamente para enxergar esse caso. `ERROR: new row violates row-level security policy`, reproduzido ao vivo. Como o registrador engole a exceção e a suíte roda em sqlite, nada acusava. Corrigido em `2026_09_01_000500` |
| — | **e a correção do próprio conserto**: eu ia revogar a escrita das `conversao_*` alegando que "quem escreve é o console, como owner". É falso — `RegistroDaConversao` usa a conexão default (`erp_app`), confirmado ao vivo na VPS (`DB_USERNAME=erp_app`, `DB_OWNER_USERNAME=erp`). Teria quebrado o registro da conversão **em silêncio**, porque toda escrita dele é protegida por `catch` |

## Os gates transversais, verificados

Rodei cada um, e não confiei no documento:

| Gate | Como verifiquei | Resultado |
|---|---|---|
| Tenancy | catálogo enumerado, 200+ tabelas com classe e owner | passa |
| RLS | `composer test:pgsql-rls` no CI, com `--fail-on-skipped` | passa (skip local é sqlite, esperado) |
| Jobs | `TenantEnvelopeRuntimeTest` + `JobsTratamentoFalhaTest` — 12 testes | passa |
| API | `TrocaAdversarialDeIdsTest` — varre rotas e exige mapeamento explícito | passa |
| RBAC/licença | 85 testes de licença/permissão/manifesto | passa |
| SPA | `cache-isolamento.test.ts`; parte de navegador exige Playwright | parcial |
| Segredos | `GatesTransversaisTest` + `SeedSemSenhaConhecidaTest` | passa |
| Drivers | `FakesBloqueadosEmProducaoTest` | passa |
| Domínio | `EscritaCanonicaTest` | passa |
| Financeiro | `estoque:conferir`, `financeiro:conferir` | passa |
| Fiscal | ausência bloqueia (`CenariosFiscaisTest`); XML homologado é F5-09 | parcial |

## O que aprendi com o próprio erro

Passei a rodada inteira corrigindo **guardião que não guarda** e **varredura que
não varre** — e cometi a mesma classe de erro no meu relato: contei tarefas sem
enumerar a lista.

A correção é a mesma que apliquei ao código: **enumere e prove**. Este documento
é a enumeração; os testes citados são a prova.
