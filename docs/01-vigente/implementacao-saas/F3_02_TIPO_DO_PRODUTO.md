# F3-02 — O produto declara o que é; a regex vira sugestão

Data: 2026-08-31 (America/Sao_Paulo)

## O código que existia

`VinculoVasilhame` decidia o que um produto **é** lendo a descrição:

```php
// recipiente
str_contains($texto, 'VASILHA') || 'CASCO' || 'BOTIJAO' || 'BOTIJÃO'
// conteúdo
$p->tipo_glp !== null || str_contains($texto, 'GLP') || 'RECARGA'
// e 'GRANEL' excluía, 'RECARGA' excluía de recipiente
```

Toda a vigilância de comodato — que está em produção e revelou 145 contratos
vencidos — depende dessa classificação. E **não havia tabela de vínculo**: a
inferência era recalculada a cada execução.

## Por que isso não sobrevive à segunda revenda

Uma revenda que cadastre **"Cilindro 13kg"**, **"P13 cheio"**, ou opere em
espanhol, some da vigilância inteira.

O modo de falhar é o pior possível: **a tela não fica vazia, fica com menos
linhas.** Um erro visível seria reportado no primeiro dia. Uma lista curta
parece uma lista.

Havia também o falso positivo do outro lado — o cliente que se chama, ou o
produto que se chama, algo contendo as palavras procuradas.

## A correção

`tipo` no produto, com três valores úteis e um quarto que é a resposta honesta:

| | |
|---|---|
| `RECIPIENTE` | o casco emprestado — objeto de comodato |
| `CONTEUDO` | o gás vendido para enchê-lo |
| `MERCADORIA` | água, acessório, serviço — fora do ciclo |
| `INDEFINIDO` | **ainda não classificado** |

`tipo` é ortogonal a `natureza` (produto/servico/taxa): um recipiente e um
conteúdo são ambos `produto`; o que os separa é serem o casco ou o gás.

**A regex não foi jogada fora — mudou de lugar.** `sugerirTipo()` a mantém como
sugestão na tela de conferência, devolvendo também a **evidência** que a
produziu, porque um palpite sem o motivo não é conferível.

## A conversão, e as três colunas

A heurística roda uma vez, na migration, e grava:

```
tipo             o que o produto é
tipo_origem      quem decidiu: `heuristica` ou `humano`
tipo_evidencia   por quê: o termo que casou
```

`tipo_origem` é o que impede a conversão de virar verdade absoluta. Uma linha
marcada `heuristica` é um palpite gravado, e a tela pode listá-la. Sem essa
coluna, palpite e decisão humana ficariam indistinguíveis no dia seguinte — e a
dívida sumiria de vista sem ser paga.

**O que não casou com nada NÃO virou `MERCADORIA`.** "Não bateu com nenhuma
palavra" e "é mercadoria comum" são afirmações diferentes, e só a primeira é
verdade. Marcar tudo como mercadoria esconderia justamente os cascos que a
heurística não reconhece — que são a razão desta migration existir.

## A lista que impede a falha silenciosa

`GET /comodatos/vinculos` passou a devolver `nao_classificados`, com a sugestão
e a evidência ao lado. É o que troca "a tela mostra menos linhas e ninguém sabe
quantas faltam" por "estes N produtos precisam de decisão".

`tipo_origem` também vai na resposta das linhas já classificadas: quem confere
vê o que ainda é palpite.

## Um detalhe que só apareceu no teste

O default da coluna vale na linha gravada, mas `new Produto` vinha com
`tipo = null` — e `null === TipoProduto::RECIPIENTE` dá `false` por acidente, não
por decisão. `protected $attributes` no model faz `INDEFINIDO` valer desde a
instância.

## Verificação

| Portão | Resultado |
|---|---|
| Testes focais | 10 (`TipoProdutoDeclaradoTest`) |
| Testes de comodato atualizados | 22 (vigilância + API de vínculos) |
| Suíte integral, modo padrão | **1470 passes / 4644 assertions** |
| Suíte integral, enforcement ligado | **1470 passes**, zero falhas |
| `tsc --noEmit` / Vitest | limpo / 39 |
| Pint | aprovado |

**Pendente:** a validação em PostgreSQL real (migration + `RlsCoberturaTest`)
não pôde ser feita — o Docker Desktop caiu no meio do lote e não voltou. A
migration usa `UPPER(...) LIKE` em vez de `ILIKE` justamente para dar o mesmo
resultado nos dois bancos, mas isso precisa ser confirmado contra o Postgres
antes do deploy. **Rodar `php artisan migrate` num Postgres limpo e
`RlsCoberturaTest` assim que o Docker voltar.**

## Segunda peça: a capacidade

O mesmo defeito, um nível abaixo. `capacidade()` extraía a grade da descrição:

```php
/P\s?(13|20|45|90)/     "P13", "P 13"
/(13|20|45|90)\s?KG/    "13KG", "45 KG"
```

**A grade brasileira de GLP estava escrita no código.** Uma revenda com outra
grade não pareia casco com gás — e o pareamento é o que sustenta a vigilância:
sem ele não há como perguntar "o cliente com 13 vasilhames P13 comprou quanto de
P13?".

`capacidade` é `varchar` e não decimal de propósito: o valor é um **rótulo de
grade comercial**, não uma medida. Dois recipientes de 13 kg de grades
diferentes não são intercambiáveis, e um número faria parecer que são. O
pareamento é por igualdade exata do rótulo, que é a semântica correta.

A ordem de precedência ficou: **coluna → `tipo_glp` → texto**. O campo fiscal
vence o texto porque é preenchido para valer; a coluna vence os dois porque é a
declaração de quem sabe.

Conversão verificada em PostgreSQL com massa real:

| Descrição | Capacidade | Origem |
|---|---|---|
| `Vasilha P13 Kg` | P13 | descricao |
| `CASCO 45 KG` | P45 | descricao |
| `P 20 cheio` | P20 | descricao |
| `Glp P13` (tipo_glp=3) | P13 | **tipo_glp** |
| `Botellon 15 kg` | — | — |
| `Produto P130 especial` | — | — |

Os dois últimos são os que provam o cuidado: "P130" **não** virou P13 (o ``
da regex evita o falso positivo), e "Botellon 15 kg" ficou nulo em vez de
receber um palpite — é exatamente o caso que a coluna existe para resolver.

## O que fica da F3

Fechadas: F3-02 (`kind`, capacidade e vínculo declarados) e F3-04A.
Abertas: F3-01 (Party), F3-03 (snapshot), F3-04 (estados ortogonais), F3-05 a
F3-11.
