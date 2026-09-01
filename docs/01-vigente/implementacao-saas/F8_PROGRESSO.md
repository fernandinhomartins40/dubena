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
