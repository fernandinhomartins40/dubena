# F7 — Progresso

Data: 2026-08-31 (America/Sao_Paulo)

## As quatorze tarefas

| Tarefa | Estado | Onde |
|---|---|---|
| F7-01 — Entidades | **parcial (3 de 8)** | `conversao_execucoes`, `conversao_linhagem`, `conversao_quarentena` |
| F7-02 — Estados | parcial | `EM_ANDAMENTO`/`CONCLUIDA`/`FALHOU`/`INTERROMPIDA`; sem CAS |
| F7-03 — Snapshot | **aberta** | exige área de staging que este ETL não usa |
| F7-04 — Registry | **já estava** | ordenação topológica; migrador desconhecido falha |
| F7-04A — Progresso | fechada | lista vazia nunca produz sucesso |
| F7-05 — Contexto | **já estava** | mapa tenant/empresa governa; ver F1 |
| F7-06 — Linhagem | fechada | chave `origem + entidade + pk`, upsert idempotente |
| F7-07 — Quarentena | fechada | um registro por decisão, com payload bruto |
| F7-08 — Erros | **já estava** | "origem indisponível" ≠ "origem vazia" |
| F7-09 — Exclusão mútua | fechada | lock por destino, liberado em `finally` |
| F7-10 — Invariantes | fechada | `INCONCLUSIVA` — fonte ausente não é aprovação |
| F7-11 — Seed | fechada | senha do ambiente; fixture recusa produção |
| F7-12 — Cutover | **aberta** | runbook com RTO/RPO é artefato de operação |
| F7-13 — Evidência | **aberta** | matéria-prima existe; falta formato e quem assina |

## O que o ETL já tinha, e é bastante

28 migradores com ordenação topológica por dependência; invariantes por
migrador; `--dry-run`; e uma trava pós-cutover que detecta o cutover **pela
evidência no banco** — existe pedido criado no sistema novo — em vez de por uma
flag que alguém precisa lembrar de ligar.

Ele já distinguia **"origem indisponível" de "origem vazia"**, que é o espírito
do F7-08: a primeira significa carga incompleta e não pode ser lida como sucesso
por um script de deploy.

> ⚠️ **Correção de um erro meu.** A primeira versão deste documento dizia "as
> nove tarefas". F7 tem **14** — o `sed` com que li o plano cortou o intervalo
> antes de F7-10, e não conferi a contagem. Duas das quatro que pulei tinham
> defeito real, corrigido em `583bafd9`.

## Os quatro defeitos encontrados

**Lista de migradores vazia produzia SUCESSO** (F7-04A). Com o registry vazio, o
loop não roda, nada falha, e o comando imprime "ETL concluído" com `SUCCESS`. Um
script de deploy leria como carga bem-sucedida, e a operação descobriria pelo
sistema vazio.

É a mesma família do guardião que varria zero arquivos: **o verde que não prova
nada é pior que o vermelho**, porque ninguém investiga.

**Não havia exclusão mútua** (F7-09). Dois `etl:run` simultâneos processam o
mesmo dump em paralelo, e como a escrita é upsert **preservando id**, a segunda
execução sobrescreve o que a primeira acabou de gravar. Nenhuma das duas falha —
o resultado é uma carga que parece bem-sucedida com estado misturado.

O `Isolatable` do Laravel resolveria, mas só quando alguém passa `--isolated`. A
proteção que depende de lembrar não protege.

**Fonte ausente valia como aprovação** (F7-10). `InvariantResult` só tinha `ok` e
`falha`, e `BalanceInvariant` devolvia **`ok`** para "sem movimentos no recorte".

A mesma resposta servia para dois fatos opostos: antes da carga, "sem movimentos"
é o esperado; **depois** dela, significa que a carga não trouxe nada — e o portão
do cutover aprovava assim mesmo.

**Senha conhecida em seeder** (F7-11). `AcessoRedeDubenaSeeder` tinha
`env('DONO_SEED_PASSWORD', 'dono@2026')` — o default entra sozinho quando a
variável falta, e esse usuário é o **dono da rede**, que enxerga todas as
filiais.

## Três tabelas, não oito

