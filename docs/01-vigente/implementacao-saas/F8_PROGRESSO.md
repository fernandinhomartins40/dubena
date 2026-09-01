# F8 — Progresso

Data: 2026-08-31 (America/Sao_Paulo)

## F8 é operação, com uma exceção

As nove tarefas (F8-01 a F8-09) descrevem o **ensaio da conversão com os dados
reais**: capturar snapshot, aprovar `MappingSet` por domínio, reconciliar,
resolver quarentena, repetir do zero, cronometrar switch e rollback, e obter
quatro aprovações — técnica, operacional, financeira/fiscal e do controlador dos
dados.

Nada disso é código. Depende do banco com os dados migrados, de quem conhece a
operação para decidir caso a caso, e de gente que assine. As **ferramentas** já
existem: `etl:run --dry-run --check`, `cutover:check`, `golive:check`,
`estoque:conferir`, `financeiro:conferir` (F5-10) e agora o registro de
conversão com quarentena (F7).

A exceção é o único item do gate que é verificável em código:

> **Gate F8:** … nenhuma regra `dubena_*` está no kernel SaaS …

## O que a medição encontrou

Varri `app/` e `config/` por `Dubena`, `Guarapuava`, `GASEMCASA` e `Gas em Casa`,
separando **comentário** de **código executável**. A maioria das ocorrências é
comentário citando o caso real que motivou a decisão — e esses são para
preservar, não para apagar: são o registro de por que o código é como é.

Sobrou **um caso real**: `FakePixDriver` montava o BR Code com o campo 60 (nome
do recebedor) fixo em `GASEMCASA`, com o comprimento `09` embutido junto.

É um Fake, então nada é cobrado de verdade. Mas o payload **sai na tela e no app
do cliente** durante toda a homologação: cada revenda que testasse o PIX veria a
concorrente como recebedora do próprio pagamento.

O nome passou a vir do tenant, com o da plataforma como último recurso, truncado
em 25 (o limite do campo). O comprimento é derivado junto — trocar um sem o
outro produziria um EMV inválido.

## Também verificado, e limpo

- **`empresa_id`/`grupo_id` fixos** em `app/Domain` e `app/Http`: nenhum;
- **valores de negócio em constante** (preço, teto, grade): nenhum. As constantes
  que existem são limiares de algoritmo — similaridade, tolerância, pesos — e os
  pesos da logística já são sobrescritos por empresa via `LogisticaConfig`;
- **inferência por palavra** (`P13`, `botijão`, `vasilhame`): resolvida em F3,
  com `TipoProduto` documentando explicitamente por que a heurística só sugere.

## O guardião ganhou duas correções

`EscritaCanonicaTest::test_nome_de_revenda_nao_vira_literal_em_codigo` existia
desde F3-10 e é bem construído — inclusive com um segundo teste garantindo que a
lista de permitidos não envelhece protegendo arquivo já renomeado.

Faltavam duas coisas:

1. **`GASEMCASA` não estava na lista de termos.** Um guardião que busca só a
   grafia bonita não pega a que de fato vaza — e a forma sem espaço é
   exatamente a que campos de largura fixa usam;
2. **não havia prova de que a varredura varreu algo.** Uma pasta renomeada faria
   a lista vir vazia e o teste passaria protegendo zero arquivo. É a armadilha
   registrada em `teste-que-varre-arquivos-pode-nao-varrer-nada`.

## Verificação

| Portão | Resultado |
|---|---|
| Suíte integral | **1709 passes / 5939 assertions** |
| Guardião | verificado: acusou o caso real do `FakePixDriver` |

## Aberto

**F8-01 a F8-09 inteiras**, como operação. O primeiro passo é rodar
`etl:run --dry-run --check` contra a cópia e ler o relatório com quem conhece a
operação — a mesma natureza do F4-07 e do F5-09.

## F8-07 — a propriedade, entregue; o ensaio, não

A tarefa pede *"repetir do zero; o segundo resultado deve ser determinístico e
idempotente"*. Isso tem duas partes, e só uma é operação.

**Repetir do zero com dados reais** é o ensaio — precisa da base real e de quem
conheça a operação para conferir. Continua aberto.

**"Determinístico e idempotente"** é uma propriedade do código, e é verificável
agora. A idempotência **já estava implementada** — `PreservaIdsDoLegado` escreve
por `upsert`, e vários migradores comentam a decisão. O que faltava era a prova:
**todo teste de migrador rodava a carga uma vez**.

### O que o teste exige, além de "não explodir"

 - a mesma quantidade de linhas — senão a recarga duplica;
 - **os mesmos ids**, e este é o que dói. Id que muda entre execuções quebra toda
   referência já gravada: linhagem da conversão, `erp_id` do app, qualquer coisa
   que tenha guardado o número. O dado "está lá" e aponta para outra linha, sem
   erro e sem log;
 - o mesmo conteúdo, campo a campo;
 - **as invariantes passando depois da segunda carga.** Uma recarga que passa na
   primeira e reprova na segunda é pior que uma que falha sempre: dá confiança no
   ensaio e quebra no cutover, a única execução que não dá para repetir.

### Um comportamento fixado de propósito

`test_recarga_sobrescreve_edicao_local_e_isso_e_esperado` documenta que o
`upsert` preserva o **id**, não o conteúdo: edição feita no sistema novo é
sobrescrita pela recarga.

Isso não é defeito — é a razão de existir a trava do `etl:run`. O teste está lá
para que ninguém "conserte" o upsert achando que ele deveria preservar edição.

Com `updateOrCreate` trocado por `create` (regressão plantada), os três testes
falharam.
