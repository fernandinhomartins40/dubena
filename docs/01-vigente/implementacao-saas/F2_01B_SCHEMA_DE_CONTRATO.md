# F2-01 (parte 2) — O contrato passa a descrever a forma, não só a existência

Data: 2026-08-31 (America/Sao_Paulo)

A primeira parte da F2-01 entregou action/resource/capability e o catálogo de
permissão por rota. Ficou aberto o **schema de request/response por rota**. É
isso que este lote fecha.

## O que faltava

O manifesto de API (`database/api-manifest.json`) é uma lista de
`"MÉTODO uri"`. Ele pega rota removida e rota acrescentada — e nada mais.

O que quebra a SPA e os apps com muito mais frequência não altera essa lista:

- um campo que **some** do payload de resposta;
- um campo que passa a ser **obrigatório** no request;
- um tipo que muda de número para string.

Nos três casos o consumidor quebra e o contrato continua dizendo "íntegro".

## Por que capturar em runtime, e não ler o código

Medido antes de decidir:

| | |
|---|---|
| Rotas usando `FormRequest` | **5** |
| Pontos de `$request->validate([...])` inline | **217** |

Um extrator estático leria bem os 5 e mal os 217 — muitos montam regras em tempo
de execução (`Rule::exists` com escopo de empresa, obrigatoriedade condicional).
**Um contrato que descreve mal metade do sistema é pior que nenhum**, porque dá
confiança onde não deve.

Capturar durante a suíte registra o que a aplicação **de fato** exige e devolve.

### O preço, dito na cara

A cobertura do contrato é a cobertura da suíte. Rota não exercitada não entra —
e o comando reporta quantas ficaram de fora. Isso é uma informação útil por si
só: é a lista de rotas que nenhum teste toca.

## Onde os ganchos ficam, e por quê

**Request — `Validator::resolver()` no `AppServiceProvider`.** É o único ponto
por onde passam tanto os `FormRequest` quanto os 217 `validate()` inline.
Instrumentar os controllers exigiria tocar em centenas de chamadas e ainda assim
perderia as escritas depois.

**Response — um middleware no grupo `api`.** É o único ponto por onde toda
resposta passa, seja ela Resource, array ou `JsonResponse`.

Ambos saem na primeira linha quando a captura está desligada: o custo em
produção é uma comparação de booleano por requisição.

## O que entra no contrato — e o que fica de fora

**Do request:** os nomes dos campos e se são obrigatórios. Não as regras
completas — gravar `min:8` faria o arquivo mudar a cada ajuste de validação sem
que o contrato tivesse mudado.

**Da resposta:** os caminhos das chaves de `data`, com o tipo. Decisões:

- **lista vira a forma do ITEM** (`data[].nome`), não uma entrada por índice: o
  contrato é o formato, não a quantidade;
- **resposta de erro não entra.** O corpo de um 422 é a forma da falha;
  misturá-la faria o contrato prometer campos que só existem quando algo deu
  errado. O status observado, esse fica registrado;
- **teto de 3 níveis de profundidade.** Uma árvore funda (pedido → itens →
  produto → …) geraria centenas de caminhos e faria o contrato virar ruído.

## O diff que importa

`api:schema --check` classifica as diferenças, em vez de só acusar mudança:

```
- GET api/admin/clientes/{id}: campo `data.credito_limite` SUMIU da resposta
! POST api/admin/pedidos: `entrega_id` passou a ser OBRIGATÓRIO
~ GET api/admin/relatorios/x não foi exercitado nesta execução
```

Campo que some vem primeiro porque é o que quebra o consumidor silenciosamente.

## Uso — duas etapas, de propósito

```bash
API_SCHEMA_CAPTURA=1 php artisan test --no-coverage   # captura
php artisan api:schema                                # consolida
php artisan api:schema --check                        # falha se mudou
```

A primeira versão disparava a suíte de dentro do comando, com um `Process`
aninhado. **Ele falhava mudo no Windows** — sem saída, sem erro, sem arquivo,
que é o pior modo de uma ferramenta falhar (custou uma execução inteira até eu
desconfiar do ambiente em vez do código). Rodar a suíte é trabalho do shell, que
já sabe fazê-lo e mostra o progresso.

Leva o tempo da suíte (~9 min), então não é gancho de commit: é passo de PR, ao
lado de `api:manifest`.

## O resultado

| | |
|---|---|
| Rotas com contrato | **368** |
| …com contrato de request | 179 |
| …com contrato de response | 292 |
| Cobertura sobre as 597 rotas | **62%** |
| Rotas que nenhum teste exercita | 229 |

Os 62% não são uma meta frustrada: são a medida honesta do que a suíte cobre, e
as 229 restantes são uma lista acionável.

Amostra — `POST api/admin/clientes` identificou exatamente os dois campos
realmente obrigatórios (`nome` e `telefones.*.telefone`) entre as ~30 chaves
aceitas, incluindo o obrigatório aninhado.

Verificado que o `--check` acusa o caso que importa: acrescentei um campo
fantasma ao contrato e ele reportou
`campo `data.campo_fantasma` SUMIU da resposta`.

## Verificação

| Portão | Resultado |
|---|---|
| Testes focais (`ApiSchemaContratoTest`) | 6 |
| Suíte integral, modo padrão | **1451 passes / 4614 assertions** |
| Suíte integral, enforcement ligado | **1451 passes**, zero falhas |
| `api:schema --check` | íntegro |
| Pint | aprovado |

## Estado da F2

Com este lote, **as oito tarefas da F2 estão fechadas**: F2-01, F2-02, F2-02A,
F2-03, F2-04, F2-05, F2-06, F2-07 e F2-08.
