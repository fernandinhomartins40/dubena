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
| F6 | 10 | 9 fechadas; falta quota/custo de F6-01 |
| F7 | **14** | 10 fechadas; **F7-03, F7-12, F7-13 abertas**; CAS de F7-02 parcial |
| F8 | 9 | só o item de código do gate; o resto é operação |
| F9 | 9 | 7 fechadas; F9-07 aberta, F9-08 parcial |
| F10 | 7 | **intocada** — depende de um segundo cliente real |

## O que está aberto, nomeadamente

### Depende de operação, não de programação

| Tarefa | Por quê |
|---|---|
| F5-09 | homologação fiscal exige especialista contábil e ambiente oficial da SEFAZ |
| F8-01 a F8-09 | ensaio da conversão com dados reais; decisões caso a caso e quatro aprovações |
| F10-01 a F10-07 | exige um **segundo cliente real** com convenções diferentes |
| F7-12 | runbook de cutover com RTO/RPO e responsáveis nomeados |

### Depende de decisão de arquitetura

| Tarefa | Por quê |
|---|---|
| F7-03 | snapshot imutável da fonte pressupõe área de *staging*, que este ETL não usa |
| F7-02 (CAS) | idem — a máquina de estados atual não tem transição concorrente |
| F7-13 | bundle de evidência: a matéria-prima existe (execução, linhagem, quarentena, invariantes), falta decidir o formato e quem assina |

### Trabalho de código ainda por fazer

| Tarefa | O que falta |
|---|---|
| F6-01 | quota, custo, finalidade e health por conta de integração (o circuito e a credencial por tenant estão feitos) |
| F9-07 | medir uso das pontes legadas e substituí-las por contratos canônicos |
| F9-08 | cenários de navegador (duas abas, request em voo) exigem Playwright, que o projeto não usa |

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