O plano nomeia oito entidades. Três resolvem o que a operação precisa **agora** —
a execução, a linhagem e o descarte. As outras cinco (`SourceSnapshot`,
`MappingSet`, `StagingRecord`, `CutoverPlan`, `EvidenceBundle`) descrevem um
pipeline de *staging* que este ETL não usa: ele lê do dump e escreve no destino,
sem área intermediária.

Criá-las vazias seria **pior que não criar**: tabela sem escritor parece
resolvida e não responde nada. Foi exatamente o que aconteceu com
`tenant_account_id` em F1 — criada, deixada nula, e por isso invisível como
problema até alguém consultar.

## Por que a quarentena é a peça que mais importa

A conferência de um cutover acontece dias depois — *"faltam 40 clientes"*. Sem
registro do descarte, a única resposta possível é rodar tudo de novo e comparar.
Com o sistema já em produção isso é impossível, e **com razão**: a trava
pós-cutover existe justamente para impedir a recarga que sobrescreve trabalho
real.

Por isso a quarentena guarda o `payload` bruto. Dizer que algo foi descartado sem
permitir recuperar o dado responde metade da pergunta, e é a metade menos útil.

## O registro nunca derruba a carga

Toda escrita de `RegistroDaConversao` é protegida: se falhar, a conversão
continua. Instrumentação que interrompe o processo que ela observa inverte a
prioridade — o dado migrado vale mais que o registro de que ele migrou.

Há teste que **apaga as três tabelas** e confere que a carga segue.

## Verificação

| Portão | Resultado |
|---|---|
| Suíte integral | **1708 passes / 5935 assertions** |
| CI (PostgreSQL, role restrita) | verde em todos os commits da rodada |
| Guardiões | verificados com regressão plantada |

## F7-03 — eu tinha classificado errado

Este documento dizia que F7-03 "só faz sentido com uma área de staging". Reli a
tarefa item a item e **estava errado**: das sete exigências, só duas dependem de
staging.

O plano pede *"fonte bruta imutável, manifesto nominal, schema, hashes,
contagens, watermark e LOB integral; carga nova nunca derruba a última boa"*.

| Exigência | Depende de staging? |
|---|---|
| manifesto nominal | não — é ler o schema da fonte |
| schema | não |
| hashes | não |
| contagens | não |
| watermark | não |
| **LOB integral** | **sim** — copiar binário exige onde pôr |
| **carga nova não derruba a boa** | **sim** — só se aplica havendo carga guardada |

Eu tinha lido "fonte bruta imutável" como *cópia* da fonte e deixado a tarefa
inteira de lado. Mas as cinco primeiras são **medições no instante da leitura**,
e são justamente elas que respondem a pergunta que a tarefa existe para
responder:

> *A fonte mudou entre o ensaio e o cutover?*

Sem isso, um ensaio bem-sucedido na sexta não diz nada sobre a virada no domingo.
Alguém edita 300 clientes no sábado e a conversão os traz com o valor novo, sem
que ninguém perceba que o ensaio validou outro estado. É exatamente o risco que
o F8 tenta cobrir — e que eu tinha deixado descoberto por erro de leitura.

### O que foi entregue

`conversao_snapshots` + `SnapshotDaFonte` + `conversao:snapshot`.

Roda-se antes do ensaio e de novo antes do cutover; `--comparar` **reprova** se a
fonte mudou.

Três decisões que valem registro:

 - **hash por tabela, não do banco.** Um hash único responde "mudou?"; por
   tabela, responde **onde** — o que separa a mudança inócua (log crescendo) da
   fatal (cliente editado depois do ensaio);
 - **soma dos hashes de linha, não concatenação.** A soma independe da ordem, e a
   fonte legada não garante ordem estável entre leituras. Um hash sensível à
   ordem acusaria mudança onde não houve — e alarme que dispara sozinho é alarme
   que se aprende a ignorar, justamente quando estiver certo;
 - **`lob_integral` declarado e FALSO.** É a entrega, não a omissão: o gate
   precisa conseguir reprovar enquanto não houver staging. Campo ausente seria
   lido como "não se aplica", e é o oposto.

