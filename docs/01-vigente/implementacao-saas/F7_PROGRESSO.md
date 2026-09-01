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

## Aberto

**F7-03** (snapshot imutável da fonte) e a parte de CAS/lock da **F7-02**. As
duas só fazem sentido com uma área de staging, que é decisão de arquitetura do
ETL — não uma correção pendente.
