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

## Aberto

Da **F7-03**, o que depende de staging: LOB integral e "carga nova nunca derruba
a última boa" — as duas pressupõem uma área para onde copiar a fonte bruta, que
este ETL não tem. Essa continua sendo decisão de arquitetura, não correção
pendente.