O caso que o guardião prova pegar é o pior de todos: **linha editada sem mudança
de contagem**. Com o hash cego aos valores (regressão plantada), dois testes
falharam.

## F7-02 — a máquina de estados que não era máquina

Segunda tarefa que eu tinha classificado como "decisão de arquitetura" e que era,
na verdade, correção pendente. O plano pede: *"estados felizes e bloqueantes;
transição exige pré-condições e CAS/lock. `COMPLETED` não pode ser setado
diretamente pelo job"*.

### O que já estava certo

As **pré-condições** viviam no `EtlRun`, e continuam onde estavam: invariante
reprovada vira `FALHOU`, origem indisponível vira `FALHOU`, o resto vira
`CONCLUIDA`. É o comando que sabe o que aconteceu na carga — mover isso para o
registro seria piorar.

### O que faltava: a máquina

`encerrar()` aceitava **qualquer string** e fazia um `update` **incondicional**.
Dois defeitos, ambos silenciosos:

**Estado inventado.** `'CONCLUÍDA'` com acento gravaria um valor que nenhuma
consulta encontra. A execução some do gate de cutover **sem sumir do banco** — o
pior desfecho: não aparece no relatório e não há erro para investigar.

Agora é enum (`SituacaoDaConversao`), como `SituacaoNota` e `EfeitoPedido` já
eram nesta base. Estado desconhecido **lança**, e isso é deliberado: a regra
"registro não derruba carga" vale para falha de infraestrutura — banco fora,
tabela ausente. Erro de digitação é bug, e engoli-lo é o defeito.

**O último a escrever vencia.** Um supervisor marca `INTERROMPIDA` porque o
processo morreu por OOM; a thread agonizante ainda consegue escrever e chama
`encerrar('CONCLUIDA')` — e uma carga incompleta passa a constar como concluída.

O CAS resolve no BANCO (`where situacao = 'EM_ANDAMENTO'`), não em PHP:
verificação em PHP perderia justamente a corrida que deveria arbitrar. E
`encerrar()` agora devolve `bool` — `false` diz "outro chegou antes", em vez de
mentir por omissão.

Com o `where` removido (regressão plantada), dois testes falharam.

## F7-12 — o pós-check, que é a parte de código

A tarefa pede *"freeze ou CDC/journal, delta, shadow target, switch atômico,
**pós-check**, blue/green e rollback com RTO/RPO/responsáveis"*. Quase tudo é
operação — mas **pós-check é código**, e não existia.

### Os portões que havia eram todos PRÉ-switch

| Comando | Pergunta | Quando |
|---|---|---|
| `cutover:check` | os dados batem com a origem? | antes — precisa da conexão legada |
| `golive:check` | a configuração está pronta? | antes — config, não operação |
| `cutover:pos-check` | o sistema está sadio agora? | **depois** |

A distinção não é acadêmica: depois do switch a origem pode nem existir mais, e
ninguém reexecuta invariante de comparação. A pergunta muda de *"a carga trouxe
tudo?"* para *"a operação consegue trabalhar?"*.

### O que se mede depois

O critério foi: o que, quebrado, **para a revenda em minutos** e só aparece com
tráfego real.

 - **sequência atrás do maior id** — o defeito clássico de carga por `insert` com
   id explícito. A sequence continua em 1 com 40 mil linhas na tabela, e o
   primeiro pedido novo colide com um migrado. O erro na tela é violação de
   chave, que ninguém associa ao cutover;
 - **execução eternamente `EM_ANDAMENTO`** — indistinguível de carga rodando
   agora; alguém espera por um processo morto;
 - **quarentena pendente** — dado que não entrou, e ninguém sabe qual;
 - **job falhado depois do switch** — nota não emitida, boleto não registrado, e
   a revenda descobre pelo cliente;
 - **empresa sem tenant** — AVISO, não falha (ver abaixo).

### Aviso × falha, e por que importa

`empresa sem tenant_account_id` deixa a revenda invisível para a RLS. Mas a
coluna é aditiva e quem a preenche é a conversão: num banco que ainda não
converteu, nulo é o estado **normal** — inclusive o que `Empresa::factory()`
produz.

