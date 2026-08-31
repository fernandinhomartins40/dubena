# F5 — Fechamento da fase

Data: 2026-08-31 (America/Sao_Paulo)

## As onze tarefas

| Tarefa | Estado | Onde |
|---|---|---|
| F5-01 — ownership financeiro | fechada | `plano_conta_modelos` + `PlanoContaModeloService` |
| F5-02 — título e liquidação | fechada | `BaixaService::reabrir` (porta única nos dois sentidos) |
| F5-03 — bancos | fechada | `CnabVetoresOficiaisTest`; o retorno **já estava** correto |
| F5-04 — conciliação | fechada | `conciliacao_lancamentos` com FITID e decisão manual |
| F5-05 — pagamentos / fakes | fechada | a trava principal **já existia** no container |
| F5-06 — perfil fiscal | fechada | `fiscal:certificado-vigilancia` (cron 06:45) |
| F5-07 — matriz tributária | fechada | vigência em `nf_impostos` |
| F5-08 — snapshot fiscal | fechada | 9 colunas novas em `nota_itens` |
| F5-09 — homologação | **parcial** | `CenariosFiscaisTest`; o resto é operação (abaixo) |
| F5-10 — reconciliação | fechada | `financeiro:conferir` |
| F5-11 — semântica temporal | fechada | `whereDate` nos três consumidores |

## Os defeitos reais encontrados

**O XML saía incompleto** (F5-08). `XmlNfeBuilder` lia seis campos do item que
**não existiam na tabela**: `origem_icms`, `modalidade_bc_icms`, `cst_pis`,
`bc_pis`, `cst_cofins`, `bc_cofins`. O `FiscalService` os calculava e descartava
— não estavam no `create()` nem no `$fillable`. Três camadas concordando em
jogar fora o mesmo dado.

O `orig` ia como 0 — declarando **nacional** qualquer produto, inclusive
importado — e o CST de PIS/COFINS ia nulo, com os valores dos tributos
preenchidos. Ninguém viu porque o driver real da SEFAZ é gate: em homologação
responde o `FakeSefazDriver`, que autoriza tudo.

**O estorno saía por fora da porta única** (F5-02). `CaixaService::estornar`
reabria a parcela escrevendo `baixado => false` direto no model, sem a
verificação de empresa que a baixa faz. A única proteção era o global scope de
tenant — que não vale em job nem em console, exatamente onde um estorno em lote
roda.

**A conciliação era inteiramente efêmera** (F5-04). Calculava certo e não
gravava nada; o parser já extraía o FITID e o campo morria na resposta HTTP.
Reprocessar o extrato do mês — rotina, não acidente — reconciliava do zero.

**A matriz não tinha data** (F5-07). Editar a regra sobrescrevia a anterior, e
uma nota de dezembro remontada depois de janeiro calculava com a alíquota nova.

## As duas descobertas de método

**O cast `date` do Eloquent grava `'AAAA-MM-DD 00:00:00'`.** O Postgres trunca a
hora ao gravar numa coluna `date`; o sqlite **não**. O mesmo relatório perdia o
último dia do período em sqlite e funcionava em produção — a pior forma da
divergência, porque a suíte é onde se confia. Vale o contrário também: um defeito
só-Postgres passa verde localmente.

**Testes tautológicos passavam por prova.** `assertSame(f($x), f($x))` na
matemática CNAB — verde com qualquer algoritmo, inclusive um que devolvesse
sempre zero. O vizinho só conferia que o retorno era `int`, e o fator de
vencimento só media o comprimento. Trocados por vetores da especificação
FEBRABAN, escritos como literais; um erro de **um dia** na data-base agora
acusa.

## Guardião só vale se você provar que detecta

O guardião de `whereBetween` **não pegou** a regressão nas duas primeiras
versões: olhava só a linha da chamada, e o limite quase sempre vem de uma
variável preparada acima. A terceira olha 8 linhas de contexto e reprova por
padrão, liberando pela exceção visível (`startOfDay`/`endOfDay`).

Todos os guardiões desta fase foram verificados com regressão plantada de
propósito.

## F5-09 é operação, não código

> **F5-09 — Homologação:** cenários por UF/regime/PF/PJ/entrada/saída/ST;
> validar com especialistas e ambientes oficiais atuais.

A primeira metade está feita: `CenariosFiscaisTest` cobre as combinações que a
resolução precisa distinguir — PJ × consumidor final, dentro × entre estados,
ST, DIFAL, direção do par de UFs, e o bloqueio quando o par não tem regra.

Descobri no caminho que `ResolucaoTributariaService` **não tinha teste nenhum**,
apesar de ser ele quem escolhe entre os dois conjuntos completos de tributos. Um
erro ali troca o CST da nota inteira, e o cálculo — que tem dez testes bons —
obedece.

A segunda metade não é trabalho de código: depende de certificado real,
credenciamento na SEFAZ e de alguém que responda pelo enquadramento tributário.
Fica registrada como operação, como o F4-07.

## Verificação

| Portão | Resultado |
|---|---|
| Suíte integral | **1646 passes / 5780 assertions** |
| CI (PostgreSQL, role restrita) | verde em todos os commits da fase |
| Migrations novas | 3, validadas em Postgres real pelo CI |
| Guardiões | cada um verificado com regressão plantada |

## Estado do plano

Fechadas: **F0, F1, F2, F3** (com quatro parciais), **F4** e **F5** (com F5-09
parcial). Restam **F6 a F10**.