Reprovar por isso faria o comando reprovar sempre fora do cutover, e **portão que
sempre reprova é portão que se aprende a ignorar** — justamente quando estiver
certo. Quem trata isso como bloqueio é o `golive:check`, que verifica prontidão.

Já **zero empresas** é falha: o vazio satisfaria "nenhuma sem tenant" sem
satisfazer a intenção. Mesma armadilha do registry vazio.

### Validado contra Postgres real, não só em sqlite

A verificação de sequências é a única que sqlite **não exercita** — e é a mais
importante das cinco. Rodei o comando contra um banco Postgres recém-migrado na
VPS:

```
Sequências
  PASS 219 sequência(s) à frente do maior id
```

Depois plantei o defeito (`insert` com id 500 + `setval(seq, 1, false)`):

```
Sequências
  FAIL 219 sequência(s) à frente do maior id — estados (próximo 1 ≤ maior id 500)
```

É exatamente o cenário que trava a primeira venda depois do cutover, e o comando
aponta a tabela e os dois números.

### O falso positivo que só apareceu rodando em homologação

Depois do deploy, rodei o comando no banco real e ele reprovou com **"nenhuma
empresa cadastrada"** — num banco com **12 empresas**.

A causa: `empresas` está sob RLS, e o comando roda como `erp_app` sem envelope de
tenant. `count()` devolve zero, e o zero é indistinguível do defeito que a
verificação procura.

**Portão que reprova por não ENXERGAR é pior que portão nenhum**: treina quem
opera a ignorá-lo, e o sintoma é idêntico ao do problema real.

Corrigido lendo `empresas` pela conexão de owner — o mesmo padrão que
`RlsCoberturaTest` já usava. As outras verificações não precisam: `conversao_*`
são PLATFORM (sem policy) e `jobs`/`failed_jobs` são infraestrutura.

O `owner()` só troca de conexão em PostgreSQL. Em sqlite, `pgsql_owner` aponta
para outro lugar e usá-la abriria um banco vazio — reintroduzindo o mesmo defeito
que ela existe para corrigir. Aprendi isso quebrando cinco testes na primeira
tentativa.

**Nenhum teste local pegaria**: sqlite não tem RLS. O defeito só existe onde o
comando roda.

### E o que o comando corrigido revelou

Com a leitura funcionando, o aviso apareceu:

```
Tenancy
  PASS há empresa cadastrada
  WARN toda empresa tem tenant — 12 de 12 sem tenant: ficam invisíveis para a RLS
```

Conferido no banco: **11 das 12 empresas têm vínculo em `tenant_companies`, e
nenhuma tem `tenant_account_id` preenchido**.

Não é defeito — é F1-10 pendente, e por decisão explícita. A migration
`2026_08_29_000300` diz, no próprio cabeçalho: *"Nenhuma migration faz backfill:
F1-10 só poderá preencher a partir de `tenant_companies` aprovado"*. E o plano
reforça: *"conforme titularidade aprovada, nunca copiando automaticamente a
fronteira de `grupo`"*.

Preencher a coluna sozinho seria exatamente o que o plano proíbe. O pós-check faz
o certo: **aponta a lacuna e não a resolve**.

Vale como registro de que homologação, hoje, tem RLS que não alcança empresa
nenhuma — o que é coerente com o ambiente ainda não ter feito a conversão.

### Um teste meu que não cobria o que prometia

`test_execucao_sem_desfecho_reprova` passava — mas passava porque a verificação
*seguinte* (`a última carga terminou CONCLUIDA`) também reprovava aquele cenário,
e a mensagem dela por acaso continha a palavra que eu asseria.

Descobri plantando a regressão: desativei a checagem de execução aberta e
**nenhum teste falhou**. Corrigido criando uma carga concluída antes, para
isolar o caso. Com a regressão replantada, o teste agora reprova.

## Aberto

Da **F7-03**, o que depende de staging: LOB integral e "carga nova nunca derruba
a última boa" — as duas pressupõem uma área para onde copiar a fonte bruta, que
este ETL não tem. Essa continua sendo decisão de arquitetura, não correção
pendente.
